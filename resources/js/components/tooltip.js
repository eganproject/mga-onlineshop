/**
 * Tooltip bantuan untuk <x-tooltip> (markup: [data-tip] > [data-tip-trigger] + [data-tip-panel]).
 *
 * - Mouse: buka saat hover (jeda singkat), tutup saat kursor pergi. Klik = sematkan.
 * - Sentuh: ketuk ikon untuk buka/tutup, ketuk di mana saja untuk menutup.
 * - Keyboard: Tab ke ikon membuka, Esc menutup (tanpa ikut menutup modal di belakangnya).
 *
 * Saat terbuka panel dipindah ke <body> dengan position: fixed supaya tidak
 * terpotong overflow card/tabel/modal dan tidak menggeser layout; saat tertutup
 * dikembalikan ke tempat asalnya. Pakai event delegation, jadi tooltip di HTML
 * yang dimuat belakangan (template Alpine, fetch) langsung berfungsi.
 */
const GAP = 10; // jarak panel ke ikon
const MARGIN = 8; // jarak minimal ke tepi layar
const OPEN_DELAY = 120;
const CLOSE_DELAY = 140;

export default function tooltip() {
    let active = null; // { trigger, panel, home, pinned }
    let openTimer = null;
    let closeTimer = null;
    let hideTimer = null;

    const panelOf = (trigger) => trigger.closest('[data-tip]')?.querySelector('[data-tip-panel]') ?? trigger._tipPanel;

    function place() {
        if (!active) return;
        const { trigger, panel } = active;
        const rect = trigger.getBoundingClientRect();

        // Ikon sudah keluar layar (mis. halaman digulir): tutup saja.
        if (rect.bottom < 0 || rect.top > window.innerHeight || (rect.width === 0 && rect.height === 0)) {
            close(true);
            return;
        }

        const vw = document.documentElement.clientWidth;
        const width = panel.offsetWidth;
        const height = panel.offsetHeight;
        const center = rect.left + rect.width / 2;

        // Utamakan di atas ikon (tidak menutupi kolom isian di bawah label).
        let placement = 'top';
        let top = rect.top - height - GAP;
        if (top < MARGIN) {
            placement = 'bottom';
            top = rect.bottom + GAP;
        }

        const left = Math.min(Math.max(center - width / 2, MARGIN), vw - width - MARGIN);
        const arrow = Math.min(Math.max(center - left, 14), width - 14);

        panel.dataset.placement = placement;
        panel.style.top = `${Math.round(top)}px`;
        panel.style.left = `${Math.round(Math.max(left, MARGIN))}px`;
        panel.style.setProperty('--tip-arrow', `${Math.round(arrow)}px`);
    }

    function open(trigger, pinned = false) {
        clearTimeout(openTimer);
        clearTimeout(closeTimer);

        if (active?.trigger === trigger) {
            if (pinned) pin();
            return;
        }
        if (active) close(true);

        const panel = panelOf(trigger);
        if (!panel) return;

        clearTimeout(hideTimer);
        trigger._tipPanel = panel;
        active = { trigger, panel, home: panel.parentNode, pinned: false };
        if (pinned) pin();

        document.body.appendChild(panel);
        panel.hidden = false;
        panel.dataset.state = 'closed';
        place();
        trigger.setAttribute('aria-expanded', 'true');
        requestAnimationFrame(() => {
            if (active?.panel === panel) panel.dataset.state = 'open';
        });
    }

    // Tooltip yang dibuka dengan klik/ketuk menangkap ketukan di atasnya, supaya
    // ketukan untuk menutup tidak ikut mengenai tombol/isian di bawah panel.
    function pin() {
        active.pinned = true;
        active.panel.dataset.pinned = '';
    }

    function close(immediate = false) {
        clearTimeout(openTimer);
        clearTimeout(closeTimer);
        if (!active) return;

        const { trigger, panel, home } = active;
        active = null;
        trigger.setAttribute('aria-expanded', 'false');
        panel.dataset.state = 'closed';
        delete panel.dataset.pinned;

        const restore = () => {
            if (active?.panel === panel) return;
            panel.hidden = true;
            // Kembalikan ke tempat asal; bila asalnya sudah dihapus (baris template), buang.
            if (home?.isConnected) home.appendChild(panel);
            else panel.remove();
        };
        clearTimeout(hideTimer);
        if (immediate) restore();
        else hideTimer = setTimeout(restore, 150);
    }

    document.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'mouse') return;
        const trigger = event.target.closest?.('[data-tip-trigger]');
        if (!trigger) return;
        clearTimeout(closeTimer);
        if (active?.trigger === trigger) return;
        clearTimeout(openTimer);
        openTimer = setTimeout(() => open(trigger), active ? 0 : OPEN_DELAY);
    });

    document.addEventListener('pointerout', (event) => {
        if (event.pointerType !== 'mouse') return;
        const trigger = event.target.closest?.('[data-tip-trigger]');
        if (!trigger || trigger.contains(event.relatedTarget)) return;
        clearTimeout(openTimer);
        if (active?.trigger === trigger && !active.pinned) {
            closeTimer = setTimeout(() => close(), CLOSE_DELAY);
        }
    });

    document.addEventListener('click', (event) => {
        if (active && active.panel.contains(event.target)) {
            // Panel ada di <body>: jangan dianggap "klik di luar" oleh modal (@click.outside).
            event.stopImmediatePropagation();
            close();
            return;
        }
        const trigger = event.target.closest?.('[data-tip-trigger]');
        if (!trigger) return;
        // Jangan ikut mengaktifkan <label>/checkbox di sekitarnya.
        event.preventDefault();
        if (active?.trigger === trigger && active.pinned) close();
        else open(trigger, true);
    });

    // Ketuk/klik di luar ikon menutup tooltip yang disematkan.
    document.addEventListener('pointerdown', (event) => {
        if (active && !event.target.closest?.('[data-tip-trigger]') && !active.panel.contains(event.target)) close();
    }, true);

    document.addEventListener('focusin', (event) => {
        const trigger = event.target.closest?.('[data-tip-trigger]');
        if (trigger && trigger.matches(':focus-visible')) open(trigger);
    });

    document.addEventListener('focusout', (event) => {
        if (active && event.target === active.trigger && !active.pinned) close();
    });

    // Esc menutup tooltip dulu; modal di belakangnya tidak ikut tertutup.
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && active) {
            event.stopPropagation();
            close();
        }
    }, true);

    window.addEventListener('scroll', () => active && place(), { capture: true, passive: true });
    window.addEventListener('resize', () => active && place(), { passive: true });
}
