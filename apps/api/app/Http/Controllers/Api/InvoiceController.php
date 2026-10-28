<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Invoices\InvoicePdf;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    /** PDF invoice for a paid order. Found by its unguessable public_id, like the order itself. */
    public function show(Order $order, InvoicePdf $invoice): Response
    {
        abort_unless($order->status === OrderStatus::Paid, 409, 'The invoice is available once the order is paid.');

        return response($invoice->render($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->filename($order).'"',
        ]);
    }
}
