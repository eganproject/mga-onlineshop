/**
 * Pilihan wilayah bertingkat: provinsi → kota/kabupaten → kecamatan.
 * Data diambil dari endpoint JSON (lihat Account\RegionController).
 * URL kota & kecamatan memakai placeholder "__ID__".
 */
export default ({ urls, provinceId = '', cityId = '', districtId = '', postalCode = '' }) => ({
    provinces: [],
    cities: [],
    districts: [],
    provinceId: provinceId ? String(provinceId) : '',
    cityId: cityId ? String(cityId) : '',
    districtId: districtId ? String(districtId) : '',
    postalCode: postalCode || '',
    loading: { provinces: false, cities: false, districts: false },
    error: '',

    async init() {
        this.provinces = await this.load('provinces', urls.provinces);
        if (this.provinceId) {
            this.cities = await this.load('cities', this.url(urls.cities, this.provinceId));
        }
        if (this.cityId) {
            this.districts = await this.load('districts', this.url(urls.districts, this.cityId));
        }
    },

    async changeProvince() {
        this.cityId = '';
        this.districtId = '';
        this.cities = [];
        this.districts = [];
        if (this.provinceId) {
            this.cities = await this.load('cities', this.url(urls.cities, this.provinceId));
        }
    },

    async changeCity() {
        this.districtId = '';
        this.districts = [];
        if (this.cityId) {
            this.districts = await this.load('districts', this.url(urls.districts, this.cityId));
        }
    },

    changeDistrict() {
        // Isi kode pos otomatis dari data kecamatan, pelanggan tetap bisa mengubahnya.
        const district = this.districts.find((d) => d.id === this.districtId);
        if (district?.postal_code) {
            this.postalCode = district.postal_code;
        }
    },

    get complete() {
        return Boolean(this.provinceId && this.cityId && this.districtId);
    },

    url(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    },

    async load(level, url) {
        this.loading[level] = true;
        this.error = '';
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!res.ok) {
                this.error = json.message || 'Data wilayah gagal dimuat.';
            }
            return json.data || [];
        } catch (e) {
            this.error = 'Data wilayah gagal dimuat. Periksa koneksi internet.';
            return [];
        } finally {
            this.loading[level] = false;
        }
    },
});
