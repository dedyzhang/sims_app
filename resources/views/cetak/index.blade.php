@extends('layouts.app')
@section('title', 'Cetak Rapor')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Cetak Rapor</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $bolehSemua ? 'Cetak rapor peserta didik per kelas' : 'Cetak rapor kelas Anda (wali kelas)' }}
                @if($sem) &bull; <span class="font-semibold text-slate-600 dark:text-slate-300">Semester {{ $sem->semester }} &middot; {{ $sem->tahun }}</span> @endif
            </p>
        </div>
        @if($siswas->isNotEmpty())
        <a href="{{ route('cetak.rapor', ['kelas' => $selKelas]) }}" target="_blank"
           class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:opacity-90 transition" style="background:var(--cp)">
            <i data-lucide="printer" class="w-4 h-4"></i> Cetak Satu Kelas
        </a>
        @endif
    </div>

    @if($bolehSemua)
    <form method="GET" action="{{ route('cetak.rapor.index') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>
    @endif

    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @else
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200">
            Daftar Peserta Didik &mdash; Kelas {{ $kelasList->firstWhere('uuid',$selKelas)?->tingkat }}{{ $kelasList->firstWhere('uuid',$selKelas)?->kelas }}
            <span class="text-slate-400 font-normal">({{ $siswas->count() }} siswa)</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center w-12">No</th>
                    <th class="text-left">Nama</th>
                    <th class="text-left">NIS</th>
                    <th class="text-center w-28">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($siswas as $i => $s)
                <tr>
                    <td class="text-center text-slate-400">{{ $i + 1 }}</td>
                    <td class="font-medium text-slate-700 dark:text-slate-200">{{ $s->nama }}</td>
                    <td class="text-slate-500">{{ $s->nis }}</td>
                    <td class="text-center">
                        <a href="{{ route('cetak.rapor', ['kelas' => $selKelas, 'siswa' => $s->uuid]) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                            <i data-lucide="printer" class="w-3.5 h-3.5"></i> Cetak
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
