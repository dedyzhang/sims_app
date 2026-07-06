@php
    $totalGuru = $totalGuru ?? \App\Models\Guru::count();
    $rowsPresensiHariIni = $rowsPresensiHariIni ?? \App\Models\PresensiGuru::whereDate('tanggal', now()->toDateString())->get();
    $prBelum = max(0, $totalGuru - $rowsPresensiHariIni->count());
@endphp
<a href="{{ route('presensi-guru.index') }}" class="card p-5 flex items-start justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Belum Presensi</p>
        <p class="text-2xl font-extrabold text-slate-700 dark:text-slate-100 tracking-tight leading-tight break-words">{{ $prBelum }} <span class="text-sm font-semibold text-slate-400">guru</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Belum absen hari ini</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="user-round-x" class="w-4 h-4"></i>
    </span>
</a>
