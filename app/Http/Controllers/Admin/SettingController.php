<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Ringkasan status integrasi (read-only). Nilai diatur lewat file .env. */
class SettingController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.settings', [
            'integrations' => [
                [
                    'name' => 'Midtrans',
                    'description' => 'Payment gateway (Snap): transfer bank, e-wallet, kartu kredit, QRIS.',
                    'driver' => config('shop.payment.driver'),
                    'active' => config('shop.payment.driver') === 'midtrans',
                    'configured' => filled(config('services.midtrans.server_key')) && filled(config('services.midtrans.client_key')),
                    'mode' => config('services.midtrans.is_production') ? 'Production' : 'Sandbox',
                    'env' => ['PAYMENT_DRIVER=midtrans', 'MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY', 'MIDTRANS_IS_PRODUCTION'],
                    'webhook' => route('webhooks.midtrans'),
                ],
                [
                    'name' => 'RajaOngkir',
                    'description' => 'Cek ongkos kirim JNE, SiCepat, J&T, POS, TIKI, dan lainnya.',
                    'driver' => config('shop.shipping.driver'),
                    'active' => config('shop.shipping.driver') === 'rajaongkir',
                    'configured' => filled(config('services.rajaongkir.api_key')) && filled(config('shop.shipping.origin_district_id')),
                    'mode' => implode(', ', array_map('strtoupper', config('shop.shipping.couriers'))),
                    'env' => ['SHIPPING_DRIVER=rajaongkir', 'RAJAONGKIR_API_KEY', 'SHOP_ORIGIN_DISTRICT_ID', 'RAJAONGKIR_COURIERS'],
                    'webhook' => null,
                ],
            ],
        ]);
    }
}
