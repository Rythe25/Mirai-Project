<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUlids;

    protected $fillable = [
        'status', 'currency', 'subtotal', 'total',
        'customer_name', 'customer_phone', 'customer_email', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /** ULID goes in public_id; the numeric id stays the primary key. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $order->forceFill([
                'order_no' => 'MR-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
