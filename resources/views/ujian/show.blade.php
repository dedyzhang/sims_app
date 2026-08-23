@extends('layouts.app')
@section('title', $ujian->judul)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> / {{ $ujian->judul }}</nav>
        <div class="flex items-center gap-2 flex-wrap">
            <h1 class="page-title">{{ $ujian->judul }}</h1>
            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $ujian->jenisLabel() }}</span>
            @php
                $statusBadge = match($ujian->status) {
                    'published' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                    'closed' => 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300',
                    default => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                };
            @endphp
            <span class="badge {{ $statusBadge }}">{{ $ujian->statusLabel() }}</span>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $ujian->pelajaran?->nama }} · Target nilai: {{ strtoupper($ujian->target_nilai) }} · {{ $ujian->durasi_menit }} menit</p>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('ujian.edit', $ujian) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="list-checks" class="w-4 h-4 inline"></i> Susun Soal ({{ $ujian->soal->count() }})
        </a>
        <a href="{{ route('ujian.pengaturan.edit', $ujian) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="settings" class="w-4 h-4 inline"></i> Pengaturan
        </a>
        @unless($ujian->status === 'draft')
        <a href="{{ route('ujian.monitor.index', $ujian) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="radar" class="w-4 h-4 inline"></i> Pemantauan Live
        </a>
        @endunless
        {{-- Nilai Esai/Hasil/Pembahasan TIDAK ikut disembunyikan saat draft — ujian yg dibuka
             kembali dari status ditutup (utk revisi soal) balik jadi draft juga, tapi data
             hasil/attempt lama yg sudah ada tak boleh "hilang" dari UI cuma krn itu. Cuma
             ujian draft yg BELUM PERNAH pakai kelas (baru dibuat) yg link ini disembunyikan. --}}
        @unless($ujian->status === 'draft' && $ujian->kelas->isEmpty())
        <a href="{{ route('ujian.grading.index', $ujian) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="pencil-line" class="w-4 h-4 inline"></i> Nilai Esai
        </a>
        <a href="{{ route('ujian.hasil.index', $ujian) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="table-2" class="w-4 h-4 inline"></i> Hasil & Transfer
        </a>
        <a href="{{ route('ujian.analisis.index', $ujian) }}" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
            <i data-lucide="file-spreadsheet" class="w-4 h-4 inline"></i> Analisis Hasil
        </a>
        <form method="POST" action="{{ route('ujian.pembahasan.toggle', $ujian) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold border {{ $ujian->tampilkan_pembahasan ? 'border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20' : 'border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                <i data-lucide="{{ $ujian->tampilkan_pembahasan ? 'eye' : 'eye-off' }}" class="w-4 h-4 inline"></i>
                Pembahasan {{ $ujian->tampilkan_pembahasan ? 'Dirilis' : 'Disembunyikan' }}
            </button>
        </form>
        @endunless
        @if($ujian->status === 'draft')
        <form method="POST" action="{{ route('ujian.publish', $ujian) }}">
            @csrf
            <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-bold"><i data-lucide="send" class="w-4 h-4 inline"></i> Terbitkan</button>
        </form>
        @elseif($ujian->status === 'published')
        <form method="POST" action="{{ route('ujian.close', $ujian) }}" onsubmit="return confirmAction(this, 'Tutup ujian ini? Siswa tidak bisa lagi memulai/melanjutkan.', 'red')">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold bg-rose-600 text-white hover:bg-rose-700">Tutup Ujian</button>
        </form>
        @elseif($ujian->status === 'closed')
        <form method="POST" action="{{ route('ujian.reopen', $ujian) }}" onsubmit="return confirmAction(this, 'Buka kembali ujian ini? Statusnya balik jadi draf — soal bisa diedit lagi, siswa belum bisa mulai sampai diterbitkan ulang.', 'orange')">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold border border-amber-300 dark:border-amber-700 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                <i data-lucide="rotate-ccw" class="w-4 h-4 inline"></i> Buka Kembali
            </button>
        </form>
        @endif
    </div>

    {{-- Kelas ter-assign --}}
    <div class="card p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Kelas & Token Masuk</h2>
        </div>
        @if($ujian->butuhSatuKelas())
        <p class="text-xs text-amber-600 dark:text-amber-400">Target nilai Sumatif — ujian ini hanya boleh untuk satu kelas.</p>
        @elseif($bolehAturKelas)
        <p class="text-xs text-slate-400">Cuma kelas yang benar-benar diajar mapel ini (via data Ngajar) yang muncul di sini. Token masuk dibagi rata per TINGKAT — semua kelas dalam satu tingkat memakai token yang sama.</p>
        @else
        <p class="text-xs text-slate-400">Kelas ditetapkan oleh admin/pengelola. Token masuk dibagi rata per TINGKAT — semua kelas dalam satu tingkat memakai token yang sama.</p>
        @endif

        @if($bolehAturKelas)
        @php
            // Gabung kelas yg valid (via Ngajar) DENGAN kelas yg SUDAH ter-assign sebelumnya —
            // supaya kelas lama yg mungkin sudah tak "valid" lagi (mis. Ngajar-nya dihapus, atau
            // mapel ujian baru diganti) tetap kelihatan di sini (bisa dilepas manual), bukan
            // diam2 hilang dari daftar pilihan tanpa penjelasan.
            $kelasOpsi = $kelasPilihan->concat($ujian->kelas->pluck('kelas')->filter())
                ->unique('uuid')->sortBy(fn($k) => [$k->tingkat, $k->kelas]);
        @endphp
        <form method="POST" action="{{ route('ujian.kelas.sync', $ujian) }}" class="space-y-3">
            @csrf
            <select name="id_kelas[]" multiple {{ $ujian->butuhSatuKelas() ? '' : 'multiple' }} class="form-select h-32" {{ $ujian->isClosed() ? 'disabled' : '' }}>
                @forelse($kelasOpsi as $k)
                    <option value="{{ $k->uuid }}" @selected($ujian->kelas->pluck('id_kelas')->contains($k->uuid))>{{ $k->tingkat }}{{ $k->kelas }}</option>
                @empty
                    <option value="" disabled>Belum ada kelas yang diajar mapel ini (cek data Ngajar)</option>
                @endforelse
            </select>
            @unless($ujian->isClosed())
            <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">Simpan Kelas</button>
            @endunless
        </form>
        @endif

        {{-- Guru pengampu SEMUA kelas disimpan lewat SATU form + SATU tombol (bukan
             form+tombol per kelas) — form-nya sengaja ditaruh di sini (bukan membungkus
             listing di bawah) krn tiap tingkat juga punya form "Buat Token Baru" sendiri,
             dan form HTML tidak boleh bersarang. Tiap <select> pengampu di bawah dikaitkan
             ke form ini lewat atribut form="form-pengampu", bukan lewat nesting DOM. --}}
        @unless($ujian->isClosed())
        <form id="form-pengampu" method="POST" action="{{ route('ujian.kelas.pengampu.batch', $ujian) }}">@csrf</form>
        @endunless

        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($ujian->kelas->sortBy(fn($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas])->groupBy(fn($uk) => $uk->kelas?->tingkat) as $tingkat => $grup)
            <div class="py-3 space-y-2.5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-sm">
                            @if($tingkat)Tingkat {{ $tingkat }} — @endif
                            {{ $grup->map(fn($uk) => $uk->kelas?->tingkat . $uk->kelas?->kelas)->filter()->implode(', ') }}
                        </p>
                        <p class="text-xs font-mono text-slate-500 mt-0.5">Token: <b>{{ $grup->first()->token_masuk ?? '—' }}</b></p>
                    </div>
                    @unless($ujian->isClosed())
                    <form method="POST" action="{{ route('ujian.kelas.token', [$ujian, $grup->first()]) }}" onsubmit="return confirmAction(this, 'Buat token baru untuk SEMUA kelas tingkat {{ $tingkat }}? Token lama tidak berlaku lagi.', 'orange')">
                        @csrf
                        <button type="submit" class="text-xs text-primary hover:underline">Buat Token Baru</button>
                    </form>
                    @endunless
                </div>

                {{-- Guru pengampu — per KELAS (bukan per tingkat), krn kelas berbeda dlm satu
                     tingkat bisa diajar guru berbeda. Dropdown SELALU tampil (bukan cuma
                     saat 2+ kandidat) supaya admin bisa ganti pengampu kapan saja; pilihan
                     tetap dibatasi ke guru yg beneran punya data Ngajar mapel ini di kelas
                     itu — kalau guru yg dicari belum muncul, tambah dulu di Data Ngajar. --}}
                <div class="pl-3 border-l-2 border-slate-100 dark:border-slate-700 space-y-1.5">
                    @foreach($grup as $uk)
                    @php $opsiGuru = $guruPengampuOpsi[$uk->uuid] ?? collect(); @endphp
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="font-medium text-slate-500 dark:text-slate-400">{{ $uk->kelas?->tingkat }}{{ $uk->kelas?->kelas }}</span>
                        @if(!$ujian->isClosed())
                        <div class="flex items-center gap-1.5">
                            <select name="pengampu[{{ $uk->uuid }}]" form="form-pengampu" class="form-select py-1 text-xs" @if($opsiGuru->isEmpty()) disabled @endif>
                                <option value="">— pilih pengampu —</option>
                                @foreach($opsiGuru as $g)
                                    <option value="{{ $g->uuid }}" @selected($uk->id_guru_pengampu === $g->uuid)>{{ $g->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if($opsiGuru->isEmpty())
                        <span class="text-slate-400 italic">(belum ada guru terdaftar di Ngajar utk mapel+kelas ini)</span>
                        @endif
                        @else
                        <span class="text-slate-400">Pengampu: {{ $uk->guruPengampu?->nama ?? '—' }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-400 py-3">Belum ada kelas ditetapkan.</p>
            @endforelse
        </div>

        @unless($ujian->isClosed() || $ujian->kelas->isEmpty())
        <div class="pt-3 text-right">
            <button type="submit" form="form-pengampu" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold">Simpan Guru Pengampu</button>
        </div>
        @endunless
    </div>
</div>
@endsection
