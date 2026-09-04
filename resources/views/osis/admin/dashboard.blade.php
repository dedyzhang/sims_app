@extends('layouts.app')
@section('title', 'Dashboard Live — '.$pemilihan->nama)

@section('content')
<div class="space-y-5" x-data="osisDashboard('{{ route('osis.dashboard.data', $pemilihan) }}', '{{ route('osis.pemilih.rosterKelas', [$pemilihan, '__KELAS__']) }}')" x-init="init()">
    <div>
        <a href="{{ route('osis.show', $pemilihan) }}" class="text-xs text-slate-400 hover:text-primary inline-flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> {{ $pemilihan->nama }}
        </a>
        <h1 class="page-title">Dashboard Live</h1>
        <p class="text-xs text-slate-400 mt-0.5">Diperbarui otomatis tiap 5 detik &middot; terakhir: <span x-text="updatedAt"></span></p>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="card p-5">
            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-1">
                <i data-lucide="users" class="w-4 h-4"></i> Siswa Sudah Memilih
            </div>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white" x-text="`${siswa.sudah} / ${siswa.total}`"></div>
            <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 mt-2 overflow-hidden">
                <div class="h-full rounded-full" style="background:var(--cp)" :style="`width:${siswa.total ? (siswa.sudah/siswa.total*100) : 0}%`"></div>
            </div>
        </div>
        <div class="card p-5">
            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400 mb-1">
                <i data-lucide="graduation-cap" class="w-4 h-4"></i> Guru Sudah Memilih
            </div>
            <div class="text-3xl font-extrabold text-slate-800 dark:text-white" x-text="`${guru.sudah} / ${guru.total}`"></div>
            <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 mt-2 overflow-hidden">
                <div class="h-full rounded-full" style="background:var(--ca)" :style="`width:${guru.total ? (guru.sudah/guru.total*100) : 0}%`"></div>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200">Rekap per Kelas</div>
        <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th class="text-left">Kelas</th><th class="text-center w-28">Progres</th><th class="text-center w-20">Sudah</th><th class="text-center w-20">Total</th><th class="text-center w-20"></th></tr></thead>
            <tbody>
                <template x-for="k in perKelas" :key="k.id_kelas">
                    <tr>
                        <td class="font-medium text-slate-700 dark:text-slate-200" x-text="`${k.tingkat}${k.kelas}`"></td>
                        <td>
                            <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                <div class="h-full rounded-full" style="background:var(--cp)" :style="`width:${k.total ? (k.sudah/k.total*100) : 0}%`"></div>
                            </div>
                        </td>
                        <td class="text-center text-sm" x-text="k.sudah"></td>
                        <td class="text-center text-sm" x-text="k.total"></td>
                        <td class="text-center"><button @click="lihatRoster(k.id_kelas)" class="text-xs text-primary font-semibold">Detail</button></td>
                    </tr>
                </template>
                <tr x-show="perKelas.length === 0"><td colspan="5" class="text-center text-slate-400 py-6">Belum ada token pemilih siswa.</td></tr>
            </tbody>
        </table>
        </div>
    </div>

    {{-- Modal roster detail — LAZY, dimuat sekali per klik (bukan bagian polling otomatis). --}}
    <div x-show="rosterOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60" @click.self="rosterOpen = false">
        <div class="card !bg-white dark:!bg-slate-800 w-full max-w-md max-h-[80vh] overflow-y-auto p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold text-slate-800 dark:text-white">Detail Kelas</h2>
                <button @click="rosterOpen = false"><i data-lucide="x" class="w-4 h-4 text-slate-400"></i></button>
            </div>
            <template x-if="rosterLoading"><p class="text-sm text-slate-400 text-center py-4">Memuat...</p></template>
            <ul class="space-y-1.5" x-show="!rosterLoading">
                <template x-for="r in roster" :key="r.nis">
                    <li class="flex items-center justify-between text-sm py-1 border-b border-slate-50 dark:border-slate-700 last:border-0">
                        <span>
                            <span class="text-slate-700 dark:text-slate-200" x-text="r.nama"></span>
                            <span class="text-slate-400 text-xs" x-text="` · ${r.nis}`"></span>
                        </span>
                        <span class="badge" :class="r.sudah ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'" x-text="r.sudah ? 'Sudah' : 'Belum'"></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function osisDashboard(url, rosterUrlTpl) {
    return {
        siswa: { sudah: 0, total: 0 }, guru: { sudah: 0, total: 0 }, perKelas: [], updatedAt: '-',
        rosterOpen: false, rosterLoading: false, roster: [],
        init() {
            this.poll();
            // simsPollInterval (bukan setInterval polos): skip polling saat tab background,
            // refresh begitu kembali visible — konvensi polling app ini (lihat layouts/app.blade.php).
            window.simsPollInterval(() => this.poll(), 5000, 'osis_dashboard');
        },
        async poll() {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            if (d.ok) { this.siswa = d.siswa; this.guru = d.guru; this.perKelas = d.per_kelas; this.updatedAt = new Date(d.updated_at).toLocaleTimeString('id-ID'); }
        },
        async lihatRoster(idKelas) {
            this.rosterOpen = true; this.rosterLoading = true; this.roster = [];
            const r = await fetch(rosterUrlTpl.replace('__KELAS__', idKelas), { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (d.ok) this.roster = d.roster.data ?? d.roster;
            this.rosterLoading = false;
        },
    };
}
</script>
@endpush
