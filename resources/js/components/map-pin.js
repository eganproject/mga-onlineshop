/**
 * Pin lokasi di peta (Leaflet + OpenStreetMap, di-import dinamis) untuk
 * <x-form.map-pin>. Ketuk peta atau geser pin untuk menandai lokasi; nilai
 * tersimpan di input tersembunyi lintang/bujur. Dipakai untuk tarif kurir
 * instan Biteship (form alamat pembeli & koordinat gudang di Pengaturan Toko).
 *
 * Bila dipasang di dalam regionPicker, peta ikut bergeser ke kecamatan yang
 * dipilih (dicari lewat Nominatim) selama pin belum ditaruh.
 */
const INDONESIA = [-2.5, 118];

export default ({ lat = null, lng = null } = {}) => ({
    lat: lat !== null && lat !== '' ? Number(lat) : null,
    lng: lng !== null && lng !== '' ? Number(lng) : null,
    map: null,
    marker: null,
    L: null,
    locating: false,
    notice: '',

    get hasPin() {
        return this.lat !== null && this.lng !== null;
    },

    async init() {
        const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
        this.L = L;

        this.map = L.map(this.$refs.map, { scrollWheelZoom: false, attributionControl: true })
            .setView(this.hasPin ? [this.lat, this.lng] : INDONESIA, this.hasPin ? 16 : 4);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
        }).addTo(this.map);

        this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng));
        if (this.hasPin) this.draw();

        // Peta di dalam elemen yang baru tampil perlu dihitung ulang ukurannya.
        new ResizeObserver(() => this.map?.invalidateSize()).observe(this.$refs.map);

        // Di dalam regionPicker: ikuti kecamatan yang dipilih.
        if ('districtId' in this) {
            this.$watch('districtId', () => this.followRegion());
            if (!this.hasPin) this.followRegion();
        }
    },

    destroy() {
        this.map?.remove();
    },

    place(lat, lng, zoom = null) {
        this.lat = Number(lat.toFixed(7));
        this.lng = Number(lng.toFixed(7));
        this.notice = '';
        this.draw();
        if (zoom) this.map.setView([this.lat, this.lng], zoom);
    },

    draw() {
        const L = this.L;
        if (!this.marker) {
            const icon = L.divIcon({ className: 'map-pin-marker', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 28] });
            this.marker = L.marker([this.lat, this.lng], { icon, draggable: true, keyboard: false }).addTo(this.map);
            this.marker.on('dragend', () => {
                const p = this.marker.getLatLng();
                this.place(p.lat, p.lng);
            });
        } else {
            this.marker.setLatLng([this.lat, this.lng]);
        }
    },

    clear() {
        this.lat = null;
        this.lng = null;
        this.marker?.remove();
        this.marker = null;
    },

    locate() {
        if (!navigator.geolocation) {
            this.notice = 'Browser ini tidak mendukung deteksi lokasi. Ketuk peta untuk menaruh pin.';
            return;
        }
        this.locating = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                this.locating = false;
                this.place(pos.coords.latitude, pos.coords.longitude, 17);
            },
            () => {
                this.locating = false;
                this.notice = 'Lokasi tidak bisa dibaca. Izinkan akses lokasi atau ketuk peta untuk menaruh pin.';
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    },

    /** Geser peta ke kecamatan terpilih (tanpa menaruh pin). */
    async followRegion() {
        if (this.hasPin || !this.districtId) return;
        const name = (list, id) => list?.find((item) => item.id === id)?.name;
        const query = [name(this.districts, this.districtId), name(this.cities, this.cityId), name(this.provinces, this.provinceId), 'Indonesia']
            .filter(Boolean)
            .join(', ');
        if (query === 'Indonesia') return;

        try {
            const res = await fetch(`https://nominatim.openstreetmap.org/search?${new URLSearchParams({ format: 'json', limit: '1', countrycodes: 'id', q: query })}`, {
                headers: { Accept: 'application/json' },
            });
            const [hit] = await res.json();
            if (hit && !this.hasPin) this.map.setView([Number(hit.lat), Number(hit.lon)], 14);
        } catch (e) {
            // Tanpa hasil pencarian peta tetap bisa digeser manual.
        }
    },
});
