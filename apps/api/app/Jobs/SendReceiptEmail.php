<?php

namespace App\Jobs;

use App\Jobs\Concerns\SendsOncePerOrder;
use App\Mail\OrderReceipt;
use App\Models\Order;
use App\Services\Invoices\InvoicePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReceiptEmail implements ShouldQueue
{
    use Queueable, SendsOncePerOrder;

    /** Waits between retries; the worker's --tries=3 caps the attempts. */
    public array $backoff = [30, 120];

    public function __construct(public int $orderId) {}

    public function handle(InvoicePdf $invoice): void
    {
        $order = Order::with('items')->findOrFail($this->orderId);

        if (blank($order->customer_email)) {
            return; // email is optional at checkout
        }

        $this->sendOnce($order->id, 'receipt_email', function () use ($order, $invoice) {
            Mail::to($order->customer_email)->send(
                new OrderReceipt($order, $invoice->render($order), $invoice->filename($order)),
            );
        });
    }
}
