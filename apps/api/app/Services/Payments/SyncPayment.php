<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\TelegramNotifyOrderPaid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
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
        if ($result['amount'] !== (string) $payment->amount || $result['currency'] !== $payment->currency) {
            Log::warning('PayWay amount mismatch', [
                'tran_id' => $payment->tran_id,
                'expected' => "{$payment->amount} {$payment->currency}",
                'paid' => "{$result['amount']} {$result['currency']}",
            ]);
            $payment->update(['status' => PaymentStatus::Failed]);

            return $payment;
        }

        return $this->markPaid($payment, $result['apv']);
    }

    /**
     * Approve the payment, mark the order paid and reduce stock once.
     * Called after ABA confirms, or by staff in the sandbox ($confirmedBy set).
     */
    public function markPaid(Payment $payment, ?string $apv, ?User $confirmedBy = null): Payment
    {
        return DB::transaction(function () use ($payment, $apv, $confirmedBy) {
            // Lock so a callback, a poll and a staff click can't all reduce stock.
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            // Staff may also approve a sandbox QR that expired while they were looking.
            $approvable = $confirmedBy ? [PaymentStatus::Pending, PaymentStatus::Expired] : [PaymentStatus::Pending];

            if (! in_array($payment->status, $approvable, true)) {
                return $payment;
            }

            $payment->update([
                'status' => PaymentStatus::Approved,
                'apv' => $apv,
                'confirmed_by' => $confirmedBy?->id,
                'paid_at' => now(),
            ]);

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($payment->order_id);

            if ($order->status === OrderStatus::Pending) {
                foreach ($order->items as $item) {
                    Product::query()->whereKey($item->product_id)->decrement('stock', $item->qty);
                }
                $order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);

                // Queued after the commit, so the job never sees an unpaid order or a rolled-back payment.
                TelegramNotifyOrderPaid::dispatch($order->id)->afterCommit();
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
