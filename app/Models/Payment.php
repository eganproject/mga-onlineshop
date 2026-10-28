<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'gateway', 'external_id', 'reference', 'status', 'amount', 'fee', 'gateway_fee', 'payment_type', 'method',
    'snap_token', 'redirect_url', 'instructions', 'payload', 'paid_at', 'expires_at',
])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'fee' => 'integer',
            'gateway_fee' => 'integer',
            'payload' => 'array',
            'instructions' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function methodLabel(): ?string
    {
        return PaymentMethod::labelFor($this->method ?: $this->payment_type);
    }

    public function paymentMethod(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom((string) $this->method);
    }

    /** Nilai dari instructions (nomor VA, kode bayar, QR, deeplink). */
    public function instruction(string $key): ?string
    {
        $value = $this->instructions[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
