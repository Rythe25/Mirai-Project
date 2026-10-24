<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Payway\PaywayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncPayment
{
    public function __construct(private PaywayClient $payway) {}

    /** Ask PayWay for the real status. The callback body and the browser are never trusted. */
    public function handle(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        $result = $this->payway->checkTransaction($payment->tran_id);
        $payment->update(['checked_at' => now()]);

        return match ($result['status']) {
            'APPROVED' => $this->approve($payment, $result),
            'DECLINED', 'CANCELLED' => tap($payment)->update(['status' => PaymentStatus::Failed]),
            default => $this->expireIfDue($payment), // PENDING, NOT_FOUND
        };
    }

    private function approve(Payment $payment, array $result): Payment
    {
        return DB::transaction(function () use ($payment, $result) {
            // Lock so a callback and a poll can't both reduce stock.
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending) {
                return $payment;
            }

            if ($result['amount'] !== (string) $payment->amount || $result['currency'] !== $payment->currency) {
                Log::warning('PayWay amount mismatch', [
                    'tran_id' => $payment->tran_id,
                    'expected' => "{$payment->amount} {$payment->currency}",
                    'paid' => "{$result['amount']} {$result['currency']}",
                ]);
                $payment->update(['status' => PaymentStatus::Failed]);

                return $payment;
            }

            $payment->update(['status' => PaymentStatus::Approved, 'apv' => $result['apv'], 'paid_at' => now()]);

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($payment->order_id);

            if ($order->status === OrderStatus::Pending) {
                foreach ($order->items as $item) {
                    Product::query()->whereKey($item->product_id)->decrement('stock', $item->qty);
                }
                $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
            }

            return $payment;
        });
    }

    private function expireIfDue(Payment $payment): Payment
    {
        if ($payment->expires_at->isPast()) {
            $payment->update(['status' => PaymentStatus::Expired]);
        }

        return $payment;
    }
}
