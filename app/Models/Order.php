<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\OrderStatusChanged;
use App\Observers\OrderObserver;
use App\Services\Billing\BillingSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'user_id', 'status', 'source', 'subtotal', 'shipping_cost', 'discount', 'shipping_discount', 'manual_discount', 'total', 'total_weight',
    'voucher_id', 'voucher_code',
    'service_fee', 'tax_label', 'tax_rate', 'tax_inclusive', 'tax_dpp_other', 'tax_base', 'tax_amount', 'rounding',
    'payment_method', 'payment_fee', 'gateway_fee', 'payment_reference',
    'billing_name', 'billing_npwp', 'billing_address', 'billing_email',
    'recipient_name', 'recipient_phone', 'shipping_address', 'destination_id',
    'destination_postal_code', 'destination_latitude', 'destination_longitude',
    'courier', 'courier_service', 'shipping_etd', 'tracking_number', 'notes', 'payment_due_at',
    'paid_at', 'shipped_at', 'delivered_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
])]
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory;

    /** Sama dengan default kolom, supaya status langsung terisi saat dibuat. */
    protected $attributes = ['status' => 'pending_payment', 'source' => 'website'];

    /** Catatan untuk riwayat status berikutnya (tidak disimpan di tabel orders). */
    public ?string $statusNote = null;

    protected static function booted(): void
    {
        // Riwayat status dicatat otomatis di satu tempat, apa pun pemicunya
        // (admin, pelanggan, webhook pembayaran, jadwal otomatis).
        static::created(fn (Order $order) => $order->recordStatusChange(null));

        static::updated(function (Order $order) {
            if ($order->wasChanged('status')) {
                $from = $order->getOriginal('status');
                $order->recordStatusChange($from instanceof OrderStatus ? $from : OrderStatus::tryFrom((string) $from));
            }
        });
    }

    private function recordStatusChange(?OrderStatus $from): void
    {
        $note = $this->statusNote;
        $this->statusNote = null;

        $this->statusHistories()->create([
            'from_status' => $from,
            'status' => $this->status,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);

        OrderStatusChanged::dispatch($this, $from, $this->status, $note);
    }

    /** Tambahkan catatan ke riwayat tanpa mengubah status. */
    public function addHistoryNote(string $note): void
    {
        $this->statusHistories()->create([
            'from_status' => $this->status,
            'status' => $this->status,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
        ]);
    }

    /** Susun ulang riwayat dari kolom waktu (untuk data contoh/impor). */
    public function rebuildStatusHistory(): void
    {
        $this->statusHistories()->delete();

        $previous = null;
        $steps = [
            'created_at' => OrderStatus::PendingPayment, 'paid_at' => OrderStatus::Paid,
            'shipped_at' => OrderStatus::Shipped, 'delivered_at' => OrderStatus::Delivered,
            'completed_at' => OrderStatus::Completed, 'cancelled_at' => OrderStatus::Cancelled,
        ];

        foreach ($steps as $column => $status) {
            if ($this->{$column}) {
                $this->statusHistories()->create(['from_status' => $previous, 'status' => $status, 'created_at' => $this->{$column}]);
                $previous = $status;
            }
        }
    }

    public function withStatusNote(?string $note): static
    {
        $this->statusNote = $note;

        return $this;
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'discount' => 'integer',
            'shipping_discount' => 'integer',
            'manual_discount' => 'integer',
            'total' => 'integer',
            'total_weight' => 'integer',
            'service_fee' => 'integer',
            'tax_rate' => 'float',
            'tax_inclusive' => 'boolean',
            'tax_dpp_other' => 'boolean',
            'tax_base' => 'integer',
            'tax_amount' => 'integer',
            'rounding' => 'integer',
            'payment_fee' => 'integer',
            'gateway_fee' => 'integer',
            'invoice_issued_at' => 'datetime',
            'shipment_price' => 'integer',
            'shipment_booked_at' => 'datetime',
            'warehouse_outbound_sent_at' => 'datetime',
            'warehouse_outbound_approved_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public static function generateCode(): string
    {
        do {
            $code = 'MGA-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Staf yang membuat penjualan manual. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Staf yang mencatat pembayaran di luar Midtrans. */
    public function paymentRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_recorded_by');
    }

    /** Cara bayar di luar Midtrans yang bisa dicatat staf (nilai orders.payment_method). */
    public const OFFLINE_METHODS = [
        'cash' => 'Tunai',
        'bank_transfer' => 'Transfer bank langsung',
        'edc' => 'Kartu via EDC',
        'other' => 'Lainnya',
    ];

    /** Lunas lewat pembayaran yang dicatat staf (bukan Midtrans). */
    public function isOfflinePaid(): bool
    {
        return $this->paid_at !== null && ($this->payment_method === 'manual' || array_key_exists((string) $this->payment_method, self::OFFLINE_METHODS));
    }

    public const SOURCE_WEBSITE = 'website';

    public const SOURCE_MANUAL = 'manual';

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    public function sourceLabel(): string
    {
        return $this->isManual() ? 'Manual' : 'Website';
    }

    // Status outbound manual di warehouse29 (kolom tidak mass-assignable, ubah lewat WarehouseOutbound).
    public const OUTBOUND_QUEUED = 'queued';

    public const OUTBOUND_PENDING = 'pending';

    public const OUTBOUND_APPROVED = 'approved';

    public const OUTBOUND_FAILED = 'failed';

    public const OUTBOUND_DELETED = 'deleted';

    /** @return array{0: string, 1: string} [label, warna badge] */
    public function outboundStatusLabel(): array
    {
        return match ($this->warehouse_outbound_status) {
            self::OUTBOUND_QUEUED => ['Antre dikirim', 'bg-stone-100 text-stone-700 ring-stone-500/20'],
            self::OUTBOUND_PENDING => ['Menunggu approve gudang', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
            self::OUTBOUND_APPROVED => ['Disetujui gudang', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
            self::OUTBOUND_FAILED => ['Gagal dikirim', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            self::OUTBOUND_DELETED => ['Dihapus di gudang', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
            default => ['Belum dikirim', 'bg-stone-100 text-stone-600 ring-stone-500/20'],
        };
    }

    /** Nama pembeli: akun pelanggan bila ada, selain itu penerima (penjualan manual tanpa akun). */
    public function customerName(): string
    {
        return $this->user?->name ?? $this->recipient_name;
    }

    public function customerEmail(): ?string
    {
        return $this->user?->email ?? $this->billing_email;
    }

    /**
     * Link tagihan untuk dibagikan ke pembeli (tanpa login). Ditandatangani,
     * berlaku sampai 30 hari setelah batas bayar supaya bukti lunas tetap bisa dibuka.
     */
    public function billUrl(): string
    {
        return URL::signedRoute('bill.show', $this, $this->paymentDeadline()->copy()->addDays(30));
    }

    /** Ke mana pembeli diarahkan setelah membayar di halaman Midtrans. */
    public function paymentFinishUrl(): string
    {
        return $this->user_id
            ? route('account.orders.payment.finish', $this)
            : $this->billUrl();
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /** Total potongan voucher (barang + ongkir). */
    public function discountTotal(): int
    {
        return (int) $this->discount + (int) $this->shipping_discount;
    }

    public function hasTax(): bool
    {
        return (int) $this->tax_amount > 0;
    }

    /** Mis. "PPN 11%", atau "PPN 12% (DPP 11/12)". */
    public function taxLabel(): string
    {
        return trim(($this->tax_label ?: 'PPN').' '.BillingSettings::percent((float) $this->tax_rate))
            .($this->tax_dpp_other ? ' (DPP 11/12)' : '');
    }

    /** Total yang dibayar pembeli, termasuk biaya transaksi metode bayar. */
    public function amountPaid(): int
    {
        return (int) $this->total + (int) $this->payment_fee;
    }

    /** Perkiraan dana bersih yang diterima toko setelah potongan Midtrans. */
    public function netReceived(): int
    {
        return $this->amountPaid() - (int) $this->gateway_fee;
    }

    public function paymentMethodLabel(): ?string
    {
        return PaymentMethod::labelFor($this->payment_method);
    }

    /** Nama di invoice: nama perusahaan bila diisi, selain itu penerima. */
    public function billedName(): string
    {
        return $this->billing_name ?: $this->recipient_name;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** Batas bayar; pesanan lama tanpa payment_due_at memakai waktu pesan + masa berlaku. */
    public function paymentDeadline(): CarbonInterface
    {
        return $this->payment_due_at
            ?? $this->created_at->copy()->addHours((int) config('shop.payment.expiry_hours'));
    }

    public function courierLabel(): ?string
    {
        if (! $this->courier) {
            return null;
        }

        return trim(Str::upper($this->courier).' '.$this->courier_service);
    }

    /** Lima digit terakhir nomor HP penerima (diminta sebagian kurir untuk lacak resi). */
    public function recipientPhoneLastDigits(int $digits = 5): string
    {
        return substr(preg_replace('/\D/', '', (string) $this->recipient_phone), -$digits);
    }
}
