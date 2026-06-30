{{-- ===== Analitik Sekolah — metrik turunan, selalu terisi ===== --}}
@php
    $perTingkat = \App\Models\Kelas::withCount('siswa')->get()
        ->groupBy('tingkat')
        ->map(fn ($g) => $g->sum('siswa_count'))
        ->sortDesc();

    $jmlTingkat = $perTingkat->count();
    $rasioGuru  = $totalGuru  > 0 ? round($totalSiswa / $totalGuru)  : 0;
    $avgKelas   = $totalKelas > 0 ? round($totalSiswa / $totalKelas) : 0;
    $avgTingkat = $jmlTingkat > 0 ? round($totalSiswa / $jmlTingkat) : 0;
    $padatTingkat = $jmlTingkat > 0 ? $perTingkat->keys()->first() : '—';
    $padatJml     = $jmlTingkat > 0 ? $perTingkat->first() : 0;

    $cards = [
        ['Rasio Guru : Siswa', '1 : '.$rasioGuru, 'Beban per guru',             'users-round'],
        ['Rata-rata / Kelas',  $avgKelas.' siswa', 'Per rombongan belajar',      'layout-grid'],
        ['Rata-rata / Tingkat', $avgTingkat.' siswa', $jmlTingkat.' tingkat aktif', 'bar-chart-3'],
        ['Tingkat Terpadat',   'Tingkat '.$padatTingkat, number_format($padatJml).' siswa', 'trending-up'],
    ];
@endphp
<div>
    <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-3 px-1">Analitik Sekolah</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($cards as [$label, $val, $sub, $icon])
        <div class="card card-hover p-4 flex items-start justify-between gap-3 group">
            <div class="min-w-0">
                <p class="text-xs text-slate-400 mb-1">{{ $label }}</p>
                <p class="text-xl font-extrabold text-slate-700 dark:text-slate-100 truncate">{{ $val }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5 truncate">{{ $sub }}</p>
            </div>
            <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition flex-shrink-0">
                <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
            </span>
        </div>
        @endforeach
    </div>
</div>
