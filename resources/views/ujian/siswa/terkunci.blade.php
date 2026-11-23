@extends('layouts.app')
@section('title', 'Ujian Terkunci')

@section('content')
<div class="max-w-md mx-auto">
    <div class="card p-8 text-center space-y-4 border-2 border-rose-200 dark:border-rose-900/60 bg-rose-50/20 dark:bg-rose-950/10">
        <div class="relative inline-block mx-auto">
            <span class="absolute -inset-2 rounded-full bg-rose-500/20 animate-ping"></span>
            <i data-lucide="lock" class="w-14 h-14 text-rose-500 relative mx-auto"></i>
        </div>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Ujian Terkunci</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
            Ujian Anda terkunci karena keluar dari layar penuh atau berpindah tab.
            Hubungi guru/panitia ujian untuk membuka kembali. Jawaban yang sudah tersimpan tidak hilang.
        </p>
        <p class="text-xs text-slate-400">{{ $ujian->judul }}</p>
        <a href="{{ route('ujian.siswa.index') }}" class="inline-block px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600">Kembali ke Daftar Ujian</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let played = false;
    function bunyikanAlarm() {
        if (played) return;
        played = true;
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const now = ctx.currentTime;
            const freqs = [880, 587.33, 880, 587.33, 880, 587.33];
            let t = now;
            freqs.forEach((freq) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(freq, t);
                gain.gain.setValueAtTime(0.001, t);
                gain.gain.exponentialRampToValueAtTime(0.5, t + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.001, t + 0.22);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(t);
                osc.stop(t + 0.24);
                t += 0.25;
            });
        } catch (e) {}
    }

    bunyikanAlarm();
    document.addEventListener('click', bunyikanAlarm, { once: true });
    document.addEventListener('touchstart', bunyikanAlarm, { once: true });
});
</script>
@endpush
