{{-- ===== Sebaran Siswa per Kelas (rombel) ===== --}}
@php
    $perKelas = \App\Models\Kelas::withCount('siswa')
        ->orderBy('tingkat')->orderBy('kelas')->get();
    $maxKelas = max($perKelas->max('siswa_count') ?? 0, 1);
@endphp
<div class="card p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-slate-700 dark:text-slate-200">Sebaran Siswa per Kelas</h2>
        <a href="{{ route('kelas.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ $perKelas->count() }} rombel</a>
    </div>
    @if($perKelas->isEmpty())
        <p class="text-sm text-slate-400 text-center py-8">Belum ada data kelas</p>
    @else
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3.5">
        @foreach($perKelas as $k)
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-300">Kelas {{ $k->tingkat }}{{ $k->kelas }}</span>
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ number_format($k->siswa_count) }}</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500" style="width: {{ max(round($k->siswa_count / $maxKelas * 100), 3) }}%; background:linear-gradient(90deg,var(--cp),var(--ca))"></div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
