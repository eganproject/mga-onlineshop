<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * Langkah pembayaran per metode untuk halaman bayar. Nomor VA / kode bayar
 * ditampilkan terpisah (dengan tombol salin), jadi langkah cukup menyebut
 * "nomor di atas".
 */
final class PaymentInstructions
{
    /** @return array<string, list<string>> judul cara bayar => langkah */
    public static function steps(Payment $payment): array
    {
        $method = $payment->paymentMethod();

        if (! $method) {
            return [];
        }

        $bank = $method->provider();
        $confirm = 'Pastikan nama toko dan nominal sesuai, lalu konfirmasi dengan PIN.';

        return match ($method) {
            PaymentMethod::BcaVa => [
                'BCA mobile (m-BCA)' => [
                    'Buka BCA mobile, pilih m-BCA lalu masukkan kode akses.',
                    'Pilih m-Transfer › BCA Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    $confirm,
                ],
                'myBCA' => [
                    'Buka myBCA, pilih Transfer › Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    $confirm,
                ],
                'ATM BCA' => [
                    'Masukkan kartu ATM dan PIN.',
                    'Pilih Transaksi Lainnya › Transfer › ke Rek BCA Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    'Periksa detail tagihan lalu pilih Ya.',
                ],
                'KlikBCA' => [
                    'Masuk ke KlikBCA Individual.',
                    'Pilih Transfer Dana › Transfer ke BCA Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    'Konfirmasi dengan respon KeyBCA.',
                ],
            ],
            PaymentMethod::BniVa, PaymentMethod::BriVa, PaymentMethod::PermataVa, PaymentMethod::CimbVa => [
                "Mobile banking {$bank}" => [
                    "Buka aplikasi mobile banking {$bank}.",
                    'Pilih menu Bayar atau Transfer › Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    $confirm,
                ],
                "ATM {$bank}" => [
                    'Masukkan kartu ATM dan PIN.',
                    'Pilih Menu Lain › Transfer/Pembayaran › Virtual Account.',
                    'Masukkan nomor Virtual Account di atas.',
                    'Periksa detail tagihan lalu konfirmasi.',
                ],
                'Dari bank lain' => [
                    'Buka m-banking/ATM bank kamu, pilih Transfer ke bank lain (online/BI-FAST).',
                    "Pilih bank tujuan {$bank}.",
                    'Masukkan nomor Virtual Account di atas sebagai nomor rekening.',
                    'Isi nominal persis sesuai tagihan, lalu konfirmasi.',
                ],
            ],
            PaymentMethod::MandiriBill => [
                "Livin' by Mandiri" => [
                    "Buka Livin' by Mandiri, pilih Bayar › E-Commerce (atau cari \"Midtrans\").",
                    'Pilih penyedia jasa Midtrans (kode perusahaan di atas).',
                    'Masukkan kode pembayaran di atas.',
                    $confirm,
                ],
                'ATM Mandiri' => [
                    'Masukkan kartu ATM dan PIN.',
                    'Pilih Bayar/Beli › Lainnya › Multi Payment.',
                    'Masukkan kode perusahaan, lalu kode pembayaran di atas.',
                    'Periksa detail tagihan lalu konfirmasi.',
                ],
            ],
            PaymentMethod::Qris => [
                'Dari HP lain / laptop' => [
                    'Buka aplikasi e-wallet atau m-banking yang mendukung QRIS (GoPay, OVO, DANA, ShopeePay, LinkAja, BCA mobile, dll).',
                    'Pilih Scan / Bayar, lalu arahkan kamera ke kode QR.',
                    $confirm,
                ],
                'Dari HP yang sama' => [
                    'Tekan Simpan QR untuk menyimpan gambar ke galeri.',
                    'Di aplikasi e-wallet/m-banking, pilih Scan lalu ambil gambar dari galeri.',
                    $confirm,
                ],
            ],
            PaymentMethod::Gopay => [
                'Dari HP ini' => [
                    'Tekan tombol Buka aplikasi GoPay.',
                    'Periksa nominal lalu konfirmasi dengan PIN GoPay.',
                ],
                'Scan QR' => [
                    'Buka aplikasi Gojek atau GoPay, pilih Bayar/Scan.',
                    'Arahkan kamera ke kode QR.',
                    'Periksa nominal lalu konfirmasi dengan PIN GoPay.',
                ],
            ],
            PaymentMethod::Shopeepay => [
                'ShopeePay' => [
                    'Buka halaman ini di HP yang ada aplikasi Shopee.',
                    'Tekan tombol Buka aplikasi ShopeePay.',
                    'Periksa nominal lalu konfirmasi dengan PIN ShopeePay.',
                ],
            ],
            PaymentMethod::Indomaret, PaymentMethod::Alfamart => [
                "Di kasir {$method->label()}" => [
                    "Datang ke gerai {$method->label()} terdekat.",
                    'Sampaikan ke kasir ingin membayar tagihan Midtrans dan tunjukkan kode pembayaran di atas.',
                    'Bayar sesuai nominal (kasir bisa menambahkan biaya admin gerai).',
                    'Simpan struk sebagai bukti pembayaran.',
                ],
            ],
            default => [],
        };
    }
}
