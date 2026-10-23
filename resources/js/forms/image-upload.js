/**
 * Pratinjau unggah satu gambar (komponen <x-form.image-upload>).
 */
export default ({ current = null, maxKb = 2048, maxLabel = '' }) => ({
    current,
    preview: current,
    fileName: '',
    fileSize: '',
    removed: false,
    dragging: false,
    error: '',

    pick(file) {
        this.error = '';
        if (!file) return this.reset();
        if (!file.type.startsWith('image/')) {
            this.error = 'File harus berupa gambar.';
            return this.reset(false);
        }
        if (file.size > maxKb * 1024) {
            this.error = `Ukuran gambar maksimal ${maxLabel}.`;
            return this.reset(false);
        }
        this.revoke();
        this.preview = URL.createObjectURL(file);
        this.fileName = file.name;
        this.fileSize = file.size >= 1024 * 1024 ? `${(file.size / 1024 / 1024).toFixed(1)} MB` : `${Math.ceil(file.size / 1024)} KB`;
        this.removed = false;
    },

    drop(event) {
        this.dragging = false;
        const files = event.dataTransfer?.files;
        if (!files?.length) return;
        this.$refs.file.files = files;
        this.pick(files[0]);
    },

    reset(clearError = true) {
        this.revoke();
        this.$refs.file.value = '';
        this.preview = this.current;
        this.fileName = '';
        this.fileSize = '';
        if (clearError) this.error = '';
    },

    revoke() {
        if (this.preview && this.preview !== this.current) URL.revokeObjectURL(this.preview);
    },

    destroy() {
        this.revoke();
    },
});
