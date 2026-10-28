<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
 * memakai order_id contoh) tetap dijawab 200. Hanya tanda tangan yang salah
 * yang ditolak.
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
            Log::warning('Notifikasi Midtrans ditolak: tanda tangan tidak valid. Pastikan Server Key & mode (Sandbox/Production) di Admin › Integrasi sama dengan dashboard Midtrans yang mengirim.', [
                'order_id' => $request->input('order_id'),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Tanda tangan notifikasi tidak valid.'], 403);
        }

        if (! $payments->apply($update)) {
            Log::info('Notifikasi Midtrans tanpa tagihan yang cocok, diabaikan.', ['order_id' => $update->externalId]);

            return response()->json(['message' => 'OK, tidak ada tagihan dengan order_id ini.']);
        }

        return response()->json(['message' => 'OK']);
    }
}
