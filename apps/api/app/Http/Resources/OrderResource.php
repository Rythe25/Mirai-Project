<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,
            'order_no' => $this->order_no,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'total' => (string) $this->total,
            // Public endpoint: name only, no phone/email.
            'customer' => ['name' => $this->customer_name],
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'unit_price' => (string) $item->unit_price,
                'qty' => $item->qty,
                'line_total' => (string) $item->line_total,
            ]),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
