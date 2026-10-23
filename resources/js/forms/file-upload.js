/**
 * Unggah satu file non-gambar, mis. Excel/CSV (komponen <x-form.file>).
 * Cek ekstensi & ukuran di browser supaya admin tidak menunggu upload yang pasti ditolak.
 */
export default ({ extensions = [], maxKb = 10240, maxLabel = '' }) => ({
    fileName: '',
    fileSize: '',
    dragging: false,
    error: '',

    pick(file) {
        this.error = '';
        if (!file) return this.reset();
        const ext = file.name.split('.').pop().toLowerCase();
        if (extensions.length && !extensions.includes(ext)) {
            this.error = `Format file harus ${extensions.map((e) => '.' + e).join(' atau ')}.`;
            return this.reset(false);
        }
        if (file.size > maxKb * 1024) {
            this.error = `Ukuran file maksimal ${maxLabel}.`;
            return this.reset(false);
        }
        this.fileName = file.name;
        this.fileSize = file.size >= 1024 * 1024 ? `${(file.size / 1024 / 1024).toFixed(1)} MB` : `${Math.ceil(file.size / 1024)} KB`;
    },

    drop(event) {
        this.dragging = false;
        const files = event.dataTransfer?.files;
        if (!files?.length) return;
        this.$refs.file.files = files;
        this.pick(files[0]);
    },

    reset(clearError = true) {
        this.$refs.file.value = '';
        this.fileName = '';
        this.fileSize = '';
        if (clearError) this.error = '';
    },
});
