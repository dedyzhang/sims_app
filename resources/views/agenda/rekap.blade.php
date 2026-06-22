@extends('layouts.app')
@section('title', 'Rekap Agenda')

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Rekap Agenda Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pantau & validasi agenda mengajar guru per periode</p>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('agenda.rekap') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label">Guru</label>
            <select name="guru" class="form-select" onchange="this.form.submit()">
                @foreach($guruList as $g)
                <option value="{{ $g->uuid }}" @selected($selectedGuru===$g->uuid)>{{ $g->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-40">
            <label class="form-label">Dari</label>
            <input type="date" name="dari" value="{{ $dari }}" class="form-input" onchange="this.form.submit()">
        </div>
        <div class="min-w-40">
            <label class="form-label">Sampai</label>
            <input type="date" name="sampai" value="{{ $sampai }}" class="form-input" onchange="this.form.submit()">
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($agendas->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="calendar-search" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada agenda pada periode ini.</p>
    </div>
    @else
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $agendas->count() }} agenda &bull; {{ \Carbon\Carbon::parse($dari)->isoFormat('D MMM') }} – {{ \Carbon\Carbon::parse($sampai)->isoFormat('D MMM Y') }}</p>

    <div class="space-y-3">
        @foreach($agendas as $a)
        <div class="card p-5 space-y-3">
            {{-- Header agenda --}}
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ $a->pelajaran?->nama ?? '-' }}</span>
                        <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">Kelas {{ $a->kelas ? $a->kelas->tingkat.$a->kelas->kelas : '-' }}</span>
                        <span class="badge {{ $a->proses==='selesai' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">{{ $a->proses==='selesai' ? 'Selesai' : 'Belum Selesai' }}</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> {{ $a->tanggal->isoFormat('dddd, D MMM Y') }}</span>
                        @if($a->jadwal?->jam_mulai)<span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i> {{ substr($a->jadwal->jam_mulai,0,5) }}–{{ substr($a->jadwal->jam_selesai,0,5) }}</span>@endif
                    </p>
                </div>
                @if($a->validasi==='valid')
                <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1"><i data-lucide="badge-check" class="w-3.5 h-3.5"></i> Tervalidasi</span>
                @else
                <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">Belum divalidasi</span>
                @endif
            </div>

            {{-- Isi agenda --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm border-t border-slate-100 dark:border-slate-700 pt-3">
                <div><p class="text-xs font-semibold text-slate-400">Pembahasan</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->pembahasan ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold text-slate-400">Metode</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->metode ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold text-slate-400">Kegiatan</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->kegiatan ?: '-' }}</p></div>
                <div><p class="text-xs font-semibold text-slate-400">Kendala &amp; Tindak Lanjut</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->kendala ?: '-' }}</p></div>
            </div>

            {{-- Ketidakhadiran --}}
            @if($a->absensi->isNotEmpty())
            <div class="text-sm">
                <p class="text-xs font-semibold text-slate-400 mb-1">Ketidakhadiran</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($a->absensi as $ab)
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $ab->siswa?->nama ?? '-' }}: <b class="ml-0.5">{{ \App\Models\Agenda::ABSENSI[$ab->absensi] ?? $ab->absensi }}</b>@if($ab->keterangan) ({{ $ab->keterangan }})@endif</span>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Validasi kepala sekolah --}}
            <form method="POST" action="{{ route('agenda.validasi', $a) }}" class="border-t border-slate-100 dark:border-slate-700 pt-3 flex flex-wrap items-end gap-2">
                @csrf
                <div class="flex-1 min-w-48">
                    <label class="form-label">Catatan Pimpinan</label>
                    <input type="text" name="catatan_kepsek" value="{{ $a->catatan_kepsek }}" class="form-input" placeholder="Catatan untuk guru (opsional)">
                </div>
                <div class="min-w-36">
                    <label class="form-label">Status</label>
                    <select name="validasi" class="form-select">
                        <option value="belum" @selected($a->validasi!=='valid')>Belum</option>
                        <option value="valid" @selected($a->validasi==='valid')>Valid</option>
                    </select>
                </div>
                <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-bold text-white flex items-center gap-1.5" style="background:var(--cp)"><i data-lucide="check" class="w-4 h-4"></i> Simpan</button>
            </form>
            @if($a->catatan_kepsek)
            <p class="text-xs text-slate-400">Catatan tersimpan: <span class="text-slate-600 dark:text-slate-300">{{ $a->catatan_kepsek }}</span></p>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
