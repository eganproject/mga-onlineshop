<?php

namespace App\Enums;

/**
 * Metode pembayaran Midtrans yang tarif biayanya bisa diatur di
 * Admin › Pajak & Biaya. Nilainya disimpan di payments.method dan
 * orders.payment_method.
 */
enum PaymentMethod: string
{
    case Qris = 'qris';
    case Gopay = 'gopay';
    case Shopeepay = 'shopeepay';
    case BcaVa = 'bca_va';
    case BniVa = 'bni_va';
    case BriVa = 'bri_va';
    case PermataVa = 'permata_va';
    case CimbVa = 'cimb_va';
    case MandiriBill = 'mandiri_bill';
    case CreditCard = 'credit_card';
    case Indomaret = 'indomaret';
    case Alfamart = 'alfamart';
    case Akulaku = 'akulaku';
    case Kredivo = 'kredivo';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::Gopay => 'GoPay',
            self::Shopeepay => 'ShopeePay',
            self::BcaVa => 'BCA Virtual Account',
            self::BniVa => 'BNI Virtual Account',
            self::BriVa => 'BRI Virtual Account',
            self::PermataVa => 'Permata Virtual Account',
            self::CimbVa => 'CIMB Niaga Virtual Account',
            self::MandiriBill => 'Mandiri Bill Payment',
            self::CreditCard => 'Kartu kredit/debit',
            self::Indomaret => 'Indomaret',
            self::Alfamart => 'Alfamart',
            self::Akulaku => 'Akulaku PayLater',
            self::Kredivo => 'Kredivo',
        };
    }

    /** Nama pendek untuk tabel & laporan. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::BcaVa => 'VA BCA',
            self::BniVa => 'VA BNI',
            self::BriVa => 'VA BRI',
            self::PermataVa => 'VA Permata',
            self::CimbVa => 'VA CIMB',
            self::MandiriBill => 'Mandiri Bill',
            self::CreditCard => 'Kartu',
            default => $this->label(),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Qris, self::Gopay, self::Shopeepay => 'QRIS & e-wallet',
            self::BcaVa, self::BniVa, self::BriVa, self::PermataVa, self::CimbVa, self::MandiriBill => 'Transfer bank (Virtual Account)',
            self::CreditCard => 'Kartu',
            self::Indomaret, self::Alfamart => 'Gerai retail',
            self::Akulaku, self::Kredivo => 'Cicilan / PayLater',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Qris, self::Gopay, self::Shopeepay => 'qr-code',
            self::CreditCard => 'credit-card',
            self::Indomaret, self::Alfamart => 'storefront',
            default => 'banknotes',
        };
    }

    /** Kode `enabled_payments` Midtrans Snap untuk metode ini. */
    public function snapCode(): string
    {
        return match ($this) {
            self::Qris => 'other_qris',
            self::MandiriBill => 'echannel',
            default => $this->value,
        };
    }

    /**
     * Tarif standar Midtrans (contoh, belum termasuk PPN). Sesuaikan dengan
     * kontrak toko di Admin › Pajak & Biaya.
     *
     * @return array{percent: float, flat: int}
     */
    public function defaultRate(): array
    {
        return match ($this) {
            self::Qris => ['percent' => 0.7, 'flat' => 0],
            self::Gopay, self::Shopeepay, self::Kredivo => ['percent' => 2.0, 'flat' => 0],
            self::Akulaku => ['percent' => 1.7, 'flat' => 0],
            self::CreditCard => ['percent' => 2.9, 'flat' => 2000],
            self::Indomaret, self::Alfamart => ['percent' => 0.0, 'flat' => 5000],
            default => ['percent' => 0.0, 'flat' => 4000],
        };
    }

    /**
     * Cocokkan metode dari notifikasi/status Midtrans (payment_type + detail
     * bank/gerai). Null bila tidak dikenal.
     */
    public static function fromMidtrans(?string $paymentType, array $payload = []): ?self
    {
        return match ($paymentType) {
            'qris', 'other_qris' => self::Qris,
            'gopay' => self::Gopay,
            'shopeepay' => self::Shopeepay,
            'echannel' => self::MandiriBill,
            'credit_card' => self::CreditCard,
            'akulaku' => self::Akulaku,
            'kredivo' => self::Kredivo,
            'cstore' => self::tryFrom(strtolower((string) ($payload['store'] ?? ''))),
            'bank_transfer' => isset($payload['permata_va_number'])
                ? self::PermataVa
                : self::tryFrom(strtolower((string) ($payload['va_numbers'][0]['bank'] ?? '')).'_va'),
            default => self::tryFrom((string) $paymentType),
        };
    }

    /** Label untuk nilai payments.method / orders.payment_method apa pun (termasuk "manual"). */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value)?->label() ?? match ($value) {
            'manual' => 'Konfirmasi manual',
            default => ucwords(str_replace('_', ' ', $value)),
        };
    }
}
