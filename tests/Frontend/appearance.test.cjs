const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/sims-appearance.js', 'utf8');
function boot(owner, records = new Map(), enabled = true) {
    const classes = new Set();
    const layer = { style: {} };
    const body = { dataset: { appearanceOwner: owner, appearanceEnabled: enabled ? '1' : '0', uiTheme: 'windows11', savedUiTheme: 'windows11' }, style: { setProperty() {} }, classList: { toggle(k, v) { v ? classes.add(k) : classes.delete(k); } } };
    const indexedDB = { open() {
        const request = {};
        queueMicrotask(() => { request.result = { close() {}, transaction() {
            const tx = { objectStore() { return {
                get(k) { const r = { result: records.get(k) }; queueMicrotask(() => tx.oncomplete()); return r; },
                put(v, k) { records.set(k, v); const r = {}; queueMicrotask(() => tx.oncomplete()); return r; },
                delete(k) { records.delete(k); const r = {}; queueMicrotask(() => tx.oncomplete()); return r; }
            }; } }; return tx;
        } }; request.onsuccess(); }); return request;
    } };
    const window = { dispatchEvent() {} };
    vm.runInNewContext(source, { window, document: { body, querySelectorAll: () => [], getElementById: () => layer }, indexedDB, Blob, URL, Uint8Array, CustomEvent, sessionStorage: { getItem: () => null, setItem() {}, removeItem() {} } });
    return { api: window.SimsAppearance, studio: window.wallpaperStudio, body, layer, classes };
}
test('wallpaper storage and deletion are isolated by account', async () => {
    const records = new Map(); const a = boot('account-a', records); await a.api.ready;
    await a.api.storage('readwrite', { preset: 'aurora', shade: 20 });
    const b = boot('account-b', records); await b.api.ready;
    assert.equal(b.classes.has('has-wallpaper'), true);
    assert.equal(b.body.dataset.wallpaperSource, 'theme');
    assert.equal(await b.api.storage('readonly'), undefined);
    await b.api.storage('readwrite', { preset: 'garden' });
    await a.api.storage('readwrite', null);
    assert.equal(records.has('account-a'), false); assert.equal(records.get('account-b').preset, 'garden');
});
test('saved wallpaper loads after navigation; cancel restores it', async () => {
    const records = new Map([['a', { preset: 'aurora', shade: 20 }]]);
    const page = boot('a', records); await page.api.ready;
    assert.equal(page.classes.has('has-wallpaper'), true);
    const editor = page.studio(); await editor.init(); editor.choose('none');
    assert.equal(page.classes.has('has-wallpaper'), false);
    editor.cancel(); assert.equal(page.classes.has('has-wallpaper'), true); assert.equal(editor.dirty, false);
});
test('kiosk and unauthenticated surfaces do not load personal wallpaper', async () => {
    const records = new Map([['a', { preset: 'aurora' }]]);
    for (const page of [boot('a', records, false), boot('', records)]) {
        await page.api.ready; page.api.setTheme('bento'); page.api.applyWallpaper({preset:'aurora'});
        assert.equal(page.body.dataset.uiTheme, 'windows11'); assert.equal(page.classes.size, 0);
    }
});
test('reject invalid types, oversized photos and spoofed image bytes', async () => {
    const { api } = boot('a'); await api.ready;
    await assert.rejects(api.preparePhoto(new File(['<svg/>'], 'test.svg', {type:'image/svg+xml'})), /JPG/);
    await assert.rejects(api.preparePhoto({type:'image/png', size:11 * 1024 * 1024}), /10 MB/);
    await assert.rejects(api.preparePhoto(new File(['<svg/>'], 'fake.png', {type:'image/png'})), /Isi berkas/);
});
test('theme changes switch the bundled wallpaper; a personal photo remains an explicit override', async () => {
    const page = boot('a'); await page.api.ready;
    assert.match(page.layer.style.backgroundImage, /windows11.svg/);
    page.api.setTheme('macos'); assert.match(page.layer.style.backgroundImage, /macos.svg/);
    page.api.applyWallpaper({preset:'photo',blob:new Blob(['photo'],{type:'image/jpeg'})});
    page.api.setTheme('bento'); assert.match(page.layer.style.backgroundImage, /blob:/);
});
test('choosing no wallpaper persists while following theme restores its default', async () => {
    const records = new Map(); const page = boot('a', records); await page.api.ready;
    const editor = page.studio(); await editor.init(); editor.choose('none'); await editor.save();
    const reload = boot('a', records); await reload.api.ready;
    assert.equal(reload.classes.has('has-wallpaper'), false);
    const editor2 = reload.studio(); await editor2.init(); editor2.choose('theme'); await editor2.save();
    assert.equal(records.has('a'), false); assert.match(reload.layer.style.backgroundImage, /windows11.svg/);
});
test('changing photo settings reuses the decoded image URL until the photo changes', async () => {
    const page = boot('a'); await page.api.ready;
    const blob = new Blob(['photo'], {type:'image/jpeg'});
    page.api.applyWallpaper({preset:'photo',blob,shade:20});
    const first = page.layer.style.backgroundImage;
    page.api.applyWallpaper({preset:'photo',blob,shade:60,blur:3});
    assert.equal(page.layer.style.backgroundImage, first);
    page.api.applyWallpaper({preset:'photo',blob:new Blob(['second']),shade:60});
    assert.notEqual(page.layer.style.backgroundImage, first);
    page.api.applyWallpaper({preset:'none'});
    assert.equal(page.layer.style.backgroundImage, 'none');
});
