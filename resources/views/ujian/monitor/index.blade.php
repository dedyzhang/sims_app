@extends('layouts.app')
@section('title', 'Pemantauan Live — ' . $ujian->judul)

@section('content')
<div class="max-w-4xl mx-auto space-y-5"
     x-data="ujianMonitor({{ Js::from(route('ujian.monitor.poll', $ujian)) }}, {{ Js::from(route('ujian.monitor.unlock', [$ujian, '__ATTEMPT__'])) }}, {{ Js::from(route('ujian.monitor.resetAttempt', [$ujian, '__ATTEMPT__'])) }})"
     x-init="init()">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Pemantauan Live
        </nav>
        <div class="flex items-center gap-2">
            <h1 class="page-title">Pemantauan Live</h1>
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" title="Diperbarui otomatis"></span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Diperbarui otomatis tiap 5 detik.</p>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="text-left px-4 py-2.5">Siswa</th>
                    <th class="text-left px-4 py-2.5">Kelas</th>
                    <th class="text-left px-4 py-2.5">Status</th>
                    <th class="text-left px-4 py-2.5">Sisa Waktu</th>
                    <th class="text-left px-4 py-2.5">Pelanggaran</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <template x-for="a in attempts" :key="a.attempt_uuid">
                    <tr>
                        <td class="px-4 py-2.5 font-medium" x-text="a.nama"></td>
                        <td class="px-4 py-2.5 text-slate-500" x-text="a.kelas"></td>
                        <td class="px-4 py-2.5">
                            <span class="badge" :class="a.dikunci ? 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400' : (a.status==='in_progress' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300')"
                                  x-text="a.dikunci ? 'Terkunci' : a.status_label"></span>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs" x-text="a.status==='in_progress' ? formatSisa(a.batas_waktu_pada) : '—'"></td>
                        <td class="px-4 py-2.5">
                            <span x-show="a.pelanggaran > 0" class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400" x-text="a.pelanggaran"></span>
                            <span x-show="a.pelanggaran === 0" class="text-slate-300">—</span>
                        </td>
                        <td class="px-4 py-2.5 text-right space-x-2 whitespace-nowrap">
                            <button type="button" x-show="a.dikunci" @click="bukaKunci(a)" class="text-xs text-primary hover:underline">Buka Kunci</button>
                            <button type="button" x-show="a.status === 'in_progress' || a.dikunci" @click="resetUlang(a)" class="text-xs text-rose-600 hover:underline">Reset Ulang</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="attempts.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada siswa yang mulai mengerjakan.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
function ujianMonitor(urlPoll, urlUnlockTemplate, urlResetTemplate) {
    return {
        attempts: [],
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        _timer: null,

        init() {
            this.muat();
            this._timer = setInterval(() => this.muat(), 5000);
        },

        async muat() {
            try {
                const res = await fetch(urlPoll, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.attempts = data.attempts;
            } catch (e) {}
        },

        formatSisa(iso) {
            if (!iso) return '—';
            const detik = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
            if (detik <= 0) return 'Habis';
            const m = Math.floor(detik / 60), s = detik % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },

        async bukaKunci(a) {
            await this._post(urlUnlockTemplate.replace('__ATTEMPT__', a.attempt_uuid));
            this.muat();
        },

        async resetUlang(a) {
            if (!window.confirm(`Reset ulang attempt ${a.nama}? Siswa akan bisa memulai ujian dari nol dengan token yang sama.`)) return;
            await this._post(urlResetTemplate.replace('__ATTEMPT__', a.attempt_uuid));
            this.muat();
        },

        async _post(url) {
            try {
                await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this._csrf, 'Accept': 'application/json' },
                });
            } catch (e) {}
        },
    };
}
</script>
@endpush
