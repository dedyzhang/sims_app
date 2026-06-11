@extends('layouts.app')
@section('title', 'Jadwal per Kelas')

@push('styles')
<style>
    .wtable { border-collapse:separate; border-spacing:0; }
    .wtable th,.wtable td { border-bottom:1px solid #eef2f7; border-right:1px solid #eef2f7; }
    .dark .wtable th,.dark .wtable td { border-color:#293548; }
    .wtable thead th { background:#f8fafc; }
    .dark .wtable thead th { background:#0f172a; }
</style>
@endpush

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('jadwal.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title">Jadwal per Kelas</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Tampilan mingguan satu kelas</p>
            </div>
        </div>
        <form method="GET" action="{{ route('jadwal.kelas') }}">
            <select name="kelas" onchange="this.form.submit()" class="form-select py-2.5 text-sm w-auto">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($jamList->isEmpty() || !$selectedKelas)
    <div class="card p-10 text-center text-slate-400">
        <i data-lucide="calendar-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data jadwal.</p>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="wtable w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-bold uppercase text-slate-500 w-28">Jam</th>
                        @foreach(\App\Models\Jadwal::HARI as $no => $nama)
                        <th class="px-2 py-2.5 text-center text-xs font-bold text-slate-600 dark:text-slate-300 min-w-[130px]">{{ $nama }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($jamList as $jam)
                        @if($jam->jenis==='istirahat')
                        <tr>
                            <td class="px-3 py-1.5 text-xs font-bold text-amber-600">{{ $jam->label ?? 'Istirahat' }}</td>
                            <td colspan="6" class="px-3 py-1.5 text-center text-xs text-amber-600 font-semibold bg-amber-50/50 dark:bg-amber-900/10">
                                <i data-lucide="coffee" class="w-3.5 h-3.5 inline"></i> {{ $jam->label ?? 'Istirahat' }} &bull; {{ $jam->rentang }}
                            </td>
                        </tr>
                        @else
                        <tr>
                            <td class="px-3 py-2 align-top">
                                <p class="font-bold text-slate-700 dark:text-slate-200">Jam {{ $jam->jam_ke ?? '-' }}</p>
                                <p class="text-[11px] text-slate-400 font-mono">{{ $jam->rentang }}</p>
                            </td>
                            @foreach(\App\Models\Jadwal::HARI as $no => $nama)
                            @php $j = $cells[$jam->uuid.'|'.$no] ?? null; @endphp
                            <td class="p-1.5 align-top">
                                @if($j && ($j->pelajaran || $j->keterangan))
                                <div class="rounded-lg px-2 py-1.5 h-full" style="background:color-mix(in srgb,var(--cp) 9%,#fff)">
                                    <p class="font-semibold text-[13px] text-slate-700 dark:text-slate-200 leading-tight">{{ $j->pelajaran?->nama ?? $j->keterangan }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ $j->guru?->nama ?? '' }}</p>
                                </div>
                                @else
                                <div class="text-center text-slate-200 dark:text-slate-700 text-xs py-2">·</div>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
