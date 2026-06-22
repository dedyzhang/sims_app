@props(['exitUrl', 'backUrl'])
{{-- Mode layar penuh terkunci. Slot = isi (materi/soal+jawaban). Fullscreen pada
     kontainer ini saja → sidebar tidak ikut tampil. Ada tombol keluar manual. --}}
<div id="kiosk-content" class="rounded-2xl overflow-hidden border border-amber-300 dark:border-amber-700">
    <div class="kiosk-bar flex items-center justify-between gap-2 px-4 py-2.5 bg-slate-800 text-white text-sm">
        <span class="flex items-center gap-2 font-semibold"><i data-lucide="lock" class="w-4 h-4 text-amber-400"></i> Mode Terkunci — jangan berpindah tab</span>
        <button id="kiosk-exit" type="button" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white/15 hover:bg-white/25"><i data-lucide="minimize" class="w-3.5 h-3.5"></i> Keluar dari Layar Penuh</button>
    </div>
    <div class="kiosk-inner bg-white dark:bg-slate-800 p-5">{{ $slot }}</div>
</div>

{{-- Overlay awal (sebelum masuk layar penuh) --}}
<div id="kiosk-overlay" class="fixed inset-0 z-[9998] bg-slate-900/95 flex items-center justify-center p-6 text-center">
    <div class="max-w-md">
        <i data-lucide="shield-alert" class="w-14 h-14 text-amber-400 mx-auto mb-4"></i>
        <h2 class="text-white text-xl font-bold">Mode Terkunci</h2>
        <p class="text-slate-300 text-sm mt-2 mb-6">Klik <b>Masuk Layar Penuh</b> untuk mulai. Jangan berpindah tab / keluar layar penuh tanpa tombol — akses tertutup otomatis &amp; guru menerima notifikasi.</p>
        <div class="flex items-center justify-center gap-2">
            <button id="kiosk-start" class="px-6 py-3 rounded-xl text-sm font-bold text-white" style="background:var(--cp)"><i data-lucide="maximize" class="w-4 h-4 inline"></i> Masuk Layar Penuh</button>
            <button id="kiosk-leave" class="px-5 py-3 rounded-xl text-sm font-semibold text-slate-200 border border-slate-600 hover:bg-slate-800">Keluar</button>
        </div>
    </div>
</div>
<div id="kiosk-kicked" class="fixed inset-0 z-[9999] bg-slate-900/97 items-center justify-center p-6 text-center" style="display:none">
    <div class="max-w-md">
        <i data-lucide="lock" class="w-14 h-14 text-rose-400 mx-auto mb-4"></i>
        <h2 class="text-white text-xl font-bold">Akses Ditutup</h2>
        <p class="text-slate-300 text-sm mt-2">Sesi terkunci berakhir. Mengarahkan kembali…</p>
    </div>
</div>

@push('styles')
<style>
    #kiosk-content:fullscreen { background:#f1f5f9; overflow:auto; border:0; border-radius:0; }
    .dark #kiosk-content:fullscreen { background:#0f172a; }
    #kiosk-content:fullscreen .kiosk-bar { position:sticky; top:0; }
    #kiosk-content:fullscreen .kiosk-inner { max-width:820px; margin:0 auto; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const exitUrl = @json($exitUrl);
    const backUrl = @json($backUrl);
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const content = document.getElementById('kiosk-content');
    const overlay = document.getElementById('kiosk-overlay');
    const kicked = document.getElementById('kiosk-kicked');
    let started = false, exited = false, intentional = false;

    function beep() {
        try {
            const ac = new (window.AudioContext || window.webkitAudioContext)(); let t = ac.currentTime;
            [660, 440, 660].forEach((f) => { const o = ac.createOscillator(), g = ac.createGain(); o.type = 'square'; o.frequency.value = f; o.connect(g); g.connect(ac.destination); g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.3, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.2); o.start(t); o.stop(t + 0.22); t += 0.24; });
        } catch (e) {}
    }
    function report(reason, silent) {
        if (exited) return; exited = true;
        if (!silent) beep();
        try { fetch(exitUrl, { method: 'POST', keepalive: true, headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' }, body: JSON.stringify({ reason }) }); } catch (e) {}
        kicked.style.display = 'flex';
        setTimeout(() => { window.location.href = backUrl; }, 1500);
    }
    function enterFs() {
        const p = content.requestFullscreen ? content.requestFullscreen() : Promise.resolve();
        Promise.resolve(p).finally(() => { started = true; overlay.style.display = 'none'; });
    }
    document.getElementById('kiosk-start')?.addEventListener('click', enterFs);
    document.getElementById('kiosk-leave')?.addEventListener('click', () => report('keluar', true));
    document.getElementById('kiosk-exit')?.addEventListener('click', () => { intentional = true; if (document.fullscreenElement) document.exitFullscreen(); report('keluar via tombol', true); });
    // Submit form di dalam (mis. kumpul tugas) = navigasi sah, bukan pelanggaran.
    content.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => { intentional = true; }));
    document.addEventListener('fullscreenchange', () => { if (started && !document.fullscreenElement && !intentional) report('keluar layar penuh'); });
    document.addEventListener('visibilitychange', () => { if (started && document.hidden && !intentional) report('pindah tab/aplikasi'); });
})();
</script>
@endpush
