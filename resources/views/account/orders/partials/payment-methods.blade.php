{{--
    Pilihan metode bayar langsung di halaman. Dipakai saat belum ada tagihan,
    atau saat pembeli mengganti metode (tagihan lama dibatalkan setelah konfirmasi).
    Variabel: $order, $methodOptions, $replacing (bool).
--}}
<form method="POST" action="{{ route('account.orders.payment.method', $order) }}"
      @if ($replacing)
          data-confirm-title="Ganti metode pembayaran?" data-confirm-ok="Ya, ganti"
          data-confirm="Nomor VA / kode bayar yang sekarang tidak berlaku lagi setelah kamu memilih metode baru."
      @endif>
    @csrf
    <div class="flex items-end justify-between gap-3">
        <div>
            <h2 class="font-display text-base font-extrabold tracking-tight uppercase">{{ $replacing ? 'Ganti metode pembayaran' : 'Pilih metode pembayaran' }}</h2>
            <p class="mt-1 text-sm text-stone-500">Nomor VA, QR, atau kode bayar langsung muncul di halaman ini.</p>
        </div>
        @if ($replacing)
            <button type="button" class="link-underline shrink-0 text-sm font-semibold text-stone-500 hover:text-ink" x-on:click="changing = false">Batal</button>
        @endif
    </div>
    <x-input-error for="method" />

    <div class="mt-5 space-y-6">
        @foreach ($methodOptions as $group => $options)
            @php($sharedHint = count($options) > 1 && collect($options)->map(fn ($o) => $o['method']->hint())->unique()->count() === 1 ? $options[0]['method']->hint() : null)
            <fieldset>
                <legend class="mb-2 text-xs font-bold tracking-wider text-stone-500 uppercase">{{ $group }}@if ($sharedHint)<span class="mt-0.5 block font-normal tracking-normal normal-case">{{ $sharedHint }}</span>@endif</legend>
                <div class="divide-y divide-stone-200 border border-stone-200">
                    @foreach ($options as $option)
                        @php($m = $option['method'])
                        <label class="relative flex min-w-0 cursor-pointer items-center gap-3 p-3 transition sm:gap-4 sm:px-4"
                               x-bind:class="method === '{{ $m->value }}' ? 'bg-sand-50 ring-2 ring-ink ring-inset' : 'hover:bg-stone-50'">
                            <input type="radio" name="method" value="{{ $m->value }}" x-model="method" class="sr-only" required>
                            <x-payment-badge :method="$m" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-stone-900">{{ $m->label() }}</span>
                                @unless ($sharedHint)
                                    <span class="block text-xs text-stone-500">{{ $m->hint() }}</span>
                                @endunless
                            </span>
                            <span class="shrink-0 text-right text-xs">
                                @if ($option['fee'])
                                    <span class="block text-stone-500">+ @rupiah($option['fee'])</span>
                                @else
                                    <span class="block font-semibold text-emerald-700">Tanpa biaya</span>
                                @endif
                            </span>
                            <span class="grid size-5 shrink-0 place-items-center rounded-full border transition"
                                  x-bind:class="method === '{{ $m->value }}' ? 'border-ink bg-ink text-white' : 'border-stone-300 bg-white text-transparent'">
                                <x-icon name="check" class="size-3" />
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    <div class="mt-6 flex flex-col gap-3 border-t border-stone-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-stone-500">
            Total bayar
            <span class="block font-display text-xl font-extrabold text-ink tabular-nums" x-text="rupiah(amount ?? {{ $order->total }})">@rupiah($order->total)</span>
        </p>
        <button class="btn btn-ink btn-pill w-full sm:w-auto" x-bind:disabled="!method">
            <x-icon name="lock" class="size-4" />
            <span x-text="method ? 'Bayar dengan ' + labels[method] : 'Pilih metode dulu'">Lanjut bayar</span>
        </button>
    </div>
</form>
