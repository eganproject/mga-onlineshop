<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Buat akun Superadmin (Owner) di server produksi tanpa seeder demo.
 * Password diketik tersembunyi, tidak pernah ditampilkan atau dicatat.
 *   php artisan shop:create-admin
 */
#[Signature('shop:create-admin')]
#[Description('Buat akun Superadmin baru (atau jadikan akun yang ada Superadmin)')]
class CreateSuperadminCommand extends Command
{
    public function handle(): int
    {
        $name = text('Nama', required: true, validate: ['name' => 'max:255']);
        $email = mb_strtolower(trim(text('Email', required: true, validate: ['email' => 'email|max:255'])));

        $user = User::where('email', $email)->first();

        if ($user && ! confirm("Email {$email} sudah terdaftar. Jadikan Superadmin dan ganti password-nya?", default: false)) {
            $this->components->warn('Dibatalkan, tidak ada yang diubah.');

            return self::FAILURE;
        }

        $secret = password('Password (min. 12 karakter, huruf & angka)', required: true);
        $validator = Validator::make(['password' => $secret], [
            'password' => [Password::min(12)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        if (password('Ulangi password', required: true) !== $secret) {
            $this->components->error('Password tidak sama.');

            return self::FAILURE;
        }

        $user ??= new User;
        $user->fill(['name' => $name, 'email' => $email, 'password' => $secret]);
        $user->forceFill([
            'role' => UserRole::Admin,
            'staff_role' => StaffRole::Owner,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->components->info("Akun Superadmin {$email} siap. Login di ".url('/masuk'));

        return self::SUCCESS;
    }
}
