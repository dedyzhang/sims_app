{{-- Sel rincian rekap: $row = Absensi/PresensiGuru|null, $batas = HH:MM --}}
@php $row = $row ?? null; @endphp
@if(!$row)
    <span class="text-slate-300 dark:text-slate-600">·</span>
@elseif($row->status === 'hadir')
    <div class="leading-tight">
        @if($row->jam_masuk)
            <span class="{{ $row->terlambat($batas) ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-emerald-600 dark:text-emerald-400' }}">{{ \Illuminate\Support\Str::of($row->jam_masuk)->substr(0,5) }}</span>
        @else
            <span class="text-emerald-600 dark:text-emerald-400 font-bold">H</span>
        @endif
        @if($row->jam_pulang ?? null)
            <span class="block text-[10px] text-slate-400">↓ {{ \Illuminate\Support\Str::of($row->jam_pulang)->substr(0,5) }}</span>
        @endif
    </div>
@elseif($row->status === 'izin')
    <span class="text-blue-600 dark:text-blue-400 font-bold">I</span>
@elseif($row->status === 'sakit')
    <span class="text-amber-600 dark:text-amber-400 font-bold">S</span>
@else
    <span class="text-rose-600 dark:text-rose-400 font-bold">A</span>
@endif
