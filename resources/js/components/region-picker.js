import { createSearchable, setSearchablePlaceholder } from '../forms';

/**
 * Pilihan wilayah bertingkat: provinsi → kota/kabupaten → kecamatan, masing-
 * masing dropdown yang bisa dicari (Tom Select). Data diambil dari endpoint
 * JSON (lihat Account\RegionController). URL kota & kecamatan memakai
 * placeholder "__ID__". Butuh x-ref="province|city|district" di <select>.
 */
const LEVELS = ['province', 'city', 'district'];

const PLACEHOLDERS = {
    province: { ready: 'Pilih provinsi', waiting: 'Pilih provinsi' },
    city: { ready: 'Pilih kota/kabupaten', waiting: 'Pilih provinsi dulu' },
    district: { ready: 'Pilih kecamatan', waiting: 'Pilih kota dulu' },
};

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
    selects: {},

    async init() {
        await Promise.all(
            LEVELS.map(async (level) => {
                this.selects[level] = await createSearchable(this.$refs[level], {
                    search: true,
                    placeholder: PLACEHOLDERS[level].waiting,
                    settings: {
                        valueField: 'id',
                        labelField: 'name',
                        searchField: ['name'],
                        allowEmptyOption: false,
                        onChange: (value) => this.select(level, value),
                    },
                });
            }),
        );

        this.provinces = await this.load('provinces', urls.provinces);
        this.fill('province', this.provinces, this.provinceId);
        if (this.provinceId) {
            this.cities = await this.load('cities', this.url(urls.cities, this.provinceId));
        }
        this.fill('city', this.cities, this.cityId);
        if (this.cityId) {
            this.districts = await this.load('districts', this.url(urls.districts, this.cityId));
        }
        this.fill('district', this.districts, this.districtId);
    },

    destroy() {
        Object.values(this.selects).forEach((ts) => ts.destroy());
    },

    /** Isi ulang opsi satu dropdown dan pilih nilainya (tanpa memicu change). */
    fill(level, items, value = '') {
        const ts = this.selects[level];
        if (!ts) return;
        ts.clear(true);
        ts.clearOptions();
        ts.addOptions(items);
        if (value && items.some((item) => item.id === value)) ts.setValue(value, true);
        ts.refreshOptions(false);
        ts.close();
        setSearchablePlaceholder(ts, items.length ? PLACEHOLDERS[level].ready : PLACEHOLDERS[level].waiting);
        items.length ? ts.enable() : ts.disable();
    },

    setLoading(level) {
        const ts = this.selects[level];
        if (!ts) return;
        ts.clear(true);
        ts.clearOptions();
        ts.disable();
        setSearchablePlaceholder(ts, 'Memuat...');
    },

    select(level, value) {
        value = value ? String(value) : '';
        if (level === 'province' && value !== this.provinceId) {
            this.provinceId = value;
            this.changeProvince();
        } else if (level === 'city' && value !== this.cityId) {
            this.cityId = value;
            this.changeCity();
        } else if (level === 'district' && value !== this.districtId) {
            this.districtId = value;
            this.changeDistrict();
        }
    },

    async changeProvince() {
        this.cityId = '';
        this.districtId = '';
        this.cities = [];
        this.districts = [];
        this.fill('district', []);
        if (this.provinceId) {
            this.setLoading('city');
            const province = this.provinceId;
            const cities = await this.load('cities', this.url(urls.cities, province));
            if (province !== this.provinceId) return; // sudah ganti provinsi lagi
            this.cities = cities;
        }
        this.fill('city', this.cities);
    },

    async changeCity() {
        this.districtId = '';
        this.districts = [];
        if (this.cityId) {
            this.setLoading('district');
            const city = this.cityId;
            const districts = await this.load('districts', this.url(urls.districts, city));
            if (city !== this.cityId) return;
            this.districts = districts;
        }
        this.fill('district', this.districts);
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
            return (json.data || []).map((item) => ({ ...item, id: String(item.id) }));
        } catch (e) {
            this.error = 'Data wilayah gagal dimuat. Periksa koneksi internet.';
            return [];
        } finally {
            this.loading[level] = false;
        }
    },
});
