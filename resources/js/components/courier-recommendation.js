/**
 * Rekomendasi kurir RajaOngkir di Admin › Pengaturan Toko. Mengambil hasil cek
 * ongkir semua kurir dari kecamatan asal (lihat Admin\StoreSettingController@couriers),
 * menandai kartu kurir, dan bisa mencentang yang direkomendasikan sekaligus.
 * Dipasang di dalam regionPicker, jadi provinceId/districtId terbaca dari sana.
 */
export default ({ url }) => ({
    loading: false,
    result: null,
    message: '',

    async check(refresh = false) {
        this.loading = true;
        this.message = '';

        const params = new URLSearchParams();
        if (this.districtId) params.set('district_id', this.districtId);
        if (this.provinceId) params.set('province_id', this.provinceId);
        if (refresh) params.set('refresh', '1');

        try {
            const res = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                this.result = null;
                this.message = data.message || (res.status === 429 ? 'Terlalu sering. Coba lagi sebentar.' : 'Rekomendasi tidak bisa dimuat.');
                return;
            }

            this.result = data;
            if (!data.recommended.length) {
                this.message = 'Tidak ada kurir yang mengembalikan tarif dari kecamatan ini.';
            }
        } catch {
            this.message = 'Rekomendasi tidak bisa dimuat. Periksa koneksi.';
        } finally {
            this.loading = false;
        }
    },

    info(code) {
        return this.result?.couriers?.[code] ?? null;
    },

    isRecommended(code) {
        return !!this.result?.recommended?.includes(code);
    },

    summary(code) {
        const row = this.info(code);
        if (!row) return '';
        if (!row.available) return 'Tidak ada tarif';

        return [
            row.cheapest ? 'mulai ' + window.rupiah(row.cheapest) : null,
            row.services + ' layanan',
        ].filter(Boolean).join(' · ');
    },

    applyRecommended() {
        const codes = this.result?.recommended ?? [];
        if (!codes.length) return;

        this.$root.querySelectorAll('input[name="couriers[]"]').forEach((input) => {
            input.checked = codes.includes(input.value);
        });
        window.toast?.(`${codes.length} kurir rekomendasi dicentang. Klik Simpan untuk menerapkan.`, 'info');
    },

    checkedAt() {
        if (!this.result?.checked_at) return '';

        return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(this.result.checked_at));
    },
});
