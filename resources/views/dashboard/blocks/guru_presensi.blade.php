@php
    $guruPrsGuru = auth()->user()->guru;
    $guruPrsHariIni = $guruPrsGuru
        ? \App\Models\PresensiGuru::where('id_guru', $guruPrsGuru->uuid)->whereDate('tanggal', now()->toDateString())->first()
        : null;
    $guruPrsWarna = ['hadir' => 'emerald', 'izin' => 'amber', 'sakit' => 'blue', 'alpa' => 'rose'];
@endphp
<div class="card p-5 h-full flex flex-col justify-center">
    <p class="text-xs text-slate-400 font-semibold mb-2 flex items-center gap-1.5"><i data-lucide="user-check" class="w-3.5 h-3.5 text-primary"></i> Absensi</p>
    @if($guruPrsHariIni)
    @php $sw = $guruPrsWarna[$guruPrsHariIni->status] ?? 'slate'; @endphp
    <span class="badge bg-{{ $sw }}-100 dark:bg-{{ $sw }}-900 text-{{ $sw }}-700 dark:text-{{ $sw }}-300 font-semibold w-fit">{{ \App\Models\PresensiGuru::STATUS[$guruPrsHariIni->status] ?? ucfirst($guruPrsHariIni->status) }}</span>
    @if($guruPrsHariIni->jam_masuk)
    <p class="text-xs text-slate-400 mt-2">Masuk {{ substr($guruPrsHariIni->jam_masuk, 0, 5) }}</p>
    @endif
    @else
    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 font-semibold w-fit">Belum Presensi</span>
    @endif
</div>
