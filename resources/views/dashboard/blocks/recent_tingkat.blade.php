@php
    $perTingkat = ($kelasStats ?? \App\Models\Kelas::withCount('siswa')->get())
        ->groupBy('tingkat')
        ->map(fn($g) => $g->sum(fn($k) => $k->siswa_count ?? ($k->siswa_l_count + $k->siswa_p_count)))
        ->sortKeys(SORT_NATURAL);
    $maxTingkat = max($perTingkat->max() ?? 0, 1);
@endphp
<div class="card p-5 flex flex-col h-full shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-slate-700 dark:text-slate-200">Siswa per Tingkat</h2>
        <a href="{{ route('siswa.index') }}" class="text-xs font-semibold text-primary hover:underline">Lihat Semua</a>
    </div>
    <div class="space-y-3.5 flex-1">
        @forelse($perTingkat as $tingkat => $jml)
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-650 dark:text-slate-300">Tingkat {{ $tingkat }}</span>
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ number_format($jml) }} <span class="font-medium text-slate-400">siswa</span></span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500" style="width: {{ max(round($jml / $maxTingkat * 100), 3) }}%; background:linear-gradient(90deg,var(--cp),var(--ca))"></div>
            </div>
        </div>
        @empty
        <p class="text-sm text-slate-400 text-center py-8">Belum ada data kelas</p>
        @endforelse
    </div>
    <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
        <span class="text-slate-400">{{ $perTingkat->count() }} tingkat aktif</span>
        <span class="font-bold text-slate-600 dark:text-slate-300">{{ number_format($perTingkat->sum()) }} total siswa</span>
    </div>
</div>
