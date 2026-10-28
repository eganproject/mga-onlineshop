<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menerima HTTP notification dari Midtrans. Atur URL ini di dashboard
 * Midtrans: Settings → Payment → Notification URL.
 *
 * Notifikasi bisa dikirim ulang atau tidak berurutan; PaymentService
 * menerapkannya secara idempoten.
 *
 * Midtrans menganggap respons selain 2xx sebagai gagal dan terus mengirim
 * ulang. Karena itu notifikasi bertanda tangan valid yang tidak cocok dengan
 * tagihan mana pun (mis. tombol "Test notification URL" di dashboard, yang
 * memakai order_id contoh) tetap dijawab 200.
 *
 * Notifikasi tanpa tanda tangan valid juga dijawab 200, tetapi isinya tidak
 * dipercaya: status tagihan diambil langsung dari API Midtrans (pakai Server
 * Key toko), sehingga request palsu tidak bisa menandai pesanan lunas.
 */
class MidtransController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $gateway = $payments->gateway();

        if ($gateway->name() !== 'midtrans') {
            Log::warning('Notifikasi Midtrans diterima, tapi driver pembayaran bukan Midtrans (Admin › Integrasi).', [
                'order_id' => $request->input('order_id'),
                'driver' => $gateway->name(),
            ]);

            return response()->json(['message' => 'Midtrans belum diaktifkan di toko, notifikasi diabaikan.']);
        }

        $update = $gateway->parseNotification($request->all());

        if (! $update) {
            return $this->verifyWithMidtrans($request, $payments);
        }

        if (! $payments->apply($update)) {
            Log::info('Notifikasi Midtrans tanpa tagihan yang cocok, diabaikan.', ['order_id' => $update->externalId]);

            return response()->json(['message' => 'OK, tidak ada tagihan dengan order_id ini.']);
        }

        return response()->json(['message' => 'OK']);
    }

    private function verifyWithMidtrans(Request $request, PaymentService $payments): JsonResponse
    {
        $orderId = $request->input('order_id');

        Log::info('Notifikasi Midtrans tanpa tanda tangan valid, status dicek langsung ke Midtrans.', [
            'order_id' => is_scalar($orderId) ? $orderId : null,
            'ip' => $request->ip(),
        ]);

        if (! is_string($orderId) || $orderId === '') {
            return response()->json(['message' => 'OK, notifikasi tanpa order_id diabaikan.']);
        }

        try {
            $payment = $payments->syncByExternalId($orderId);
        } catch (Throwable $e) {
            Log::warning('Cek status Midtrans untuk notifikasi gagal', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            // Bukan 2xx supaya Midtrans mengirim ulang notifikasinya nanti.
            return response()->json(['message' => 'Status belum bisa dicek ke Midtrans, coba lagi nanti.'], 503);
        }

        return response()->json(['message' => $payment ? 'OK, status dicek ke Midtrans.' : 'OK, tidak ada tagihan dengan order_id ini.']);
    }
}
