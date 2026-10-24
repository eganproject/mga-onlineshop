/**
 * Infinite scroll untuk <x-infinite-list> (tanpa Alpine, cukup atribut data-*).
 *
 * - Halaman berikutnya diambil dengan header X-Infinite-Scroll; server membalas
 *   potongan HTML daftar saja (App\Support\InfiniteScroll), lalu itemnya ditempel.
 * - Dimuat otomatis lewat IntersectionObserver sebelum pengguna sampai di bawah.
 *   Setiap AUTO_BATCH kali berhenti dan menampilkan tombol "Muat lebih banyak"
 *   supaya footer tetap bisa dicapai. Tombol juga dipakai bila observer tidak ada,
 *   saat gagal memuat, atau bila data-infinite-auto="false".
 * - Kembali (Back) dari halaman produk memulihkan item yang sudah dimuat + posisi
 *   scroll lewat sessionStorage.
 */
const AUTO_BATCH = 4;
const STORE_PREFIX = 'infinite:';

function navigationType() {
    return performance.getEntriesByType?.('navigation')?.[0]?.type ?? 'navigate';
}

function readStore(key) {
    try {
        return JSON.parse(sessionStorage.getItem(key) || 'null');
    } catch {
        return null;
    }
}

function writeStore(key, value) {
    try {
        if (value) sessionStorage.setItem(key, JSON.stringify(value));
        else sessionStorage.removeItem(key);
    } catch {
        // Penyimpanan penuh / diblokir: tidak apa-apa, hanya tidak bisa dipulihkan.
    }
}

function setup(root, index) {
    const items = root.querySelector('[data-infinite-items]');
    const status = root.querySelector('[data-infinite-status]');
    if (!items || !status) return;

    const pager = root.querySelector('[data-infinite-pager]');
    const button = root.querySelector('[data-infinite-more]');
    const loading = root.querySelector('[data-infinite-loading]');
    const sentinel = root.querySelector('[data-infinite-sentinel]');
    const shown = root.querySelector('[data-infinite-shown]');
    const progress = root.querySelector('[data-infinite-progress]');
    const skeleton = root.querySelector('template[data-infinite-skeleton]');
    const label = root.dataset.infiniteLabel || 'data';
    const total = Number(progress?.dataset.total || 0);
    const storeKey = `${STORE_PREFIX}${location.pathname}${location.search}#${index}`;

    let next = root.dataset.infiniteNext || '';
    let auto = root.dataset.infiniteAuto !== 'false' && 'IntersectionObserver' in window;
    let autoLoads = 0;
    let busy = false;
    let loaded = []; // potongan HTML yang ditempel, untuk dipulihkan saat Back
    let observer = null;
    let firstShown = Number((shown?.textContent || '0').replace(/\D/g, '')) - items.children.length;

    // JS aktif: link halaman diganti status + tombol.
    pager?.classList.add('hidden');
    status.classList.remove('hidden');
    status.classList.add('flex');

    const count = () => firstShown + items.children.length;

    function render() {
        const done = !next;
        const showButton = !done && !busy && !auto;
        button?.classList.toggle('hidden', !showButton);
        loading?.classList.toggle('hidden', !busy);
        loading?.classList.toggle('flex', busy);
        button?.toggleAttribute('aria-busy', busy);
        root.setAttribute('aria-busy', busy ? 'true' : 'false');

        if (shown) shown.textContent = new Intl.NumberFormat('id-ID').format(count());
        if (progress && total) progress.style.width = `${Math.min(100, Math.round((count() / total) * 100))}%`;
        if (done && pager) pager.remove();
    }

    function append(html) {
        const template = document.createElement('template');
        template.innerHTML = html;
        const incoming = template.content.querySelector('[data-infinite]');
        const list = incoming?.querySelector('[data-infinite-items]');
        if (!list) throw new Error('Respons daftar tidak valid');

        items.append(...list.children);
        next = incoming.dataset.infiniteNext || '';
    }

    function showSkeleton() {
        if (!skeleton) return null;
        const node = skeleton.content.firstElementChild.cloneNode(true);
        // Jarak antar-baris kerangka = jarak antar-baris daftar.
        const gap = getComputedStyle(items).rowGap;
        if (gap && gap !== 'normal') node.style.marginTop = gap;
        items.after(node);
        return node;
    }

    async function load() {
        if (busy || !next) return;
        busy = true;
        render();
        const placeholder = showSkeleton();

        try {
            const response = await fetch(next, {
                headers: { 'X-Infinite-Scroll': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const html = await response.text();
            append(html);
            loaded.push(html);
        } catch (error) {
            auto = false; // jangan terus-menerus mencoba; pengguna bisa menekan tombol
            window.toast?.(`Gagal memuat ${label} berikutnya. Coba lagi.`, 'error');
        } finally {
            placeholder?.remove();
            busy = false;
            render();
            // Sentinel masih terlihat (layar tinggi / scroll cepat)? Observer tidak
            // memicu ulang dengan sendirinya, jadi pasang ulang.
            if (auto && next && observer) {
                observer.unobserve(sentinel);
                observer.observe(sentinel);
            }
        }
    }

    button?.addEventListener('click', () => {
        // Lanjutkan muat otomatis setelah tombol ditekan (kecuali daftar mode tombol).
        if (root.dataset.infiniteAuto !== 'false' && 'IntersectionObserver' in window) {
            auto = true;
            autoLoads = 0;
        }
        load();
    });

    function autoLoad() {
        if (!auto || busy || !next) return;
        autoLoads++;
        if (autoLoads > AUTO_BATCH) {
            auto = false;
            render();
            return;
        }
        load();
    }

    if ('IntersectionObserver' in window && sentinel) {
        observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) autoLoad();
        }, { rootMargin: '0px 0px 800px 0px' });
        observer.observe(sentinel);

        // Scroll sangat cepat (mis. tombol End) bisa melompati sentinel tanpa memicu
        // observer; cek juga posisinya saat scroll.
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking || !auto || busy || !next) return;
            ticking = true;
            requestAnimationFrame(() => {
                ticking = false;
                if (sentinel.getBoundingClientRect().top < window.innerHeight + 800) autoLoad();
            });
        }, { passive: true });
    }

    // Pulihkan setelah Back dari halaman lain (bila halaman tidak dari bfcache).
    const saved = navigationType() === 'back_forward' ? readStore(storeKey) : null;
    if (saved?.pages?.length) {
        try {
            saved.pages.forEach(append);
            loaded = saved.pages;
            next = saved.next || '';
            if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
            const restore = () => window.scrollTo(0, saved.scroll || 0);
            requestAnimationFrame(restore);
            window.addEventListener('load', restore, { once: true });
        } catch {
            // Data lama rusak: abaikan.
        }
    }
    writeStore(storeKey, null);

    window.addEventListener('pagehide', () => {
        if (loaded.length) writeStore(storeKey, { pages: loaded, next, scroll: window.scrollY });
    });

    render();
}

export default function infiniteScroll() {
    document.querySelectorAll('[data-infinite]').forEach(setup);
}
