<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\ShippingUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingService;
use Illuminate\Http\JsonResponse;

/** Data wilayah (JSON) untuk pilihan bertingkat di form alamat. */
class RegionController extends Controller
{
    public function __construct(private readonly ShippingService $shipping) {}

    public function provinces(): JsonResponse
    {
        return $this->respond(fn () => $this->shipping->provinces());
    }

    public function cities(string $province): JsonResponse
    {
        // ID wilayah selalu angka; tolak input lain sebelum memanggil API.
        return $this->respond(fn () => ctype_digit($province) ? $this->shipping->cities($province) : []);
    }

    public function districts(string $city): JsonResponse
    {
        return $this->respond(fn () => ctype_digit($city) ? $this->shipping->districts($city) : []);
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()]);
        } catch (ShippingUnavailableException $e) {
            return response()->json(['data' => [], 'message' => $e->getMessage()], 503);
        }
    }
}
