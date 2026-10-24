<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Services\Payments\SyncPayment;
use App\Services\Payway\PaywayClient;
use App\Services\Payway\PaywayException;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /** Create a KHQR for a pending order, or return the one still open. */
    public function store(Order $order, PaywayClient $payway): PaymentResource
    {
        abort_unless($order->status === OrderStatus::Pending, 409, 'This order is not awaiting payment.');

        // Lock so two requests at once (e.g. React dev double effects) don't create two QRs.
        $payment = Cache::lock("pay-order-{$order->id}", 20)->block(10, function () use ($order, $payway) {
            $latest = $order->payments()->latest('id')->first();

            if ($latest?->isOpen()) {
                return $latest;
            }

            $tranId = 'MR'.$order->id.'T'.now()->format('ymdHis');
            $qr = $payway->generateQr($tranId, (string) $order->total, $order->currency);

            return $order->payments()->create([
                'tran_id' => $tranId,
                'status' => PaymentStatus::Pending,
                'amount' => $order->total,
                'currency' => $order->currency,
                'qr_string' => $qr['qrString'],
                'qr_image' => $qr['qrImage'],
                'deeplink' => $qr['abapay_deeplink'] ?? null,
                'expires_at' => now()->addMinutes(config('services.payway.lifetime')),
            ]);
        });

        return PaymentResource::make($payment);
    }

    /** Polled by the pay page. Asks PayWay at most every 5 s while the QR is open. */
    public function show(Order $order, SyncPayment $sync): PaymentResource
    {
        $payment = $order->payments()->latest('id')->first() ?? abort(404, 'No payment for this order yet.');

        if ($payment->status === PaymentStatus::Pending && ! $payment->checked_at?->gt(now()->subSeconds(5))) {
            try {
                $payment = $sync->handle($payment);
            } catch (PaywayException $e) {
                report($e); // keep polling; the next call retries
            }
        }

        return PaymentResource::make($payment);
    }
}
