<?php

namespace App\Services\Promo;

use App\Enums\VoucherType;
use App\Exceptions\VoucherException;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Aturan voucher: syarat pemakaian, perhitungan potongan, dan voucher yang
 * sedang dipasang di checkout (disimpan di session).
 *
 * Kuota & batas per pelanggan dihitung dari pesanan yang tidak dibatalkan,
 * sehingga pesanan batal/kedaluwarsa otomatis mengembalikan kuota.
 */
class VoucherService
{
    private const SESSION_KEY = 'checkout_voucher';

    /** Total bayar minimal setelah potongan (payment gateway menolak Rp0). */
    public const MIN_PAYABLE = 1;

    public function __construct(private readonly Session $session) {}

    public function find(?string $code): ?Voucher
    {
        $code = Voucher::normalizeCode($code);

        return $code === '' ? null : Voucher::where('code', $code)->first();
    }

    /** Cari voucher lalu pastikan bisa dipakai. */
    public function resolve(?string $code, User $user, int $subtotal): Voucher
    {
        $voucher = $this->find($code) ?? throw new VoucherException('Kode voucher tidak ditemukan.');
        $this->check($voucher, $user, $subtotal);

        return $voucher;
    }

    /** @throws VoucherException bila voucher tidak bisa dipakai pelanggan ini. */
    public function check(Voucher $voucher, User $user, int $subtotal): void
    {
        if (! $voucher->is_active) {
            throw new VoucherException('Voucher ini sedang tidak aktif.');
        }

        if ($voucher->starts_at?->isFuture()) {
            throw new VoucherException('Voucher baru berlaku mulai '.$voucher->starts_at->translatedFormat('j F Y, H:i').'.');
        }

        if ($voucher->ends_at?->isPast()) {
            throw new VoucherException('Masa berlaku voucher sudah berakhir.');
        }

        if ($subtotal <= 0) {
            // Hanya terjadi bila semua barang berharga grosir & voucher tidak boleh digabung grosir.
            throw new VoucherException('Voucher tidak berlaku untuk produk dengan harga grosir.');
        }

        if ($subtotal < $voucher->min_spend) {
            throw new VoucherException(sprintf(
                'Voucher berlaku untuk belanja minimal %s. Tambah %s lagi untuk memakainya.',
                Money::rupiah($voucher->min_spend), Money::rupiah($voucher->min_spend - $subtotal),
            ));
        }

        if ($voucher->quota !== null && $voucher->redemptions()->count() >= $voucher->quota) {
            throw new VoucherException('Kuota voucher sudah habis.');
        }

        if ($voucher->per_user_limit !== null
            && $voucher->redemptions()->where('user_id', $user->id)->count() >= $voucher->per_user_limit) {
            throw new VoucherException($voucher->per_user_limit === 1
                ? 'Kamu sudah pernah memakai voucher ini.'
                : "Voucher ini maksimal dipakai {$voucher->per_user_limit} kali per pelanggan.");
        }
    }

    /**
     * Hitung potongan barang & ongkir. Total bayar tidak pernah di bawah MIN_PAYABLE.
     *
     * @return array{discount: int, shipping_discount: int}
     */
    public function calculate(Voucher $voucher, int $subtotal, int $shipping): array
    {
        $discount = match ($voucher->type) {
            VoucherType::Percent => min(intdiv($subtotal * $voucher->value, 100), $voucher->max_discount ?? PHP_INT_MAX),
            VoucherType::Fixed => $voucher->value,
            VoucherType::FreeShipping => 0,
        };
        $discount = max(0, min($discount, $subtotal));

        $shippingDiscount = $voucher->type === VoucherType::FreeShipping
            ? ($voucher->value > 0 ? min($voucher->value, $shipping) : $shipping)
            : 0;

        $over = self::MIN_PAYABLE - ($subtotal - $discount + $shipping - $shippingDiscount);

        if ($over > 0) {
            $cut = min($discount, $over);
            $discount -= $cut;
            $shippingDiscount = max(0, $shippingDiscount - ($over - $cut));
        }

        return ['discount' => $discount, 'shipping_discount' => $shippingDiscount];
    }

    /**
     * Data voucher untuk komponen checkout (Alpine). Potongan ongkir dihitung
     * di browser karena bergantung pada kurir yang dipilih.
     */
    public function payload(Voucher $voucher, int $subtotal): array
    {
        return [
            'code' => $voucher->code,
            'name' => $voucher->name,
            'benefit' => $voucher->benefitLabel(),
            'type' => $voucher->type->value,
            'discount' => $this->calculate($voucher, $subtotal, 0)['discount'],
            'shipping_cap' => $voucher->type === VoucherType::FreeShipping && $voucher->value > 0 ? $voucher->value : null,
        ];
    }

    /**
     * Voucher publik yang sedang berjalan, untuk ditawarkan di checkout.
     * Setiap item diberi keterangan apakah bisa dipakai sekarang.
     *
     * @return Collection<int, array{voucher: Voucher, error: ?string}>
     */
    public function suggestions(User $user, int $subtotal, int $limit = 6): Collection
    {
        return Voucher::running()->where('is_public', true)
            ->orderBy('min_spend')->latest('id')->take($limit * 2)->get()
            ->map(function (Voucher $voucher) use ($user, $subtotal) {
                if ($subtotal < $voucher->min_spend) {
                    return ['voucher' => $voucher, 'error' => 'Tambah '.Money::rupiah($voucher->min_spend - $subtotal).' lagi untuk memakai'];
                }

                try {
                    $this->check($voucher, $user, $subtotal);

                    return ['voucher' => $voucher, 'error' => null];
                } catch (VoucherException $e) {
                    return ['voucher' => $voucher, 'error' => $e->getMessage()];
                }
            })
            // Sembunyikan yang kuotanya habis / sudah dipakai; yang kurang belanja tetap tampil sebagai motivasi.
            ->reject(fn ($item) => $item['error'] && $item['voucher']->min_spend <= $subtotal)
            ->take($limit)
            ->values();
    }

    public function appliedCode(): ?string
    {
        return $this->session->get(self::SESSION_KEY);
    }

    public function remember(Voucher $voucher): void
    {
        $this->session->put(self::SESSION_KEY, $voucher->code);
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }
}
