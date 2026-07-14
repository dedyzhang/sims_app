<script>
window.arenaFullscreenMixin = function () {
    return {
        isFullscreen: false,
        _cssFs: false,
        _fsListeners: null,
        initFs() {
            const sync = () => {
                const native = !!(document.fullscreenElement || document.webkitFullscreenElement);
                this.isFullscreen = native || this._cssFs;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            };
            const onKey = (e) => {
                if (e.key === 'Escape' && this._cssFs) this.exitCssFullscreen();
            };
            this._fsListeners = { sync, onKey };
            document.addEventListener('fullscreenchange', sync);
            document.addEventListener('webkitfullscreenchange', sync);
            document.addEventListener('keydown', onKey);
        },
        destroyFs() {
            if (this._fsListeners) {
                document.removeEventListener('fullscreenchange', this._fsListeners.sync);
                document.removeEventListener('webkitfullscreenchange', this._fsListeners.sync);
                document.removeEventListener('keydown', this._fsListeners.onKey);
                this._fsListeners = null;
            }
            this.exitCssFullscreen();
        },
        toggleFullscreen() {
            const el = this.$refs.fsRoot;
            if (!el) return;
            const active = document.fullscreenElement || document.webkitFullscreenElement;
            if (active || this._cssFs) {
                if (active) {
                    (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
                }
                this.exitCssFullscreen();
                return;
            }
            const req = el.requestFullscreen || el.webkitRequestFullscreen;
            if (req) {
                Promise.resolve(req.call(el)).catch(() => this.enterCssFullscreen(el));
            } else {
                this.enterCssFullscreen(el);
            }
        },
        enterCssFullscreen(el) {
            el.classList.add('arena-is-fullscreen');
            this._cssFs = true;
            this.isFullscreen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        exitCssFullscreen() {
            const el = this.$refs.fsRoot;
            el?.classList.remove('arena-is-fullscreen');
            this._cssFs = false;
            this.isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement);
            document.body.style.overflow = '';
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
    };
};
</script>
