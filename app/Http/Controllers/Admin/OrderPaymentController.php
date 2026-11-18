<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PaymentUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Activity\ActivityLogger;
use App\Services\Billing\BillingSettings;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Tagihan pembayaran dari admin: staf memilih metode (QRIS, VA, e-wallet,
 * gerai) lalu QR / nomor VA tampil di detail pesanan dan di link tagihan
 * yang bisa dibagikan ke pembeli.
 */
class OrderPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function choose(Request $request, Order $order, ActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate(['method' => ['required', Rule::enum(PaymentMethod::class)]], [
            'method.required' => 'Pilih metode pembayaran terlebih dahulu.',
        ]);
        $method = PaymentMethod::from($data['method']);

        if (! in_array($method, self::methods(), true)) {
            return back()->withErrors(['method' => 'Metode pembayaran ini tidak tersedia untuk tagihan dari admin.']);
        }

        try {
            $payment = $this->payments->choose($order, $method);
        } catch (PaymentUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $method->label().' belum bisa dibuat. Cek pengaturan Midtrans atau coba metode lain.');
        }

        $activity->log('order.payment_created', "Membuat tagihan {$method->shortLabel()} untuk pesanan {$order->code}", $order);

        return back()->with('success', 'Tagihan '.$method->label().' siap. Bagikan link tagihan atau tunjukkan QR / nomor VA ke pembeli.')
            ->withFragment('tagihan')
            ->with('payment_created', $payment->id);
    }

    /** Cek status ke Midtrans (bila notifikasi belum masuk). */
    public function check(Order $order): RedirectResponse
    {
        try {
            $this->payments->syncPending($order);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Status pembayaran belum bisa dicek. Coba lagi nanti.');
        }

        return back()->with(...($order->refresh()->status === OrderStatus::PendingPayment
            ? ['info', 'Pembayaran belum diterima.']
            : ['success', 'Pembayaran sudah diterima.']));
    }

    /** Batalkan tagihan aktif supaya bisa memilih metode lain. */
    public function reset(Order $order): RedirectResponse
    {
        try {
            $this->payments->regenerate($order);
        } catch (PaymentUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Tagihan belum bisa dibatalkan. Coba lagi.');
        }

        return back()->with('success', 'Tagihan lama dibatalkan. Pilih metode bayar yang baru.')->withFragment('tagihan');
    }

    /**
     * Metode yang bisa dibuat dari admin: yang hasilnya QR / nomor / kode
     * (kartu & PayLater butuh formulir Midtrans yang diisi pembeli sendiri).
     *
     * @return list<PaymentMethod>
     */
    public static function methods(): array
    {
        return array_values(array_filter(
            BillingSettings::current()->enabledMethods(),
            fn (PaymentMethod $method) => $method->channel() !== PaymentMethod::CHANNEL_SNAP,
        ));
    }
}
