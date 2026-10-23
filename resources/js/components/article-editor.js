/**
 * Editor isi artikel (Tiptap). Library dimuat terpisah supaya halaman lain
 * tidak ikut mengunduhnya. HTML hasil editor disimpan ke <input name="body">
 * dan disaring lagi di server (App\Services\Content\ArticleSanitizer).
 *
 * Gambar: tombol gambar, tempel (Ctrl+V), atau seret file ke editor,
 * diunggah ke uploadUrl lalu disisipkan sebagai <img>.
 *
 * Catatan: instance editor sengaja tidak disimpan di data Alpine (proxy
 * reaktif Alpine merusak state ProseMirror).
 */
let tiptap;

const loadTiptap = () =>
    (tiptap ??= Promise.all([
        import('@tiptap/core'),
        import('@tiptap/starter-kit'),
        import('@tiptap/extension-image'),
        import('@tiptap/extension-placeholder'),
    ]).then(([{ Editor }, { default: StarterKit }, { default: Image }, { default: Placeholder }]) => ({
        Editor,
        StarterKit,
        Image,
        Placeholder,
    })));

const MAX_KB = 4096;

export default function articleEditor({ content = '', uploadUrl, placeholder = 'Mulai menulis...' }) {
    let editor = null;

    return {
        html: content,
        ready: false,
        uploading: 0,
        error: '',
        words: 0,
        active: {},

        async init() {
            const { Editor, StarterKit, Image, Placeholder } = await loadTiptap();

            editor = new Editor({
                element: this.$refs.editor,
                content: this.html,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3] },
                        code: false,
                        codeBlock: false,
                        link: {
                            openOnClick: false,
                            autolink: true,
                            defaultProtocol: 'https',
                            HTMLAttributes: { target: null, rel: null },
                        },
                    }),
                    Image.configure({ HTMLAttributes: { loading: 'lazy' } }),
                    Placeholder.configure({ placeholder }),
                ],
                editorProps: {
                    attributes: {
                        class: 'article-prose min-h-[420px] px-5 py-4 focus:outline-none sm:px-8 sm:py-6',
                        'aria-label': 'Isi artikel',
                    },
                    handlePaste: (view, event) => this.handleFiles(event.clipboardData?.files),
                    handleDrop: (view, event, slice, moved) => !moved && this.handleFiles(event.dataTransfer?.files),
                },
                onCreate: () => this.sync(),
                onUpdate: () => this.sync(),
                onSelectionUpdate: () => this.refresh(),
            });

            this.ready = true;
        },

        destroy() {
            editor?.destroy();
        },

        sync() {
            this.html = editor.isEmpty ? '' : editor.getHTML();
            this.words = editor.getText().trim().split(/\s+/).filter(Boolean).length;
            this.refresh();
        },

        refresh() {
            this.active = {
                bold: editor.isActive('bold'),
                italic: editor.isActive('italic'),
                underline: editor.isActive('underline'),
                strike: editor.isActive('strike'),
                h2: editor.isActive('heading', { level: 2 }),
                h3: editor.isActive('heading', { level: 3 }),
                bulletList: editor.isActive('bulletList'),
                orderedList: editor.isActive('orderedList'),
                blockquote: editor.isActive('blockquote'),
                link: editor.isActive('link'),
                canUndo: editor.can().undo(),
                canRedo: editor.can().redo(),
            };
        },

        run(command, ...args) {
            if (!editor) return;
            const chain = editor.chain().focus();
            chain[command](...args).run();
            this.refresh();
        },

        setLink() {
            const previous = editor.getAttributes('link').href ?? '';
            const url = window.prompt('Alamat link (https://... atau /produk/...)', previous);
            if (url === null) return;
            if (url.trim() === '') {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
            } else {
                editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
            }
            this.refresh();
        },

        pickImage() {
            this.$refs.file.click();
        },

        handleFiles(files) {
            const images = Array.from(files ?? []).filter((file) => file.type.startsWith('image/'));
            if (!images.length) return false;
            images.forEach((file) => this.upload(file));
            return true;
        },

        async upload(file) {
            this.error = '';
            if (file.size > MAX_KB * 1024) {
                this.error = `${file.name} lebih dari 4 MB.`;
                return;
            }

            this.uploading++;
            const body = new FormData();
            body.append('image', file);

            try {
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body,
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.errors?.image?.[0] ?? data.message ?? 'Gagal mengunggah gambar.');

                const alt = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
                editor.chain().focus().setImage({ src: data.url, alt }).run();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.uploading--;
                this.$refs.file.value = '';
            }
        },
    };
}
