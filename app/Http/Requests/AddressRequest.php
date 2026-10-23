<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:500'],
            'destination_id' => ['nullable', 'string', 'max:50'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'label' => 'label alamat',
            'recipient_name' => 'nama penerima',
            'phone' => 'nomor HP',
            'province' => 'provinsi',
            'city' => 'kota/kabupaten',
            'district' => 'kecamatan',
            'postal_code' => 'kode pos',
            'address_line' => 'alamat lengkap',
        ];
    }
}
