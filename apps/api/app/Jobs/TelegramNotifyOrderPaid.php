<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Jobs\Concerns\SendsOncePerOrder;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Telegram\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TelegramNotifyOrderPaid implements ShouldQueue
{
    use Queueable, SendsOncePerOrder;

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

        $this->sendOnce($this->orderId, self::CHANNEL, function () use ($telegram) {
            $order = Order::with('items')->findOrFail($this->orderId);
            $payment = $order->payments()->where('status', PaymentStatus::Approved)->latest('id')->first();
            $telegram->sendToOrdersChat($this->message($order, $payment));
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
