<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Jabatan staf (Admin › Jabatan & Hak Akses). users.staff_role berisi `key`.
 * Superadmin (`owner`) selalu punya semua izin dan tidak bisa dihapus, jadi
 * toko tidak mungkin terkunci dari panel admin.
 */
class Role extends Model
{
    public const OWNER = 'owner';

    /** Warna badge yang bisa dipilih. */
    public const COLORS = [
        'dark' => ['Hitam', 'bg-stone-900 text-white ring-stone-900/20'],
        'brand' => ['Teal', 'bg-brand-50 text-brand-700 ring-brand-600/20'],
        'amber' => ['Kuning', 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'sky' => ['Biru', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
        'emerald' => ['Hijau', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'violet' => ['Ungu', 'bg-violet-50 text-violet-700 ring-violet-600/20'],
        'rose' => ['Merah', 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'stone' => ['Abu-abu', 'bg-stone-100 text-stone-700 ring-stone-500/20'],
    ];

    protected $table = 'staff_roles';

    protected $attributes = [
        'key' => null,
        'description' => null,
        'color' => 'stone',
        'permissions' => '[]',
        'sort_order' => 0,
    ];

    protected $fillable = ['name', 'description', 'color', 'permissions', 'sort_order'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'sort_order' => 'integer'];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'staff_role', 'key');
    }

    public static function findByKey(?string $key): ?self
    {
        return $key === null ? null : static::query()->where('key', $key)->first();
    }

    /** Kunci unik dari nama jabatan (users.staff_role maks 20 karakter). */
    public static function makeKey(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 16, '') ?: 'jabatan';
        $key = $base;
        $i = 2;
        while (static::query()->where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }

    /** Cadangan bila baris Superadmin hilang dari database (mis. dihapus manual). */
    public static function ownerFallback(): self
    {
        $role = new self(['name' => 'Superadmin', 'color' => 'dark']);
        $role->key = self::OWNER;

        return $role;
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function isOwner(): bool
    {
        return $this->key === self::OWNER;
    }

    public function label(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return (string) $this->description;
    }

    public function badgeClass(): string
    {
        return (self::COLORS[$this->color] ?? self::COLORS['stone'])[1];
    }

    /** @return list<Permission> */
    public function permissionList(): array
    {
        if ($this->isOwner()) {
            return Permission::cases();
        }

        return array_values(array_filter(array_map(fn ($v) => Permission::tryFrom((string) $v), (array) $this->permissions)));
    }

    public function allows(Permission $permission): bool
    {
        return $this->isOwner() || in_array($permission->value, (array) $this->permissions, true);
    }
}
