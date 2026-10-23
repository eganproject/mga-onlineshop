/**
 * Form produk di admin: galeri foto (urutkan, hapus, unggah baru) dan
 * editor varian (tambah/hapus baris, isi massal harga & stok).
 */
export default (config) => ({
    hasVariants: Boolean(config.hasVariants),
    images: config.images, // [{ id, url, removed }]
    uploads: [], // [{ url, name }]
    variants: config.variants.map((v) => ({ uid: crypto.randomUUID?.() ?? Math.random(), ...v })),
    bulkPrice: '',
    bulkStock: '',
    maxImages: config.maxImages,

    get activeImages() {
        return this.images.filter((image) => !image.removed);
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
        });
        this.$nextTick(() => this.$refs.variantRows?.querySelector('tr:last-child input')?.focus());
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

    toggleVariants() {
        if (this.hasVariants && this.variants.length === 0) this.addVariant();
    },

    get totalStock() {
        return this.variants
            .filter((variant) => variant.is_active)
            .reduce((sum, variant) => sum + (parseInt(variant.stock, 10) || 0), 0);
    },
});
