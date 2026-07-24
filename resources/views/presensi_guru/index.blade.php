@extends('layouts.app')
@section('title', 'Presensi Guru')

@push('styles')
<style>
    .stat-radio:checked + .stat-pill { color:#fff; }
    .stat-h:checked + .stat-pill { background:#10b981; border-color:#10b981; }
    .stat-i:checked + .stat-pill { background:#3b82f6; border-color:#3b82f6; }
    .stat-s:checked + .stat-pill { background:#f59e0b; border-color:#f59e0b; }
    .stat-a:checked + .stat-pill { background:#ef4444; border-color:#ef4444; }
    .stat-pill { cursor:pointer; transition:all .12s; }
</style>
@endpush

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Presensi Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kehadiran guru harian</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('absensi.scan', ['tanggal'=>$tanggal]) }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="scan-face" class="w-4 h-4"></i> Scan Wajah (Siswa & Guru)
            </a>
            @if(auth()->user()->canAccess('manage_absensi'))
            <a href="{{ route('absensi.wajah-guru') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition" title="Registrasi Wajah Guru">
                <i data-lucide="user-plus" class="w-4 h-4"></i> <span class="hidden sm:inline">Daftar Wajah</span>
            </a>
            @endif
            <a href="{{ route('presensi-guru.rekap') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Rekap
            </a>
            <a href="{{ route('presensi-guru.jamPulang.index') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="clock-4" class="w-4 h-4"></i> Jadwal Pulang
            </a>
        </div>
    </div>

    {{-- Filter tanggal --}}
    <form method="GET" action="{{ route('presensi-guru.index') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input" onchange="this.form.submit()">
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($gurus->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data guru.</p>
    </div>
    @else
    <form method="POST" action="{{ route('presensi-guru.store') }}">
        @csrf
        <input type="hidden" name="tanggal" value="{{ $tanggal }}">

        <div class="card overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $gurus->count() }} guru &bull; {{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMMM Y') }}</p>
                <button type="button" onclick="document.querySelectorAll('.stat-h').forEach(r=>r.checked=true)" class="text-xs font-semibold text-emerald-600 hover:underline flex items-center gap-1">
                    <i data-lucide="check-check" class="w-3.5 h-3.5"></i> Tandai semua Hadir
                </button>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($gurus as $i => $g)
                @php
                    $row = $existing->get($g->uuid);
                    $cur = $row?->status;
                @endphp
                <div class="p-3.5 flex items-center gap-3 flex-wrap sm:flex-nowrap hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                    <span class="text-xs text-slate-400 w-5 flex-shrink-0">{{ $i+1 }}</span>
                    <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background:{{ $g->jk==='P' ? '#ec4899' : 'var(--cp)' }}">{{ strtoupper(substr($g->nama,0,1)) }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $g->nama }}</p>
                        <p class="text-xs text-slate-400 flex flex-wrap items-center gap-x-2 gap-y-0.5 min-w-0">
                            <span>{{ $g->nip ?: ($g->nik ?: 'Guru') }}</span>
                            @if($row?->jam_masuk)<span class="{{ $row->terlambat($batas) ? 'text-rose-500' : 'text-emerald-500' }} flex items-center gap-0.5" title="Jam masuk"><i data-lucide="log-in" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::of($row->jam_masuk)->substr(0,5) }}</span>@endif
                            @if($row?->jam_pulang)<span class="text-slate-500 flex items-center gap-0.5" title="Jam pulang"><i data-lucide="log-out" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::of($row->jam_pulang)->substr(0,5) }}</span>@endif
                            @if($row && $row->terlambat($batas))<span class="badge bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-300 font-semibold">Terlambat</span>@endif
                            @if(!$g->active_face_descriptor)<span class="text-amber-500">⚠ belum daftar wajah</span>@endif
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 flex-shrink-0 w-full sm:w-auto sm:justify-end mt-2 sm:mt-0 pl-[68px] sm:pl-0">
                        @foreach(['hadir'=>['H','h'],'izin'=>['I','i'],'sakit'=>['S','s'],'alpa'=>['A','a']] as $val => [$abbr,$cls])
                        <label>
                            <input type="radio" name="status[{{ $g->uuid }}]" value="{{ $val }}" @checked($cur===$val) class="hidden stat-radio stat-{{ $cls }}">
                            <span class="stat-pill grid place-items-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500 text-sm font-bold" title="{{ \App\Models\PresensiGuru::STATUS[$val] }}">{{ $abbr }}</span>
                        </label>
                        @endforeach
                        <input type="text" name="keterangan[{{ $g->uuid }}]" value="{{ $row?->keterangan }}" placeholder="Ket." class="form-input !py-1.5 !w-24 text-xs">
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between mt-4 flex-wrap gap-3">
            <div class="flex items-center gap-3 text-xs text-slate-400 flex-wrap">
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500"></span> Hadir</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-500"></span> Izin</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500"></span> Sakit</span>
                <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-rose-500"></span> Alpa</span>
            </div>
            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Presensi
            </button>
        </div>
    </form>
    @endif
</div>
@endsection
