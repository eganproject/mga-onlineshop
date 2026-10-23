/**
 * Pemilih produk Best Seller di Admin › Tampilan Beranda: cari produk (JSON),
 * tambah, hapus, dan urutkan dengan seret (mouse & sentuh) atau tombol naik/turun.
 * Urutan dikirim sebagai best_seller_products[] lewat input tersembunyi.
 */
export default ({ mode = 'auto', items = [], max = 24, shown = 12, searchUrl }) => ({
    mode,
    items,
    max,
    shown,
    query: '',
    results: [],
    loading: false,
    open: false,
    dragging: null,
    request: null,

    get full() {
        return this.items.length >= this.max;
    },

    /** Produk yang benar-benar tampil di beranda: aktif & ada stok, maks. `shown`. */
    get visibleIds() {
        return this.items.filter((p) => this.problem(p) === null).slice(0, this.shown).map((p) => p.id);
    },

    problem(product) {
        if (!product.active) return 'Nonaktif';
        if (product.stock <= 0) return 'Stok habis';
        return null;
    },

    has(id) {
        return this.items.some((p) => p.id === id);
    },

    async search() {
        this.open = true;
        this.loading = true;
        this.request?.abort();
        const controller = (this.request = new AbortController());

        try {
            const url = `${searchUrl}?q=${encodeURIComponent(this.query.trim())}`;
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            this.results = response.ok ? await response.json() : [];
        } catch (error) {
            if (error.name === 'AbortError') return;
            this.results = [];
        }
        this.loading = false;
    },

    add(product) {
        if (this.has(product.id) || this.full) return;
        this.items.push(product);
    },

    remove(index) {
        this.items.splice(index, 1);
    },

    move(index, step) {
        const to = index + step;
        if (to < 0 || to >= this.items.length) return;
        const [item] = this.items.splice(index, 1);
        this.items.splice(to, 0, item);
    },

    startDrag(index, event) {
        this.dragging = this.items[index].id;
        event.currentTarget.setPointerCapture(event.pointerId);
    },

    drag(event) {
        if (this.dragging === null) return;
        const row = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-pick]');
        if (!row || !this.$root.contains(row)) return;
        const to = Number(row.dataset.pick);
        const from = this.items.findIndex((p) => p.id === this.dragging);
        if (from !== to) this.move(from, to - from);
    },

    endDrag() {
        this.dragging = null;
    },
});
