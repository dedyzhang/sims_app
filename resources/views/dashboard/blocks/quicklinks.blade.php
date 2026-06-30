{{-- ===== Tautan Cepat ===== --}}
@php
    $shortcuts = [
        ['Tambah Siswa', 'siswa.create',   'user-plus'],
        ['Tambah Guru',  'guru.create',    'user-round-plus'],
        ['Set Kelas',    'kelas.setKelas', 'layout-grid'],
        ['Data Siswa',   'siswa.index',    'users'],
        ['Absensi',      'absensi.index',  'clipboard-check'],
        ['Kalender',     'kalender.index', 'calendar-days'],
        ['Pengaturan',   'setting.index',  'settings-2'],
    ];
@endphp
<div>
    <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-3 px-1">Tautan Cepat</h2>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        @foreach($shortcuts as [$label, $route, $icon])
        @if(\Illuminate\Support\Facades\Route::has($route))
        <a href="{{ route($route) }}"
           class="card card-hover p-3 flex flex-col items-center justify-center gap-2 text-center group">
            <span class="grid place-items-center w-11 h-11 rounded-2xl bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition">
                <i data-lucide="{{ $icon }}" class="w-5 h-5"></i>
            </span>
            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-300 leading-tight">{{ $label }}</span>
        </a>
        @endif
        @endforeach
    </div>
</div>
