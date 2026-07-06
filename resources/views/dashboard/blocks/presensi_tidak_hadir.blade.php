@php
    $rowsPresensiHariIni = $rowsPresensiHariIni ?? \App\Models\PresensiGuru::whereDate('tanggal', now()->toDateString())->get();
    $prIzin  = $rowsPresensiHariIni->where('status', 'izin')->count();
    $prSakit = $rowsPresensiHariIni->where('status', 'sakit')->count();
    $prAlpa  = $rowsPresensiHariIni->where('status', 'alpa')->count();
    $prTidakHadir = $prIzin + $prSakit + $prAlpa;
@endphp
<a href="{{ route('presensi-guru.index') }}" class="card p-5 flex items-start justify-between relative overflow-hidden group hover:border-primary/20 transition-all duration-300 h-full shadow-sm">
    <div class="min-w-0">
        <p class="text-xs text-slate-400 font-semibold mb-1 leading-tight break-words">Izin / Sakit / Alpa</p>
        <p class="text-2xl font-extrabold text-rose-500 tracking-tight leading-tight break-words">{{ $prTidakHadir }} <span class="text-sm font-semibold text-slate-400">guru</span></p>
        <p class="text-[11px] text-slate-400 mt-0.5 leading-tight break-words">{{ $prIzin }} izin &bull; {{ $prSakit }} sakit &bull; {{ $prAlpa }} alpa</p>
    </div>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition duration-300 flex-shrink-0">
        <i data-lucide="user-x" class="w-4 h-4"></i>
    </span>
</a>
