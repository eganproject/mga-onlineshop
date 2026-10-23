<?php

namespace App\Http\Requests;

use App\Exceptions\ShippingUnavailableException;
use App\Services\Shipping\ShippingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddressRequest extends FormRequest
{
    /** Hasil pencocokan wilayah dengan penyedia ongkir (diisi saat validasi). */
    private ?array $region = null;

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
            'province_id' => ['required', 'string', 'max:20'],
            'city_id' => ['required', 'string', 'max:20'],
            'district_id' => ['required', 'string', 'max:20'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^[0-9]{5}$/'],
            'address_line' => ['required', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->hasAny(['province_id', 'city_id', 'district_id'])) {
                return;
            }

            try {
                $this->region = app(ShippingService::class)->resolveRegion(
                    $this->input('province_id'), $this->input('city_id'), $this->input('district_id'),
                );
            } catch (ShippingUnavailableException $e) {
                $validator->errors()->add('district_id', $e->getMessage());

                return;
            }

            if (! $this->region) {
                $validator->errors()->add('district_id', 'Kombinasi provinsi, kota, dan kecamatan tidak valid. Silakan pilih ulang.');
            }
        }];
    }

    /** Data siap simpan: nama wilayah diambil dari penyedia, bukan dari form. */
    public function addressData(): array
    {
        $data = $this->safe()->except('is_default');

        return [
            ...$data,
            'province' => $this->region['province']->name,
            'city' => $this->region['city']->name,
            'district' => $this->region['district']->name,
            'postal_code' => $data['postal_code'] ?? $this->region['district']->postalCode,
            'is_default' => $this->boolean('is_default'),
        ];
    }

    public function attributes(): array
    {
        return [
            'label' => 'label alamat',
            'recipient_name' => 'nama penerima',
            'phone' => 'nomor HP',
            'province_id' => 'provinsi',
            'city_id' => 'kota/kabupaten',
            'district_id' => 'kecamatan',
            'postal_code' => 'kode pos',
            'address_line' => 'alamat lengkap',
        ];
    }

    public function messages(): array
    {
        return ['postal_code.regex' => 'Kode pos harus 5 digit angka.'];
    }
}
