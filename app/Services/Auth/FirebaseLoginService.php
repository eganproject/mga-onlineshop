<?php

namespace App\Services\Auth;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mencocokkan identitas Firebase ke akun pelanggan: lewat firebase_uid,
 * lalu email terverifikasi (akun lama ditautkan), atau membuat akun baru.
 * Akun staf tidak bisa masuk lewat Firebase.
 */
class FirebaseLoginService
{
    /** @return array{0: User, 1: bool} pengguna dan apakah akun baru dibuat */
    public function resolveCustomer(FirebaseIdentity $identity): array
    {
        $user = User::where('firebase_uid', $identity->uid)->first();

        if (! $user) {
            if (! $identity->email || ! $identity->emailVerified) {
                $this->fail('Akun Google ini tidak memiliki email terverifikasi. Silakan daftar dengan email dan password.');
            }

            $user = User::where('email', $identity->email)->first();
        }

        if (! $user) {
            return [$this->register($identity), true];
        }

        if (! $user->isCustomer()) {
            $this->fail('Akun staf hanya bisa masuk dengan email dan password.');
        }

        if (! $user->is_active) {
            $this->fail(__('auth.failed'));
        }

        if ($user->firebase_uid === null) {
            // Akun lama dengan email yang sama: tautkan, email kini terbukti milik pengguna.
            $user->forceFill([
                'firebase_uid' => $identity->uid,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } elseif ($user->firebase_uid !== $identity->uid) {
            $this->fail('Email ini sudah tertaut ke akun Google lain. Silakan masuk dengan email dan password.');
        }

        return [$user, false];
    }

    private function register(FirebaseIdentity $identity): User
    {
        $user = DB::transaction(function () use ($identity) {
            $user = new User([
                'name' => Str::limit(trim((string) $identity->name) ?: Str::before($identity->email, '@'), 250, ''),
                'email' => $identity->email,
                // Password acak; pelanggan bisa membuatnya lewat "Lupa password".
                'password' => Str::password(32),
            ]);
            $user->forceFill([
                'firebase_uid' => $identity->uid,
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });

        event(new Registered($user));
        Mail::to($user)->queue(new WelcomeMail($user));

        return $user;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['firebase' => $message]);
    }
}
