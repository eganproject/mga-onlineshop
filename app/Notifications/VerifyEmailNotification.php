<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Email verifikasi berbahasa Indonesia, dikirim lewat antrean. */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi email akun '.config('shop.name'))
            ->greeting('Satu langkah lagi!')
            ->line('Klik tombol di bawah untuk memverifikasi alamat email akun '.config('shop.full_name').' kamu.')
            ->action('Verifikasi email', $url)
            ->line('Tautan ini berlaku selama '.config('auth.verification.expire', 60).' menit.')
            ->line('Kalau kamu tidak merasa mendaftar, abaikan saja email ini.')
            ->salutation('Salam, Tim '.config('shop.name'));
    }
}
