<?php

namespace App\Services\Invoices;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    /** PDF bytes for a paid order, rendered on demand from the order's own (snapshot) data. */
    public function render(Order $order): string
    {
        // Subsetting embeds only the glyphs used (~900 KB → a few KB per invoice).
        return Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isFontSubsettingEnabled' => true])
            ->loadView('pdf.invoice', $this->data($order))
            ->setPaper('a4')
            ->output();
    }

    /** View data, also used by tests to check what the PDF shows. */
    public function data(Order $order): array
    {
        $order->loadMissing('items');

        return [
            'order' => $order,
            'payment' => $order->payments()->where('status', PaymentStatus::Approved)->latest('id')->first(),
            'sandbox' => config('services.payway.env') === 'sandbox',
        ];
    }

    public function filename(Order $order): string
    {
        return "Mirai-{$order->order_no}.pdf";
    }
}
