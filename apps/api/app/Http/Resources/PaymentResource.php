<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tran_id' => $this->tran_id,
            'status' => $this->status->value,
            'amount' => (string) $this->amount,
            'currency' => $this->currency,
            'qr_image' => $this->qr_image,
            'deeplink' => $this->deeplink,
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
