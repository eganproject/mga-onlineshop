/**
 * Halaman checkout: ganti alamat → ongkir dimuat ulang tanpa reload halaman,
 * pilih kurir, dan total dihitung langsung. Harga final tetap dihitung ulang
 * di server saat pesanan dibuat.
 */
export default ({ ratesUrl, addressId = '', rates = [], error = '', subtotal = 0, selected = '' }) => ({
    addressId: addressId ? String(addressId) : '',
    rates,
    error: error || '',
    subtotal,
    selected: rates.some((r) => r.key === selected) ? selected : rates[0]?.key || '',
    courier: '',
    loading: false,
    submitting: false,
    requestId: 0,

    get rate() {
        return this.rates.find((r) => r.key === this.selected) || null;
    },

    get shipping() {
        return this.rate?.cost || 0;
    },

    get total() {
        return this.subtotal + this.shipping;
    },

    get couriers() {
        const seen = new Map();
        this.rates.forEach((r) => seen.set(r.courier, r.courier_name));
        return [...seen].map(([code, name]) => ({ code, name }));
    },

    get visibleRates() {
        return this.courier ? this.rates.filter((r) => r.courier === this.courier) : this.rates;
    },

    get cheapestKey() {
        if (this.rates.length < 2) return null;
        return this.rates.reduce((a, b) => (b.cost < a.cost ? b : a)).key;
    },

    get fastestKey() {
        const timed = this.rates.filter((r) => r.min_days !== null);
        if (timed.length < 2) return null;
        const min = Math.min(...timed.map((r) => r.min_days));
        const fastest = timed.filter((r) => r.min_days === min);
        // Tidak perlu penanda bila semua layanan sama cepatnya.
        if (fastest.length === timed.length) return null;
        return fastest.reduce((a, b) => (b.cost < a.cost ? b : a)).key;
    },

    get canSubmit() {
        return Boolean(this.addressId && this.rate && !this.loading && !this.submitting);
    },

    async changeAddress(id) {
        const previous = this.selected;
        const requestId = ++this.requestId;
        this.addressId = String(id);
        this.loading = true;
        this.error = '';
        this.rates = [];
        this.selected = '';
        this.courier = '';

        const url = new URL(window.location.href);
        url.searchParams.set('alamat', id);
        window.history.replaceState(null, '', url);

        try {
            const res = await fetch(`${ratesUrl}?address_id=${encodeURIComponent(id)}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            if (requestId !== this.requestId) return; // ada permintaan yang lebih baru
            this.rates = json.rates || [];
            this.error = json.error || (res.ok ? '' : 'Ongkos kirim gagal dimuat.');
            this.selected = this.rates.some((r) => r.key === previous) ? previous : this.rates[0]?.key || '';
        } catch (e) {
            if (requestId === this.requestId) {
                this.error = 'Ongkos kirim gagal dimuat. Periksa koneksi internet lalu coba lagi.';
            }
        } finally {
            if (requestId === this.requestId) {
                this.loading = false;
            }
        }
    },
});
