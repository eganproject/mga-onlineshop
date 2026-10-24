<x-layouts.admin title="Staf">
    <x-admin.page-header title="Staf" description="Akun yang bisa masuk ke panel admin dan apa saja yang boleh mereka lakukan.">
        <a href="{{ route('admin.staff.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Tambah staf</a>
    </x-admin.page-header>

    @error('staff')
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">{{ $message }}</div>
    @enderror

    <div class="card overflow-hidden">
        <form method="GET" class="flex flex-col gap-3 border-b border-stone-100 p-4 sm:flex-row" x-data @change="$el.submit()">
            <div class="relative flex-1 sm:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama atau email..." class="input pl-10">
            </div>
            <select name="jabatan" class="input sm:w-52" x-data x-searchable aria-label="Filter jabatan">
                <option value="">Semua jabatan</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected(request('jabatan') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </form>

        @if ($staff->isEmpty())
            <x-empty-state icon="users" title="Tidak ada staf" description="Tidak ada akun staf yang cocok dengan filter ini." />
        @else
            <div class="relative overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-stone-50/70"><tr><th>Staf</th><th>Jabatan</th><th>Login terakhir</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($staff as $member)
                            @php($role = $member->staffRole())
                            <tr class="hover:bg-stone-50/50">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-stone-900 text-sm font-bold text-white">{{ $member->initials() }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-stone-900">{{ $member->name }} @if ($member->is(auth()->user()))<span class="text-xs font-normal text-stone-400">(Anda)</span>@endif</p>
                                            <p class="truncate text-xs text-stone-500">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge {{ $role->badgeClass() }}">{{ $role->label() }}</span></td>
                                <td class="whitespace-nowrap text-sm text-stone-600">{{ $member->last_login_at?->diffForHumans() ?? 'Belum pernah' }}</td>
                                <td>
                                    @if ($member->is_active)
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Aktif</span>
                                    @else
                                        <span class="badge bg-stone-100 text-stone-600 ring-stone-500/20">Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.staff.edit', $member) }}" class="btn btn-secondary btn-sm">Ubah</a>
                                        @unless ($member->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.staff.toggle', $member) }}"
                                                  data-confirm-title="{{ $member->is_active ? 'Nonaktifkan '.$member->name.'?' : 'Aktifkan kembali '.$member->name.'?' }}"
                                                  data-confirm="{{ $member->is_active ? 'Ia langsung keluar dari panel admin dan tidak bisa login lagi.' : 'Ia bisa login lagi ke panel admin sesuai jabatannya.' }}"
                                                  data-confirm-ok="{{ $member->is_active ? 'Ya, nonaktifkan' : 'Ya, aktifkan' }}"
                                                  data-confirm-tone="{{ $member->is_active ? 'danger' : 'primary' }}">
                                                @csrf
                                                @method('PATCH')
                                                <button @class(['btn btn-sm', 'btn-ghost text-rose-600 hover:bg-rose-50' => $member->is_active, 'btn-secondary' => ! $member->is_active])>
                                                    {{ $member->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$staff" label="staf" />
        @endif
    </div>

    {{-- Ringkasan hak akses tiap jabatan --}}
    <h2 class="mt-8 mb-3 font-bold text-stone-900">Hak akses per jabatan</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($roles as $role)
            <div class="card p-5">
                <span class="badge {{ $role->badgeClass() }}">{{ $role->label() }}</span>
                <p class="mt-2 text-xs text-stone-500">{{ $role->description() }}</p>
                <ul class="mt-3 space-y-1.5 text-sm">
                    @foreach (\App\Enums\Permission::cases() as $permission)
                        @php($allowed = $role->allows($permission))
                        <li @class(['flex items-start gap-2', 'text-stone-700' => $allowed, 'text-stone-300' => ! $allowed])>
                            <x-icon :name="$allowed ? 'check' : 'minus'" @class(['mt-0.5 size-4 shrink-0', 'text-emerald-600' => $allowed]) />
                            <span>{{ $permission->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
