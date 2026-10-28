/**
 * Halaman bayar pesanan (account/orders/payment.blade.php).
 *
 * - pilih metode: total bayar ikut berubah sesuai biaya transaksi metode;
 * - salin nomor VA / kode bayar;
 * - QR dirender di browser dari qr_string (library di-import dinamis);
 * - kartu & PayLater: formulir Midtrans Snap disematkan di halaman (bukan popup);
 * - status dicek otomatis berkala, pindah ke detail pesanan begitu lunas.
 */
export default function paymentPage({ statusUrl = null, amounts = {}, labels = {}, selected = null } = {}) {
    return {
        method: selected,
        amounts,
        labels,
        changing: false,
        waiting: false,
        timer: null,

        init() {
            if (statusUrl) {
                this.timer = setInterval(() => this.poll(), 8000);
                document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.poll());
            }
        },

        destroy() {
            clearInterval(this.timer);
        },

        get amount() {
            return this.amounts[this.method] ?? null;
        },

        async poll() {
            if (this.waiting || document.visibilityState !== 'visible') return;
            this.waiting = true;
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                if (data.redirect) {
                    clearInterval(this.timer);
                    window.location.href = data.redirect;
                }
            } catch {
                // Offline sebentar: coba lagi di putaran berikutnya.
            } finally {
                this.waiting = false;
            }
        },

        async copy(text, label = 'Nomor') {
            try {
                await navigator.clipboard.writeText(text);
                window.toast?.(`${label} disalin.`, 'success');
            } catch {
                window.toast?.(`Salin manual: ${text}`, 'info');
            }
        },

        async renderQr(el, text) {
            const { default: QRCode } = await import('qrcode');
            el.innerHTML = await QRCode.toString(text, { type: 'svg', margin: 1, errorCorrectionLevel: 'M' });
            el.querySelector('svg')?.setAttribute('aria-hidden', 'true');
        },

        async downloadQr(text, filename) {
            const { default: QRCode } = await import('qrcode');
            const link = document.createElement('a');
            link.href = await QRCode.toDataURL(text, { width: 720, margin: 2 });
            link.download = filename;
            link.click();
        },

        /** Formulir Midtrans (kartu/PayLater) langsung di halaman. */
        embedSnap(token, fallbackUrl, checkForm) {
            const start = (tries = 0) => {
                if (!window.snap) {
                    if (tries > 40) {
                        window.location.href = fallbackUrl;
                        return;
                    }
                    setTimeout(() => start(tries + 1), 250);
                    return;
                }
                const check = () => document.getElementById(checkForm)?.submit();
                window.snap.embed(token, { embedId: 'snap-container', onSuccess: check, onPending: check });
            };
            start();
        },
    };
}
