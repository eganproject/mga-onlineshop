<x-layouts.admin title="Profil">
    <x-admin.page-header title="Profil saya" description="Perbarui data akun dan password admin." />
    <div class="grid gap-6 lg:grid-cols-2">
        @include('partials.profile-forms', ['updateRoute' => route('admin.profile.update'), 'passwordRoute' => route('admin.profile.password')])
    </div>
</x-layouts.admin>
