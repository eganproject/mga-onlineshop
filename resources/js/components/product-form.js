/**
 * Form produk di admin: galeri foto (urutkan, hapus, unggah baru) dan
 * editor varian (tambah/hapus baris, isi massal harga & stok).
 */
const uid = () => crypto.randomUUID?.() ?? String(Math.random());
const digits = (value) => parseInt(String(value ?? '').replace(/\D/g, ''), 10) || 0;
const rupiah = (value) => 'Rp' + Number(value).toLocaleString('id-ID');
const withUid = (tiers) => (tiers ?? []).map((tier) => ({ uid: uid(), min_qty: tier.min_qty ?? '', price: tier.price ?? '' }));

export default (config) => ({
    hasVariants: Boolean(config.hasVariants),
    // Tingkat harga grosir produk tanpa varian: [{ uid, min_qty, price }].
    tiers: withUid(config.tiers),
    basePrice: config.defaultPrice ?? '',
    images: config.images, // [{ id, url, removed }]
    uploads: [], // [{ url, name }]
    // Error validasi ikut objek varian, jadi tetap di baris yang benar saat baris lain dihapus.
    variants: config.variants.map((v, i) => ({
        uid: crypto.randomUUID?.() ?? Math.random(),
        ...v,
        tiers: withUid(v.tiers),
        errors: config.variantErrors?.[i] ?? [],
    })),
    bulkPrice: '',
    bulkStock: '',
    maxImages: config.maxImages,

    get activeImages() {
        return this.images.filter((image) => !image.removed);
    },

    rowErrors(index) {
        return this.variants[index]?.errors ?? [];
    },

    variantImage(variant) {
        if (!variant.image_id) return this.activeImages[0] ?? null;
        return this.activeImages.find((image) => String(image.id) === String(variant.image_id)) ?? null;
    },

    get remainingSlots() {
        return this.maxImages - this.activeImages.length - this.uploads.length;
    },

    move(index, step) {
        const target = index + step;
        if (target < 0 || target >= this.images.length) return;
        [this.images[index], this.images[target]] = [this.images[target], this.images[index]];
    },

    makeCover(index) {
        const [image] = this.images.splice(index, 1);
        this.images.unshift(image);
    },

    previewUploads(event) {
        this.uploads.forEach((upload) => URL.revokeObjectURL(upload.url));
        this.uploads = Array.from(event.target.files).map((file) => ({
            url: URL.createObjectURL(file),
            name: file.name,
        }));
    },

    clearUploads() {
        this.uploads.forEach((upload) => URL.revokeObjectURL(upload.url));
        this.uploads = [];
        this.$refs.uploadInput.value = '';
    },

    addVariant() {
        const last = this.variants[this.variants.length - 1];
        this.variants.push({
            uid: crypto.randomUUID?.() ?? Math.random(),
            id: null,
            name: '',
            sku: '',
            price: last?.price ?? config.defaultPrice ?? '',
            compare_at_price: last?.compare_at_price ?? '',
            stock: 0,
            stock_original: null,
            weight: '',
            image_id: '',
            is_active: true,
            tiers: withUid(last?.tiers),
            errors: [],
        });
        this.$nextTick(() => {
            const row = this.$refs.variantRows?.querySelector('[data-variant-row]:last-of-type');
            row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            row?.querySelector('input:not([type=hidden])')?.focus({ preventScroll: true });
        });
    },

    removeVariant(index) {
        this.variants.splice(index, 1);
    },

    applyBulk() {
        this.variants.forEach((variant) => {
            if (this.bulkPrice !== '') variant.price = this.bulkPrice;
            if (this.bulkStock !== '') variant.stock = this.bulkStock;
        });
        this.bulkPrice = '';
        this.bulkStock = '';
    },

    /* ---- Harga grosir ---- */

    addTier(list, base) {
        const last = list[list.length - 1];
        const lastQty = digits(last?.min_qty);
        const lastPrice = digits(last?.price) || digits(base);
        list.push({
            uid: uid(),
            min_qty: lastQty ? lastQty * (lastQty < 10 ? 2 : 5) : 10,
            price: lastPrice ? String(Math.round((lastPrice * 0.95) / 500) * 500 || lastPrice - 1) : '',
        });
    },

    /** Pesan bila tingkat tidak masuk akal (dicek ulang di server). */
    tierProblem(list, index, base) {
        const tier = list[index];
        const qty = digits(tier.min_qty);
        const price = digits(tier.price);
        const prev = list[index - 1];
        if (!qty || !price) return '';
        if (qty < 2) return 'Jumlah minimal 2 pcs.';
        if (prev && qty <= digits(prev.min_qty)) return 'Jumlah harus lebih banyak dari tingkat sebelumnya.';
        if (digits(base) && price >= digits(base)) return 'Harga grosir harus lebih murah dari harga normal.';
        if (prev && price >= digits(prev.price)) return 'Harga harus lebih murah dari tingkat sebelumnya.';
        return '';
    },

    tierSummary(list, index, base) {
        const qty = digits(list[index].min_qty);
        const price = digits(list[index].price);
        if (!qty || !price) return 'Isi jumlah & harga per pcs.';
        const next = digits(list[index + 1]?.min_qty);
        const range = next > qty ? `${qty}–${next - 1} pcs` : `${qty} pcs ke atas`;
        const save = digits(base) ? Math.round(100 - (price / digits(base)) * 100) : 0;
        return `Pembeli ${range} bayar ${rupiah(price)}/pcs` + (save > 0 ? ` (hemat ${save}%)` : '');
    },

    /**
     * Salin tingkat grosir varian ke semua varian lain. Jumlah sama; harga ikut persen
     * potongannya bila harga varian berbeda (dibulatkan ke Rp100).
     */
    copyTiers(index) {
        const source = this.variants[index];
        const sourcePrice = digits(source.price);
        this.variants.forEach((variant, i) => {
            const targetPrice = digits(variant.price);
            if (i === index || !targetPrice) return; // varian tanpa harga dilewati
            variant.tiers = withUid(source.tiers.map((tier) => {
                const price = digits(tier.price);
                if (!sourcePrice || !targetPrice || targetPrice === sourcePrice) return tier;
                return { min_qty: tier.min_qty, price: String(Math.max(1, Math.round((targetPrice * (price / sourcePrice)) / 100) * 100)) };
            }));
        });
        window.toast?.(`Harga grosir disalin ke ${this.variants.length - 1} varian lain.`, 'success');
    },

    toggleVariants() {
        if (this.hasVariants && this.variants.length === 0) this.addVariant();
    },

    get totalStock() {
        return this.variants
            .filter((variant) => variant.is_active)
            .reduce((sum, variant) => sum + (parseInt(variant.stock, 10) || 0), 0);
    },
});
