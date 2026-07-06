{{-- ===== Tautan Cepat ===== --}}
@php
    $access = auth()->user()?->access;
    if ($access === 'kepala') {
        $shortcuts = [
            ['Data Siswa',    'siswa.index',           'users'],
            ['Data Guru',     'guru.index',            'graduation-cap'],
            ['Absensi Siswa', 'absensi.index',         'clipboard-check'],
            ['Presensi Guru', 'presensi-guru.index',   'user-check'],
            ['Jadwal',        'jadwal.index',          'calendar-clock'],
            ['Laporan Sarpras', 'sarpras.laporan.index', 'bar-chart-3'],
        ];
    } else {
        $shortcuts = [
            ['Tambah Siswa', 'siswa.create',   'user-plus'],
            ['Tambah Guru',  'guru.create',    'user-round-plus'],
            ['Set Kelas',    'kelas.setKelas', 'layout-grid'],
            ['Data Siswa',   'siswa.index',    'users'],
            ['Absensi',      'absensi.index',  'clipboard-check'],
            ['Kalender',     'kalender.index', 'calendar-days'],
            ['Pengaturan',   'setting.index',  'settings-2'],
        ];
    }
@endphp
<div>
    <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-3 px-1">Tautan Cepat</h2>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        @foreach($shortcuts as [$label, $route, $icon])
        @if(\Illuminate\Support\Facades\Route::has($route))
        <a href="{{ route($route) }}"
           class="card p-4 flex flex-col items-center justify-center gap-3 text-center group hover:border-primary/20 hover:-translate-y-0.5 hover:shadow-md transition-all duration-350">
            <span class="grid place-items-center w-11 h-11 rounded-2xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-all duration-300">
                <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
            </span>
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-300 leading-tight tracking-wide">{{ $label }}</span>
        </a>
        @endif
        @endforeach
    </div>
</div>
