<?php

namespace App\Http\Controllers\Webhooks;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Menerima HTTP notification dari Midtrans (atur URL ini di dashboard Midtrans). */
class MidtransController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): JsonResponse
    {
        if ($gateway->name() !== 'midtrans') {
            return response()->json(['message' => 'Midtrans tidak aktif.'], 404);
        }

        $payment = $gateway->handleNotification($request->all());

        if (! $payment) {
            return response()->json(['message' => 'Notifikasi tidak valid.'], 403);
        }

        return response()->json(['message' => 'OK']);
    }
}
