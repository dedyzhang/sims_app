@extends('layouts.app')
@section('title', 'Nilai Formatif')

@section('content')
@include('nilai._tabs')
@include('nilai._autosave')
@include('nilai._materipop')

@php
    $materiIsi = $materi->filter(fn($m) => $m->tujuan->count() > 0)->values();
    // index kolom global tiap TP (untuk navigasi panah ↑↓)
    $colOf = [];
    $c = 0;
    foreach ($materiIsi as $mm) { foreach ($mm->tujuan as $tt) { $colOf[$tt->uuid] = $c++; } }
@endphp

<div class="mt-5">
    @include('nilai._terkunci')
    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @elseif($materiIsi->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="list-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada materi/TP.</p>
        <a href="{{ route('nilai.materi', $ngajar->uuid) }}" class="text-primary hover:underline text-sm mt-1 inline-block">Buat materi & Tujuan Pembelajaran dulu</a>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between gap-3 flex-wrap px-4 py-2.5 border-b border-slate-100 dark:border-slate-700">
            <p class="text-xs text-slate-400 flex items-start gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 flex-shrink-0"></i>
                <span>Klik nama materi untuk buka/tutup TP. Nilai 0–100 tersimpan otomatis; kolom <b>RT</b> (Rata-rata) dihitung otomatis.</span>
            </p>
            <div class="flex items-center gap-1.5">
                <button type="button" onclick="toggleSemuaMateri(true)" class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Buka semua</button>
                <button type="button" onclick="toggleSemuaMateri(false)" class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">Tutup semua</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table grid-bordered">
                <thead>
                    <tr>
                        <th class="text-center w-12 sticky-col-no" rowspan="2">No</th>
                        <th class="text-left sticky-col-nama" rowspan="2">Nama Siswa</th>
                        @foreach($materiIsi as $m)
                            <th class="text-center materi-head" data-materi="{{ $m->uuid }}" data-ntp="{{ $m->tujuan->count() }}" colspan="1">
                                <button type="button" class="materi-toggle w-full flex items-center justify-center gap-1 font-bold hover:text-primary transition"
                                        data-materi="{{ $m->uuid }}" data-nama="{{ $m->nama }}"
                                        data-tps='@json($m->tujuan->pluck("tupe")->values())'
                                        onclick="toggleMateri('{{ $m->uuid }}')">
                                    <span class="materi-chevron inline-block transition-transform" data-materi="{{ $m->uuid }}" style="transform:rotate(-90deg)"><i data-lucide="chevron-down" class="w-4 h-4"></i></span>
                                    M{{ $loop->iteration }}
                                </button>
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($materiIsi as $m)
                            @foreach($m->tujuan as $ti => $t)
                            <th class="text-center font-normal col-nilai tp-col" data-materi-col="{{ $m->uuid }}" style="display:none" title="{{ $t->tupe }}">TP{{ $ti + 1 }}</th>
                            @endforeach
                            <th class="text-center col-nilai avg-head" title="Rata-rata TP {{ $m->nama }}">RT</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($siswas as $i => $s)
                    <tr>
                        <td class="text-center text-slate-400 sticky-col-no">{{ $i + 1 }}</td>
                        <td class="font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap sticky-col-nama">{{ $s->nama }}</td>
                        @foreach($materiIsi as $m)
                            @foreach($m->tujuan as $t)
                            <td class="text-center col-nilai tp-col" data-materi-col="{{ $m->uuid }}" style="display:none">
                                <div class="nilai-cell formatif-cell" contenteditable="{{ $terkunci ? 'false' : 'true' }}" inputmode="numeric"
                                     data-col="{{ $colOf[$t->uuid] }}" data-materi="{{ $m->uuid }}" data-siswa="{{ $s->uuid }}" data-kkm="{{ $kktp }}"
                                     data-url="{{ route('nilai.formatif.cell', $ngajar->uuid) }}"
                                     data-body='@json(["id_tupe" => $t->uuid, "id_siswa" => $s->uuid])'>{{ isset($skor[$t->uuid][$s->uuid]) ? (int) $skor[$t->uuid][$s->uuid] : '' }}</div>
                            </td>
                            @endforeach
                            <td class="text-center col-nilai avg-col font-bold text-slate-700 dark:text-slate-200 bg-slate-50/70 dark:bg-slate-700/30">
                                <span class="avg-val" data-materi="{{ $m->uuid }}" data-siswa="{{ $s->uuid }}" data-kkm="{{ $kktp }}">–</span>
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

@push('scripts')
<script>
(function () {
    const open = {};   // status buka/tutup per materi (default: tutup)

    window.toggleMateri = function (id) {
        open[id] = !open[id];
        document.querySelectorAll('[data-materi-col="' + id + '"]').forEach(el => {
            el.style.display = open[id] ? '' : 'none';
        });
        document.querySelectorAll('.materi-chevron[data-materi="' + id + '"]').forEach(ch => {
            ch.style.transform = open[id] ? 'rotate(0deg)' : 'rotate(-90deg)';
        });
        // colspan header materi = jumlah kolom yang terlihat (TP terbuka + 1 kolom Rata²)
        const head = document.querySelector('th.materi-head[data-materi="' + id + '"]');
        if (head) {
            const n = parseInt(head.dataset.ntp || '0', 10);
            head.colSpan = open[id] ? (n + 1) : 1;
        }
    };

    window.toggleSemuaMateri = function (buka) {
        document.querySelectorAll('.materi-toggle').forEach(btn => {
            const id = btn.dataset.materi;
            if (!!open[id] !== buka) window.toggleMateri(id);
        });
    };

    function hitungAvg(materi, siswa) {
        let sum = 0, n = 0;
        document.querySelectorAll('.formatif-cell[data-materi="' + materi + '"][data-siswa="' + siswa + '"]').forEach(c => {
            const v = (c.textContent || '').replace(/[^0-9]/g, '');
            if (v !== '') { sum += parseInt(v, 10); n++; }
        });
        return n ? Math.round(sum / n) : null;
    }
    function refreshAvg(materi, siswa) {
        const el = document.querySelector('.avg-val[data-materi="' + materi + '"][data-siswa="' + siswa + '"]');
        if (!el) return;
        const a = hitungAvg(materi, siswa);
        el.textContent = (a === null ? '–' : a);
        const kkm = parseInt(el.dataset.kkm || '', 10);
        el.closest('td').classList.toggle('avg-below', a !== null && !isNaN(kkm) && a < kkm);
    }
    function refreshSemua() {
        document.querySelectorAll('.avg-val').forEach(el => refreshAvg(el.dataset.materi, el.dataset.siswa));
    }

    // hitung ulang rata-rata saat mengetik & setelah pindah/simpan sel
    document.addEventListener('input', e => {
        if (e.target.classList && e.target.classList.contains('formatif-cell'))
            refreshAvg(e.target.dataset.materi, e.target.dataset.siswa);
    });
    document.addEventListener('focusout', e => {
        if (e.target.classList && e.target.classList.contains('formatif-cell'))
            setTimeout(() => refreshAvg(e.target.dataset.materi, e.target.dataset.siswa), 0);
    });

    refreshSemua();
})();
</script>
@endpush
