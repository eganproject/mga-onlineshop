/**
 * Login pelanggan dengan Google lewat Firebase Authentication.
 * SDK Firebase di-import dinamis (hanya dimuat saat tombol diklik). ID token
 * dikirim ke server lewat form biasa, lalu server memverifikasi & membuat sesi
 * Laravel. Sesi Firebase di browser langsung dikeluarkan karena tidak dipakai.
 */
const MESSAGES = {
    'auth/popup-blocked': 'Popup diblokir browser. Izinkan popup untuk situs ini lalu coba lagi.',
    'auth/network-request-failed': 'Koneksi bermasalah. Periksa internet kamu lalu coba lagi.',
    'auth/unauthorized-domain': 'Domain ini belum diizinkan di Firebase (Authorized domains).',
    'auth/operation-not-allowed': 'Login Google belum diaktifkan di Firebase.',
};

const IGNORED = ['auth/popup-closed-by-user', 'auth/cancelled-popup-request', 'auth/user-cancelled'];

export default (config) => ({
    busy: false,
    error: null,

    async signIn() {
        if (this.busy) return;
        this.busy = true;
        this.error = null;

        try {
            const [{ initializeApp, getApps }, { getAuth, GoogleAuthProvider, signInWithPopup, signOut }] =
                await Promise.all([import('firebase/app'), import('firebase/auth')]);

            const app = getApps()[0] ?? initializeApp(config);
            const auth = getAuth(app);
            auth.languageCode = 'id';

            const provider = new GoogleAuthProvider();
            provider.setCustomParameters({ prompt: 'select_account' });

            const result = await signInWithPopup(auth, provider);
            this.$refs.token.value = await result.user.getIdToken();
            await signOut(auth).catch(() => {});

            this.$refs.form.submit();
        } catch (e) {
            this.busy = false;
            if (IGNORED.includes(e?.code)) return;
            console.error(e);
            this.error = MESSAGES[e?.code] ?? 'Gagal masuk dengan Google. Silakan coba lagi.';
        }
    },
});
