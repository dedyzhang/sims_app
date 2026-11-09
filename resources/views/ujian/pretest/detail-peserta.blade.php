@extends('layouts.app')
@section('title', 'Rincian Jawaban Pre-Test — ' . $peserta->nama)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Breadcrumb & Judul --}}
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> /
            <a href="{{ route('ujian.pretest.index', $ujian) }}" class="hover:underline">Pre-Test</a> / Rincian Peserta
        </nav>
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="page-title text-xl font-bold text-slate-800 dark:text-slate-100 m-0">Rincian Jawaban Pre-Test</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Peserta: <strong>{{ $peserta->nama }}</strong> ({{ $peserta->identitas }}) · {{ $ujian->pelajaran?->nama }}
                </p>
            </div>
            <a href="{{ route('ujian.pretest.index', $ujian) }}" class="btn-secondary px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Pre-Test
            </a>
        </div>
    </div>

    {{-- Kartu Ringkasan Hasil Peserta --}}
    <div class="card p-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl">
                <span class="text-xs text-slate-400 block mb-0.5">Nilai Akhir</span>
                <span class="text-2xl font-extrabold {{ $peserta->total_skor >= ($ujian->pelajaran?->kkm ?? 75) ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    {{ number_format($peserta->total_skor, 1) }}
                </span>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl">
                <span class="text-xs text-slate-400 block mb-0.5">Jumlah Benar</span>
                <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $peserta->jumlah_benar }}</span>
                <span class="text-xs text-slate-400">/ {{ $soalList->count() }} soal</span>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl">
                <span class="text-xs text-slate-400 block mb-0.5">Jumlah Salah</span>
                <span class="text-2xl font-extrabold text-rose-500">{{ $peserta->jumlah_salah }}</span>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl">
                <span class="text-xs text-slate-400 block mb-0.5">Waktu Pengerjaan</span>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200 block mt-1">
                    {{ $peserta->mulai_pada?->diffInMinutes($peserta->selesai_pada) ?? 0 }} Menit
                </span>
                <span class="text-[10px] text-slate-400">{{ $peserta->mulai_pada?->translatedFormat('d M, H:i') }}</span>
            </div>
        </div>
    </div>

    {{-- Daftar Jawaban Per Butir Soal --}}
    <div class="space-y-4">
        <h2 class="font-bold text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <i data-lucide="check-square" class="w-4 h-4 text-primary"></i> Evaluasi Butir Soal
        </h2>

        @foreach($soalList as $idx => $soal)
        @php
            $jawaban = $jawabanBySoal->get($soal->uuid);
            $isBenar = $jawaban?->is_benar;
        @endphp
        <div class="card p-5 space-y-3 border-l-4 {{ $isBenar === true ? '!border-l-emerald-500' : ($isBenar === false ? '!border-l-rose-500' : '!border-l-amber-500') }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-sm text-slate-800 dark:text-slate-100">Soal #{{ $idx + 1 }}</span>
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 uppercase text-[10px]">{{ $soal->tipe }}</span>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($isBenar === true)
                        <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 flex items-center gap-1 text-xs">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i> Benar
                        </span>
                    @elseif($isBenar === false)
                        <span class="badge bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 flex items-center gap-1 text-xs">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i> Salah
                        </span>
                    @else
                        <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-400 text-xs">Esai / Belum Dinilai</span>
                    @endif
                    <span class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
                        {{ $jawaban?->skor_diperoleh ?? 0 }} / {{ $soal->poinEfektif() }} Poin
                    </span>
                </div>
            </div>

            {{-- Teks Soal --}}
            <div class="text-sm text-slate-800 dark:text-slate-100 leading-relaxed pt-1">
                @include('ujian.partials.rich-body', ['html' => $soal->teks_soal])
            </div>

            {{-- Opsi / Jawaban Peserta --}}
            <div class="bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-xl space-y-2 text-xs">
                @if(in_array($soal->tipe, ['mcq', 'true_false']))
                    <div class="space-y-1.5">
                        @foreach($soal->opsi as $o)
                        @php
                            $dipilih = $jawaban && $jawaban->id_opsi_dipilih === $o->uuid;
                            $kunci = (bool) $o->is_benar;
                        @endphp
                        <div class="flex items-center justify-between p-2 rounded-lg border {{ $dipilih ? ($kunci ? 'bg-emerald-50/80 border-emerald-300 dark:bg-emerald-950/40 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300' : 'bg-rose-50/80 border-rose-300 dark:bg-rose-950/40 dark:border-rose-800 text-rose-800 dark:text-rose-300') : ($kunci ? 'bg-emerald-50/40 border-dashed border-emerald-300 dark:border-emerald-800' : 'border-transparent') }}">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] {{ $dipilih ? 'bg-primary text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}">
                                    {{ chr(65 + $loop->index) }}
                                </span>
                                <span>{{ $o->teks_opsi }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if($dipilih)
                                    <span class="badge bg-primary/20 text-primary font-semibold text-[10px]">Jawaban Peserta</span>
                                @endif
                                @if($kunci)
                                    <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 font-semibold text-[10px]">Kunci Benar</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @elseif($soal->tipe === 'mcq_complex')
                    <div class="space-y-1.5">
                        <span class="text-slate-400 block mb-1">Opsi Terpilih &amp; Kunci:</span>
                        @foreach($soal->opsi as $o)
                        @php
                            $dipilih = $jawaban && in_array($o->uuid, (array) ($jawaban->opsi_dipilih_multi ?? []));
                            $kunci = (bool) $o->is_benar;
                        @endphp
                        <div class="flex items-center justify-between p-2 rounded-lg border {{ $dipilih ? ($kunci ? 'bg-emerald-50 border-emerald-300 dark:bg-emerald-950/40 dark:border-emerald-800' : 'bg-rose-50 border-rose-300 dark:bg-rose-950/40 dark:border-rose-800') : ($kunci ? 'border-dashed border-emerald-300 dark:border-emerald-800' : 'border-transparent') }}">
                            <div class="flex items-center gap-2">
                                <i data-lucide="{{ $dipilih ? 'check-square' : 'square' }}" class="w-4 h-4 {{ $dipilih ? 'text-primary' : 'text-slate-400' }}"></i>
                                <span>{{ $o->teks_opsi }}</span>
                            </div>
                            <div>
                                @if($kunci)
                                    <span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300 text-[10px]">Kunci Benar</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @elseif($soal->tipe === 'match')
                    <div class="space-y-1.5">
                        <span class="text-slate-400 block mb-1">Pasangan yang Dijodohkan:</span>
                        @php
                            $pairs = $soal->meta['pairs'] ?? [];
                            $pasanganPeserta = $jawaban->jawaban_pasangan ?? [];
                        @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($pairs as $p)
                            @php
                                $dijodohkan = $pasanganPeserta[$p['left']] ?? '-';
                                $cocok = $dijodohkan === $p['right'];
                            @endphp
                            <div class="p-2 rounded-lg border {{ $cocok ? 'bg-emerald-50/60 border-emerald-200 dark:bg-emerald-950/30 dark:border-emerald-800' : 'bg-rose-50/60 border-rose-200 dark:bg-rose-950/30 dark:border-rose-800' }}">
                                <div class="font-bold text-slate-700 dark:text-slate-200">{{ $p['left'] }}</div>
                                <div class="text-[11px] mt-0.5">
                                    Dijawab: <strong class="{{ $cocok ? 'text-emerald-600' : 'text-rose-600' }}">{{ $dijodohkan }}</strong>
                                    @if(!$cocok)
                                        <span class="text-slate-400">(Kunci: {{ $p['right'] }})</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @elseif($soal->tipe === 'essay')
                    <div>
                        <span class="text-slate-400 block mb-1">Jawaban Esai Peserta:</span>
                        <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 whitespace-pre-wrap leading-relaxed">
                            {{ $jawaban->jawaban_esai ?? '(Tidak dijawab)' }}
                        </div>
                        @if(!empty($soal->meta['kunci_jawaban']))
                        <div class="mt-2 text-[11px] text-slate-500">
                            <strong>Referensi Kunci:</strong> {{ $soal->meta['kunci_jawaban'] }}
                        </div>
                        @endif
                    </div>
                @endif
            </div>

            @if($soal->penjelasan)
            <div class="text-xs text-slate-500 dark:text-slate-400 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 p-3 rounded-xl flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5"></i>
                <div>
                    <strong class="text-amber-800 dark:text-amber-300 block mb-0.5">Pembahasan:</strong>
                    {{ $soal->penjelasan }}
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endsection
