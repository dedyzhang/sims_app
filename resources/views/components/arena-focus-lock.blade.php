@props([
    'exitUrl',
    'context' => 'solo', // solo|live|template
    'attemptId' => null,
    'sessionId' => null,
    'enabled' => true,
])
@php
    // Mode fokus WEB aktif. Update APK / FASE 12 native DITAHAN — bridge Android opsional
    // (typeof-check); tanpa method native web tetap pakai Fullscreen API + CSS fallback.
    $holdArenaFocusLock = false;
@endphp
{{--
  Mode fokus Arena: fullscreen / immersive wajib + pantau tab/app switch.
  Browser: Fullscreen API (+ CSS fallback).
  Android WebView (opsional, APK ditahan): bridge AndroidFcm.enterArenaFocus/exitArenaFocus.
  Keluar = log + overlay kembali (tidak kick).
--}}
@if(! $holdArenaFocusLock && $enabled)
<div id="arena-focus-root" class="contents"
     data-exit-url="{{ $exitUrl }}"
     data-context="{{ $context }}"
     data-attempt-id="{{ $attemptId }}"
     data-session-id="{{ $sessionId }}">
    {{ $slot }}
</div>

<div id="arena-focus-gate" class="fixed inset-0 z-[9998] bg-slate-900/95 flex items-center justify-center p-6 text-center">
    <div class="max-w-md space-y-4">
        <i data-lucide="shield-alert" class="w-14 h-14 text-amber-400 mx-auto"></i>
        <h2 class="text-white text-xl font-black m-0" style="font-family:'Fredoka',sans-serif">Mode Fokus Arena</h2>
        <p class="text-slate-300 text-sm m-0 leading-relaxed" id="arena-focus-gate-copy">
            Ketuk <b>Mulai layar penuh</b> untuk bermain.
            Jangan pindah tab / keluar layar penuh — guru akan melihat peringatan keluar sesi.
        </p>
        <button id="arena-focus-start" type="button"
                class="px-6 py-3 rounded-xl text-sm font-bold text-white inline-flex items-center justify-center gap-2 min-h-[48px]"
                style="background:var(--cp,#0d9488)">
            <i data-lucide="maximize" class="w-4 h-4"></i> <span id="arena-focus-start-label">Mulai layar penuh</span>
        </button>
    </div>
</div>

<div id="arena-focus-return" class="fixed inset-0 z-[9999] bg-slate-900/97 items-center justify-center p-6 text-center" style="display:none">
    <div class="max-w-md space-y-4">
        <i data-lucide="eye-off" class="w-14 h-14 text-rose-400 mx-auto"></i>
        <h2 class="text-white text-xl font-black m-0">Anda meninggalkan sesi</h2>
        <p class="text-slate-300 text-sm m-0 leading-relaxed" id="arena-focus-return-copy">
            Perpindahan tab / keluar layar penuh tercatat.
            Kembali ke layar penuh untuk melanjutkan.
        </p>
        <button id="arena-focus-resume" type="button"
                class="px-6 py-3 rounded-xl text-sm font-bold text-white inline-flex items-center justify-center gap-2 min-h-[48px]"
                style="background:var(--cp,#0d9488)">
            <i data-lucide="maximize" class="w-4 h-4"></i> <span id="arena-focus-resume-label">Kembali ke layar penuh</span>
        </button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const root = document.getElementById('arena-focus-root');
    if (!root) return;

    const exitUrl = root.dataset.exitUrl;
    const context = root.dataset.context || 'solo';
    const attemptId = root.dataset.attemptId || '';
    const sessionId = root.dataset.sessionId || '';
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    const gate = document.getElementById('arena-focus-gate');
    const ret = document.getElementById('arena-focus-return');
    const fsTarget = document.querySelector('[data-arena-focus-target]') || root.parentElement || document.documentElement;

    const androidBridge = () => window.AndroidFcm || window.AndroidArena || null;
    const isAndroidWebView = () => {
        if (androidBridge()) return true;
        // Hanya WebView Android (; wv)) — jangan samakan dengan Chrome biasa di HP.
        return /; wv\)/i.test(navigator.userAgent || '');
    };
    const onAndroid = isAndroidWebView();

    if (onAndroid) {
        const gateCopy = document.getElementById('arena-focus-gate-copy');
        const returnCopy = document.getElementById('arena-focus-return-copy');
        const startLabel = document.getElementById('arena-focus-start-label');
        const resumeLabel = document.getElementById('arena-focus-resume-label');
        if (gateCopy) {
            gateCopy.innerHTML = 'Ketuk <b>Mulai mode fokus</b> untuk bermain. Jangan pindah aplikasi / tekan Home — guru akan melihat peringatan keluar sesi.';
        }
        if (returnCopy) {
            returnCopy.textContent = 'Perpindahan aplikasi / keluar fokus tercatat. Kembali ke mode fokus untuk melanjutkan.';
        }
        if (startLabel) startLabel.textContent = 'Mulai mode fokus';
        if (resumeLabel) resumeLabel.textContent = 'Kembali ke mode fokus';
    }

    let started = false;
    let intentional = false;
    let lastReport = 0;
    let cssImmersive = false;

    function setActiveFlag(on) {
        window.__arenaFocusActive = !!on;
        document.documentElement.dataset.arenaFocus = on ? '1' : '0';
        try {
            const b = androidBridge();
            if (b && typeof b.setArenaFocusActive === 'function') {
                b.setArenaFocusActive(!!on);
            }
        } catch (e) {}
    }

    function callNativeEnter() {
        try {
            const b = androidBridge();
            if (b && typeof b.enterArenaFocus === 'function') b.enterArenaFocus();
        } catch (e) {}
    }

    function callNativeExit() {
        try {
            const b = androidBridge();
            if (b && typeof b.exitArenaFocus === 'function') b.exitArenaFocus();
        } catch (e) {}
    }

    function uuidOrNull(v) {
        v = String(v || '').trim();
        return v || null;
    }

    function beep() {
        try {
            const ac = new (window.AudioContext || window.webkitAudioContext)();
            let t = ac.currentTime;
            [720, 480].forEach((f) => {
                const o = ac.createOscillator(), g = ac.createGain();
                o.type = 'square'; o.frequency.value = f; o.connect(g); g.connect(ac.destination);
                g.gain.setValueAtTime(0.0001, t);
                g.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + 0.18);
                o.start(t); o.stop(t + 0.2); t += 0.2;
            });
        } catch (e) {}
    }

    function report(reason) {
        const now = Date.now();
        if (now - lastReport < 1800) return;
        lastReport = now;
        beep();
        try {
            fetch(exitUrl, {
                method: 'POST',
                keepalive: true,
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    context,
                    reason: String(reason || 'keluar fokus').slice(0, 100),
                    attempt_id: uuidOrNull(root.dataset.attemptId || attemptId),
                    session_id: uuidOrNull(root.dataset.sessionId || sessionId),
                }),
            });
        } catch (e) {}
        if (ret) ret.style.display = 'flex';
    }

    function enterCssImmersive(el) {
        el.classList?.add?.('arena-is-fullscreen');
        document.body.classList.add('arena-focus-active');
        cssImmersive = true;
        callNativeEnter();
    }

    function leaveCssImmersive() {
        fsTarget.classList?.remove?.('arena-is-fullscreen');
        document.body.classList.remove('arena-focus-active');
        cssImmersive = false;
        callNativeExit();
        setActiveFlag(false);
    }

    function markDone() {
        started = true;
        intentional = false;
        if (gate) gate.style.display = 'none';
        if (ret) ret.style.display = 'none';
        document.body.classList.add('arena-focus-active');
        setActiveFlag(true);
        window.lucide && lucide.createIcons();
    }

    function enterFs() {
        intentional = false;
        const el = fsTarget;
        const req = el.requestFullscreen || el.webkitRequestFullscreen;

        // Android WebView: Fullscreen API sering gagal → langsung CSS + native immersive.
        if (onAndroid || !req) {
            enterCssImmersive(el);
            markDone();
            return;
        }

        Promise.resolve(req.call(el)).then(() => {
            cssImmersive = false;
            callNativeEnter();
            markDone();
        }).catch(() => {
            enterCssImmersive(el);
            markDone();
        });
    }

    function onLeaveSignal(reason) {
        if (!started || intentional) return;
        const inFs = !!(document.fullscreenElement || document.webkitFullscreenElement);
        const cssFs = fsTarget.classList?.contains?.('arena-is-fullscreen');
        if (reason === 'fullscreen' && (inFs || cssFs)) return;
        report(reason);
    }

    function markSafe() {
        intentional = true;
        setActiveFlag(false);
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            try {
                (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
            } catch (e) {}
        }
        leaveCssImmersive();
    }

    document.getElementById('arena-focus-start')?.addEventListener('click', enterFs);
    document.getElementById('arena-focus-resume')?.addEventListener('click', enterFs);

    document.addEventListener('fullscreenchange', () => {
        if (started && !document.fullscreenElement && !document.webkitFullscreenElement && !intentional && !cssImmersive) {
            onLeaveSignal('keluar layar penuh');
        }
    });
    document.addEventListener('webkitfullscreenchange', () => {
        if (started && !document.fullscreenElement && !document.webkitFullscreenElement && !intentional && !cssImmersive) {
            onLeaveSignal('keluar layar penuh');
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (started && document.hidden && !intentional) {
            onLeaveSignal(onAndroid ? 'pindah aplikasi' : 'pindah tab/aplikasi');
        }
    });
    // Android WebView kadang pakai pagehide saat app di-background.
    window.addEventListener('pagehide', () => {
        if (started && !intentional) onLeaveSignal(onAndroid ? 'pindah aplikasi' : 'pindah halaman');
    });

    document.querySelectorAll('[data-arena-focus-safe]').forEach((el) => {
        el.addEventListener('click', markSafe);
        el.addEventListener('submit', markSafe);
    });
    document.querySelectorAll('form[data-arena-focus-safe]').forEach((f) => {
        f.addEventListener('submit', markSafe);
    });

    window.arenaFocusMarkSafe = markSafe;
    /** Dipanggil native Android saat tombol Back ditekan selama mode fokus aktif. */
    window.arenaFocusAndroidBack = function () {
        if (!started || intentional) return false;
        onLeaveSignal('tombol back Android');
        if (ret) ret.style.display = 'flex';
        return true; // native: jangan WebView.goBack()
    };
    window.__arenaFocusActive = false;

    window.lucide && lucide.createIcons();
})();
</script>
@endpush
@else
{{ $slot }}
@endif
