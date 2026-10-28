/**
 * Modal konfirmasi & toast pengganti confirm()/alert() bawaan browser.
 *
 * Konfirmasi tanpa JS tambahan, cukup atribut di <form> atau tombol submit:
 *   <form data-confirm="Hapus produk ini?" data-confirm-title="Hapus produk"
 *         data-confirm-ok="Ya, hapus" data-confirm-tone="danger">
 * Form dengan @method('DELETE') otomatis bernada "danger".
 *
 * Dari JS/Alpine:  if (await window.confirmDialog({ message: '...' })) { ... }
 * Toast:           window.toast('Tersimpan', 'success' | 'error' | 'warning' | 'info')
 */
export default function dialogs(Alpine) {
    Alpine.store('confirm', {
        open: false,
        title: '',
        message: '',
        okLabel: 'Ya, lanjutkan',
        cancelLabel: 'Batal',
        tone: 'primary',
        resolver: null,

        ask(options = {}) {
            const opts = typeof options === 'string' ? { message: options } : options;
            // Konfirmasi sebelumnya yang masih terbuka dianggap batal.
            this.resolver?.(false);
            this.tone = opts.tone ?? 'primary';
            this.title = opts.title ?? (this.tone === 'danger' ? 'Hapus data?' : 'Lanjutkan?');
            this.message = opts.message ?? '';
            this.okLabel = opts.ok ?? (this.tone === 'danger' ? 'Ya, hapus' : 'Ya, lanjutkan');
            this.cancelLabel = opts.cancel ?? 'Batal';
            this.open = true;

            return new Promise((resolve) => {
                this.resolver = resolve;
            });
        },

        close(result) {
            this.open = false;
            const resolve = this.resolver;
            this.resolver = null;
            resolve?.(result);
        },
    });

    Alpine.store('toasts', {
        items: [],
        nextId: 1,

        push(message, type = 'success', timeout = 5000) {
            if (!message) return;
            const id = this.nextId++;
            this.items.push({ id, message, type, show: true });
            if (timeout) setTimeout(() => this.dismiss(id), timeout);
            return id;
        },

        dismiss(id) {
            const item = this.items.find((t) => t.id === id);
            if (!item) return;
            item.show = false;
            setTimeout(() => {
                this.items = this.items.filter((t) => t.id !== id);
            }, 250);
        },
    });

    window.confirmDialog = (options) => Alpine.store('confirm').ask(options);
    window.toast = (message, type, timeout) => Alpine.store('toasts').push(message, type, timeout);
    Alpine.magic('confirm', () => window.confirmDialog);
    Alpine.magic('toast', () => window.toast);

    // Tangkap submit form ber-data-confirm sebelum handler lain (capture phase).
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        const submitter = event.submitter;
        const source = submitter?.hasAttribute('data-confirm') ? submitter : form.hasAttribute('data-confirm') ? form : null;
        if (!source) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        // getAttribute: form.method tertimpa bila ada isian bernama "method".
        const method = String(form.querySelector('input[name="_method"]')?.value ?? form.getAttribute('method') ?? '').toUpperCase();
        const tone = source.dataset.confirmTone ?? (method === 'DELETE' ? 'danger' : 'primary');

        window.confirmDialog({
            message: source.dataset.confirm,
            title: source.dataset.confirmTitle,
            ok: source.dataset.confirmOk,
            tone,
        }).then((ok) => {
            if (!ok) return;
            form.dataset.confirmed = '1';
            // Kirim ulang dengan tombol yang sama (name/value-nya ikut terkirim).
            if (submitter && submitter.form === form) form.requestSubmit(submitter);
            else form.requestSubmit();
        });
    }, true);
}
