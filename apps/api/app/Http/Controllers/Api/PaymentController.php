<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\SyncPayment;
use App\Services\Payway\PaywayClient;
use App\Services\Payway\PaywayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    /**
     * Start a payment for a pending order. KHQR: create a QR, or return the one still open.
     * Card: a new tran_id plus a signed PayWay checkout form for the browser to post.
     */
    public function store(StartPaymentRequest $request, Order $order, PaywayClient $payway): PaymentResource
    {
        abort_unless($order->status === OrderStatus::Pending, 409, 'This order is not awaiting payment.');

        $method = $request->paymentMethod();

        // Lock so two requests at once (e.g. React dev double effects, double clicks) don't create two payments.
        $payment = Cache::lock("pay-order-{$order->id}", 20)->block(10, function () use ($order, $payway, $method) {
            $latest = $order->payments()->latest('id')->first();

            if ($latest?->method === $method && $latest->isOpen()) {
                // A KHQR stays reusable while open. A card checkout gets a new tran_id each time, except a
                // repeat within a few seconds (same tran_id would collide, and it's the same click anyway).
                if ($method === PaymentMethod::Khqr || $latest->created_at->gt(now()->subSeconds(3))) {
                    return $latest;
                }
            }

            $tranId = 'MR'.$order->id.'T'.now()->format('ymdHis');
            $attributes = [
                'method' => $method,
                'tran_id' => $tranId,
                'status' => PaymentStatus::Pending,
                'amount' => $order->total,
                'currency' => $order->currency,
                'expires_at' => now()->addMinutes(config('services.payway.lifetime')),
            ];

            if ($method === PaymentMethod::Khqr) {
                $qr = $payway->generateQr($tranId, (string) $order->total, $order->currency);
                $attributes += [
                    'qr_string' => $qr['qrString'],
                    'qr_image' => $qr['qrImage'],
                    'deeplink' => $qr['abapay_deeplink'] ?? null,
                ];
            }

            return $order->payments()->create($attributes);
        });

        $resource = PaymentResource::make($payment);

        if ($payment->method === PaymentMethod::Card) {
            // Signed fresh on every call: req_time is part of the hash.
            $resource->checkout = $payway->cardCheckout(
                $payment->tran_id,
                (string) $payment->amount,
                $payment->currency,
                $this->returnUrl($request, $order),
            );
        }

        return $resource;
    }

    /**
     * Polled by the pay page. Asks PayWay about each recent pending attempt at most every 5 s,
     * because a customer may pay an older QR or card checkout after starting a new one.
     */
    public function show(Order $order, SyncPayment $sync): PaymentResource
    {
        $pending = $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->limit(5)->get();

        foreach ($pending as $payment) {
            if ($payment->checked_at?->gt(now()->subSeconds(5))) {
                continue;
            }

            try {
                $sync->handle($payment);
            } catch (PaywayException $e) {
                report($e); // keep polling; the next call retries
            }
        }

        $payment = $order->payments()->where('status', PaymentStatus::Approved)->latest('id')->first()
            ?? $order->payments()->latest('id')->first()
            ?? abort(404, 'No payment for this order yet.');

        return PaymentResource::make($payment);
    }

    /** Where PayWay sends the card customer back: the calling storefront if its origin is allowed, else the configured one. */
    private function returnUrl(Request $request, Order $order): string
    {
        $origin = $request->headers->get('Origin');
        $storefront = in_array($origin, config('cors.allowed_origins'), true) ? $origin : config('app.storefront_url');

        return rtrim($storefront, '/')."/pay/{$order->public_id}?returned=1";
    }
}
