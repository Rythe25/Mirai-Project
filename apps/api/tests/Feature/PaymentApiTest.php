<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\Orders\PlaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-api-key';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payway' => [
            'base_url' => 'https://payway.test',
            'merchant_id' => 'mirai.test',
            'api_key' => self::KEY,
            'callback_url' => null,
            'lifetime' => 6,
        ]]);
    }

    /** 2 × $2.50 = $5.00, stock 5. */
    private function order(): Order
    {
        $this->product = Product::factory()->create(['price' => '2.50', 'stock' => 5]);

        return app(PlaceOrder::class)->handle(
            ['name' => 'Test Buyer', 'phone' => '012345678'],
            [['product_id' => $this->product->id, 'qty' => 2]],
        );
    }

    private function fakePayway(string $status = 'APPROVED', float $paid = 5.00): void
    {
        Http::fake([
            '*/generate-qr' => Http::response([
                'status' => ['code' => '0', 'message' => 'Success.'],
                'qrString' => 'KHQR',
                'qrImage' => 'data:image/png;base64,AAAA',
                'abapay_deeplink' => 'abamobilebank://x',
            ]),
            '*/check-transaction-2' => Http::response([
                'data' => ['payment_status' => $status, 'payment_amount' => $paid, 'payment_currency' => 'USD', 'apv' => '123456'],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ]),
        ]);
    }

    private function pay(Order $order): TestResponse
    {
        return $this->postJson("/api/orders/{$order->public_id}/pay");
    }

    private function sendCallback(string $tranId, ?string $signature = null): TestResponse
    {
        $payload = ['apv' => '123456', 'return_params' => '', 'status' => '0', 'tran_id' => $tranId]; // sorted
        $signature ??= base64_encode(hash_hmac('sha512', implode('', $payload), self::KEY, true));

        return $this->postJson('/api/payway/callback', $payload, ['X-PayWay-HMAC-SHA512' => $signature]);
    }

    public function test_pay_creates_a_khqr_with_the_documented_hash(): void
    {
        $this->fakePayway();

        $this->pay($this->order())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '5.00')
            ->assertJsonPath('data.qr_image', 'data:image/png;base64,AAAA');

        Http::assertSent(function (Request $r) {
            // req_time, merchant_id, tran_id, amount, 5 empty fields, purchase_type, payment_option,
            // 2 empty fields, currency, 3 empty fields, lifetime, qr_image_template
            $b4hash = $r['req_time'].'mirai.test'.$r['tran_id'].'5'
                .'purchase'.'abapay_khqr'.'USD'.'6'.'template3_color';

            return str_ends_with($r->url(), '/generate-qr')
                && $r['amount'] === 5
                && $r['hash'] === base64_encode(hash_hmac('sha512', $b4hash, self::KEY, true));
        });
    }

    public function test_pay_reuses_the_open_qr(): void
    {
        $this->fakePayway();
        $order = $this->order();

        $first = $this->pay($order)->json('data.tran_id');
        $this->assertSame($first, $this->pay($order)->json('data.tran_id'));
        Http::assertSentCount(1);
    }

    public function test_forged_callback_is_rejected(): void
    {
        $this->fakePayway();
        $order = $this->order();
        $tranId = $this->pay($order)->json('data.tran_id');

        $this->sendCallback($tranId, 'forged')->assertUnauthorized();
        $this->assertSame('pending', $order->fresh()->status->value);
    }

    public function test_callback_marks_the_order_paid_once(): void
    {
        $this->fakePayway();
        $order = $this->order();
        $tranId = $this->pay($order)->json('data.tran_id');

        $this->sendCallback($tranId)->assertOk();
        $this->sendCallback($tranId)->assertOk(); // PayWay may retry

        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertSame(3, $this->product->fresh()->stock); // reduced once
        $this->assertDatabaseHas('payments', ['tran_id' => $tranId, 'status' => 'approved', 'apv' => '123456']);
    }

    public function test_callback_is_not_trusted_while_payway_says_pending(): void
    {
        $this->fakePayway('PENDING', 0);
        $order = $this->order();

        $this->sendCallback($this->pay($order)->json('data.tran_id'))->assertOk();
        $this->assertSame('pending', $order->fresh()->status->value);
    }

    public function test_callback_for_unknown_tran_id_is_404(): void
    {
        $this->fakePayway();

        $this->sendCallback('MR999T000000000000')->assertNotFound();
    }

    public function test_amount_mismatch_does_not_mark_paid(): void
    {
        $this->fakePayway('APPROVED', 0.01);
        $order = $this->order();

        $this->sendCallback($this->pay($order)->json('data.tran_id'))->assertOk();

        $this->assertSame('pending', $order->fresh()->status->value);
        $this->assertSame(5, $this->product->fresh()->stock);
        $this->assertDatabaseHas('payments', ['status' => 'failed']);
    }

    public function test_status_poll_checks_payway(): void
    {
        $this->fakePayway();
        $order = $this->order();
        $this->pay($order);

        $this->getJson("/api/orders/{$order->public_id}/payment")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.order.status', 'paid');
    }

    public function test_expired_qr_can_be_replaced(): void
    {
        $this->fakePayway('PENDING', 0);
        $order = $this->order();
        $first = $this->pay($order)->json('data.tran_id');

        $this->travel(7)->minutes();

        $this->getJson("/api/orders/{$order->public_id}/payment")->assertJsonPath('data.status', 'expired');
        $this->assertNotSame($first, $this->pay($order)->assertCreated()->json('data.tran_id'));
    }

    public function test_paid_order_cannot_be_paid_again(): void
    {
        $this->fakePayway();
        $order = $this->order();
        $this->sendCallback($this->pay($order)->json('data.tran_id'));

        $this->pay($order)->assertStatus(409);
    }
}
