@props(['eventsUrl'])
{{-- Panel pemantauan siswa (guru): daftar SEMUA siswa kelas + status + suara. --}}
<div class="space-y-3">
    <div class="card p-4 flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3 text-sm flex-wrap">
            <span class="flex items-center gap-1.5"><i data-lucide="users" class="w-4 h-4 text-slate-400"></i> Total: <b id="mon-total">0</b></span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Di dalam: <b id="mon-in" class="text-emerald-600">0</b></span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Keluar: <b id="mon-out" class="text-rose-600">0</b></span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span> Belum: <b id="mon-belum" class="text-slate-500">0</b></span>
            <span class="text-slate-400 text-xs flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> live · 3 dtk</span>
        </div>
        <button id="mon-sound" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
            <i data-lucide="volume-2" class="w-4 h-4"></i> <span>Aktifkan Suara</span>
        </button>
    </div>
    <div class="card overflow-hidden">
        <div id="mon-list" class="divide-y divide-slate-100 dark:divide-slate-700"><p class="p-6 text-center text-sm text-slate-400">Memuat daftar siswa…</p></div>
    </div>
</div>
<div id="mon-toast" class="fixed top-20 right-5 z-[9999] space-y-2"></div>

@push('scripts')
<script>
(function () {
    const url = @json($eventsUrl);
    const list = document.getElementById('mon-list');
    const cIn = document.getElementById('mon-in'), cOut = document.getElementById('mon-out'), cBelum = document.getElementById('mon-belum'), cTotal = document.getElementById('mon-total');
    const tabBadge = document.getElementById('mon-tab-out');
    const toastWrap = document.getElementById('mon-toast');
    const soundBtn = document.getElementById('mon-sound');
    const esc = (s) => (s || '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
    let soundOn = false, audioCtx = null, firstLoad = true;
    const seen = new Set();

    function beep(freqs) {
        if (!soundOn || !audioCtx) return; let t = audioCtx.currentTime;
        (freqs || [880, 660]).forEach((f) => { const o = audioCtx.createOscillator(), g = audioCtx.createGain(); o.type = 'sine'; o.frequency.value = f; o.connect(g); g.connect(audioCtx.destination); g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.35, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.26); o.start(t); o.stop(t + 0.28); t += 0.3; });
    }
    soundBtn?.addEventListener('click', () => {
        soundOn = !soundOn;
        if (soundOn) {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            beep([1046, 784]);
            soundBtn.innerHTML = '<i data-lucide="volume-2" class="w-4 h-4"></i> <span>Suara Aktif</span>';
            soundBtn.classList.add('border-emerald-300', 'text-emerald-700', 'bg-emerald-50');
        } else {
            soundBtn.innerHTML = '<i data-lucide="volume-x" class="w-4 h-4"></i> <span>Aktifkan Suara</span>';
            soundBtn.classList.remove('border-emerald-300', 'text-emerald-700', 'bg-emerald-50');
        }
        if (window.lucide) lucide.createIcons();
    });
    function toast(text) { const t = document.createElement('div'); t.className = 'px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold'; t.style.background = '#e11d48'; t.innerHTML = '🔔 ' + esc(text); toastWrap.appendChild(t); setTimeout(() => t.remove(), 7000); }
    function dot(s) { return s === 'keluar' ? 'bg-rose-500' : (s === 'masuk' ? 'bg-emerald-500' : 'bg-slate-300'); }
    function badge(p) {
        if (p.status === 'keluar') return `<span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 font-semibold">Keluar — ${esc(p.reason||'')} · ${esc(p.waktu)}</span>`;
        if (p.status === 'masuk') return `<span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-semibold">Di dalam</span>`;
        return `<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">Belum membuka</span>`;
    }
    async function poll() {
        if (document.hidden) return;
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } }); if (!r.ok) return;
            const d = await r.json();
            cIn.textContent = d.di_dalam; cOut.textContent = d.keluar; if (cBelum) cBelum.textContent = d.belum; if (cTotal) cTotal.textContent = d.total;
            if (tabBadge) { if (d.keluar > 0) { tabBadge.textContent = d.keluar; tabBadge.classList.remove('hidden'); } else tabBadge.classList.add('hidden'); }
            list.innerHTML = d.peserta.length
                ? d.peserta.map((p, i) => `<div class="flex items-center justify-between gap-3 px-4 py-2.5"><div class="flex items-center gap-2.5 min-w-0"><span class="text-xs text-slate-400 w-5 text-right">${i+1}</span><span class="w-2.5 h-2.5 rounded-full flex-shrink-0 ${dot(p.status)}"></span><span class="font-medium text-slate-700 dark:text-slate-200 truncate">${esc(p.nama)}</span></div>${badge(p)}</div>`).join('')
                : '<p class="p-6 text-center text-sm text-slate-400">Belum ada anggota siswa di kelas ini.</p>';
            (d.keluar_baru || []).forEach(x => { if (seen.has(x.id)) return; seen.add(x.id); if (firstLoad) return; toast((x.nama || 'Siswa') + ' KELUAR — ' + (x.reason || '')); beep([880, 660, 880]); });
            firstLoad = false; if (window.lucide) lucide.createIcons();
        } catch (e) {}
    }
    poll(); setInterval(poll, 3000);
})();
</script>
@endpush
