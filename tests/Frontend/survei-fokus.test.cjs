const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

const script = fs.readFileSync('public/js/survei-fokus.js', 'utf8');
const source = script.slice(script.indexOf('    const BATAS_LAYAR_PENUH_MS'), script.indexOf('    const sections ='));

// Jalankan potongan logika layar penuh dengan DOM & timer palsu.
function boot({ request, enabled = true }) {
    const timers = [];
    const document = {
        fullscreenEnabled: enabled,
        fullscreenElement: null,
        addEventListener() {},
        removeEventListener() {},
    };
    const root = {};
    const ctx = {
        document, root, Promise,
        pakaiSeb: false, pakaiKios: false,
        requestFullscreen: request,
        fullscreenActive: () => Boolean(document.fullscreenElement),
        setTimeout: (fn) => { timers.push(fn); return timers.length; },
        clearTimeout: () => {},
    };
    vm.runInNewContext(source + '\nthis.api = { mintaLayarPenuh, modeFokusAktif, layarPenuhDidukung };', ctx);
    return { ...ctx.api, document, root, timers };
}

test('permintaan layar penuh yang tidak pernah selesai berhenti oleh batas waktu (tidak macet)', async () => {
    const ctx = boot({ request: () => new Promise(() => {}) });
    const hasil = ctx.mintaLayarPenuh();
    assert.equal(ctx.timers.length, 1);
    ctx.timers[0]();
    assert.equal(await hasil, false);
});

test('permintaan ditolak atau melempar error langsung selesai dengan false', async () => {
    assert.equal(await boot({ request: () => Promise.reject(new Error('ditolak')) }).mintaLayarPenuh(), false);
    assert.equal(await boot({ request: () => { throw new TypeError('tidak didukung'); } }).mintaLayarPenuh(), false);
});

test('layar penuh yang berhasil terdeteksi tanpa menunggu batas waktu', async () => {
    const ctx = boot({ request() { ctx.document.fullscreenElement = ctx.root; return Promise.resolve(); } });
    assert.equal(await ctx.mintaLayarPenuh(), true);
});

test('perangkat tanpa dukungan layar penuh (iPhone/WebView) tidak memanggil API sama sekali', async () => {
    let dipanggil = false;
    const ctx = boot({ request: () => { dipanggil = true; }, enabled: false });
    assert.equal(ctx.layarPenuhDidukung, false);
    assert.equal(await ctx.mintaLayarPenuh(), false);
    assert.equal(dipanggil, false);
    assert.equal(ctx.timers.length, 0);
});

test('tombol mulai memakai mode fokus tanpa layar penuh saat layar penuh gagal', () => {
    assert.match(script, /const layarPenuh = modeFokusAktif\(\) \|\| await mintaLayarPenuh\(\);\s*start\.disabled = false;/);
    assert.match(script, /if \(!layarPenuh\) tanpaLayarPenuh = true;/);
});
