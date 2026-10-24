#!/usr/bin/env node
/**
 * Cek cepat "konten melewati layar" di HP/tablet.
 *
 * Membuka halaman pelanggan & admin di beberapa lebar layar dengan Chromium
 * (Playwright), lalu melaporkan halaman yang bisa digeser ke samping atau punya
 * elemen yang keluar dari layar. Isi yang memang bisa digeser (tabel admin,
 * slider produk, tab) tidak dihitung.
 *
 * Pakai data demo (akun pelanggan@mga.test & admin@mga.test, password `password`):
 *   php artisan serve                      # terminal 1
 *   npm install --no-save playwright && npx playwright install chromium
 *   node scripts/check-mobile-overflow.mjs # terminal 2
 *
 * Opsi lewat env: BASE_URL (default http://127.0.0.1:8000), WIDTHS (default
 * 320,360,375,390,414,768,1024), ONLY=guest,customer,admin.
 * Exit code 1 bila ada masalah, jadi bisa dipakai di CI.
 */
import { createRequire } from 'node:module';

let chromium;
try {
    // require() juga mencari di NODE_PATH (Playwright yang terpasang global).
    ({ chromium } = createRequire(import.meta.url)('playwright'));
} catch {
    console.error('Playwright belum terpasang. Jalankan: npm install --no-save playwright && npx playwright install chromium');
    process.exit(2);
}

const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const WIDTHS = (process.env.WIDTHS || '320,360,375,390,414,768,1024').split(',').map(Number);
const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;

const GROUPS = {
    guest: {
        email: null,
        pages: ['/', '/produk', '/produk?q=akrilik', '/artikel', '/masuk', '/daftar', '/lupa-password', '/keranjang'],
        discover: [['/produk', 'a[href*="/produk/"]'], ['/artikel', 'a[href*="/artikel/"]']],
    },
    customer: {
        email: 'pelanggan@mga.test',
        pages: ['/keranjang', '/checkout', '/akun', '/akun/pesanan', '/akun/alamat', '/akun/alamat/create', '/akun/profil', '/akun/ulasan', '/akun/wishlist'],
        discover: [['/akun/pesanan', 'a[href*="/akun/pesanan/MGA"]']],
    },
    admin: {
        email: 'admin@mga.test',
        pages: [
            '/admin', '/admin/orders', '/admin/products', '/admin/products/create', '/admin/products/import-export',
            '/admin/categories', '/admin/categories/create', '/admin/stock', '/admin/stock/history', '/admin/customers',
            '/admin/vouchers', '/admin/vouchers/create', '/admin/banners', '/admin/banners/create', '/admin/articles',
            '/admin/articles/create', '/admin/article-categories', '/admin/reviews', '/admin/reports', '/admin/staff',
            '/admin/staff/create', '/admin/activity', '/admin/store-settings', '/admin/storefront', '/admin/integrations',
            '/admin/profile',
        ],
        discover: [['/admin/orders', 'a[href*="/admin/orders/MGA"]'], ['/admin/products', 'a[href$="/edit"][href*="/admin/products/"]'], ['/admin/customers', 'a[href*="/admin/customers/"]']],
    },
};

/** Jalan di browser: elemen yang keluar dari sisi kanan/kiri layar dan tidak berada di wadah yang bisa digeser. */
function findOverflow() {
    const vw = document.documentElement.clientWidth;
    const problems = [];
    const scrollable = (el) => {
        for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) {
            const style = getComputedStyle(a);
            const box = a.getBoundingClientRect();
            if (box.right > vw + 1 || box.left < -1) continue;
            if (/(auto|scroll)/.test(style.overflowX)) return true;
            // Dipotong sengaja oleh komponen kecil (gambar, slider) — bukan dipotong oleh layout halaman.
            if (/(hidden|clip)/.test(style.overflowX) && box.width < vw * 0.9) return true;
        }
        return false;
    };
    for (const el of document.querySelectorAll('body *')) {
        const box = el.getBoundingClientRect();
        if (!box.width || !box.height) continue;
        if (box.right <= vw + 1 && box.left >= -1) continue;
        const style = getComputedStyle(el);
        if (style.visibility === 'hidden' || style.position === 'fixed' && (box.left >= vw - 1 || box.right <= 1)) continue;
        if (box.left >= vw - 1 || box.right <= 1) continue; // panel off-canvas yang disembunyikan
        if (scrollable(el)) continue;
        // Laporkan elemen terdalam saja.
        if ([...el.children].some((c) => { const b = c.getBoundingClientRect(); return b.width && (b.right > vw + 1 || b.left < -1); })) continue;
        const label = el.tagName.toLowerCase() + (el.id ? `#${el.id}` : '') + (typeof el.className === 'string' && el.className ? `.${el.className.trim().split(/\s+/).slice(0, 4).join('.')}` : '');
        problems.push(`${label} (${Math.round(box.left)}–${Math.round(box.right)}px) "${(el.innerText || '').trim().slice(0, 40).replace(/\s+/g, ' ')}"`);
    }
    return { scrollWidth: document.documentElement.scrollWidth, vw, problems: problems.slice(0, 6) };
}

async function login(context, email) {
    const page = await context.newPage();
    await page.goto(`${BASE}/masuk`);
    await page.fill('#email', email);
    await page.fill('#password', 'password');
    await Promise.all([page.waitForNavigation(), page.press('#password', 'Enter')]);
    const ok = !page.url().includes('/masuk');
    await page.close();
    if (!ok) throw new Error(`Gagal masuk sebagai ${email} (data demo belum di-seed atau kena batas percobaan login).`);
}

const browser = await chromium.launch();
const report = [];
let checked = 0;

for (const [name, group] of Object.entries(GROUPS)) {
    if (ONLY && !ONLY.includes(name)) continue;
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    if (group.email) await login(context, group.email);
    const page = await context.newPage();

    const pages = [...group.pages];
    for (const [from, selector] of group.discover) {
        await page.goto(BASE + from);
        const href = await page.locator(selector).first().getAttribute('href').catch(() => null);
        if (href) pages.push(new URL(href, BASE).pathname);
    }

    for (const width of WIDTHS) {
        await page.setViewportSize({ width, height: 800 });
        for (const path of pages) {
            const response = await page.goto(BASE + path, { waitUntil: 'networkidle' }).catch(() => null);
            await page.waitForTimeout(100);
            const result = await page.evaluate(findOverflow);
            checked++;
            if (response && response.status() >= 400) report.push(`[${name} ${width}px] ${path} → HTTP ${response.status()}`);
            if (result.scrollWidth > result.vw || result.problems.length) {
                report.push(`[${name} ${width}px] ${path} lebar halaman ${result.scrollWidth}/${result.vw}px\n    ` + result.problems.join('\n    '));
            }
        }
    }
    await context.close();
}
await browser.close();

if (report.length) {
    console.log(report.join('\n'));
    console.log(`\n${report.length} masalah dari ${checked} pengecekan.`);
    process.exit(1);
}
console.log(`OK: ${checked} pengecekan, tidak ada konten yang melewati layar.`);
