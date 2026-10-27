/**
 * Halaman checkout: ganti alamat → ongkir dimuat ulang tanpa reload halaman,
 * pilih kurir, pasang voucher, dan total dihitung langsung. Harga final tetap
 * dihitung ulang di server saat pesanan dibuat.
 */
const MIN_PAYABLE = 1;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

export default ({
    ratesUrl,
    voucherUrl,
    addressId = '',
    rates = [],
    error = '',
    subtotal = 0,
    selected = '',
    voucher = null,
    voucherError = '',
    code = '',
    billing = {},
}) => ({
    billing,
    addressId: addressId ? String(addressId) : '',
    rates,
    error: error || '',
    subtotal,
    selected: rates.some((r) => r.key === selected) ? selected : rates[0]?.key || '',
    courier: '',
    loading: false,
    submitting: false,
    requestId: 0,
    voucher,
    voucherCode: code || '',
    voucherError: voucherError || '',
    voucherMessage: '',
    voucherLoading: false,

    get rate() {
        return this.rates.find((r) => r.key === this.selected) || null;
    },

    get shipping() {
        return this.rate?.cost || 0;
    },

    get discount() {
        return this.voucher?.discount || 0;
    },

    get shippingDiscount() {
        if (this.voucher?.type !== 'free_shipping') return 0;
        const cap = this.voucher.shipping_cap;
        return cap === null || cap === undefined ? this.shipping : Math.min(cap, this.shipping);
    },

    // Biaya layanan, PPN & pembulatan: rumus sama dengan App\Services\Billing\PriceCalculator.
    get serviceFee() {
        return this.billing.serviceFee || 0;
    },

    get taxable() {
        const shipping = this.billing.taxShipping ? Math.max(0, this.shipping - this.shippingDiscount) : 0;
        return Math.max(0, this.subtotal - this.discount) + this.serviceFee + shipping;
    },

    get tax() {
        if (!this.billing.tax || this.taxable <= 0) return 0;
        const ratio = this.billing.taxDppOther ? 11 / 12 : 1;
        const rate = this.billing.taxRate;
        if (this.billing.taxInclusive) {
            return this.taxable - Math.round(this.taxable / (1 + (rate * ratio) / 100));
        }
        return Math.round((Math.round(this.taxable * ratio) * rate) / 100);
    },

    get beforeRounding() {
        // Sama dengan server: total bayar tidak pernah di bawah MIN_PAYABLE.
        const tax = this.billing.taxInclusive ? 0 : this.tax;
        return Math.max(MIN_PAYABLE, this.subtotal - this.discount + this.shipping - this.shippingDiscount + this.serviceFee + tax);
    },

    get rounding() {
        const unit = Math.max(1, this.billing.roundingUnit || 1);
        const amount = this.beforeRounding;
        const fn = { nearest: Math.round, up: Math.ceil, down: Math.floor }[this.billing.roundingMode];
        if (!fn) return 0;
        const rounded = fn(amount / unit) * unit;
        return rounded > 0 ? rounded - amount : 0;
    },

    get total() {
        return this.beforeRounding + this.rounding;
    },

    async applyVoucher(code = null) {
        if (code !== null) this.voucherCode = code;
        if (!this.voucherCode.trim() || this.voucherLoading) return;

        this.voucherLoading = true;
        this.voucherError = '';
        this.voucherMessage = '';

        try {
            const body = new FormData();
            body.append('code', this.voucherCode.trim());
            const res = await fetch(voucherUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body,
            });
            const json = await res.json().catch(() => ({}));

            if (res.ok) {
                this.voucher = json.voucher;
                this.voucherMessage = json.message || '';
                this.voucherCode = '';
            } else if (res.status === 429) {
                this.voucherError = 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.';
            } else if (res.status === 419) {
                this.voucherError = 'Sesi berakhir. Muat ulang halaman lalu coba lagi.';
            } else {
                this.voucherError = json.errors?.code?.[0] || json.message || 'Voucher tidak bisa dipakai.';
            }
        } catch (e) {
            this.voucherError = 'Voucher gagal diperiksa. Periksa koneksi internet lalu coba lagi.';
        } finally {
            this.voucherLoading = false;
        }
    },

    async removeVoucher() {
        this.voucherLoading = true;
        this.voucherError = '';

        try {
            const res = await fetch(voucherUrl, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
            if (res.ok) {
                this.voucher = null;
                this.voucherMessage = '';
            } else {
                this.voucherError = 'Voucher gagal dilepas. Muat ulang halaman lalu coba lagi.';
            }
        } catch (e) {
            this.voucherError = 'Voucher gagal dilepas. Periksa koneksi internet lalu coba lagi.';
        } finally {
            this.voucherLoading = false;
        }
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
