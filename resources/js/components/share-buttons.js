/** Tombol bagikan artikel: Web Share API di HP, salin link sebagai cadangan. */
export default function shareButtons({ url, title }) {
    return {
        copied: false,
        canShare: typeof navigator !== 'undefined' && !!navigator.share,

        async share() {
            try {
                await navigator.share({ title, url });
            } catch {
                // Dibatalkan pengguna.
            }
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(url);
            } catch {
                window.prompt('Salin link ini:', url);
                return;
            }
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        },
    };
}
