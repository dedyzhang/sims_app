const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const layout = fs.readFileSync('resources/views/layouts/app.blade.php', 'utf8');
const classroomComments = fs.readFileSync('resources/views/classroom/partials/comments.blade.php', 'utf8');
const chatbotWidget = fs.readFileSync('resources/views/chatbot/index.blade.php', 'utf8');
const chatbotAdminInbox = fs.readFileSync('resources/views/chatbot/admin/inbox.blade.php', 'utf8');
const source = layout.slice(layout.indexOf('        window.simsPollTimers ='), layout.indexOf('    </script>', layout.indexOf('        window.simsPollTimers =')));
function boot() {
    let now = 0; const timers = []; const listeners = [];
    const document = { hidden: false, addEventListener: (_, fn) => listeners.push(fn) };
    const window = { simsPollingNonaktif: () => false };
    vm.runInNewContext(source, { window, document, Date: { now: () => now }, setInterval: fn => { timers.push(fn); return timers.length; } });
    return { window, document, timers, listeners, time: n => now = n };
}
test('ticker registers once, obeys cadence and never overlaps a pending request', async () => {
    const ctx = boot(); let count = 0; let finish;
    const task = () => { count++; return new Promise(resolve => finish = resolve); };
    const id = ctx.window.simsPollInterval(task, 60000, 'ticker', 'footer-ticker');
    assert.equal(ctx.window.simsPollInterval(task, 60000, 'ticker', 'footer-ticker'), id);
    assert.equal(ctx.timers.length, 1);
    ctx.time(60000); const pending = ctx.timers[0]();
    ctx.time(120000); await ctx.listeners[0](); assert.equal(count, 1);
    finish(); await pending;
    const next = ctx.timers[0](); assert.equal(count, 2); finish(); await next;
    await ctx.listeners[0](); assert.equal(count, 2);
});
test('timer jitter of a few ms does not skip a legitimate tick, but a visibility burst still dedupes', async () => {
    const ctx = boot(); let count = 0;
    ctx.window.simsPollInterval(() => count++, 60000, 'ticker', 'footer-ticker');
    ctx.time(60005); await ctx.timers[0](); assert.equal(count, 1);
    ctx.time(120001); await ctx.timers[0](); assert.equal(count, 2);
    ctx.time(125000); await ctx.listeners[0](); assert.equal(count, 2);
});
test('hidden tabs and disabled widgets do not send polling requests', async () => {
    const ctx = boot(); let count = 0;
    ctx.window.simsPollInterval(() => count++, 60000, 'ticker', 'footer-ticker');
    ctx.time(60000); ctx.document.hidden = true; await ctx.timers[0](); assert.equal(count, 0);
    ctx.document.hidden = false; ctx.window.simsPollingNonaktif = () => true;
    await ctx.listeners[0](); assert.equal(count, 0);
    ctx.window.simsPollingNonaktif = () => false; await ctx.listeners[0](); assert.equal(count, 1);
});

test('classroom comments use one keyed fallback and never create an unmanaged interval', () => {
    assert.doesNotMatch(classroomComments, /setInterval\s*\(/);
    assert.match(classroomComments, /if \(!window\.simsPollingNonaktif\('komentar_kelas'\)\)/);
    assert.match(classroomComments, /simsPollInterval\([\s\S]*?'komentar_kelas',[\s\S]*?'classroom-comments-/);
});

test('chatbot polling skips a second request while the previous response is pending', () => {
    assert.match(chatbotWidget, /if \(document\.visibilityState !== 'visible' \|\| pollInFlight\) return;/);
    assert.match(chatbotWidget, /pollInFlight = true;[\s\S]*?finally \{ pollInFlight = false; \}/);
});

test('admin inbox keeps one Firebase listener or one keyed polling fallback', () => {
    assert.match(chatbotAdminInbox, /if \(firebaseListenerStarted\) return;/);
    assert.match(chatbotAdminInbox, /simsPollInterval\(tick, pollEvery, 'chatbot_admin_inbox', 'chatbot-admin-inbox'\)/);
    assert.match(chatbotAdminInbox, /queueLoadInFlight/);
    assert.match(chatbotAdminInbox, /messageLoadsInFlight\.has\(conversationId\)/);
    assert.doesNotMatch(chatbotAdminInbox, /setInterval\s*\(/);
});

test('footer ticker stops its keyed timer and hides itself once the server reports Mode Ujian', () => {
    const block = layout.slice(layout.indexOf('const updateTickerStats'), layout.indexOf("window.simsPollInterval(updateTickerStats"));
    assert.match(block, /if \(data\.mode_ujian\) \{[\s\S]*?clearInterval\(window\.simsPollTimers\.get\('footer-ticker'\)\);[\s\S]*?window\.simsPollTimers\.delete\('footer-ticker'\);[\s\S]*?style\.display = 'none';[\s\S]*?return;/);
});
