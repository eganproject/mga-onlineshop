<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu baris pengaturan toko: key = kunci config (mis. "shop.whatsapp"), value = JSON. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
