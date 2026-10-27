<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\TelegramNotifyOrderPaid;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Orders\PlaceOrder;
use App\Services\Payments\SyncPayment;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram' => ['bot_token' => 'test-token', 'orders_chat_id' => '-1001']]);
    }

    /** Order of 2 × $2.50 for a customer whose name needs HTML escaping, with a pending card payment. */
    private function pendingPayment(): Payment
    {
        $product = Product::factory()->create(['name' => 'Test Cable', 'price' => '2.50', 'stock' => 5]);
        $order = app(PlaceOrder::class)->handle(
            ['name' => '<b>Bob</b>', 'phone' => '012345678'],
            [['product_id' => $product->id, 'qty' => 2]],
        );

        return $order->payments()->create([
            'method' => PaymentMethod::Card,
            'tran_id' => "MR{$order->id}T000000000000",
            'status' => PaymentStatus::Pending,
            'amount' => $order->total,
            'currency' => 'USD',
            'expires_at' => now()->addMinutes(6),
        ]);
    }

    private function runJob(Payment $payment): void
    {
        (new TelegramNotifyOrderPaid($payment->order_id))->handle(app(TelegramClient::class));
    }

    public function test_a_paid_order_sends_one_telegram_message(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $payment = $this->pendingPayment();

        app(SyncPayment::class)->markPaid($payment, '123456'); // sync queue in tests: runs after commit

        Http::assertSentCount(1);
        Http::assertSent(function (Request $r) use ($payment) {
            return str_ends_with($r->url(), '/bottest-token/sendMessage')
                && $r['chat_id'] === '-1001'
                && $r['parse_mode'] === 'HTML'
                && str_contains($r['text'], "Order {$payment->order->order_no} paid")
                && str_contains($r['text'], '5.00 USD</b> · Card · APV 123456')
                && str_contains($r['text'], 'Test Cable × 2 — 5.00')
                && str_contains($r['text'], '&lt;b&gt;Bob&lt;/b&gt;')
                && str_contains($r['text'], $payment->tran_id);
        });
        $this->assertDatabaseHas('notification_log', [
            'order_id' => $payment->order_id,
            'channel' => 'telegram_orders',
            'status' => 'sent',
            'attempts' => 1,
        ]);
    }

    public function test_paying_twice_or_rerunning_the_job_sends_once(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $payment = $this->pendingPayment();

        app(SyncPayment::class)->markPaid($payment, '123456');
        app(SyncPayment::class)->markPaid($payment, '123456');
        $this->runJob($payment); // e.g. a retried job

        Http::assertSentCount(1);
    }

    public function test_nothing_is_sent_when_telegram_is_not_configured(): void
    {
        config(['services.telegram.bot_token' => null]);
        Http::fake();
        $payment = $this->pendingPayment();

        app(SyncPayment::class)->markPaid($payment, '123456');

        Http::assertNothingSent();
        $this->assertSame('paid', $payment->order->fresh()->status->value);
    }

    public function test_a_failed_send_is_logged_and_retried_later(): void
    {
        Queue::fake();
        $payment = $this->pendingPayment();
        app(SyncPayment::class)->markPaid($payment, '123456');
        Queue::assertPushed(TelegramNotifyOrderPaid::class, 1);

        Http::fakeSequence('api.telegram.org/*')
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
            ->push(['ok' => true]);

        try {
            $this->runJob($payment);
            $this->fail('The first attempt should throw so the queue retries it.');
        } catch (TelegramException) {
        }
        $this->assertDatabaseHas('notification_log', [
            'order_id' => $payment->order_id,
            'status' => 'failed',
            'last_error' => 'Telegram rejected the message: Bad Request: chat not found',
        ]);

        $this->runJob($payment);

        $this->assertDatabaseHas('notification_log', ['order_id' => $payment->order_id, 'status' => 'sent', 'attempts' => 2]);
    }

    public function test_a_connection_error_does_not_leak_the_token(): void
    {
        Queue::fake();
        $payment = $this->pendingPayment();
        app(SyncPayment::class)->markPaid($payment, '123456');
        Http::fake(fn () => throw new ConnectionException('cURL error 6 for https://api.telegram.org/bottest-token/sendMessage'));

        try {
            $this->runJob($payment);
            $this->fail('Expected a TelegramException.');
        } catch (TelegramException $e) {
            $this->assertStringNotContainsString('test-token', $e->getMessage());
        }
        $this->assertStringNotContainsString(
            'test-token',
            (string) NotificationLog::where('order_id', $payment->order_id)->value('last_error'),
        );
    }
}
