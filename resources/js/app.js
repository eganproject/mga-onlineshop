import Alpine from 'alpinejs';
import productForm from './components/product-form';
import productPage from './components/product-page';

window.Alpine = Alpine;

/** Format angka ke rupiah, dipakai di komponen Alpine. */
window.rupiah = (value) => 'Rp' + new Intl.NumberFormat('id-ID').format(value || 0);

/** Autocomplete lokasi tujuan untuk form alamat (data dari ShippingProvider). */
Alpine.data('destinationSearch', (url, initialLabel = '') => ({
    query: initialLabel,
    results: [],
    open: false,
    loading: false,
    error: '',
    async search() {
        if (this.query.trim().length < 3) {
            this.results = [];
            return;
        }
        this.loading = true;
        this.error = '';
        try {
            const res = await fetch(`${url}?q=${encodeURIComponent(this.query)}`, {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            this.results = json.data || [];
            this.error = json.message || '';
            this.open = true;
        } catch (e) {
            this.error = 'Gagal memuat lokasi.';
        } finally {
            this.loading = false;
        }
    },
    choose(item) {
        this.query = item.label;
        this.open = false;
        this.$dispatch('destination-selected', item);
    },
}));

Alpine.data('productForm', productForm);
Alpine.data('productPage', productPage);

Alpine.start();
