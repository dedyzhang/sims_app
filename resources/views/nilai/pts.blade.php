@extends('layouts.app')
@section('title', 'Nilai PTS')

@section('content')
@include('nilai._tabs')
@include('nilai._autosave')

<div class="mt-5">
    @include('nilai._terkunci')
    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @else
    <div class="card overflow-hidden max-w-xl">
        <div class="flex items-start gap-2 px-4 py-2.5 border-b border-slate-100 dark:border-slate-700 text-xs text-slate-400">
            <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i>
            <span>Penilaian Tengah Semester (0–100) — <b class="text-emerald-600">tersimpan otomatis</b>. <b>Catatan:</b> PTS tidak masuk perhitungan nilai rapor.</span>
        </div>
        <div class="table-responsive">
            <table class="data-table grid-bordered">
                <thead><tr><th class="text-center w-12 sticky-col-no">No</th><th class="text-left sticky-col-nama">Nama Siswa</th><th class="text-center w-28">Nilai PTS</th></tr></thead>
                <tbody>
                    @foreach($siswas as $i => $s)
                    <tr>
                        <td class="text-center text-slate-400 sticky-col-no">{{ $i + 1 }}</td>
                        <td class="font-medium text-slate-700 dark:text-slate-200 sticky-col-nama">{{ $s->nama }}</td>
                        <td class="text-center">
                            <div class="nilai-cell" contenteditable="{{ ($terkunci || !empty($readOnly)) ? 'false' : 'true' }}" inputmode="numeric" data-col="0" data-kkm="{{ $kktp }}"
                                 data-url="{{ route('nilai.pts.cell', $ngajar->uuid) }}"
                                 data-body='@json(["id_siswa" => $s->uuid])'>{{ isset($skor[$s->uuid]) ? (int) $skor[$s->uuid] : '' }}</div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
