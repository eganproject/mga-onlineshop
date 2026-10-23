<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/** Email reset password berbahasa Indonesia, dikirim lewat antrean. */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url): MailMessage
    {
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset password akun '.config('shop.name'))
            ->greeting('Halo!')
            ->line('Kami menerima permintaan untuk mengatur ulang password akunmu.')
            ->action('Atur ulang password', $url)
            ->line("Tautan ini berlaku selama {$expire} menit.")
            ->line('Kalau kamu tidak meminta reset password, abaikan email ini. Password lamamu tetap aman.')
            ->salutation('Salam, Tim '.config('shop.name'));
    }
}
