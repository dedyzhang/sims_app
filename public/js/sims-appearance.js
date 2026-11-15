/* Personal appearance: photos stay on this browser; no network upload. */
(() => {
    'use strict';
    const themes = ['windows11', 'macos', 'fluent', 'macos-minimal', 'bento'];
    const body = document.body;
    const owner = body.dataset.appearanceOwner;
    const enabled = body.dataset.appearanceEnabled === '1' && !!owner;
    const previewKey = `sims-theme-preview:${owner}`;
    let imageUrl = null;
    let imageBlob = null;
    let currentWallpaper = null;
    const defaults = { shade: 35, blur: 0, position: 'center', fit: 'cover', preset: 'theme' };
    const presets = {
        aurora: 'radial-gradient(ellipse at 15% 15%, #7dd3fc, transparent 55%), radial-gradient(ellipse at 85% 80%, #c4b5fd, transparent 60%), #dbeafe',
        garden: 'radial-gradient(ellipse at 10% 20%, #d9f99d, transparent 55%), radial-gradient(ellipse at 80% 85%, #5eead4, transparent 60%), #e7eee4',
        dusk: 'radial-gradient(ellipse at 15% 20%, #fed7aa, transparent 55%), radial-gradient(ellipse at 80% 85%, #c4b5fd, transparent 60%), #fce7f3'
    };
    function settings(value = {}) {
        value = value || {};
        const clamp = (v, min, max, fallback) => Number.isFinite(Number(v)) ? Math.max(min, Math.min(max, Number(v))) : fallback;
        return {
            shade: clamp(value.shade, 0, 85, 35), blur: clamp(value.blur, 0, 12, 0),
            position: ['center', 'top', 'bottom'].includes(value.position) ? value.position : 'center',
            fit: ['cover', 'contain'].includes(value.fit) ? value.fit : 'cover',
            preset: ['theme', 'none', 'photo', ...Object.keys(presets)].includes(value.preset) ? value.preset : 'theme'
        };
    }
    function setTheme(theme, preview = true) {
        if (!enabled || !themes.includes(theme)) return;
        body.dataset.uiTheme = theme;
        document.querySelectorAll('.edu-dashboard').forEach(root => {
            [...root.classList].filter(c => c.startsWith('dashboard-theme-')).forEach(c => root.classList.remove(c));
            root.classList.add(`dashboard-theme-${theme}`);
        });
        try { if (preview) sessionStorage.setItem(previewKey, theme); else sessionStorage.removeItem(previewKey); } catch (_) {}
        applyWallpaper(currentWallpaper);
        window.dispatchEvent(new CustomEvent('sims-theme-changed', { detail: theme }));
    }
    function applyWallpaper(record) {
        if (!enabled) return;
        const layer = document.getElementById('sims-wallpaper');
        if (!layer) return;
        currentWallpaper = record;
        const opts = settings(record);
        let background = presets[opts.preset] || 'none';
        if (opts.preset === 'theme') {
            const theme = themes.includes(body.dataset.uiTheme) ? body.dataset.uiTheme : 'windows11';
            background = `url("${body.dataset.wallpaperBase || '/images/themes'}/${theme}.svg")`;
        }
        body.dataset.wallpaperSource = opts.preset;
        if (opts.preset === 'photo' && record?.blob instanceof Blob) {
            if (!imageUrl || imageBlob !== record.blob) {
                if (imageUrl) URL.revokeObjectURL(imageUrl);
                imageUrl = URL.createObjectURL(record.blob);
                imageBlob = record.blob;
            }
            background = `url("${imageUrl}")`;
        } else if (imageUrl) {
            URL.revokeObjectURL(imageUrl);
            imageUrl = null;
            imageBlob = null;
        }
        if (layer.style.backgroundImage !== background) layer.style.backgroundImage = background;
        layer.style.backgroundPosition = opts.position;
        layer.style.backgroundSize = opts.fit;
        layer.style.filter = `blur(${opts.blur}px)`;
        body.style.setProperty('--wallpaper-shade', String((opts.preset === 'theme' ? 8 : opts.shade) / 100));
        body.classList.toggle('has-wallpaper', background !== 'none');
    }
    async function database() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('sims-personal-appearance', 1);
            request.onupgradeneeded = () => request.result.createObjectStore('wallpapers');
            request.onerror = () => reject(new Error('Penyimpanan browser tidak tersedia.'));
            request.onblocked = () => reject(new Error('Tutup tab SIMS lain lalu coba lagi.'));
            request.onsuccess = () => resolve(request.result);
        });
    }
    async function storage(mode, record) {
        if (!enabled) return null;
        const db = await database();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('wallpapers', mode);
            const store = tx.objectStore('wallpapers');
            const request = mode === 'readonly' ? store.get(owner) : record ? store.put(record, owner) : store.delete(owner);
            tx.oncomplete = () => { db.close(); resolve(request.result); };
            tx.onerror = tx.onabort = () => { db.close(); reject(new Error('Wallpaper tidak dapat disimpan. Periksa ruang penyimpanan browser.')); };
        });
    }
    async function preparePhoto(file) {
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) throw new Error('Pilih foto JPG, PNG, atau WebP.');
        if (file.size > 10 * 1024 * 1024) throw new Error('Ukuran foto maksimal 10 MB.');
        const bytes = new Uint8Array(await file.slice(0, 12).arrayBuffer());
        const png = [137,80,78,71,13,10,26,10].every((v, i) => bytes[i] === v);
        const jpeg = bytes[0] === 255 && bytes[1] === 216 && bytes[2] === 255;
        const webp = String.fromCharCode(...bytes.slice(0,4)) === 'RIFF' && String.fromCharCode(...bytes.slice(8,12)) === 'WEBP';
        if (!png && !jpeg && !webp) throw new Error('Isi berkas bukan foto yang didukung.');
        const url = URL.createObjectURL(file);
        try {
            const img = new Image(); img.src = url;
            await img.decode();
            if (img.naturalWidth * img.naturalHeight > 32000000) throw new Error('Resolusi foto terlalu besar. Gunakan foto di bawah 32 megapiksel.');
            const scale = Math.min(1, 2560 / Math.max(img.naturalWidth, img.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(img.naturalWidth * scale)); canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(img, 0, 0, canvas.width, canvas.height);
            return await new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Foto tidak dapat diproses.')), 'image/jpeg', .88));
        } catch (e) { throw new Error(e.message.includes('megapiksel') ? e.message : 'Foto tidak dapat dibaca. Pilih foto lain.'); }
        finally { URL.revokeObjectURL(url); }
    }
    const api = window.SimsAppearance = { setTheme, applyWallpaper, preparePhoto, settings, defaults, storage, savedWallpaper: null, loadError: '' };
    window.setDashboardTheme = setTheme;
    api.ready = (async () => {
        if (!enabled) return;
        let theme = body.dataset.uiTheme;
        try { theme = sessionStorage.getItem(previewKey) || theme; } catch (_) {}
        setTheme(theme, false);
        // Keep the preview for navigation in this tab until explicitly saved/cancelled.
        if (theme !== body.dataset.savedUiTheme) { try { sessionStorage.setItem(previewKey, theme); } catch (_) {} }
        try { api.savedWallpaper = await storage('readonly'); applyWallpaper(api.savedWallpaper); }
        catch (e) { api.loadError = e.message; }
    })();
    window.wallpaperStudio = () => ({
        ...defaults, blob: null, busy: false, dirty: false, message: '', error: '',
        async init() { await api.ready; this.restore(); this.error = api.loadError; },
        restore() { Object.assign(this, settings(api.savedWallpaper)); this.blob = api.savedWallpaper?.blob || null; this.dirty = false; this.message = ''; this.error = ''; },
        record() { return { ...settings(this), blob: this.preset === 'photo' ? this.blob : null }; },
        preview() { applyWallpaper(this.record()); this.dirty = true; this.message = ''; },
        async upload(event) {
            const file = event.target.files?.[0]; if (!file) return;
            this.busy = true; this.error = ''; this.message = '';
            try { this.blob = await preparePhoto(file); this.preset = 'photo'; this.preview(); }
            catch (e) { this.error = e.message; }
            finally { this.busy = false; event.target.value = ''; }
        },
        choose(preset) { this.preset = preset; this.preview(); },
        cancel() { this.restore(); applyWallpaper(api.savedWallpaper); },
        async save() {
            this.busy = true; this.error = ''; this.message = '';
            try { const record = this.record(); await storage('readwrite', record.preset === 'theme' ? null : record); api.savedWallpaper = record; this.dirty = false; this.message = 'Wallpaper tersimpan di browser ini.'; }
            catch (e) { this.error = e.message; }
            finally { this.busy = false; }
        }
    });
})();
