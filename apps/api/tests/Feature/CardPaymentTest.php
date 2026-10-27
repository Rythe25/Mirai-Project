<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\Orders\PlaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CardPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-api-key';

    private Product $product;

    /** The card attempt PayWay reports as paid in the "older attempt" test. */
    private string $cardTranId = '';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payway' => [
                'base_url' => 'https://payway.test',
                'merchant_id' => 'mirai.test',
                'api_key' => self::KEY,
                'callback_url' => 'https://api.mirai.test/api/payway/callback',
                'lifetime' => 6,
            ],
            'app.storefront_url' => 'https://shop.mirai.test',
            'cors.allowed_origins' => ['http://localhost:3000', 'https://mirai.kandycode.com'],
        ]);
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

    private function payByCard(Order $order, array $headers = []): TestResponse
    {
        return $this->postJson("/api/orders/{$order->public_id}/pay", ['method' => 'card'], $headers);
    }

    private function fakeCheck(string $status = 'APPROVED', float $paid = 5.00): void
    {
        Http::fake([
            '*/check-transaction-2' => Http::response([
                'data' => ['payment_status' => $status, 'payment_amount' => $paid, 'payment_currency' => 'USD', 'apv' => '123456'],
                'status' => ['code' => '00', 'message' => 'Success!'],
            ]),
        ]);
    }

    public function test_card_payment_returns_a_signed_purchase_form_without_calling_payway(): void
    {
        Http::fake();
        $order = $this->order();

        $response = $this->payByCard($order, ['Origin' => 'https://mirai.kandycode.com'])
            ->assertCreated()
            ->assertJsonPath('data.method', 'card')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.qr_image', null)
            ->assertJsonPath('data.checkout.action', 'https://payway.test/api/payment-gateway/v1/payments/purchase');

        $f = $response->json('data.checkout.fields');
        $returnTo = "https://mirai.kandycode.com/pay/{$order->public_id}?returned=1";

        $this->assertSame('cards', $f['payment_option']);
        $this->assertSame('0', $f['payment_gate']);
        $this->assertSame('hosted_view', $f['view_type']);
        $this->assertSame('5', $f['amount']);
        $this->assertSame($returnTo, $f['continue_success_url']);
        $this->assertSame(base64_encode('https://api.mirai.test/api/payway/callback'), $f['return_url']);

        // 24-field hash order from the PayWay Purchase docs; empty fields count as ''.
        $b4hash = $f['req_time'].'mirai.test'.$f['tran_id'].'5'
            .''.''.''.''.''.''                       // items, shipping, firstname, lastname, email, phone
            .'purchase'.'cards'.$f['return_url'].$returnTo.$returnTo
            .''.'USD'.''.''.''                        // return_deeplink, currency, custom_fields, return_params, payout
            .'6'.''.''.'';                            // lifetime, additional_params, google_pay_token, skip_success_page
        $this->assertSame(base64_encode(hash_hmac('sha512', $b4hash, self::KEY, true)), $f['hash']);

        Http::assertNothingSent();
    }

    public function test_return_url_falls_back_to_the_configured_storefront_for_unknown_origins(): void
    {
        Http::fake();
        $order = $this->order();

        $this->payByCard($order, ['Origin' => 'https://evil.example'])
            ->assertJsonPath('data.checkout.fields.continue_success_url', "https://shop.mirai.test/pay/{$order->public_id}?returned=1");
    }

    public function test_each_card_checkout_gets_a_new_tran_id(): void
    {
        Http::fake();
        $order = $this->order();

        $first = $this->payByCard($order)->json('data.tran_id');
        $this->travel(5)->seconds();
        $second = $this->payByCard($order)->json('data.tran_id');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_a_double_click_reuses_the_card_payment_just_created(): void
    {
        Http::fake();
        $order = $this->order();

        $first = $this->payByCard($order)->json('data.tran_id');
        $this->assertSame($first, $this->payByCard($order)->json('data.tran_id'));
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_card_payment_is_confirmed_by_the_status_check(): void
    {
        $this->fakeCheck();
        $order = $this->order();
        $this->payByCard($order);

        $this->getJson("/api/orders/{$order->public_id}/payment")
            ->assertOk()
            ->assertJsonPath('data.method', 'card')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.order.status', 'paid');

        $this->assertSame(3, $this->product->fresh()->stock);
    }

    public function test_an_older_pending_attempt_that_gets_paid_still_marks_the_order_paid(): void
    {
        // Card checkout started, then the customer switched to KHQR, but the card payment went through.
        Http::fake([
            '*/generate-qr' => Http::response([
                'status' => ['code' => '0'], 'qrString' => 'KHQR', 'qrImage' => 'data:image/png;base64,AAAA',
            ]),
            '*/check-transaction-2' => function ($request) {
                $paid = $request['tran_id'] === $this->cardTranId;

                return Http::response([
                    'data' => ['payment_status' => $paid ? 'APPROVED' : 'PENDING', 'payment_amount' => $paid ? 5.0 : 0, 'payment_currency' => $paid ? 'USD' : '', 'apv' => $paid ? '654321' : ''],
                    'status' => ['code' => '00'],
                ]);
            },
        ]);
        $order = $this->order();
        $this->cardTranId = $this->payByCard($order)->json('data.tran_id');
        $this->travel(5)->seconds();
        $this->postJson("/api/orders/{$order->public_id}/pay")->assertCreated()->assertJsonPath('data.method', 'khqr');

        $this->getJson("/api/orders/{$order->public_id}/payment")
            ->assertJsonPath('data.tran_id', $this->cardTranId)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.order.status', 'paid');
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $order = $this->order();

        $this->postJson("/api/orders/{$order->public_id}/pay", ['method' => 'bitcoin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('method');
    }
}
