/**
 * Keranjang tanpa reload halaman.
 *
 * Form dengan atribut `data-cart-form` (tambah ke keranjang, ubah jumlah,
 * hapus) dikirim lewat fetch; server membalas JSON berisi pesan, jumlah
 * keranjang terbaru, dan (di halaman keranjang) isi keranjang yang baru.
 * Tanpa JS, form tetap terkirim biasa dan halaman kembali dengan pesan flash.
 *
 * Badge jumlah keranjang memakai `$store.cart.count`; nilai awal diambil dari
 * `<body data-cart-count>`.
 *
 * Tombol submit dengan `data-cart-native` (mis. "Beli sekarang") dikirim biasa.
 */
export default function cart(Alpine) {
    Alpine.store('cart', {
        count: Number(document.body?.dataset.cartCount ?? 0),
    });

    const busy = new WeakSet();
    // Toast keranjang sebelumnya ditutup agar tidak menumpuk saat menambah berkali-kali.
    let lastToast = null;

    async function send(form, submitter) {
        if (busy.has(form)) return;
        busy.add(form);

        // Ambil isian sebelum tombol dinonaktifkan (tombol nonaktif tidak ikut terkirim).
        const body = new FormData(form, submitter && submitter.form === form ? submitter : undefined);

        // Halaman keranjang: angka jumlah langsung berubah, server lalu mengoreksi bila perlu.
        const qtyLabel = form.querySelector('[data-cart-qty]');
        const previousQty = qtyLabel?.textContent;
        if (qtyLabel && body.has('quantity')) qtyLabel.textContent = body.get('quantity');
        const buttons = [...form.querySelectorAll('button'), ...(form.id ? document.querySelectorAll(`button[form="${form.id}"]`) : [])];
        const wasDisabled = new Map(buttons.map((button) => [button, button.disabled]));
        buttons.forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
        form.setAttribute('aria-busy', 'true');

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const json = await res.json().catch(() => null);

            if (res.status === 419) {
                if (qtyLabel?.isConnected) qtyLabel.textContent = previousQty;
                window.toast('Sesi kamu sudah berakhir. Muat ulang halaman lalu coba lagi.', 'error');
                return;
            }

            if (!json) throw new Error(`HTTP ${res.status}`);

            if (json.redirect) {
                window.location.href = json.redirect;
                return;
            }

            if (typeof json.cart_count === 'number') Alpine.store('cart').count = json.cart_count;

            if (typeof json.html === 'string') {
                // Morph: hanya bagian yang berubah yang diganti (foto tidak dimuat ulang, tidak berkedip).
                const root = document.querySelector('[data-cart-root]');
                if (root) Alpine.morph(root, json.html.trim(), { key: (el) => el.dataset?.cartLine });
            }

            const type = res.ok ? (json.type ?? 'success') : 'error';
            if (lastToast) Alpine.store('toasts').dismiss(lastToast);
            lastToast = json.message ? window.toast(json.message, type, type === 'error' ? 8000 : 5000) : null;

            if (res.ok) form.dispatchEvent(new CustomEvent('cart-updated', { bubbles: true, detail: json }));
        } catch (error) {
            if (qtyLabel?.isConnected) qtyLabel.textContent = previousQty;
            window.toast('Gagal terhubung ke server. Periksa koneksi lalu coba lagi.', 'error');
        } finally {
            busy.delete(form);
            form.removeAttribute('aria-busy');
            buttons.forEach((button) => {
                if (!button.isConnected) return;
                button.removeAttribute('aria-busy');
                button.disabled = wasDisabled.get(button);
            });
        }
    }

    // Fase bubble: listener lain (Alpine @submit, data-confirm) sempat membatalkan dulu.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-cart-form')) return;
        if (event.defaultPrevented) return;
        if (event.submitter?.hasAttribute('data-cart-native')) return;

        event.preventDefault();
        send(form, event.submitter);
    });
}
