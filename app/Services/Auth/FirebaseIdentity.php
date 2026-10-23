<?php

namespace App\Services\Auth;

/** Data pengguna dari ID token Firebase yang sudah terverifikasi. */
final readonly class FirebaseIdentity
{
    public function __construct(
        public string $uid,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name,
        public ?string $provider,
    ) {}
}
