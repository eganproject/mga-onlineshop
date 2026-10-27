/**
 * Admin › Pajak & Biaya: simulasi tagihan dari isian form yang belum disimpan.
 * Setiap perubahan dikirim (debounce) ke endpoint pratinjau; hitungan tetap di server
 * supaya hasilnya sama persis dengan checkout.
 */
export default ({ url }) => ({
    preview: null,
    previewError: '',
    previewLoading: false,
    timer: null,
    requestId: 0,

    init() {
        const form = this.$root;
        const schedule = () => {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), 350);
        };
        form.addEventListener('input', schedule);
        form.addEventListener('change', schedule);
        // Tunggu komponen isian di dalam form selesai diinisialisasi.
        this.$nextTick(() => this.refresh());
    },

    async refresh() {
        const requestId = ++this.requestId;
        this.previewLoading = true;

        try {
            const body = new FormData(this.$root);
            body.delete('_method');
            const res = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const json = await res.json().catch(() => ({}));
            if (requestId !== this.requestId) return;

            if (res.ok) {
                this.preview = json;
                this.previewError = '';
            } else {
                const first = json.errors ? Object.values(json.errors)[0]?.[0] : null;
                this.previewError = first || json.message || 'Simulasi gagal dimuat.';
            }
        } catch (e) {
            if (requestId === this.requestId) this.previewError = 'Simulasi gagal dimuat. Periksa koneksi internet.';
        } finally {
            if (requestId === this.requestId) this.previewLoading = false;
        }
    },
});
