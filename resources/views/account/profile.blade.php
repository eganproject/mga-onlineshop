<x-layouts.account title="Profil" heading="Profil & keamanan">
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        @include('partials.profile-forms', ['updateRoute' => route('account.profile.update'), 'passwordRoute' => route('account.profile.password')])
    </div>
</x-layouts.account>
