<?php

namespace App\Services\Shipping;

use App\Contracts\ShippingProvider;
use App\Data\Destination;
use App\Data\ShippingRate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrasi RajaOngkir versi Komerce (https://rajaongkir.komerce.id).
 *
 * Kerangka awal: endpoint dan pemetaan respons perlu diverifikasi dengan
 * API key asli sebelum dipakai di produksi.
 */
class RajaOngkirShippingProvider implements ShippingProvider
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly ?string $originId,
        private readonly array $couriers,
    ) {}

    public function searchDestinations(string $keyword): array
    {
        if (mb_strlen(trim($keyword)) < 3) {
            return [];
        }

        $data = Cache::remember(
            'rajaongkir:destinations:'.md5(mb_strtolower($keyword)),
            now()->addDay(),
            fn () => $this->client()
                ->get('/destination/domestic-destination', ['search' => $keyword, 'limit' => 10, 'offset' => 0])
                ->throw()
                ->json('data', []),
        );

        return collect($data)->map(fn (array $row) => new Destination(
            id: (string) $row['id'],
            label: $row['label'] ?? '',
            province: $row['province_name'] ?? '',
            city: $row['city_name'] ?? '',
            district: $row['district_name'] ?? null,
            postalCode: isset($row['zip_code']) ? (string) $row['zip_code'] : null,
        ))->all();
    }

    public function rates(string $destinationId, int $weight): array
    {
        if (! $this->originId) {
            throw new RuntimeException('SHOP_ORIGIN_CITY_ID belum diatur.');
        }

        $data = $this->client()
            ->asForm()
            ->post('/calculate/domestic-cost', [
                'origin' => $this->originId,
                'destination' => $destinationId,
                'weight' => max(1, $weight),
                'courier' => implode(':', $this->couriers),
                'price' => 'lowest',
            ])
            ->throw()
            ->json('data', []);

        return collect($data)->map(fn (array $row) => new ShippingRate(
            courier: mb_strtolower($row['code']),
            courierName: $row['name'] ?? mb_strtoupper($row['code']),
            service: $row['service'],
            description: $row['description'] ?? '',
            cost: (int) $row['cost'],
            etd: $row['etd'] ?? null,
        ))->all();
    }

    private function client(): PendingRequest
    {
        if (! $this->apiKey) {
            throw new RuntimeException('RAJAONGKIR_API_KEY belum diatur.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['key' => $this->apiKey])
            ->acceptJson()
            ->timeout(15);
    }
}
