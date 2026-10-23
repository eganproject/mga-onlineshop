<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Pendaftaran publik selalu menjadi pelanggan (role default).
        $user = User::create($data);

        // Registered juga memicu email verifikasi (User implements MustVerifyEmail).
        event(new Registered($user));
        Mail::to($user)->queue(new WelcomeMail($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))
            ->with('success', 'Selamat datang di MGA, '.$user->name.'!');
    }
}
