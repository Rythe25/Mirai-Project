<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /** Signed PayWay card checkout form ({action, fields}); only sent when a card payment is started. */
    public ?array $checkout = null;

    public function toArray(Request $request): array
    {
        return [
            'method' => $this->method->value,
            'tran_id' => $this->tran_id,
            'status' => $this->status->value,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'qr_image' => $this->qr_image,
            'deeplink' => $this->deeplink,
            'checkout' => $this->when($this->checkout !== null, fn () => $this->checkout),
            'expires_at' => $this->expires_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'order' => [
                'public_id' => $this->order->public_id,
                'order_no' => $this->order->order_no,
                'status' => $this->order->status->value,
            ],
        ];
    }
}
