<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Receipt email with the PDF invoice attached. Sent (not queued) from the SendReceiptEmail job. */
class OrderReceipt extends Mailable
{
    public function __construct(public Order $order, private string $pdf, private string $filename) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Mirai receipt — {$this->order->order_no}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-receipt', with: [
            'sandbox' => config('services.payway.env') === 'sandbox',
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, $this->filename)->withMime('application/pdf'),
        ];
    }
}
