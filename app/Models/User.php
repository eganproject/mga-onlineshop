<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
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
            'staff_role' => StaffRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Akun staf panel admin (semua jabatan). */
    public function scopeStaff(Builder $query): void
    {
        $query->where('role', UserRole::Admin);
    }

    /**
     * Jabatan staf. Admin lama/tanpa jabatan dianggap Owner (hanya bisa dibuat
     * lewat seeder/tinker; form staf selalu mengisi jabatan).
     */
    public function staffRole(): ?StaffRole
    {
        if (! $this->isAdmin()) {
            return null;
        }

        return $this->staff_role ?? StaffRole::Owner;
    }

    public function isOwner(): bool
    {
        return $this->staffRole() === StaffRole::Owner;
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
