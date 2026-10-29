{{--
    Tombol simpan form admin. Desktop: tampil di tempat (kolom samping, ikut sticky bila dibungkus).
    HP/tablet (< lg): menempel di bawah layar, jadi form panjang bisa disimpan tanpa menggulir ke paling bawah.
    <x-admin.save-bar>
        <button class="btn btn-primary w-full py-3">Simpan perubahan</button>
    </x-admin.save-bar>
    Harus berada di dalam <form>. Spacer ikut dirender supaya isi terakhir tidak tertutup bar.
--}}
<div aria-hidden="true" class="h-14 lg:hidden"></div>
<div {{ $attributes->class(['save-bar']) }}>
    {{ $slot }}
</div>
