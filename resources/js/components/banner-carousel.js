/**
 * Slider banner promo di beranda: geser (swipe) di HP, tombol & titik
 * navigasi, dan putar otomatis yang berhenti saat disentuh/di-hover.
 */
export default ({ count = 1, interval = 6000 }) => ({
    index: 0,
    count,
    timer: null,

    init() {
        this.play();
    },

    play() {
        this.stop();
        if (this.count < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        this.timer = setInterval(() => {
            if (!document.hidden) this.go(this.index + 1);
        }, interval);
    },

    stop() {
        clearInterval(this.timer);
        this.timer = null;
    },

    go(i) {
        const track = this.$refs.track;
        this.index = (i + this.count) % this.count;
        track.scrollTo({ left: this.index * track.clientWidth, behavior: 'smooth' });
    },

    onScroll() {
        const track = this.$refs.track;
        this.index = Math.min(this.count - 1, Math.round(track.scrollLeft / Math.max(1, track.clientWidth)));
    },
});
