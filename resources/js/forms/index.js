/**
 * Perilaku input form yang dipakai di seluruh aplikasi, dipasang sebagai
 * directive Alpine supaya bisa dipakai di Blade biasa maupun di dalam
 * komponen Alpine (x-for, x-model):
 *
 *   x-searchable          <select> jadi dropdown yang bisa dicari (Tom Select)
 *   x-datepicker[.time]   kalender tanggal / tanggal+jam (flatpickr, bahasa Indonesia)
 *   x-daterange           rentang tanggal: 1 kalender, isi 2 input tersembunyi
 *   x-money               format ribuan Rupiah, terkirim sebagai angka bersih
 *   x-phone               hanya angka, +, spasi, dan -
 *
 * Library dimuat terpisah (dynamic import) supaya halaman tanpa form tidak
 * ikut mengunduhnya. Komponen Blade siap pakai ada di
 * resources/views/components/form.
 */

import imageUpload from './image-upload';

let tomSelect;
let flatpickr;

const loadTomSelect = () =>
    (tomSelect ??= import('tom-select/base').then(async ({ default: TomSelect }) => {
        const { default: dropdownInput } = await import('tom-select/plugins/dropdown_input/plugin.js');
        TomSelect.define('dropdown_input', dropdownInput);
        return TomSelect;
    }));

const loadFlatpickr = () =>
    (flatpickr ??= Promise.all([import('flatpickr'), import('flatpickr/dist/l10n/id.js')]).then(
        ([{ default: fp }, { Indonesian }]) => {
            fp.localize(Indonesian);
            return fp;
        },
    ));

/** Opsi lebih dari ini baru diberi kotak pencarian. */
const SEARCH_THRESHOLD = 7;

/**
 * Pasang Tom Select di <select>. Kelas input asli (mis. "input input-error
 * sm:w-48") ikut ke pembungkus, jadi tampilan & lebar sama dengan input lain.
 * options: { search: bool, placeholder: string, settings: {opsi Tom Select} }.
 * Dipakai directive x-searchable dan komponen region-picker.
 */
export async function createSearchable(el, options = {}) {
    const TomSelect = await loadTomSelect();
    if (el.tomselect) return el.tomselect;
    const count = el.querySelectorAll('option').length;
    const search = options.search ?? count > SEARCH_THRESHOLD;

    return new TomSelect(el, {
        allowEmptyOption: true,
        maxOptions: null,
        controlInput: search ? undefined : null,
        plugins: search ? ['dropdown_input'] : [],
        placeholder: options.placeholder ?? el.dataset.placeholder,
        render: {
            no_results: () => '<div class="no-results">Tidak ditemukan</div>',
            loading: () => '<div class="no-results">Memuat...</div>',
        },
        onInitialize() {
            this.wrapper.classList.toggle('ts-searchable', search);
            if (search) this.control_input.placeholder = 'Ketik untuk mencari...';
            // Kotak pencarian jangan memicu @change milik form (mis. filter auto-submit).
            this.control_input.addEventListener('change', (event) => event.stopPropagation());
        },
        ...options.settings,
    });
}

/** Ganti teks placeholder Tom Select (mis. "Memuat..."). */
export function setSearchablePlaceholder(ts, text) {
    ts.settings.placeholder = text;
    const target = ts.control.querySelector('.items-placeholder') ?? ts.control_input;
    if (target) target.placeholder = text;
}

export const digitsOnly = (value) => String(value ?? '').replace(/\D/g, '');

export const formatThousands = (value) => {
    const digits = digitsOnly(value).replace(/^0+(?=\d)/, '');
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
};

/** Set nilai input lalu kabari x-model / listener lain. */
const setValue = (el, value) => {
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
};

export default function forms(Alpine) {
    Alpine.data('imageUpload', imageUpload);

    /*
     * <select x-searchable> — Tom Select. Kelas input asli (mis. "input
     * input-error sm:w-48") ikut ke pembungkus, jadi tampilan & lebar sama.
     * Opsi: x-searchable="{ placeholder: '...', search: true }".
     */
    Alpine.directive('searchable', (el, { expression }, { evaluate, cleanup }) => {
        if (el.tomselect) return;
        const options = expression ? evaluate(expression) : {};
        let instance;

        createSearchable(el, options).then((ts) => {
            instance = ts;
            el.dispatchEvent(new CustomEvent('searchable:ready', { detail: ts }));
        });

        cleanup(() => instance?.destroy());
    });

    /*
     * <input x-datepicker> / <input x-datepicker.time>. Nilai yang dikirim
     * tetap "Y-m-d" atau "Y-m-d\TH:i" (sama dengan input date/datetime-local),
     * yang tampil "5 Okt 2026, 14:30". Atribut min/max ikut dipakai.
     */
    Alpine.directive('datepicker', (el, { expression, modifiers }, { evaluate, cleanup }) => {
        const options = expression ? evaluate(expression) : {};
        const time = modifiers.includes('time');
        let picker;

        loadFlatpickr().then((fp) => {
            if (!el.isConnected) return;
            const id = el.id;
            const clear = el.parentElement?.querySelector('[data-date-clear]');
            const syncClear = () => clear?.classList.toggle('hidden', !picker || picker.selectedDates.length === 0);
            picker = fp(el, {
                altInput: true,
                altFormat: time ? 'j M Y, H:i' : 'j M Y',
                dateFormat: time ? 'Y-m-d\\TH:i' : 'Y-m-d',
                enableTime: time,
                time_24hr: true,
                minuteIncrement: 5,
                disableMobile: true,
                minDate: el.min || null,
                maxDate: el.max || null,
                onChange: () => syncClear(),
                ...options,
            });
            // Pindahkan id ke input yang terlihat supaya <label for> tetap jalan.
            if (id && picker.altInput) {
                el.removeAttribute('id');
                picker.altInput.id = id;
            }
            clear?.addEventListener('click', () => {
                picker.clear();
                syncClear();
            });
            syncClear();
            el._flatpickr = picker;
        });

        cleanup(() => picker?.destroy());
    });

    /*
     * <div x-daterange> berisi input[data-range-display] (kalender) dan dua
     * input tersembunyi [data-range-from] & [data-range-to] yang dikirim.
     */
    Alpine.directive('daterange', (el, { expression }, { evaluate, cleanup }) => {
        const options = expression ? evaluate(expression) : {};
        const display = el.querySelector('[data-range-display]');
        const from = el.querySelector('[data-range-from]');
        const to = el.querySelector('[data-range-to]');
        const clear = el.querySelector('[data-date-clear]');
        let picker;

        loadFlatpickr().then((fp) => {
            if (!el.isConnected) return;
            const format = (date) => picker.formatDate(date, 'Y-m-d');
            const syncClear = () => clear?.classList.toggle('hidden', !from.value && !to.value);
            picker = fp(display, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                disableMobile: true,
                minDate: display.min || null,
                maxDate: display.max || null,
                defaultDate: [from.value, to.value].filter(Boolean),
                onChange(dates) {
                    if (dates.length === 2) {
                        setValue(from, format(dates[0]));
                        setValue(to, format(dates[1]));
                    } else if (dates.length === 0) {
                        setValue(from, '');
                        setValue(to, '');
                    }
                    syncClear();
                },
                onClose(dates) {
                    // Hanya klik satu tanggal: anggap rentang satu hari.
                    if (dates.length === 1) picker.setDate([dates[0], dates[0]], true);
                },
                ...options,
            });
            if (display.id && picker.altInput) {
                picker.altInput.id = display.id;
                display.removeAttribute('id');
            }
            clear?.addEventListener('click', () => picker.clear());
            syncClear();
        });

        cleanup(() => picker?.destroy());
    });

    /*
     * <input x-money> — tampil "1.250.000" saat diketik, terkirim "1250000"
     * (lihat listener formdata di bawah). Listener capture supaya x-model
     * di elemen yang sama menerima nilai yang sudah diformat.
     */
    Alpine.directive('money', (el, {}, { cleanup }) => {
        el.dataset.money = '';
        el.setAttribute('inputmode', 'numeric');
        el.setAttribute('autocomplete', 'off');

        const format = () => {
            const raw = el.value;
            const focused = document.activeElement === el;
            const caret = focused ? (el.selectionStart ?? raw.length) : raw.length;
            const digitsBefore = digitsOnly(raw.slice(0, caret)).length;
            const formatted = formatThousands(raw);
            if (formatted === raw) return;
            el.value = formatted;
            if (!focused) return;
            let position = 0;
            for (let seen = 0; position < formatted.length && seen < digitsBefore; position++) {
                if (/\d/.test(formatted[position])) seen++;
            }
            el.setSelectionRange(position, position);
        };

        el.addEventListener('input', format, { capture: true });
        el.addEventListener('blur', format);
        format();
        cleanup(() => {
            el.removeEventListener('input', format, { capture: true });
            el.removeEventListener('blur', format);
        });
    });

    /* <input x-phone> — buang karakter selain angka, +, spasi, dan -. */
    Alpine.directive('phone', (el, {}, { cleanup }) => {
        el.setAttribute('inputmode', 'tel');
        const clean = () => {
            const value = el.value.replace(/[^\d+\-\s]/g, '').replace(/(?!^)\+/g, '');
            if (value !== el.value) el.value = value;
        };
        el.addEventListener('input', clean, { capture: true });
        cleanup(() => el.removeEventListener('input', clean, { capture: true }));
    });

    /* Nominal Rupiah dikirim tanpa titik pemisah ribuan. */
    document.addEventListener(
        'formdata',
        (event) => {
            for (const input of event.target.elements) {
                if (!('money' in input.dataset) || !input.name || input.disabled) continue;
                if (!event.formData.has(input.name)) continue;
                event.formData.set(input.name, digitsOnly(input.value));
            }
        },
        { capture: true },
    );

    /* Bila halaman kembali dengan error validasi, gulir ke isian pertama yang salah. */
    document.addEventListener('DOMContentLoaded', () => {
        const first = document.querySelector('[data-field-error]');
        if (!first) return;
        const rect = first.getBoundingClientRect();
        if (rect.top < 0 || rect.bottom > window.innerHeight) {
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });
}
