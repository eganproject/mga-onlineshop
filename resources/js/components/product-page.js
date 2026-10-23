/**
 * Halaman detail produk: galeri foto + pemilih varian. Memilih varian
 * mengganti harga, stok, dan foto (bila varian punya foto sendiri).
 */
export default (config) => ({
    images: config.images, // [{ id, url }]
    active: 0,
    variants: config.variants, // [{ id, name, price, compare_at_price, stock, image_id }]
    selectedId: config.selectedId ?? null,
    qty: 1,

    get selected() {
        return this.variants.find((variant) => variant.id === this.selectedId) ?? null;
    },

    get stock() {
        return this.selected ? this.selected.stock : config.stock;
    },

    get max() {
        return Math.max(1, this.stock);
    },

    get price() {
        return this.selected ? this.selected.price : config.price;
    },

    get compareAt() {
        const compare = this.selected ? this.selected.compare_at_price : config.compareAt;
        return compare && compare > this.price ? compare : null;
    },

    get discount() {
        return this.compareAt ? Math.round(100 - (this.price / this.compareAt) * 100) : 0;
    },

    get needsVariant() {
        return this.variants.length > 0 && !this.selected;
    },

    select(variant) {
        if (variant.stock < 1) return;
        this.selectedId = variant.id;
        this.qty = Math.min(this.qty, Math.max(1, variant.stock));
        const imageIndex = this.images.findIndex((image) => image.id === variant.image_id);
        if (imageIndex >= 0) this.active = imageIndex;
    },

    next(step) {
        if (this.images.length < 2) return;
        this.active = (this.active + step + this.images.length) % this.images.length;
    },
});
