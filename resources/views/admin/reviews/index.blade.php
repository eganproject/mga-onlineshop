@php
    use App\Enums\ReviewStatus;
    $tabs = [null => 'Semua'] + collect(ReviewStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
@endphp
<x-layouts.admin title="Ulasan">
    <x-admin.page-header title="Ulasan produk" description="Moderasi ulasan pelanggan sebelum tayang di halaman produk." />

    <div class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-stone-100 p-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
            <nav class="no-scrollbar -mx-1 flex gap-1 overflow-x-auto px-1 lg:shrink-0" aria-label="Filter status">
                @foreach ($tabs as $value => $label)
                    @php
                        $active = ($activeStatus?->value ?? null) === ($value ?: null);
                        $count = $value ? ($counts[$value] ?? 0) : $counts->sum();
                    @endphp
                    <a href="{{ route('admin.reviews.index', array_filter(['status' => $value, 'q' => request('q'), 'rating' => request('rating')])) }}"
                       @class(['flex shrink-0 items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-semibold transition', 'bg-stone-900 text-white' => $active, 'text-stone-600 hover:bg-stone-100' => ! $active])>
                        {{ $label }}
                        <span @class(['rounded-full px-1.5 text-[11px]', 'bg-white/20' => $active, 'bg-stone-100' => ! $active])>{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="flex gap-2">
                @if ($activeStatus)<input type="hidden" name="status" value="{{ $activeStatus->value }}">@endif
                <select name="rating" class="input w-44" x-data x-searchable onchange="this.form.submit()" aria-label="Filter rating">
                    <option value="">Semua rating</option>
                    @for ($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" @selected(request('rating') == $i)>{{ $i }} bintang</option>
                    @endfor
                </select>
                <div class="relative min-w-0 flex-1 sm:w-64">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Produk, pelanggan, isi..." class="input pl-10">
                </div>
            </form>
        </div>

        @forelse ($reviews as $review)
            <article class="border-b border-stone-100 p-5 last:border-0" x-data="{ reply: false }">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
                    <div class="flex min-w-0 flex-1 gap-4">
                        @if ($review->product)
                            <x-product-image :product="$review->product" class="size-14 shrink-0 rounded-lg" />
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-star-rating :rating="$review->rating" />
                                <span class="badge {{ $review->status->badgeClasses() }}">{{ $review->status->label() }}</span>
                                @if ($review->order_id)
                                    <span class="text-xs text-emerald-700">Pembeli terverifikasi</span>
                                @endif
                            </div>
                            @if ($review->title)<p class="mt-2 font-semibold text-stone-900">{{ $review->title }}</p>@endif
                            <p class="mt-1 whitespace-pre-line text-sm text-stone-600">{{ $review->body }}</p>
                            <p class="mt-2 text-xs text-stone-500">
                                {{ $review->user?->name ?? 'Pengguna dihapus' }} ·
                                @if ($review->product)
                                    <a href="{{ route('products.show', $review->product) }}#ulasan" target="_blank" class="font-medium text-stone-700 hover:underline">{{ $review->product->name }}</a> ·
                                @endif
                                {{ $review->updated_at->translatedFormat('d M Y H:i') }}
                            </p>
                            @if ($review->admin_reply)
                                <div class="mt-3 rounded-xl bg-stone-50 p-3 text-sm">
                                    <p class="text-xs font-semibold text-stone-500">Balasan toko</p>
                                    <p class="mt-0.5 whitespace-pre-line text-stone-700">{{ $review->admin_reply }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @unless ($review->status === ReviewStatus::Approved)
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="approved">
                                <button class="btn btn-primary btn-sm"><x-icon name="check" class="size-4" /> Tayangkan</button>
                            </form>
                        @endunless
                        @unless ($review->status === ReviewStatus::Rejected)
                            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="rejected">
                                <button class="btn btn-secondary btn-sm"><x-icon name="x" class="size-4" /> Tolak</button>
                            </form>
                        @endunless
                        <button type="button" @click="reply = !reply" class="btn btn-ghost btn-sm"><x-icon name="chat" class="size-4" /> Balas</button>
                        <x-confirm-delete :action="route('admin.reviews.destroy', $review)" title="Hapus ulasan?" message="Ulasan dari {{ $review->user?->name ?? 'pelanggan' }} akan dihapus permanen dan rating produk dihitung ulang." />
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.reviews.update', $review) }}" x-show="reply" x-cloak class="mt-4 lg:pl-[72px]">
                    @csrf @method('PATCH')
                    <x-form.textarea :id="'reply-'.$review->id" name="admin_reply" label="Balasan toko (tampil di bawah ulasan)" :value="$review->admin_reply" rows="3" maxlength="2000" />
                    <div class="mt-2 flex gap-2">
                        <button class="btn btn-primary btn-sm">Simpan balasan</button>
                        <button type="button" @click="reply = false" class="btn btn-ghost btn-sm">Batal</button>
                    </div>
                </form>
            </article>
        @empty
            <x-empty-state icon="star" title="Tidak ada ulasan" description="Ulasan dari pembeli akan muncul di sini untuk dimoderasi." />
        @endforelse

        <x-admin.pagination :paginator="$reviews" label="ulasan" />
    </div>
</x-layouts.admin>
