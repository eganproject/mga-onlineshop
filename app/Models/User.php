<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'staffRoleRecord'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'customer',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Akun staf panel admin (semua jabatan). */
    public function scopeStaff(Builder $query): void
    {
        $query->where('role', UserRole::Admin);
    }

    /** Isi users.staff_role: key jabatan (string). Menerima juga enum StaffRole / model Role. */
    public function setStaffRoleAttribute(mixed $value): void
    {
        $this->attributes['staff_role'] = match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof Role => $value->key,
            blank($value) => null,
            default => (string) $value,
        };
    }

    public function staffRoleRecord(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'staff_role', 'key');
    }

    /**
     * Jabatan staf (Admin › Jabatan & Hak Akses). Admin lama/tanpa jabatan dianggap
     * Superadmin (hanya bisa dibuat lewat seeder/tinker; form staf selalu mengisi jabatan).
     */
    public function staffRole(): ?Role
    {
        if (! $this->isAdmin()) {
            return null;
        }

        $key = $this->staff_role ?: Role::OWNER;
        $role = $this->relationLoaded('staffRoleRecord') ? $this->getRelation('staffRoleRecord') : null;

        if (! $role || $role->key !== $key) {
            $role = Role::findByKey($key) ?? ($key === Role::OWNER ? Role::ownerFallback() : null);
            $this->setRelation('staffRoleRecord', $role);
        }

        return $role;
    }

    public function isOwner(): bool
    {
        return (bool) $this->staffRole()?->isOwner();
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $permission = $permission instanceof Permission ? $permission : Permission::tryFrom($permission);

        return $permission !== null && $this->is_active && (bool) $this->staffRole()?->allows($permission);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /** Halaman tujuan setelah login, sesuai peran. */
    public function homeRoute(): string
    {
        return $this->isAdmin() ? route('admin.dashboard') : route('account.dashboard');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Produk yang disimpan pelanggan, terbaru di atas. */
    public function wishlist(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlist_items')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
