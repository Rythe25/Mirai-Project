<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramNotifyOrderPaid implements ShouldQueue
{
    use Queueable;

    private const CHANNEL = 'telegram_orders';

    /** Waits between retries; the worker's --tries=3 caps the attempts. */
    public array $backoff = [30, 120];

    public function __construct(public int $orderId) {}

    public function handle(TelegramClient $telegram): void
    {
        if (! $telegram->isConfigured()) {
            Log::info('Telegram not configured; order paid message skipped', ['order_id' => $this->orderId]);

            return;
        }

        // One lock per order + channel, so a retry and a duplicate job can't both send.
        Cache::lock("notify-{$this->orderId}-".self::CHANNEL, 30)->block(10, function () use ($telegram) {
            $log = NotificationLog::firstOrCreate(['order_id' => $this->orderId, 'channel' => self::CHANNEL]);

            if ($log->status === 'sent') {
                return;
            }

            $order = Order::with('items')->findOrFail($this->orderId);
            $payment = $order->payments()->where('status', PaymentStatus::Approved)->latest('id')->first();
            $log->increment('attempts');

            try {
                $telegram->sendToOrdersChat($this->message($order, $payment));
            } catch (TelegramException $e) {
                $log->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

                throw $e; // the queue retries it
            }

            $log->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        });
    }

    private function message(Order $order, ?Payment $payment): string
    {
        $how = match (true) {
            $payment === null => 'Paid',
            $payment->confirmed_by !== null => $payment->method->getLabel().' · marked paid in the backoffice (sandbox)',
            default => $payment->method->getLabel().' · APV '.e($payment->apv),
        };
        $items = $order->items
            ->map(fn ($item) => '• '.e($item->product_name)." × {$item->qty} — {$item->line_total}")
            ->implode("\n");

        return implode("\n", array_filter([
            "✅ <b>Order {$order->order_no} paid</b>",
            "<b>{$order->total} {$order->currency}</b> · {$how}",
            $items,
            ' ', // spacer line between the items and the customer (array_filter would drop '')
            'Customer: '.e($order->customer_name).' · '.e($order->customer_phone),
            $payment ? 'Tran ID: <code>'.e($payment->tran_id).'</code>' : null,
            '<a href="'.e(OrderResource::getUrl('view', ['record' => $order], panel: 'admin')).'">Open in backoffice</a>',
        ]));
    }
}
