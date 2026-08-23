@extends('layouts.app')
@section('title', 'Rekap Agenda')

@section('content')
<div class="space-y-5">

    <div>
        <h1 class="page-title">Rekap Agenda Guru</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pantau agenda mengajar guru — yang sudah & belum diisi — per periode</p>
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

    @if($daftar->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="calendar-search" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Tidak ada jam mengajar pada periode ini.</p>
    </div>
    @else
    {{-- Ringkasan --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="card p-4"><p class="text-xs text-slate-400">Total Jam</p><p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ $daftar->count() }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-400">Sudah Diisi</p><p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $sudah }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-400">Belum Diisi</p><p class="text-2xl font-extrabold {{ $belum > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">{{ $belum }}</p></div>
    </div>

    <div class="space-y-6">
        @foreach($daftar->groupBy('tanggal') as $tgl => $items)
        @php
            $first = $items->first();
            $belumHari = $items->filter(fn($x)=>!$x['agenda'] && $x['wajib'])->count();
            $adaWajib = $items->contains(fn($x)=>$x['wajib']);
            $c = \Carbon\Carbon::parse($tgl)->locale('id');
        @endphp
        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-slate-100 dark:border-slate-700/60 first:pt-0 first:border-0">

            {{-- Rail tanggal --}}
            <div class="sm:w-44 flex-shrink-0">
                <div class="sm:sticky sm:top-24 flex sm:flex-col items-center sm:items-start gap-2 sm:gap-1.5">
                    <div class="flex items-baseline sm:items-start gap-2 sm:gap-0 sm:flex-col">
                        <p class="font-extrabold text-slate-800 dark:text-slate-100 leading-none">{{ $c->isoFormat('dddd') }}</p>
                        <p class="text-sm text-slate-400 dark:text-slate-500 sm:mt-1">{{ $c->isoFormat('D MMMM Y') }}</p>
                    </div>
                    @if($first['hari_ini'] ?? false)<span class="badge bg-amber-500 text-white font-semibold">Hari ini</span>@endif
                    @if($belumHari > 0)
                    <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-semibold">{{ $belumHari }} belum diisi</span>
                    @elseif($adaWajib)
                    <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold">Lengkap</span>
                    @else
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 font-semibold">Tidak wajib</span>
                    @endif
                </div>
            </div>

            {{-- Grid kartu --}}
            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 items-start">
                @foreach($items->sortBy('jam_mulai') as $d)
                @php $a = $d['agenda']; @endphp

                @if($a)
                {{-- Terisi: kartu ringkas + expand detail/validasi --}}
                <div class="card p-4 flex flex-col gap-2.5" x-data="{ open:false }">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $d['pelajaran'] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-col gap-0.5">
                                <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3.5 h-3.5 text-slate-400"></i> Kelas {{ $d['kelas'] }}</span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i> {{ $d['jam_mulai'] }}–{{ $d['jam_selesai'] }}</span>
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                            <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Sudah diisi</span>
                            @if(!empty($d['is_orphaned']))
                            <span class="badge bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 font-semibold text-[10px]">Jadwal Lama</span>
                            @endif
                        </div>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 border-t border-slate-100 dark:border-slate-700 pt-2">
                        <span class="font-semibold text-slate-600 dark:text-slate-300">Pembahasan:</span> {{ \Illuminate\Support\Str::limit($a->pembahasan, 90) }}
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        @if($a->validasi==='valid')
                        <span class="badge bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1"><i data-lucide="badge-check" class="w-3 h-3"></i> Tervalidasi</span>
                        @else
                        <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">Belum divalidasi</span>
                        @endif
                        <button type="button" @click="open=true" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Detail
                        </button>
                    </div>

                    {{-- Modal detail + validasi --}}
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-[9998] flex items-center justify-center p-4" @keydown.escape.window="open=false">
                            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open=false"
                                 x-show="open" x-transition.opacity></div>
                            <div class="relative card w-full max-w-lg max-h-[88vh] overflow-y-auto p-5 space-y-4"
                                 x-show="open" x-transition.scale.origin.center @click.outside="open=false">
                                {{-- Header --}}
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100">{{ $d['pelajaran'] }}</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelas {{ $d['kelas'] }} · {{ $d['jam_mulai'] }}–{{ $d['jam_selesai'] }}@if(!empty($d['tanggal_label'])) · {{ $d['tanggal_label'] }}@endif</p>
                                    </div>
                                    <button type="button" @click="open=false" class="p-1.5 -mt-1 -mr-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"><i data-lucide="x" class="w-5 h-5"></i></button>
                                </div>

                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="badge bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Sudah diisi</span>
                                    <span class="badge {{ $a->proses==='selesai' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300' }}">{{ $a->proses==='selesai' ? 'Selesai' : 'Belum Selesai' }}</span>
                                    @if($a->validasi==='valid')<span class="badge bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1"><i data-lucide="badge-check" class="w-3 h-3"></i> Tervalidasi</span>@endif
                                </div>

                                {{-- Detail --}}
                                <div class="space-y-2.5 text-sm border-t border-slate-100 dark:border-slate-700 pt-3">
                                    <div><p class="text-[11px] font-semibold text-slate-400">Pembahasan</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->pembahasan ?: '-' }}</p></div>
                                    <div><p class="text-[11px] font-semibold text-slate-400">Metode</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->metode ?: '-' }}</p></div>
                                    <div><p class="text-[11px] font-semibold text-slate-400">Kegiatan</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->kegiatan ?: '-' }}</p></div>
                                    <div><p class="text-[11px] font-semibold text-slate-400">Kendala &amp; Tindak Lanjut</p><p class="text-slate-700 dark:text-slate-200 whitespace-pre-wrap">{{ $a->kendala ?: '-' }}</p></div>
                                </div>

                                @if($a->absensi->isNotEmpty())
                                <div class="border-t border-slate-100 dark:border-slate-700 pt-3">
                                    <p class="text-[11px] font-semibold text-slate-400 mb-1.5">Ketidakhadiran</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($a->absensi as $ab)
                                        <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-[11px]">{{ $ab->siswa?->nama ?? '-' }}: <b class="ml-0.5">{{ \App\Models\Agenda::ABSENSI[$ab->absensi] ?? $ab->absensi }}</b>@if($ab->keterangan) ({{ $ab->keterangan }})@endif</span>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                {{-- Validasi --}}
                                <form method="POST" action="{{ route('agenda.validasi', $a) }}" class="space-y-2 border-t border-slate-100 dark:border-slate-700 pt-3">
                                    @csrf
                                    <div>
                                        <label class="form-label !mb-1">Catatan Pimpinan</label>
                                        <input type="text" name="catatan_kepsek" value="{{ $a->catatan_kepsek }}" class="form-input text-sm" placeholder="Opsional">
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <div class="flex-1">
                                            <label class="form-label !mb-1">Status Validasi</label>
                                            <select name="validasi" class="form-select text-sm">
                                                <option value="belum" @selected($a->validasi!=='valid')>Belum</option>
                                                <option value="valid" @selected($a->validasi==='valid')>Valid</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="px-4 py-2 rounded-lg text-xs font-bold text-white flex items-center gap-1.5" style="background:var(--cp)"><i data-lucide="check" class="w-3.5 h-3.5"></i> Simpan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>

                @else
                {{-- Belum diisi / tidak wajib: kartu ringkas --}}
                <div class="card p-4 flex flex-col gap-2.5 {{ $d['wajib'] ? 'border-amber-200 dark:border-amber-800 bg-amber-50/30 dark:bg-amber-900/10' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $d['pelajaran'] }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-col gap-0.5">
                                <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3.5 h-3.5 text-slate-400"></i> Kelas {{ $d['kelas'] }}</span>
                                <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i> {{ $d['jam_mulai'] }}–{{ $d['jam_selesai'] }}</span>
                            </p>
                        </div>
                        @if($d['wajib'])
                        <span class="badge bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 font-semibold flex-shrink-0">Belum diisi</span>
                        @else
                        <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 font-semibold flex-shrink-0">Tidak wajib</span>
                        @endif
                    </div>
                </div>
                @endif

                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
