<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\FirebaseLoginService;
use App\Services\Auth\FirebaseTokenVerifier;
use App\Services\Auth\InvalidFirebaseToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Login/daftar pelanggan lewat Firebase Authentication (Google). */
class FirebaseLoginController extends Controller
{
    public function store(Request $request, FirebaseTokenVerifier $verifier, FirebaseLoginService $logins): RedirectResponse
    {
        abort_unless(config('services.firebase.enabled'), 404);

        $request->validate(['id_token' => ['required', 'string', 'max:4096']]);

        try {
            $identity = $verifier->verify($request->input('id_token'));
        } catch (InvalidFirebaseToken $e) {
            Log::notice('Login Firebase ditolak', ['reason' => $e->getMessage()]);

            throw ValidationException::withMessages(['firebase' => 'Gagal masuk dengan Google. Silakan coba lagi.']);
        }

        [$user, $created] = $logins->resolveCustomer($identity);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('account.dashboard'))->with('success', $created
            ? 'Selamat datang di MGA, '.$user->name.'!'
            : 'Halo lagi, '.$user->name.'!');
    }
}
