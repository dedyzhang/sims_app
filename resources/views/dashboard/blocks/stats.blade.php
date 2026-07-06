{{-- ===== Top stat cards ===== --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @php
        $icons = [
            'siswa' => 'users',
            'guru' => 'user-check',
            'kelas' => 'layout-grid',
            'mapel' => 'book-open'
        ];
    @endphp
    @foreach([
        ['Total Siswa', $totalSiswa, 'Terdaftar', 'siswa'],
        ['Total Guru', $totalGuru, 'Aktif mengajar', 'guru'],
        ['Total Kelas', $totalKelas, 'Rombongan belajar', 'kelas'],
    ] as [$label, $val, $sub, $key])
    <div class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300">
        <div class="min-w-0">
            <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">{{ $label }}</p>
            <p class="text-2xl font-extrabold text-slate-700 dark:text-slate-100 tracking-tight">{{ number_format($val) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">{{ $sub }}</p>
        </div>
        <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
            <i data-lucide="{{ $icons[$key] }}" class="w-4 h-4"></i>
        </span>
    </div>
    @endforeach
    {{-- last cell with book-open icon --}}
    <div class="card p-5 flex items-center justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300">
        <div class="min-w-0">
            <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Mata Pelajaran</p>
            <p class="text-2xl font-extrabold text-slate-700 dark:text-slate-100 tracking-tight">{{ number_format($totalMapel) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Kurikulum</p>
        </div>
        <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
            <i data-lucide="{{ $icons['mapel'] }}" class="w-4 h-4"></i>
        </span>
    </div>
</div>

