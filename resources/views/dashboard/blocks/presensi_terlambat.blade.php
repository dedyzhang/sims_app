@php
    $rowsPresensiHariIni = $rowsPresensiHariIni ?? \App\Models\PresensiGuru::whereDate('tanggal', now()->toDateString())->get();
    $prBatas = \App\Models\Setting::get('waktu_terlambat_guru', \App\Models\Setting::get('waktu_terlambat', '07:30'));
    $prTerlambat = $rowsPresensiHariIni->where('status', 'hadir')->filter(fn ($r) => $r->terlambat($prBatas))->count();
@endphp
<a href="{{ route('presensi-guru.index') }}" class="card p-5 flex items-start justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Terlambat</p>
        <p class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 tracking-tight leading-tight break-words">{{ $prTerlambat }} <span class="text-sm font-semibold text-slate-400">guru</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">Melewati jam {{ substr($prBatas, 0, 5) }}</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="alarm-clock" class="w-4 h-4"></i>
    </span>
</a>
