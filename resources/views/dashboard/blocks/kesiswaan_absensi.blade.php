@php
    $kesRekapAbsensi = \App\Models\Absensi::whereDate('tanggal', now()->toDateString())
        ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
@endphp
<div class="card p-5 h-full">
    <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-slate-400 font-semibold flex items-center gap-1.5"><i data-lucide="clipboard-check" class="w-3.5 h-3.5 text-primary"></i> Absensi Siswa Hari Ini</p>
    </div>
    <div class="grid grid-cols-4 gap-2 text-center">
        <div><p class="text-lg font-extrabold text-emerald-600">{{ $kesRekapAbsensi['hadir'] ?? 0 }}</p><p class="text-[10px] text-slate-400">Hadir</p></div>
        <div><p class="text-lg font-extrabold text-amber-600">{{ $kesRekapAbsensi['izin'] ?? 0 }}</p><p class="text-[10px] text-slate-400">Izin</p></div>
        <div><p class="text-lg font-extrabold text-blue-600">{{ $kesRekapAbsensi['sakit'] ?? 0 }}</p><p class="text-[10px] text-slate-400">Sakit</p></div>
        <div><p class="text-lg font-extrabold text-rose-600">{{ $kesRekapAbsensi['alpa'] ?? 0 }}</p><p class="text-[10px] text-slate-400">Alpa</p></div>
    </div>
</div>
