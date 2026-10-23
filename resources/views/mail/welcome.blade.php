<x-mail::message>
# Halo, {{ \Illuminate\Support\Str::words($user->name, 1, '') }}!

Terima kasih sudah bergabung di **{{ config('shop.full_name') }}**. Akunmu sudah aktif, jadi kamu bisa langsung belanja aksesoris gadget original dengan harga bersahabat.

Dengan akun ini kamu bisa:

- Menyimpan produk incaran ke **wishlist**
- Checkout lebih cepat dengan alamat tersimpan
- Melacak pesanan dan nomor resi
- Menulis ulasan setelah pesanan selesai

<x-mail::button :url="route('products.index')">
Mulai belanja
</x-mail::button>

Jangan lupa verifikasi emailmu lewat email terpisah yang kami kirim, supaya info pesanan selalu sampai.

Salam,<br>
Tim {{ config('shop.name') }}
</x-mail::message>
