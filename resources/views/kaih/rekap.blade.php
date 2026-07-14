@extends('layouts.app')
@section('title', 'Rekap 7 KAIH')

@push('styles')
<style>
    .rekap-grid { border-collapse:separate; border-spacing:0; }
    .rekap-grid th, .rekap-grid td { border-bottom:1px solid #f1ede7; white-space:nowrap; }
    .dark .rekap-grid th, .dark .rekap-grid td { border-color:#293548; }
    .rekap-grid .col-nama { position:sticky; left:0; z-index:2; background:#fff; box-shadow:1px 0 0 #eee; }
    .dark .rekap-grid .col-nama { background:#1e293b; box-shadow:1px 0 0 #334155; }
    .rekap-grid thead .col-nama { z-index:3; }
    .col-libur { background:#fbf7f2; }
    .dark .col-libur { background:#0f172a; }
</style>
@endpush

@section('content')
<div class="space-y-5" x-data="{ rincian:false }">
    <div>
        <h1 class="page-title">Rekap 7 KAIH</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Bobot nilai kebiasaan harian siswa &bull; skor maksimal {{ $maxSkor }} / hari</p>
    </div>

    {{-- Mode: per hari vs per rentang tanggal --}}
    <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 w-max">
        <a href="{{ route('kaih.rekap', array_filter(['kelas' => $selectedKelas, 'tampilan' => 'harian'])) }}" class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ $tampilan==='harian' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500' }}">Per Hari</a>
        <a href="{{ route('kaih.rekap', array_filter(['kelas' => $selectedKelas, 'tampilan' => 'rentang'])) }}" class="px-4 py-2 rounded-lg text-sm font-semibold transition {{ $tampilan==='rentang' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500' }}">Per Rentang Tanggal</a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('kaih.rekap') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="tampilan" value="{{ $tampilan }}">
        @if($kelasList->count() > 1)
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selectedKelas===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        @endif

        @if($tampilan === 'harian')
        <div class="min-w-40">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input" onchange="this.form.submit()">
        </div>
        @else
        <div class="min-w-36">
            <label class="form-label">Dari tanggal</label>
            <input type="date" name="dari" value="{{ $dari }}" class="form-input" onchange="this.form.submit()">
        </div>
        <div class="min-w-36">
            <label class="form-label">Sampai tanggal</label>
            <input type="date" name="sampai" value="{{ $sampai }}" class="form-input" onchange="this.form.submit()">
        </div>
        @endif
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Tampilkan</button>
    </form>

    @if($siswas->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada siswa di kelas ini.</p>
    </div>

    @elseif($tampilan === 'harian')
    {{-- ══════════════════════ MODE: PER HARI ══════════════════════ --}}
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-700">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $siswas->count() }} siswa &bull; {{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMMM Y') }}</p>
        </div>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($siswas as $i => $s)
            @php $j = $jawabanHarian->get($s->uuid); @endphp
            <div x-data="{ open:false }" @keydown.escape.window="open=false">
                <div class="p-3.5 flex items-center gap-3 flex-wrap sm:flex-nowrap hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                    <span class="text-xs text-slate-400 w-5 flex-shrink-0">{{ $i + 1 }}</span>
                    <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background:{{ $s->jk==='L' ? 'var(--cp)' : '#ec4899' }}">{{ strtoupper(substr($s->nama,0,1)) }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $s->nama }}</p>
                        <p class="text-xs text-slate-400 font-mono">{{ $s->nis }}</p>
                    </div>

                    @if(!$j)
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 font-semibold">Belum diisi</span>
                    <a href="{{ route('kaih.override.form', ['siswa' => $s, 'tanggal' => $tanggal, 'tampilan' => 'harian']) }}" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 flex-shrink-0">
                        <i data-lucide="pencil-line" class="w-3.5 h-3.5"></i> Isi Manual
                    </a>
                    @elseif($j->status === 'dilewati')
                    <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-300 font-semibold" title="{{ $j->keterangan }}">Dilewati</span>
                    @else
                    <span class="badge {{ $j->total_skor >= $maxSkor * 0.75 ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-300' : ($j->total_skor >= $maxSkor * 0.5 ? 'bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-300' : 'bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-300') }} font-bold">
                        Skor {{ $j->total_skor }} / {{ $maxSkor }}
                    </span>
                    @if($j->diisi_oleh)<span class="text-[11px] text-slate-400" title="Diisi manual oleh admin/walikelas"><i data-lucide="user-cog" class="w-3 h-3 inline"></i></span>@endif
                    <button type="button" @click="open=true" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1 flex-shrink-0">
                        <i data-lucide="list-checks" class="w-3.5 h-3.5"></i> Lihat Jawaban
                    </button>
                    @endif
                </div>

                @if($j && $j->status === 'diisi')
                <template x-teleport="body">
                    <div x-show="open" x-cloak class="modal-backdrop" x-transition @click.self="open=false">
                        <div class="modal-box max-w-lg w-full" @click.stop>
                            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                                <div class="min-w-0">
                                    <h3 class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $s->nama }}</h3>
                                    <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMMM Y') }} &bull; Skor {{ $j->total_skor }} / {{ $maxSkor }}</p>
                                </div>
                                <button @click="open=false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 flex-shrink-0"><i data-lucide="x" class="w-4 h-4"></i></button>
                            </div>
                            <div class="p-5 space-y-3 max-h-[70vh] overflow-y-auto">
                                @foreach($j->detail->sortBy('pertanyaan.urutan') as $d)
                                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                                    <p class="text-xs font-bold uppercase tracking-wide text-primary">{{ $d->pertanyaan?->kebiasaan ?? 'Pertanyaan telah dihapus' }}</p>
                                    @if($d->pertanyaan?->pertanyaan)
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $d->pertanyaan->pertanyaan }}</p>
                                    @endif
                                    <p class="text-sm text-slate-700 dark:text-slate-200 mt-1.5 flex items-center justify-between gap-2">
                                        <span>{{ $d->opsi?->label ?? '-' }}</span>
                                        <span class="badge bg-primary/10 text-primary font-bold flex-shrink-0">Bobot {{ $d->bobot }}</span>
                                    </p>
                                </div>
                                @endforeach
                                @if($j->refleksi)
                                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1"><i data-lucide="pencil-line" class="w-3 h-3"></i> Refleksi Hari Ini</p>
                                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-0.5 whitespace-pre-line">{{ $j->refleksi }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </template>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    @else
    {{-- ══════════════════════ MODE: PER RENTANG TANGGAL ══════════════════════ --}}
    <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 w-max">
        <button @click="rincian=false" :class="!rincian ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Ringkasan</button>
        <button @click="rincian=true" :class="rincian ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Rincian per Tanggal</button>
    </div>

    {{-- Ringkasan --}}
    <div x-show="!rincian" class="card overflow-hidden">
        <div class="table-responsive overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Nama Siswa</th>
                        <th class="text-center">Hari Diisi</th>
                        <th class="text-center">Dilewati</th>
                        <th class="text-center">Total Skor</th>
                        <th class="text-center">Rata-rata / Hari</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rekap as $i => $r)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $i+1 }}</td>
                        <td>
                            <p class="font-medium text-slate-800 dark:text-slate-200">{{ $r['siswa']->nama }}</p>
                            <p class="text-xs text-slate-400 font-mono">{{ $r['siswa']->nis }}</p>
                        </td>
                        <td class="text-center"><span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">{{ $r['diisi'] }}</span></td>
                        <td class="text-center">
                            @if($r['dilewati'] > 0)
                            <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">{{ $r['dilewati'] }}</span>
                            @else
                            <span class="text-slate-300">&mdash;</span>
                            @endif
                        </td>
                        <td class="text-center font-bold text-slate-700 dark:text-slate-200">{{ $r['totalSkor'] }}</td>
                        <td class="text-center">
                            @php $pct = $maxSkor ? round($r['rataRata']/$maxSkor*100) : 0; @endphp
                            <div class="flex items-center gap-2 justify-center">
                                <div class="w-14 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden hide-mobile">
                                    <div class="h-full rounded-full" style="width:{{ $pct }}%;background:{{ $pct>=75 ? '#10b981' : ($pct>=50 ? '#f59e0b' : '#ef4444') }}"></div>
                                </div>
                                <span class="text-sm font-bold {{ $pct>=75 ? 'text-emerald-600' : ($pct>=50 ? 'text-amber-600' : 'text-rose-600') }}">{{ $r['rataRata'] }}</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Rincian per tanggal --}}
    <div x-show="rincian" x-cloak class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rekap-grid w-full text-xs">
                <thead>
                    <tr>
                        <th class="col-nama text-left px-3 py-2.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">Nama Siswa</th>
                        @foreach($dates as $d)
                        <th class="px-2 py-2 text-center font-semibold {{ $d['libur'] ? 'col-libur text-rose-400' : 'text-slate-500' }}">
                            <div class="text-[10px] opacity-70">{{ $d['hari'] }}</div>
                            <div>{{ $d['tgl'] }}</div>
                        </th>
                        @endforeach
                        <th class="px-3 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-slate-400">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rekap as $r)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/40">
                        <td class="col-nama px-3 py-2 font-medium text-slate-700 dark:text-slate-200 max-w-[160px] truncate">{{ $r['siswa']->nama }}</td>
                        @foreach($dates as $d)
                        @php $row = $r['byDate'][$d['ymd']] ?? null; @endphp
                        <td class="px-2 py-2 text-center {{ $d['libur'] ? 'col-libur' : '' }}">
                            @if(!$row)
                                @if($d['ymd'] <= now()->toDateString())
                                <a href="{{ route('kaih.override.form', ['siswa' => $r['siswa'], 'tanggal' => $d['ymd'], 'tampilan' => 'rentang', 'dari' => $dari, 'sampai' => $sampai]) }}" class="text-slate-300 dark:text-slate-600 hover:text-primary" title="Isi manual utk tanggal ini">&middot;</a>
                                @else
                                <span class="text-slate-200 dark:text-slate-700">&middot;</span>
                                @endif
                            @elseif($row->status === 'dilewati')
                                <span class="text-amber-500 font-bold cursor-help" title="Dilewati: {{ $row->keterangan }}">D</span>
                            @else
                                <div x-data="{ open:false }" class="inline-block" @keydown.escape.window="open=false">
                                    <button type="button" @click="open=true" class="font-bold hover:underline {{ $row->total_skor >= $maxSkor*0.75 ? 'text-emerald-600 dark:text-emerald-400' : ($row->total_skor >= $maxSkor*0.5 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">{{ $row->total_skor }}</button>
                                    <template x-teleport="body">
                                        <div x-show="open" x-cloak class="modal-backdrop" x-transition @click.self="open=false">
                                            <div class="modal-box max-w-lg w-full" @click.stop>
                                                <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                                                    <div class="min-w-0">
                                                        <h3 class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $r['siswa']->nama }}</h3>
                                                        <p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($d['ymd'])->isoFormat('dddd, D MMMM Y') }} &bull; Skor {{ $row->total_skor }} / {{ $maxSkor }}</p>
                                                    </div>
                                                    <button @click="open=false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 flex-shrink-0"><i data-lucide="x" class="w-4 h-4"></i></button>
                                                </div>
                                                <div class="p-5 space-y-3 max-h-[70vh] overflow-y-auto text-left">
                                                    @foreach($row->detail->sortBy('pertanyaan.urutan') as $dd)
                                                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                                                        <p class="text-xs font-bold uppercase tracking-wide text-primary">{{ $dd->pertanyaan?->kebiasaan ?? 'Pertanyaan telah dihapus' }}</p>
                                                        @if($dd->pertanyaan?->pertanyaan)
                                                        <p class="text-xs text-slate-400 mt-0.5">{{ $dd->pertanyaan->pertanyaan }}</p>
                                                        @endif
                                                        <p class="text-sm text-slate-700 dark:text-slate-200 mt-1.5 flex items-center justify-between gap-2">
                                                            <span>{{ $dd->opsi?->label ?? '-' }}</span>
                                                            <span class="badge bg-primary/10 text-primary font-bold flex-shrink-0">Bobot {{ $dd->bobot }}</span>
                                                        </p>
                                                    </div>
                                                    @endforeach
                                                    @if($row->refleksi)
                                                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
                                                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center gap-1"><i data-lucide="pencil-line" class="w-3 h-3"></i> Refleksi Hari Ini</p>
                                                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-0.5 whitespace-pre-line">{{ $row->refleksi }}</p>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            @endif
                        </td>
                        @endforeach
                        <td class="px-3 py-2 text-center font-bold text-slate-700 dark:text-slate-200">{{ $r['totalSkor'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Legenda --}}
        <div class="p-3 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
            <span><span class="text-emerald-600 font-bold">28</span> = skor hari itu (klik utk lihat jawaban)</span>
            <span><span class="text-amber-500 font-bold">D</span> = dilewati</span>
            <span><span class="text-slate-300">&middot;</span> = belum diisi (klik utk isi manual)</span>
        </div>
    </div>
    @endif
</div>
@endsection
