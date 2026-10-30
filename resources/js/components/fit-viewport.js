/**
 * x-fit-viewport: batasi tinggi elemen sticky (sidebar filter katalog) supaya
 * bagian bawahnya selalu terlihat di layar dan isinya bisa di-scroll sendiri,
 * termasuk saat halaman masih di paling atas (sidebar belum menempel).
 *
 * Modifier angka = jarak bawah dalam px (default 16). Hanya aktif di layar lg ke atas.
 */
export default function fitViewport(Alpine) {
    Alpine.directive('fit-viewport', (el, { modifiers }, { cleanup }) => {
        const gap = Number(modifiers.find((m) => /^\d+$/.test(m)) ?? 16);
        const desktop = window.matchMedia('(min-width: 1024px)');
        let frame = null;

        const update = () => {
            frame = null;
            if (! desktop.matches) {
                el.style.maxHeight = '';
                return;
            }
            const top = Math.max(el.getBoundingClientRect().top, 0);
            el.style.maxHeight = `${Math.max(window.innerHeight - top - gap, 200)}px`;
        };
        const schedule = () => {
            if (frame === null) frame = requestAnimationFrame(update);
        };

        update();
        window.addEventListener('scroll', schedule, { passive: true });
        window.addEventListener('resize', schedule);
        desktop.addEventListener('change', schedule);

        cleanup(() => {
            window.removeEventListener('scroll', schedule);
            window.removeEventListener('resize', schedule);
            desktop.removeEventListener('change', schedule);
            if (frame !== null) cancelAnimationFrame(frame);
        });
    });
}
