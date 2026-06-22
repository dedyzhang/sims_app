@extends('layouts.app')
@section('title', 'Rekap Nilai')

@push('styles')
<style>
    .sticky-col-no { position: sticky !important; left: 0 !important; z-index: 10 !important; }
    .sticky-col-nama { position: sticky !important; left: 44px; z-index: 10 !important; }
    th.sticky-col-no, th.sticky-col-nama { background-color: color-mix(in srgb, var(--cp) 4%, #f8fafc) !important; }
    .dark th.sticky-col-no, .dark th.sticky-col-nama { background-color: #0f172a !important; }
    td.sticky-col-no, td.sticky-col-nama { background-color: #fff !important; }
    .dark td.sticky-col-no, .dark td.sticky-col-nama { background-color: #1e293b !important; }
    th.sticky-col-nama, td.sticky-col-nama { border-right: 2px solid color-mix(in srgb, var(--cp) 16%, #d8dee9) !important; }
    .dark th.sticky-col-nama, .dark td.sticky-col-nama { border-right-color: #334155 !important; }
    .data-table thead th.text-center { text-align: center !important; }
    .rk-red { background: rgba(239,68,68,.13) !important; color: #dc2626; }
    .dark .rk-red { background: rgba(239,68,68,.22) !important; color: #fca5a5; }
    .rk-cell { cursor: pointer; }
    .rk-cell:hover { box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--cp) 45%, transparent); }
    #rktip { position: fixed; z-index: 9999; pointer-events: none; max-width: 320px; background: #0f172a; color: #fff;
             border-radius: 10px; padding: 9px 12px; font-size: 12px; line-height: 1.45; text-align: left;
             box-shadow: 0 14px 34px -8px rgba(0,0,0,.5); opacity: 0; transition: opacity .12s; transform: translate(-50%,-100%); }
    #rktip.show { opacity: 1; }
    #rktip .rt-h { font-weight: 700; margin-bottom: 4px; }
    #rktip .rt-pos { color: #86efac; }
    #rktip .rt-neg { color: #fca5a5; margin-top: 3px; }
    #rktip::after { content: ''; position: absolute; left: 50%; bottom: -5px; transform: translateX(-50%); border: 5px solid transparent; border-top-color: #0f172a; border-bottom: 0; }
    @media print {
        .no-print { display: none !important; }
        .card { box-shadow: none !important; border: 1px solid #e2e8f0; }
    }
    /* Compact Table Styles */
    .data-table { font-size: 12px !important; }
    .data-table th, .data-table td { padding: 6px 8px !important; }
    .data-table td span { font-size: 10px !important; }
</style>
@endpush

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Rekap Nilai</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $bolehSemua ? 'Rekap nilai per kelas' : 'Rekap nilai kelas Anda (wali kelas)' }}
                @if($sem) &bull; <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $sem->nama_lengkap }}</span> @endif
            </p>
        </div>
        <button type="button" onclick="window.print()" class="no-print flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            <i data-lucide="printer" class="w-4 h-4"></i> Cetak
        </button>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('rekap.nilai') }}" class="no-print card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="form-label">Jenis Nilai</label>
            <select name="jenis" class="form-select" onchange="this.form.submit()">
                @foreach($jenisList as $key => $label)
                <option value="{{ $key }}" @selected($jenis===$key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if($bolehSemua)
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        @else
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <input type="text" value="Kelas {{ $kelasList->first()?->tingkat }}{{ $kelasList->first()?->kelas }}" disabled class="form-input opacity-70">
        </div>
        @endif
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada siswa di kelas ini.</p></div>
    @elseif($ngajars->isEmpty())
    <div class="card p-12 text-center text-slate-400"><i data-lucide="book-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada mata pelajaran untuk kelas ini.</p></div>
    @else
    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 text-sm">
            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $jenisList[$jenis] }}</span>
            <span class="text-slate-400">&bull; Kelas {{ $kelasList->firstWhere('uuid',$selKelas)?->tingkat }}{{ $kelasList->firstWhere('uuid',$selKelas)?->kelas }}</span>
        </div>
        <div class="table-responsive">
            <table class="data-table grid-bordered">
                <thead>
                    <tr>
                        <th class="text-center w-11 sticky-col-no">No</th>
                        <th class="text-left sticky-col-nama">Nama Siswa</th>
                        @foreach($ngajars as $ng)
                        <th class="text-center col-nilai" title="{{ $ng->pelajaran?->nama }}{{ $ng->guru ? ' — '.$ng->guru->nama : '' }} (KKTP {{ $ng->kktp }})">{{ $ng->pelajaran?->kode ?: \Illuminate\Support\Str::limit($ng->pelajaran?->nama, 6, '') }}</th>
                        @endforeach
                        <th class="text-center bg-slate-50 dark:bg-slate-700/40">Rata²</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($siswas as $i => $s)
                    @php
                        $sid = $s->uuid;
                        $vals = collect($ngajars)->map(fn($ng) => $nilai[$sid][$ng->uuid] ?? null)->filter(fn($v) => $v !== null);
                        $rata = $vals->count() ? round($vals->avg()) : null;
                    @endphp
                    <tr>
                        <td class="text-center text-slate-400 sticky-col-no">{{ $i + 1 }}</td>
                        <td class="font-medium text-slate-700 dark:text-slate-200 whitespace-nowrap sticky-col-nama">{{ $s->nama }}<span class="block text-[11px] text-slate-400 font-normal">{{ $s->nis }}</span></td>
                        @foreach($ngajars as $ng)
                        @php $v = $nilai[$sid][$ng->uuid] ?? null; $kktp = $ng->kktp; $dd = $desk[$sid][$ng->uuid] ?? null; @endphp
                        <td class="text-center col-nilai font-semibold {{ ($jenis==='rapor' && $dd && ($dd['pos'] || $dd['neg'])) ? 'rk-cell' : '' }} {{ $v !== null && $v < $kktp ? 'rk-red' : 'text-slate-700 dark:text-slate-200' }}"
                            @if($jenis==='rapor' && $dd) data-pos="{{ $dd['pos'] }}" data-neg="{{ $dd['neg'] }}" data-nama="{{ $s->nama }}" data-mapel="{{ $ng->pelajaran?->nama }}" @endif>{{ $v ?? '·' }}</td>
                        @endforeach
                        <td class="text-center font-bold bg-slate-50 dark:bg-slate-700/40 text-slate-800 dark:text-slate-100">{{ $rata ?? '–' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-slate-400">
            <span>Kode = mata pelajaran (arahkan kursor untuk nama lengkap)</span>
            <span><b class="text-rose-500">Merah</b> = di bawah KKTP</span>
            <span>· = belum ada nilai</span>
            @if($jenis==='rapor')<span><b class="text-primary">Klik / arahkan kursor</b> ke nilai untuk lihat deskripsi capaian</span>@endif
        </div>
    </div>
    @endif
</div>

{{-- Popup deskripsi capaian (hover/tap di rekap Rapor) --}}
<div id="rktip"></div>
@endsection

@push('scripts')
<script>
(function () {
    const tip = document.getElementById('rktip');
    if (!tip) return;
    const esc = (s) => (s || '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
    function show(el) {
        const pos = el.dataset.pos || '', neg = el.dataset.neg || '';
        if (!pos && !neg) return;
        let html = '<div class="rt-h">' + esc(el.dataset.nama) + ' — ' + esc(el.dataset.mapel) + '</div>';
        if (pos) html += '<div class="rt-pos">+ ' + esc(pos) + '</div>';
        if (neg) html += '<div class="rt-neg">− ' + esc(neg) + '</div>';
        tip.innerHTML = html;
        const r = el.getBoundingClientRect();
        tip.style.left = (r.left + r.width / 2) + 'px';
        tip.style.top = (r.top - 8) + 'px';
        tip.classList.add('show');
    }
    function hide() { tip.classList.remove('show'); }
    document.querySelectorAll('.rk-cell').forEach(c => {
        c.addEventListener('mouseenter', () => show(c));
        c.addEventListener('mouseleave', hide);
        c.addEventListener('click', (e) => { e.stopPropagation(); show(c); });
    });
    document.addEventListener('click', hide);
    window.addEventListener('scroll', hide, true);
})();
</script>
@endpush
