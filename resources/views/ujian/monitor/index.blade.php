@extends('layouts.app')
@section('title', 'Pemantauan Live — ' . $ujian->judul)

@section('content')
<div class="max-w-4xl mx-auto space-y-5"
     x-data="ujianMonitor({{ Js::from(route('ujian.monitor.poll', $ujian)) }}, {{ Js::from(route('ujian.monitor.unlock', [$ujian, '__ATTEMPT__'])) }})"
     x-init="init()">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Pemantauan Siswa
        </nav>
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h1 class="page-title">Daftar Hadir Ujian</h1>
            <button type="button" @click="muat()" class="btn-primary px-4 py-2 text-sm font-semibold flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-4 h-4" :class="{'animate-spin': loading}"></i> Segarkan Data
            </button>
        </div>
    </div>

    <div x-show="kelasOpsi.length > 1" class="flex items-center gap-2">
        <label class="text-xs font-semibold text-slate-500">Kelas:</label>
        <select class="form-select py-1.5 text-sm w-auto" x-model="kelasFilter" @change="muat()">
            <option value="">Semua kelas</option>
            <template x-for="k in kelasOpsi" :key="k.uuid">
                <option :value="k.uuid" x-text="k.label"></option>
            </template>
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
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
                    <template x-for="a in attempts" :key="a.attempt_uuid || a.nama">
                        <tr>
                            <td class="px-4 py-2.5 font-medium whitespace-nowrap" x-text="a.nama"></td>
                            <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap" x-text="a.kelas"></td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span class="badge"
                                      :class="a.status==='belum_mulai' ? 'bg-slate-100 dark:bg-slate-700 text-slate-500' : (a.dikunci ? 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400' : (a.status==='in_progress' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'))"
                                      x-text="a.dikunci ? 'Terkunci' : a.status_label"></span>
                            </td>
                            <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap" x-text="a.status==='in_progress' ? formatSisa(a.batas_waktu_pada) : '—'"></td>
                            <td class="px-4 py-2.5 whitespace-nowrap">
                                <span x-show="a.pelanggaran > 0" class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400" x-text="a.pelanggaran"></span>
                                <span x-show="a.pelanggaran === 0" class="text-slate-300">—</span>
                            </td>
                            <td class="px-4 py-2.5 text-right space-x-2 whitespace-nowrap">
                                <button type="button" x-show="a.dikunci" @click="bukaKunci(a)" title="Buka Kunci" class="inline-flex p-1.5 rounded-lg text-primary hover:bg-primary/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                </button>
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
</div>
@endsection

@push('scripts')
<script>
function ujianMonitor(urlPoll, urlUnlockTemplate, urlResetTemplate) {
    return {
        attempts: [],
        kelasOpsi: [],
        kelasFilter: '',
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        _timer: null,

        init() {
            this.muat();
        },

        async muat() {
            try {
                const url = this.kelasFilter ? urlPoll + '?kelas=' + encodeURIComponent(this.kelasFilter) : urlPoll;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.attempts = data.attempts;
                this.kelasOpsi = data.kelasOpsi;
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
