<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Pencatat aktivitas staf. Aksi admin yang mengubah data dicatat otomatis oleh
 * middleware LogAdminActivity; controller boleh memanggil log() sendiri untuk
 * detail yang lebih kaya (mis. nilai lama → baru), dan middleware lalu melewati
 * request tersebut agar tidak tercatat dua kali.
 */
class ActivityLogger
{
    /** Kunci input yang tidak pernah disimpan. */
    private const SECRET_KEYS = ['password', 'password_confirmation', 'current_password', '_token', '_method', 'token', 'server_key', 'api_key'];

    /** Penanda di request bahwa aksi ini sudah dicatat (dibaca middleware). */
    private const REQUEST_FLAG = 'activity_logged';

    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): ActivityLog
    {
        $user ??= auth()->user();
        $request = app()->bound('request') ? request() : null;
        $request?->attributes->set(self::REQUEST_FLAG, true);

        return ActivityLog::create([
            'user_id' => $user?->getKey(),
            'user_name' => $user?->name,
            'action' => $action,
            'description' => Str::limit($description, 250),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? Str::limit(self::labelFor($subject), 250) : null,
            'properties' => $this->clean($properties) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
            'created_at' => now(),
        ]);
    }

    public function loggedThisRequest(): bool
    {
        return (bool) request()->attributes->get(self::REQUEST_FLAG, false);
    }

    /** Nama yang mudah dibaca untuk subjek log (kode pesanan, nama produk, dst.). */
    public static function labelFor(Model $model): string
    {
        foreach (['code', 'name', 'title', 'email'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /** Buang data rahasia & file, potong teks panjang, dan batasi ukuran total. */
    public function clean(array $data, int $depth = 0): array
    {
        $clean = [];

        foreach (Arr::except($data, self::SECRET_KEYS) as $key => $value) {
            if (is_string($key) && Str::contains(Str::lower($key), ['password', 'secret', 'token', 'server_key', 'api_key'])) {
                continue;
            }

            $clean[$key] = match (true) {
                $value instanceof \Illuminate\Http\UploadedFile => '[file]',
                is_array($value) => $depth < 2 ? $this->clean(array_slice($value, 0, 20, true), $depth + 1) : '[…]',
                is_string($value) => Str::limit($value, 200),
                is_scalar($value), $value === null => $value,
                $value instanceof \BackedEnum => $value->value,
                default => '['.get_debug_type($value).']',
            };
        }

        // Jaga satu baris log tetap kecil (form produk bisa sangat panjang).
        if ($depth === 0 && strlen((string) json_encode($clean)) > 6000) {
            return ['_ringkas' => 'Data terlalu panjang, hanya nama kolom yang disimpan.', 'kolom' => array_slice(array_keys($clean), 0, 40)];
        }

        return $clean;
    }
}
