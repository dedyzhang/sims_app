@php
    $totalGuru = $totalGuru ?? \App\Models\Guru::count();
    $rowsPresensiHariIni = $rowsPresensiHariIni ?? \App\Models\PresensiGuru::whereDate('tanggal', now()->toDateString())->get();
    $prHadir = $rowsPresensiHariIni->where('status', 'hadir')->count();
@endphp
<a href="{{ route('presensi-guru.index') }}" class="card p-5 flex items-start justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Hadir Hari Ini</p>
        <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 tracking-tight leading-tight break-words">{{ $prHadir }} <span class="text-sm font-semibold text-slate-400">/ {{ $totalGuru }}</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Guru sudah presensi</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="user-check" class="w-4 h-4"></i>
    </span>
</a>
