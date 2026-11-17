/**
 * Form penjualan manual (admin/orders/create.blade.php).
 *
 * - cari produk (nama/SKU) lalu tambah baris; produk bervarian pilih varian;
 * - cek ongkir RajaOngkir ke kecamatan tujuan (berat dihitung server), atau
 *   ongkir manual / ambil di toko;
 * - rincian (harga grosir, voucher, diskon khusus, PPN, total) dihitung
 *   server lewat endpoint quote setiap isian berubah.
 * Form dikirim biasa (POST); baris produk jadi input tersembunyi.
 */
export default function manualOrder({ urls, old = {} }) {
    return {
        query: '',
        results: [],
        searching: false,
        searchOpen: false,
        lines: [],
        mode: old.shipping_mode || 'courier',
        destination: old.district_id ? String(old.district_id) : '',
        rate: old.rate || '',
        rates: [],
        ratesLoading: false,
        ratesError: '',
        courierName: old.courier_name || '',
        shippingCost: old.shipping_cost ? Number(old.shipping_cost) : 0,
        voucherCode: old.voucher_code || '',
        manualDiscount: old.manual_discount ? Number(old.manual_discount) : 0,
        customerEmail: old.customer_email || '',
        quote: null,
        quoting: false,
        timer: null,
        searchTimer: null,
        lastRatesKey: '',

        async init() {
            const items = Array.isArray(old.items) ? old.items : Object.values(old.items || {});
            if (items.length) {
                const products = await this.fetchProducts({ ids: items.map((i) => i.product_id).join(',') });
                items.forEach((item) => {
                    const product = products.find((p) => String(p.id) === String(item.product_id));
                    if (product) this.lines.push({ key: crypto.randomUUID?.() ?? Math.random(), product, variantId: item.variant_id ? String(item.variant_id) : '', quantity: Number(item.quantity) || 1 });
                });
            }
            ['mode', 'voucherCode', 'manualDiscount', 'shippingCost', 'courierName', 'customerEmail', 'rate'].forEach((field) =>
                this.$watch(field, () => this.refresh()),
            );
            this.$watch('lines', () => this.refresh(), { deep: true });
            this.refresh();
        },

        // ---- Produk -------------------------------------------------------

        search() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(async () => {
                this.searching = true;
                this.results = await this.fetchProducts({ q: this.query.trim() });
                this.searching = false;
                this.searchOpen = true;
            }, 250);
        },

        async fetchProducts(params) {
            try {
                const res = await fetch(`${urls.products}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json' } });
                return res.ok ? (await res.json()).data : [];
            } catch {
                return [];
            }
        },

        add(product) {
            const existing = !product.has_variants && this.lines.find((l) => l.product.id === product.id);
            if (existing) {
                existing.quantity++;
            } else {
                const firstVariant = product.variants.find((v) => v.active && v.stock > 0);
                this.lines.push({ key: crypto.randomUUID?.() ?? Math.random(), product, variantId: firstVariant ? String(firstVariant.id) : '', quantity: 1 });
            }
            this.query = '';
            this.searchOpen = false;
            this.$nextTick(() => this.$refs.search?.focus());
        },

        remove(index) {
            this.lines.splice(index, 1);
        },

        stockOf(line) {
            if (!line.product.has_variants) return line.product.stock;
            return line.product.variants.find((v) => String(v.id) === line.variantId)?.stock ?? 0;
        },

        // ---- Ongkir -------------------------------------------------------

        setDistrict(id) {
            id = id ? String(id) : '';
            if (id === this.destination) return;
            this.destination = id;
            this.rate = '';
            this.rates = [];
            this.lastRatesKey = '';
            if (id) this.loadRates();
        },

        async loadRates() {
            const weight = this.quote?.weight || 0;
            if (this.mode !== 'courier' || !this.destination || weight <= 0) return;
            const key = `${this.destination}|${weight}`;
            if (key === this.lastRatesKey && this.rates.length) return;
            this.lastRatesKey = key;
            this.ratesLoading = true;
            this.ratesError = '';
            try {
                const res = await fetch(`${urls.rates}?${new URLSearchParams({ district_id: this.destination, weight })}`, { headers: { Accept: 'application/json' } });
                const json = await res.json();
                this.rates = json.data || [];
                if (!res.ok) this.ratesError = json.message || 'Ongkir gagal dimuat.';
                // Tarif yang dipilih masih ada? (berat berubah = tarif bisa beda)
                if (this.rate && !this.rates.some((r) => r.key === this.rate)) this.rate = '';
            } catch {
                this.ratesError = 'Ongkir gagal dimuat. Periksa koneksi internet.';
            } finally {
                this.ratesLoading = false;
            }
        },

        // ---- Rincian ------------------------------------------------------

        payload() {
            return {
                items: this.lines.map((l) => ({ product_id: l.product.id, variant_id: l.variantId || null, quantity: Number(l.quantity) || 0 })),
                customer_email: this.customerEmail || null,
                shipping_mode: this.mode,
                district_id: this.destination || null,
                rate: this.rate || null,
                courier_name: this.courierName || null,
                shipping_cost: this.mode === 'custom' ? Number(this.shippingCost) || 0 : null,
                voucher_code: this.voucherCode || null,
                manual_discount: Number(this.manualDiscount) || 0,
            };
        },

        refresh() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetchQuote(), 300);
        },

        async fetchQuote() {
            this.quoting = true;
            try {
                const res = await fetch(urls.quote, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                    body: JSON.stringify(this.payload()),
                });
                if (res.ok) {
                    this.quote = await res.json();
                    this.loadRates();
                }
            } catch {
                // Offline sebentar: rincian dihitung ulang saat isian berubah lagi.
            } finally {
                this.quoting = false;
            }
        },

        problem(index) {
            return this.quote?.problems?.[index] ?? null;
        },

        linePrice(index) {
            return this.quote?.lines?.[index] ?? null;
        },

        get canSubmit() {
            if (!this.lines.length || this.quoting) return false;
            if (this.quote && Object.keys(this.quote.problems || {}).length) return false;
            if (this.mode === 'courier' && !this.rate) return false;
            return true;
        },
    };
}
