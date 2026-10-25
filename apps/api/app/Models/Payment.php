<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'tran_id', 'status', 'amount', 'currency',
        'qr_string', 'qr_image', 'deeplink', 'apv', 'confirmed_by', 'expires_at', 'checked_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'checked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** Still payable: pending and not past its lifetime. */
    public function isOpen(): bool
    {
        return $this->status === PaymentStatus::Pending && $this->expires_at->isFuture();
    }
}
