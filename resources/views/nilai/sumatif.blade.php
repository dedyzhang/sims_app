@extends('layouts.app')
@section('title', 'Nilai Sumatif')

@section('content')
@include('nilai._tabs')
@include('nilai._autosave')
@include('nilai._materipop')

<div class="mt-5">
    @include('nilai._terkunci')
    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @elseif($materi->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="list-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada materi.</p>
        <a href="{{ route('nilai.materi', $ngajar->uuid) }}" class="text-primary hover:underline text-sm mt-1 inline-block">Buat materi dulu</a>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="flex items-start gap-2 px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 text-xs text-slate-400">
            <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i>
            <span>Nilai sumatif per materi (0–100) — <b class="text-emerald-600">tersimpan otomatis</b> saat pindah sel.</span>
        </div>
        <div class="table-responsive">
            <table class="data-table grid-bordered">
                <thead>
                    <tr>
                        <th class="text-center w-12 sticky-col-no">No</th>
                        <th class="text-left sticky-col-nama">Nama Siswa</th>
                        @foreach($materi as $m)
                        <th class="text-center col-nilai">
                            <span class="materi-pop cursor-help font-bold border-b border-dotted border-slate-300 dark:border-slate-500" data-nama="{{ $m->nama }}" data-tps='@json($m->tujuan->pluck("tupe")->values())'>M{{ $loop->iteration }}</span>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($siswas as $i => $s)
                    <tr>
                        <td class="text-center text-slate-400 sticky-col-no">{{ $i + 1 }}</td>
                        <td class="font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap sticky-col-nama">{{ $s->nama }}</td>
                        @foreach($materi as $ki => $m)
                        <td class="text-center col-nilai">
                            <div class="nilai-cell" contenteditable="{{ $terkunci ? 'false' : 'true' }}" inputmode="numeric" data-col="{{ $ki }}" data-kkm="{{ $kktp }}"
                                 data-url="{{ route('nilai.sumatif.cell', $ngajar->uuid) }}"
                                 data-body='@json(["id_materi" => $m->uuid, "id_siswa" => $s->uuid])'>{{ isset($skor[$m->uuid][$s->uuid]) ? (int) $skor[$m->uuid][$s->uuid] : '' }}</div>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
