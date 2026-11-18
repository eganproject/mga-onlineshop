{{--
    Catat pembayaran di luar Midtrans: tunai, transfer langsung ke rekening
    toko, EDC, atau lainnya. Pesanan langsung lunas (invoice, gudang, laporan).
    Izin payments.record. Variabel: $order.
--}}
@php($openForm = $errors->hasAny(['method', 'paid_at', 'reference', 'note', 'proof']))
<div id="catat-pembayaran" class="card scroll-mt-24 overflow-hidden" x-data="{ open: @js($openForm), method: @js(old('method', 'bank_transfer')) }" x-on:open-record="open = true">
    <button type="button" class="flex w-full items-center justify-between gap-3 p-5 text-left" x-on:click="open = !open" x-bind:aria-expanded="open">
        <span>
            <span class="flex items-center gap-2 font-bold text-stone-900"><x-icon name="banknotes" class="size-5 text-brand-600" /> Catat pembayaran di luar Midtrans</span>
            <span class="mt-0.5 block text-xs text-stone-500">Pembeli bayar tunai, transfer langsung ke rekening toko, EDC, atau cara lain.</span>
        </span>
        <x-icon name="chevron-down" class="size-5 shrink-0 text-stone-400 transition" x-bind:class="open && 'rotate-180'" />
    </button>

    <form method="POST" action="{{ route('admin.orders.offline-payment', $order) }}" enctype="multipart/form-data" x-show="open" x-cloak
          class="space-y-4 border-t border-stone-100 p-5"
          data-confirm-title="Catat pembayaran & tandai lunas?" data-confirm-ok="Ya, sudah dibayar" data-confirm-tone="primary"
          data-confirm="Pastikan dana @rupiah($order->total) sudah benar-benar diterima. Pesanan langsung lunas, invoice terbit, dan tagihan QRIS / VA yang masih aktif dibatalkan.">
        @csrf
        <fieldset>
            <legend class="label">Cara bayar</legend>
            <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach (\App\Models\Order::OFFLINE_METHODS as $value => $label)
                    <label class="flex cursor-pointer items-center justify-center rounded-xl border border-stone-200 px-3 py-2.5 text-center text-sm font-semibold text-stone-700 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:text-brand-800">
                        <input type="radio" name="method" value="{{ $value }}" x-model="method" class="sr-only" required>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-input-error for="method" />
        </fieldset>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-form.date name="paid_at" label="Tanggal & jam bayar" time :value="now()" required />
            <x-form.input name="reference" label="No. referensi" maxlength="120" autocomplete="off"
                          x-bind:required="method === 'bank_transfer'"
                          tooltip="Wajib untuk transfer: nomor referensi / berita transfer atau nama & rekening pengirim. Untuk EDC: nomor approval di struk. Tunai boleh dikosongkan."
                          placeholder="Mis. ref. 123456 / a.n. Budi BCA" />
        </div>

        <div class="rounded-xl bg-stone-50 p-3 text-sm text-stone-600">
            Jumlah yang dicatat: <b class="text-stone-900">@rupiah($order->total)</b> (total pesanan, tanpa biaya transaksi Midtrans).
        </div>

        <x-form.file name="proof" label="Bukti bayar" optional :extensions="['jpg', 'jpeg', 'png', 'webp', 'pdf']" :max-kb="5120"
                     tooltip="Foto/scan bukti transfer, struk EDC, atau kuitansi. Hanya bisa dibuka staf dari detail pesanan." />
        <x-form.textarea name="note" label="Catatan" optional rows="2" maxlength="250" placeholder="Mis. diterima kasir toko" />

        <div class="flex flex-wrap items-center gap-2">
            <button class="btn btn-primary"><x-icon name="check-circle" class="size-4" /> Simpan & tandai lunas</button>
            <button type="button" class="btn btn-ghost" x-on:click="open = false">Batal</button>
        </div>
    </form>
</div>
