@php
    $perKelas = $kelasStats ?? \App\Models\Kelas::withCount([
            'siswa as siswa_l_count' => fn ($q) => $q->where('jk', 'L'),
            'siswa as siswa_p_count' => fn ($q) => $q->where('jk', 'P'),
        ])
        ->orderBy('tingkat')->orderBy('kelas')->get();
    
    // Fallback if not loaded via kelasStats
    if (!isset($perKelas[0]->siswa_count)) {
        $perKelas->each(fn ($k) => $k->siswa_count = $k->siswa_l_count + $k->siswa_p_count);
    }
    
    $maxSingle = max($perKelas->max('siswa_l_count') ?? 0, $perKelas->max('siswa_p_count') ?? 0, 1);
@endphp
<div class="card p-5">
    <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
        <div>
            <h2 class="font-bold text-slate-700 dark:text-slate-200">Sebaran Siswa per Kelas</h2>
            <p class="text-[10px] text-slate-400 dark:text-slate-500">Jumlah siswa terdistribusi di setiap rombongan belajar, per jenis kelamin</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400"><span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:var(--cp)"></span> Laki-laki</span>
            <span class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400"><span class="w-2.5 h-2.5 rounded-full flex-shrink-0 bg-[#db7793]"></span> Perempuan</span>
            <a href="{{ route('kelas.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ $perKelas->count() }} rombel</a>
        </div>
    </div>
    @if($perKelas->isEmpty())
        <p class="text-sm text-slate-400 text-center py-8">Belum ada data kelas</p>
    @else
    <div class="overflow-x-auto scrollbar-thin">
        <div class="relative h-52 min-w-[650px] w-full pt-8 pb-6">
            <!-- Grid Lines -->
            <div class="absolute inset-x-0 top-8 bottom-6 flex flex-col justify-between pointer-events-none opacity-50 dark:opacity-20">
                <div class="border-b border-dashed border-slate-200 dark:border-slate-700 w-full"></div>
                <div class="border-b border-dashed border-slate-200 dark:border-slate-700 w-full"></div>
                <div class="border-b border-dashed border-slate-200 dark:border-slate-700 w-full"></div>
                <div class="border-b border-dashed border-slate-200 dark:border-slate-700 w-full"></div>
            </div>
            <!-- Bars Container -->
            <div class="absolute inset-x-0 top-8 bottom-6 flex items-end justify-between gap-2.5 px-2">
                @foreach($perKelas as $k)
                @php
                    $lPercent = $k->siswa_l_count > 0 ? max(round($k->siswa_l_count / $maxSingle * 100), 6) : 0;
                    $pPercent = $k->siswa_p_count > 0 ? max(round($k->siswa_p_count / $maxSingle * 100), 6) : 0;
                @endphp
                <div class="flex-1 flex flex-col items-center h-full justify-end group relative">
                    <!-- Tooltip -->
                    <div class="absolute bottom-full mb-2 hidden group-hover:flex flex-col items-center transition-all duration-200 z-30">
                        <span class="bg-slate-800 text-white text-[10px] font-bold px-2 py-1 rounded shadow-md whitespace-nowrap">
                            {{ $k->siswa_count }} Siswa &bull; {{ $k->siswa_l_count }}L / {{ $k->siswa_p_count }}P
                        </span>
                        <!-- Arrow -->
                        <div class="w-1.5 h-1.5 bg-slate-800 rotate-45 -mt-1"></div>
                    </div>
                    <!-- Dua bar berdampingan: Laki-laki & Perempuan -->
                    <div class="w-full h-full flex items-end justify-center gap-1 cursor-pointer">
                        <div class="w-3 sm:w-3.5 rounded-t shadow-sm group-hover:shadow-md transition-all duration-300"
                             style="height: {{ $lPercent }}%; background-image: linear-gradient(to top, var(--cp), color-mix(in srgb, var(--cp) 45%, white));"></div>
                        <div class="w-3 sm:w-3.5 rounded-t shadow-sm group-hover:shadow-md transition-all duration-300"
                             style="height: {{ $pPercent }}%; background-image: linear-gradient(to top, #db7793, color-mix(in srgb, #db7793 45%, white));"></div>
                    </div>
                    <!-- Label -->
                    <span class="absolute top-full mt-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 group-hover:text-primary transition-colors text-center whitespace-nowrap">
                        {{ $k->tingkat }}{{ $k->kelas }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
