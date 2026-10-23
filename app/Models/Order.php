<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Observers\OrderObserver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'user_id', 'status', 'subtotal', 'shipping_cost', 'total', 'total_weight',
    'recipient_name', 'recipient_phone', 'shipping_address', 'destination_id',
    'courier', 'courier_service', 'shipping_etd', 'tracking_number', 'notes', 'payment_due_at',
    'paid_at', 'shipped_at', 'delivered_at', 'completed_at', 'cancelled_at', 'cancellation_reason',
])]
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory;

    /** Sama dengan default kolom, supaya status langsung terisi saat dibuat. */
    protected $attributes = ['status' => 'pending_payment'];

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
            'total' => 'integer',
            'total_weight' => 'integer',
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
