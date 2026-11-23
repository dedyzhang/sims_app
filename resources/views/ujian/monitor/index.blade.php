@extends('layouts.app')
@section('title', 'Pemantauan Live — ' . $ujian->judul)

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Pemantauan Siswa
        </nav>
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h1 class="page-title">Daftar Hadir Ujian</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pemantauan live status kehadiran, pengerjaan, dan pelanggaran peserta ujian.</p>
            </div>
            <div class="flex items-center gap-2">
                @if($kelasFilter && $ujianKelasList->count() > 1)
                <a href="{{ route('ujian.monitor.index', $ujian) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span>Kembali ke Pilih Kelas</span>
                </a>
                @endif
                @if(!$kelasFilter && $ujianKelasList->count() > 1)
                <a href="{{ route('ujian.monitor.index', ['ujian' => $ujian, 'kelas' => 'semua']) }}" class="btn-primary px-3.5 py-2 text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Pantau Seluruh Kelas</span>
                </a>
                @endif
                @if(!$kelasFilter)
                <a href="{{ url()->current() }}" class="btn-secondary px-4 py-2 text-sm font-semibold flex items-center gap-2 rounded-xl">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> Segarkan Data
                </a>
                @endif
            </div>
        </div>
    </div>

    @if(!$kelasFilter)
    {{-- TAMPILAN 1: PILIH KELAS (Ringkasan per-kelas agar server ringan & tidak meload ratusan siswa sekaligus) --}}
    <div class="card p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/60 rounded-2xl flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 text-blue-600 shrink-0 mt-0.5"></i>
        <div class="text-xs text-blue-900 dark:text-blue-200 space-y-0.5">
            <p class="font-bold">Ujian Multi-Kelas ({{ $ujianKelasList->count() }} Kelas Ditugaskan)</p>
            <p>Pilih kelas di bawah untuk memantau status kehadiran, sisa waktu pengerjaan, dan pelanggaran siswa secara langsung. Membuka pemantauan per kelas memastikan data dimuat lebih cepat dan menghemat beban server.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($kelasSummary as $c)
        <div class="card p-5 border border-slate-200 dark:border-slate-700/60 rounded-2xl flex flex-col justify-between hover:shadow-md hover:border-primary/50 transition overflow-hidden">
            <div class="space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="inline-block px-2.5 py-1 rounded-lg bg-primary/10 text-primary font-bold text-base">
                        Kelas {{ $c['kelas']?->tingkat }}{{ $c['kelas']?->kelas }}
                    </span>
                    <span class="shrink-0 whitespace-nowrap text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        {{ $c['total_siswa'] }} Siswa
                    </span>
                </div>
                @if($c['ujianKelas']->guruPengampu)
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate" title="Pengampu: {{ $c['ujianKelas']->guruPengampu->nama }}">
                    Pengampu: {{ $c['ujianKelas']->guruPengampu->nama }}
                </p>
                @endif

                {{-- Status Breakdown --}}
                <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div class="bg-amber-50 dark:bg-amber-950/30 p-2 rounded-xl border border-amber-100 dark:border-amber-900/50">
                        <span class="text-slate-500 block text-[11px]">Sedang Ujian</span>
                        <span class="text-amber-700 dark:text-amber-400 font-bold text-sm">{{ $c['in_progress'] }}</span>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-950/30 p-2 rounded-xl border border-emerald-100 dark:border-emerald-900/50">
                        <span class="text-slate-500 block text-[11px]">Selesai</span>
                        <span class="text-emerald-700 dark:text-emerald-400 font-bold text-sm">{{ $c['selesai'] }}</span>
                    </div>
                    <div class="bg-rose-50 dark:bg-rose-950/30 p-2 rounded-xl border border-rose-100 dark:border-rose-900/50">
                        <span class="text-slate-500 block text-[11px]">Terkunci</span>
                        <span class="text-rose-700 dark:text-rose-400 font-bold text-sm">{{ $c['terkunci'] }}</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl border border-slate-200/60 dark:border-slate-700">
                        <span class="text-slate-500 block text-[11px]">Belum Mulai</span>
                        <span class="text-slate-600 dark:text-slate-300 font-bold text-sm">{{ $c['belum_mulai'] }}</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-3 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('ujian.monitor.index', ['ujian' => $ujian, 'kelas' => $c['ujianKelas']->id_kelas]) }}" class="btn-primary w-full py-2 rounded-xl text-center text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm">
                    <span>Pantau Siswa Kelas Ini</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    @else
    {{-- TAMPILAN 2: DAFTAR SISWA KELAS TERPILIH --}}
    <div x-data="ujianMonitor({{ Js::from(route('ujian.monitor.poll', $ujian)) }}, {{ Js::from(route('ujian.monitor.unlock', [$ujian, '__ATTEMPT__'])) }}, '{{ $kelasFilter }}')"
         x-init="init()"
         class="space-y-4">
        
        <div class="flex items-center justify-between flex-wrap gap-4">
            @if($ujianKelasList->count() > 1)
            <form method="GET" action="{{ route('ujian.monitor.index', $ujian) }}" class="flex items-center gap-2">
                <label class="text-xs font-semibold text-slate-500">Kelas:</label>
                <select name="kelas" class="form-select py-1.5 text-sm w-auto font-semibold" onchange="this.form.submit()">
                    <option value="semua" @selected($kelasFilter === 'semua')>Semua Kelas</option>
                    @foreach($ujianKelasList->sortBy(fn($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas]) as $uk)
                        <option value="{{ $uk->id_kelas }}" @selected($kelasFilter === $uk->id_kelas)>Kelas {{ $uk->kelas?->tingkat }}{{ $uk->kelas?->kelas }}</option>
                    @endforeach
                </select>
            </form>
            @else
            <div></div>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" @click="muat()" :disabled="loading" class="btn-primary px-3.5 py-1.5 text-xs font-semibold flex items-center gap-1.5 rounded-xl shadow-sm">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5" :class="{'animate-spin': loading}"></i>
                    <span>Segarkan</span>
                </button>
            </div>
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
                            <th class="text-right px-4 py-2.5">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        <template x-for="a in attempts" :key="a.attempt_uuid || a.nama">
                            <tr :class="a.dikunci ? 'bg-rose-50/60 dark:bg-rose-950/20' : ''">
                                <td class="px-4 py-2.5 font-medium whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full shrink-0"
                                              :class="a.dikunci ? 'bg-rose-500 animate-ping' : (a.status==='in_progress' ? 'bg-emerald-500' : 'bg-slate-300')"></span>
                                        <span x-text="a.nama"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap" x-text="a.kelas"></td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <span class="badge"
                                          :class="a.status==='belum_mulai' ? 'bg-slate-100 dark:bg-slate-700 text-slate-500' : (a.dikunci ? 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 font-bold' : (a.status==='in_progress' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'))"
                                          x-text="a.dikunci ? 'Terkunci' : a.status_label"></span>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs whitespace-nowrap" x-text="a.status==='in_progress' ? formatSisa(a.batas_waktu_pada) : '—'"></td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5" x-show="a.pelanggaran > 0">
                                        <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 font-bold" x-text="a.pelanggaran"></span>
                                        <span class="text-xs text-rose-600 dark:text-rose-400" x-show="a.alasan_pelanggaran" x-text="'(' + a.alasan_pelanggaran + ')'"></span>
                                    </div>
                                    <span x-show="a.pelanggaran === 0" class="text-slate-300">—</span>
                                </td>
                                <td class="px-4 py-2.5 text-right space-x-2 whitespace-nowrap">
                                    <button type="button" x-show="a.dikunci" @click="bukaKunci(a)" title="Buka Kunci" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg text-rose-700 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 hover:bg-rose-100 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                                        <span>Buka Kunci</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!loading && attempts.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada siswa yang terdaftar di kelas ini.</td>
                        </tr>
                        <tr x-show="loading && attempts.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                <div class="flex items-center justify-center gap-2">
                                    <i data-lucide="loader-2" class="w-5 h-5 animate-spin text-primary"></i>
                                    <span>Memuat data siswa...</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function ujianMonitor(urlPoll, urlUnlockTemplate, initialKelas) {
    return {
        attempts: [],
        kelasFilter: initialKelas || '',
        loading: false,
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        init() {
            this.muat();
        },

        async muat() {
            if (this.loading) return;
            this.loading = true;
            try {
                const url = this.kelasFilter ? urlPoll + '?kelas=' + encodeURIComponent(this.kelasFilter) : urlPoll;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                this.attempts = data.attempts || [];
            } catch (e) {
                console.error('Error saat memuat status ujian:', e);
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            }
        },

        formatSisa(iso) {
            if (!iso) return '—';
            const detik = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
            if (detik <= 0) return 'Habis';
            const m = Math.floor(detik / 60), s = detik % 60;
            return `${m}:${String(s).padStart(2, '0')}`;
        },

        async bukaKunci(a) {
            const uuid = a.attempt_uuid;
            await this._post(urlUnlockTemplate.replace('__ATTEMPT__', uuid));
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
