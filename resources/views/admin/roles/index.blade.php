<x-layouts.admin title="Jabatan & Hak Akses">
    <x-admin.page-header title="Jabatan & Hak Akses" description="Atur menu dan aksi yang boleh dibuka tiap jabatan. Perubahan langsung berlaku untuk semua staf berjabatan itu.">
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah jabatan</a>
    </x-admin.page-header>

    @php($total = count(\App\Enums\Permission::cases()))
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-4">
        @foreach ($roles as $role)
            @php($allowed = $role->permissionList())
            <div class="card flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="badge {{ $role->badgeClass() }}">{{ $role->label() }}</span>
                        @if ($role->description)
                            <p class="mt-2 text-xs leading-relaxed text-stone-500">{{ $role->description }}</p>
                        @endif
                    </div>
                    @if ($role->isOwner())
                        <x-icon name="lock" class="size-4 shrink-0 text-stone-400" title="Selalu akses penuh" />
                    @endif
                </div>

                <div class="mt-4 flex items-center justify-between gap-3 text-sm">
                    @can('staff.manage')
                        <a href="{{ route('admin.staff.index', ['jabatan' => $role->key]) }}" class="font-medium text-stone-700 hover:text-brand-700">{{ $role->users_count }} staf</a>
                    @else
                        <span class="font-medium text-stone-700">{{ $role->users_count }} staf</span>
                    @endcan
                    <span class="text-stone-500">{{ count($allowed) }} dari {{ $total }} izin</span>
                </div>
                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $total ? round(count($allowed) / $total * 100) : 0 }}%"></div>
                </div>

                <ul class="mt-4 flex-1 space-y-1.5 text-sm">
                    @foreach (\App\Enums\Permission::cases() as $permission)
                        @php($ok = $role->allows($permission))
                        <li @class(['flex items-start gap-2', 'text-stone-700' => $ok, 'text-stone-300' => ! $ok])>
                            <x-icon :name="$ok ? 'check' : 'minus'" @class(['mt-0.5 size-4 shrink-0', 'text-emerald-600' => $ok]) />
                            <span>{{ $permission->label() }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-4">
                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" /> Ubah</a>
                    <a href="{{ route('admin.roles.create', ['salin' => $role->key]) }}" class="btn btn-ghost btn-sm" title="Buat jabatan baru dengan hak akses yang sama">Salin</a>
                    @unless ($role->isOwner())
                        @if ($role->users_count === 0)
                            <x-confirm-delete :action="route('admin.roles.destroy', $role)" class="ml-auto" title="Hapus jabatan {{ $role->name }}?"
                                              message="Jabatan ini belum dipakai staf mana pun. Tindakan ini tidak bisa dibatalkan." />
                        @else
                            <span class="ml-auto text-xs text-stone-400" title="Pindahkan dulu stafnya ke jabatan lain">Masih dipakai</span>
                        @endif
                    @endunless
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 flex gap-3 rounded-2xl border border-stone-200 bg-white p-4 text-sm text-stone-600">
        <x-icon name="info" class="mt-0.5 size-5 shrink-0 text-brand-600" />
        <p>Superadmin selalu punya akses penuh dan tidak bisa dihapus, jadi panel admin tidak akan terkunci. Halaman ini hanya bisa dibuka Superadmin. Untuk memberi jabatan ke seseorang, buka <a href="{{ route('admin.staff.index') }}" class="font-semibold text-brand-700 hover:underline">Staf</a>.</p>
    </div>
</x-layouts.admin>
