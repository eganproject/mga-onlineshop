<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'label', 'recipient_name', 'phone', 'province_id', 'province', 'city_id', 'city',
    'district_id', 'district', 'postal_code', 'address_line', 'is_default',
])]
class Address extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ongkir hanya bisa dihitung bila kecamatan sudah dipilih dari data wilayah. */
    public function canShip(): bool
    {
        return filled($this->district_id);
    }

    /** Alamat lengkap dalam satu baris, dipakai sebagai salinan di pesanan. */
    public function fullAddress(): string
    {
        return collect([
            $this->address_line,
            $this->district,
            $this->city,
            $this->province,
            $this->postal_code,
        ])->filter()->implode(', ');
    }
}
