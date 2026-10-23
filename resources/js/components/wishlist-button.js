/**
 * Tombol wishlist (hati). Tanpa JS, form tetap dikirim biasa dan halaman
 * kembali dengan pesan flash; dengan JS, status diubah tanpa reload.
 */
export default (initial = false) => ({
    on: initial,
    busy: false,
    async toggle(form) {
        if (this.busy) return;
        this.busy = true;
        this.on = !this.on;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.status === 401 || res.status === 403 || res.redirected) {
                form.submit();
                return;
            }
            const json = await res.json();
            this.on = json.wishlisted;
            window.dispatchEvent(new CustomEvent('wishlist-updated', { detail: json }));
        } catch (e) {
            this.on = !this.on;
        } finally {
            this.busy = false;
        }
    },
});
