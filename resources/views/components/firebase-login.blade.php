{{-- Tombol "Lanjutkan dengan Google" (Firebase Authentication), khusus pelanggan.
     Tidak tampil bila FIREBASE_API_KEY / FIREBASE_PROJECT_ID belum diisi. --}}
@if (config('services.firebase.enabled'))
    @php
        $firebaseConfig = [
            'apiKey' => config('services.firebase.api_key'),
            'authDomain' => config('services.firebase.auth_domain'),
            'projectId' => config('services.firebase.project_id'),
            'appId' => config('services.firebase.app_id'),
        ];
    @endphp
    <div x-data="firebaseLogin(@js($firebaseConfig))" {{ $attributes }}>
        <form method="POST" action="{{ route('login.firebase') }}" x-ref="form">
            @csrf
            <input type="hidden" name="id_token" x-ref="token">
        </form>
        <button type="button" x-on:click="signIn()" x-bind:disabled="busy"
                class="btn w-full rounded-full bg-white py-3 text-ink ring-1 ring-stone-300 ring-inset hover:bg-stone-50 disabled:opacity-60">
            <svg x-show="!busy" class="size-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
            </svg>
            <span x-show="busy" x-cloak class="size-5 animate-spin rounded-full border-2 border-stone-200 border-t-brand-600"></span>
            <span>{{ $slot->isEmpty() ? 'Lanjutkan dengan Google' : $slot }}</span>
        </button>
        <p x-show="error" x-cloak x-text="error" class="field-error"></p>
        <x-input-error for="firebase" />

        <div class="my-6 flex items-center gap-3 text-xs text-stone-400">
            <span class="h-px flex-1 bg-stone-200"></span> atau dengan email <span class="h-px flex-1 bg-stone-200"></span>
        </div>
    </div>
@endif
