<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menerima HTTP notification dari Midtrans. Atur URL ini di dashboard
 * Midtrans: Settings → Payment → Notification URL.
 *
 * Notifikasi bisa dikirim ulang atau tidak berurutan; PaymentService
 * menerapkannya secara idempoten.
 */
class MidtransController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $gateway = $payments->gateway();

        if ($gateway->name() !== 'midtrans') {
            return response()->json(['message' => 'Midtrans tidak aktif.'], 404);
        }

        $update = $gateway->parseNotification($request->all());

        if (! $update) {
            return response()->json(['message' => 'Notifikasi tidak valid.'], 403);
        }

        if (! $payments->apply($update)) {
            return response()->json(['message' => 'Tagihan tidak ditemukan.'], 404);
        }

        return response()->json(['message' => 'OK']);
    }
}
