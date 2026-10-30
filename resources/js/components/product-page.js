/**
 * Halaman detail produk: galeri foto + pemilih varian. Memilih varian
 * mengganti harga, stok, dan foto (bila varian punya foto sendiri).
 *
 * Galeri = jalur geser (scroll-snap) yang disinkronkan dengan `active`:
 * - desktop (kursor): arahkan kursor = zoom 1,75x mengikuti posisi kursor;
 * - klik/ketuk = lightbox layar penuh PhotoSwipe (cubit/zoom, geser, Esc),
 *   library-nya dimuat dinamis hanya saat pertama dibuka.
 */
const ZOOM = 1.75;
const canHoverZoom = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/** Ukuran asli foto (PhotoSwipe butuh lebar/tinggi). */
const sizeCache = new Map();
function imageSize(url) {
    if (sizeCache.has(url)) return sizeCache.get(url);
    const promise = new Promise((resolve) => {
        const img = new Image();
        const done = () => resolve(img.naturalWidth ? { width: img.naturalWidth, height: img.naturalHeight } : { width: 1200, height: 1200 });
        img.onload = done;
        img.onerror = done;
        setTimeout(done, 2500);
        img.src = url;
    });
    sizeCache.set(url, promise);
    return promise;
}

export default (config) => ({
    images: config.images, // [{ id, url }]
    active: 0,
    variants: config.variants, // [{ id, name, price, compare_at_price, stock, image_id }]
    selectedId: config.selectedId ?? null,
    minQty: config.minQty ?? 1,
    qty: config.minQty ?? 1,
    lightboxOpening: false,

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

    /** Tingkat harga grosir varian terpilih / produk: [{ min_qty, price }]. */
    get tiers() {
        return (this.selected ? this.selected.tiers : config.tiers) ?? [];
    },

    /** Tingkat grosir yang berlaku untuk jumlah di form (null = harga normal). */
    get activeTier() {
        return this.tiers.filter((tier) => this.qty >= tier.min_qty).pop() ?? null;
    },

    get unitPrice() {
        return this.activeTier ? this.activeTier.price : this.price;
    },

    /** Baris tabel grosir: harga normal lalu tiap tingkat, dengan rentang jumlahnya. */
    get tierRows() {
        if (!this.tiers.length) return [];
        const rows = [{ min_qty: 1, price: this.price }, ...this.tiers];
        return rows.map((row, i) => {
            const next = rows[i + 1]?.min_qty;
            return {
                ...row,
                label: next ? `${row.min_qty}–${next - 1} pcs` : `≥ ${row.min_qty} pcs`,
                save: i && this.price ? Math.round(100 - (row.price / this.price) * 100) : 0,
                active: this.qty >= row.min_qty && (!next || this.qty < next),
            };
        });
    },

    setQty(value) {
        this.qty = Math.min(this.max, Math.max(1, value));
    },

    get needsVariant() {
        return this.variants.length > 0 && !this.selected;
    },

    init() {
        // Varian/thumbnail/tombol mengganti `active` → geser jalur foto ke sana.
        this.$watch('active', (index) => {
            this.scrollTrackTo(index);
            this.scrollThumbTo(index);
        });
    },

    go(index) {
        this.active = index;
    },

    hoverThumb(index) {
        if (canHoverZoom()) this.active = index;
    },

    scrollTrackTo(index) {
        const track = this.$refs.track;
        if (!track) return;
        const left = index * track.clientWidth;
        if (Math.abs(track.scrollLeft - left) > 2) track.scrollTo({ left, behavior: 'smooth' });
    },

    scrollThumbTo(index) {
        const list = this.$refs.thumbs;
        const thumb = list?.children[index];
        if (!thumb) return;
        // Geser di dalam daftar thumbnail saja (bukan halaman).
        const vertical = list.scrollHeight > list.clientHeight + 1 && getComputedStyle(list).flexDirection === 'column';
        if (vertical) {
            const top = thumb.offsetTop - (list.clientHeight - thumb.offsetHeight) / 2;
            list.scrollTo({ top, behavior: 'smooth' });
        } else {
            const left = thumb.offsetLeft - (list.clientWidth - thumb.offsetWidth) / 2;
            list.scrollTo({ left, behavior: 'smooth' });
        }
    },

    /** Pengguna menggeser foto (HP/trackpad) → perbarui `active`. */
    syncFromScroll() {
        const track = this.$refs.track;
        if (!track || !track.clientWidth) return;
        const index = Math.round(track.scrollLeft / track.clientWidth);
        if (index !== this.active && index >= 0 && index < this.images.length) this.active = index;
    },

    zoomStart(event) {
        if (!canHoverZoom()) return;
        const img = event.currentTarget.querySelector('img');
        img.style.transition = 'transform .25s ease-out';
        img.style.transform = `scale(${ZOOM})`;
        this.zoomMove(event);
    },

    zoomMove(event) {
        if (!canHoverZoom()) return;
        const slide = event.currentTarget;
        const img = slide.querySelector('img');
        const rect = slide.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        img.style.transformOrigin = `${Math.min(100, Math.max(0, x))}% ${Math.min(100, Math.max(0, y))}%`;
        if (!img.style.transform) img.style.transform = `scale(${ZOOM})`;
    },

    zoomEnd(event) {
        const img = event.currentTarget.querySelector('img');
        img.style.transform = '';
    },

    async openLightbox(index = this.active) {
        if (!this.images.length || this.lightboxOpening) return;
        this.lightboxOpening = true;
        try {
            const [{ default: PhotoSwipe }, sizes] = await Promise.all([
                import('photoswipe'),
                Promise.all(this.images.map((image) => imageSize(image.url))),
                import('photoswipe/style.css'),
            ]);
            const pswp = new PhotoSwipe({
                dataSource: this.images.map((image, i) => ({ src: image.url, ...sizes[i], alt: `Foto ${i + 1}` })),
                index,
                bgOpacity: 0.94,
                showHideAnimationType: 'fade',
                wheelToZoom: true,
                secondaryZoomLevel: 2.5,
                maxZoomLevel: 4,
                padding: { top: 24, bottom: 24, left: 0, right: 0 },
                closeTitle: 'Tutup (Esc)',
                zoomTitle: 'Perbesar',
                arrowPrevTitle: 'Sebelumnya',
                arrowNextTitle: 'Berikutnya',
                errorMsg: 'Foto tidak bisa dimuat',
                indexIndicatorSep: ' / ',
                // Fokus dikembalikan sendiri (tanpa scroll) ke foto yang terakhir dilihat;
                // bawaan PhotoSwipe memfokus foto awal dan ikut menggeser galeri.
                returnFocus: false,
            });
            pswp.on('change', () => {
                this.active = pswp.currIndex;
            });
            pswp.on('destroy', () => {
                this.$refs.track?.children[this.active]?.focus({ preventScroll: true });
                this.scrollTrackTo(this.active);
            });
            pswp.init();
        } catch (error) {
            window.toast?.('Foto tidak bisa dibuka. Coba lagi.', 'error');
        } finally {
            this.lightboxOpening = false;
        }
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
