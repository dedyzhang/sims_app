@extends('layouts.app')
@section('title', $lihatSemua ? 'Analisis Survei per Guru' : 'Rekap Survei Saya')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 pb-12">
    {{-- Navigasi Atas --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ auth()->user()->isAdmin() ? route('survei.periode', $periode) : route('survei.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-800 dark:text-sky-400 dark:hover:text-sky-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke rekap periode
        </a>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 shadow-sm transition">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Halaman
            </button>
            @if($lihatSemua)
                <a class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition" href="{{ route('survei.laporan-guru', $periode) }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh PDF Rekap Guru
                </a>
            @endif
        </div>
    </div>

    {{-- Banner Utama --}}
    <header class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-sky-950 to-slate-900 p-6 text-white shadow-lg sm:p-8">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-sky-500/20 text-sky-200 border border-sky-400/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    {{ $periode->judul }}
                </span>
                <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ $lihatSemua ? 'Analisis & Hasil Digital Survei Guru' : 'Rekap Hasil Survei Saya' }}
                </h1>
                <p class="mt-2 text-sm text-slate-300 max-w-2xl leading-relaxed">
                    Evaluasi komprehensif persepsi peserta didik berbasis instrumen pembelajaran. Hasil skor, grafik per indikator, kesan karakter, dan catatan kualitatif tersaji langsung secara transparan tanpa syarat minimal responden.
                </p>
            </div>
            <div class="flex flex-col items-start md:items-end justify-center gap-1 bg-white/5 border border-white/10 rounded-xl p-4 backdrop-blur-sm">
                <span class="text-xs font-medium text-slate-400">Total Guru Dievaluasi</span>
                <span class="text-3xl font-black text-sky-300">{{ $gurus->count() }}</span>
                <span class="text-[11pt] text-slate-300">{{ $periode->mulai->format('d M') }} — {{ $periode->selesai->format('d M Y') }}</span>
            </div>
        </div>
    </header>

    {{-- Akses Laporan Kelas --}}
    @if($kelas->isNotEmpty())
        <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Laporan PDF per Kelas</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Unduh dokumen cetak rekap skor per kelas untuk arsip kurikulum atau kepala sekolah.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                @foreach($kelas as $kelasId => $namaKelas)
                    <a class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-sky-300 hover:bg-sky-50 dark:hover:bg-slate-800 hover:border-sky-300 transition" href="{{ route('survei.laporan-kelas', [$periode, $kelasId]) }}">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        {{ $namaKelas }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Navigasi Guru Cepat --}}
    @if($gurus->count() > 1)
        <nav aria-label="Daftar guru" class="flex items-center gap-2 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-3 shadow-sm scrollbar-thin">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 whitespace-nowrap pl-1">Lompat ke Guru:</span>
            @foreach($gurus as $guruId => $guru)
                <a href="#guru-{{ $guruId }}" class="inline-block whitespace-nowrap rounded-lg bg-slate-100 hover:bg-sky-100 dark:bg-slate-800 dark:hover:bg-slate-700 px-3 py-1 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-sky-900 transition">
                    {{ $guru['nama'] }}
                </a>
            @endforeach
        </nav>
    @endif

    {{-- Daftar Rekap per Guru --}}
    @forelse($gurus as $guruId => $guru)
        <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden space-y-6 p-5 sm:p-7 scroll-mt-6" id="guru-{{ $guruId }}">
            {{-- Header Profil Guru --}}
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-600 to-indigo-700 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        {{ strtoupper(mb_substr($guru['nama'], 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900 dark:text-slate-50">{{ $guru['nama'] }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Hasil survei persepsi siswa semester ini</p>
                    </div>
                </div>
            </div>

            @foreach($guru['peran'] as $jenis => $ringkasan)
                @php
                    $chartKey = 'chart_'.Str::slug($guruId).'_'.$jenis;
                    $chartDataIndikator = $ringkasan['butir']->map(function($b) {
                        return [
                            'label' => 'Butir '.($b['index'] + 1),
                            'text' => $b['text'],
                            'nilai' => round($b['rata'] * 20, 2),
                            'skor' => round($b['rata'], 2),
                            'n' => $b['n'],
                            'negatif' => $b['negatif']
                        ];
                    })->values();

                    $chartDataKarakter = (!empty($ringkasan['karakter']) && $ringkasan['karakter']->isNotEmpty())
                        ? $ringkasan['karakter']->values()
                        : collect();
                @endphp

                <div class="space-y-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 p-5 sm:p-6">
                    {{-- Judul Peran & Ringkasan Angka --}}
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider {{ $jenis === 'wali' ? 'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-300' }}">
                                {{ $jenis === 'wali' ? 'Wali Kelas' : 'Guru Mata Pelajaran' }}
                            </span>
                            @if($jenis !== 'wali')
                                <span class="text-xs text-slate-600 dark:text-slate-400 font-medium">
                                    {{ $ringkasan['targets']->pluck('pelajaran_nama')->unique()->implode(', ') }}
                                </span>
                            @endif
                        </div>

                        @if($ringkasan['skor'] !== null)
                            @php
                                $nilaiTotal = $ringkasan['nilai'];
                                $badgeKategori = $nilaiTotal >= 85 ? ['Sangat Baik', 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-300']
                                    : ($nilaiTotal >= 75 ? ['Baik', 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-300 border-sky-300']
                                    : ($nilaiTotal >= 60 ? ['Cukup', 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300 border-amber-300']
                                    : ['Perlu Pembinaan', 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-300 border-rose-300']));
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $badgeKategori[1] }}">
                                    Predikat: {{ $badgeKategori[0] }}
                                </span>
                            </div>
                        @endif
                    </div>

                    @if($ringkasan['skor'] === null)
                        <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-6 text-center text-sm text-slate-500">
                            Belum ada isian survei yang masuk untuk peran ini.
                        </div>
                    @else
                        {{-- Kartu Metrik Angka --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide block">Nilai Skala 100</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold text-blue-700 dark:text-sky-400">{{ number_format($ringkasan['nilai'], 2, ',', '.') }}</span>
                                    <span class="text-xs font-medium text-slate-400">/ 100</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide block">Skor Rata-rata</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ number_format($ringkasan['skor'], 2, ',', '.') }}</span>
                                    <span class="text-xs font-medium text-slate-400">/ 5,00</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide block">Isian Masuk</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ $ringkasan['isian'] }}</span>
                                    <span class="text-xs font-medium text-slate-400">respon</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
                                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide block">Cakupan Kelas</span>
                                <div class="mt-1 flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ $ringkasan['kelas'] }}</span>
                                    <span class="text-xs font-medium text-slate-400">kelas</span>
                                </div>
                            </div>
                        </div>

                        {{-- Visual Chart Digital (Interaktif) --}}
                        <div class="grid grid-cols-1 {{ $chartDataKarakter->isNotEmpty() ? 'lg:grid-cols-3' : '' }} gap-4">
                            {{-- Chart Indikator Bar --}}
                            <div class="{{ $chartDataKarakter->isNotEmpty() ? 'lg:col-span-2' : '' }} rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm">
                                <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Grafik Nilai per Indikator (Skala 100)</h4>
                                        <p class="text-xs text-slate-500">Visualisasi pencapaian instrumen penilaian siswa</p>
                                    </div>
                                    <span class="text-[11px] font-medium text-slate-400">Chart Digital</span>
                                </div>
                                <div class="relative w-full" style="height: {{ max(240, $chartDataIndikator->count() * 38) }}px;">
                                    <canvas id="canvas-indikator-{{ $chartKey }}" data-chart='@json($chartDataIndikator)'></canvas>
                                </div>
                            </div>

                            {{-- Chart Karakter Siswa Donut --}}
                            @if($chartDataKarakter->isNotEmpty())
                                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm flex flex-col justify-between">
                                    <div>
                                        <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Kesan Karakter</h4>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-200">Persepsi Siswa</span>
                                        </div>
                                        <p class="text-xs text-slate-500 mb-3">"Menurut kamu, guru ini termasuk guru yang ..."</p>
                                        <div class="relative w-full h-56 flex items-center justify-center">
                                            <canvas id="canvas-karakter-{{ $chartKey }}" data-chart='@json($chartDataKarakter)'></canvas>
                                        </div>
                                    </div>
                                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-1.5">
                                        @foreach($ringkasan['karakter'] as $item)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-amber-50 dark:bg-amber-950/30 text-amber-900 dark:text-amber-200 border border-amber-200/60 dark:border-amber-800/60">
                                                <span>{{ $item['label'] }}</span>
                                                <span class="bg-amber-200/80 dark:bg-amber-800/60 text-amber-950 dark:text-amber-100 px-1 rounded text-[10px] font-bold">{{ $item['jumlah'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Kekuatan & Area Perbaikan --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/20 p-4 text-emerald-950 dark:text-emerald-200">
                                <div class="flex items-center gap-2 mb-2 font-bold text-sm text-emerald-900 dark:text-emerald-300">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Kekuatan Utama (Skor ≥ 4,00)
                                </div>
                                <ul class="space-y-2 text-xs">
                                    @forelse($ringkasan['kekuatan'] as $butir)
                                        <li class="bg-white/80 dark:bg-slate-900/80 rounded-lg p-2.5 border border-emerald-200/60 dark:border-emerald-900/40">
                                            <div class="flex items-center justify-between font-bold text-emerald-800 dark:text-emerald-300">
                                                <span>Indikator {{ $butir['index'] + 1 }}</span>
                                                <span>{{ number_format($butir['rata'] * 20, 1, ',', '.') }} / 100 ({{ number_format($butir['rata'], 2, ',', '.') }} / 5)</span>
                                            </div>
                                            <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">{{ $butir['text'] }}</p>
                                        </li>
                                    @empty
                                        <li class="text-slate-500 italic">Belum ada indikator yang mencapai skor minimal 4,00.</li>
                                    @endforelse
                                </ul>
                            </div>

                            <div class="rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50/70 dark:bg-amber-950/20 p-4 text-amber-950 dark:text-amber-200">
                                <div class="flex items-center gap-2 mb-2 font-bold text-sm text-amber-900 dark:text-amber-300">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    Area Perbaikan (Skor &lt; 3,50)
                                </div>
                                <ul class="space-y-2 text-xs">
                                    @forelse($ringkasan['perbaikan'] as $butir)
                                        <li class="bg-white/80 dark:bg-slate-900/80 rounded-lg p-2.5 border border-amber-200/60 dark:border-amber-900/40">
                                            <div class="flex items-center justify-between font-bold text-amber-800 dark:text-amber-300">
                                                <span>Indikator {{ $butir['index'] + 1 }}</span>
                                                <span>{{ number_format($butir['rata'] * 20, 1, ',', '.') }} / 100 ({{ number_format($butir['rata'], 2, ',', '.') }} / 5)</span>
                                            </div>
                                            <p class="mt-1 text-slate-700 dark:text-slate-300 leading-relaxed">{{ $butir['text'] }}</p>
                                        </li>
                                    @empty
                                        <li class="text-slate-500 italic">Tidak ada indikator yang berada di bawah skor 3,50.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>

                        {{-- Tabel Lengkap Indikator dengan Progress Bar Visual CSS --}}
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                            <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40 flex items-center justify-between">
                                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Rincian Lengkap Skor per Indikator</h4>
                                <span class="text-xs text-slate-500">{{ $ringkasan['butir']->count() }} butir instrumen</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                                            <th class="py-2.5 px-3 w-12 text-center">No.</th>
                                            <th class="py-2.5 px-3">Pernyataan Indikator</th>
                                            <th class="py-2.5 px-3 w-24 text-center">Jawaban</th>
                                            <th class="py-2.5 px-3 w-24 text-right">Skor / 5</th>
                                            <th class="py-2.5 px-3 w-48 text-right">Nilai / 100 & Visual</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        @foreach($ringkasan['butir'] as $butir)
                                            @php
                                                $nilaiPersen = min(100, max(0, $butir['rata'] * 20));
                                                $barColor = $nilaiPersen >= 80 ? 'bg-emerald-500' : ($nilaiPersen >= 70 ? 'bg-sky-500' : 'bg-amber-500');
                                            @endphp
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                                                <td class="py-2.5 px-3 text-center font-bold text-slate-500">{{ $butir['index'] + 1 }}</td>
                                                <td class="py-2.5 px-3">
                                                    <p class="font-medium text-slate-800 dark:text-slate-200 leading-snug">{{ $butir['text'] }}</p>
                                                    @if($butir['negatif'])
                                                        <span class="inline-block mt-0.5 text-[10px] text-amber-700 dark:text-amber-400 font-semibold">(R) Skor dibalik: nilai tinggi berarti perilaku negatif jarang/tidak terjadi.</span>
                                                    @endif
                                                </td>
                                                <td class="py-2.5 px-3 text-center text-slate-500">{{ $butir['n'] }}</td>
                                                <td class="py-2.5 px-3 text-right font-semibold text-slate-700 dark:text-slate-300">{{ number_format($butir['rata'], 2, ',', '.') }}</td>
                                                <td class="py-2.5 px-3 text-right">
                                                    <div class="font-bold text-slate-900 dark:text-slate-100 mb-1">{{ number_format($nilaiPersen, 1, ',', '.') }}</div>
                                                    <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                                                        <div class="{{ $barColor }} h-2 rounded-full transition-all duration-500" style="width: {{ $nilaiPersen }}%;"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Rekap Catatan Siswa Lengkap (Real-time tanpa minimal 5) --}}
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        Rekap Catatan & Komentar Siswa
                                    </h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Teks asli masukan siswa disajikan langsung untuk bahan refleksi pembelajaran.</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800">
                                    {{ $ringkasan['catatan']->sum('jumlah') }} Catatan Diterima
                                </span>
                            </div>

                            @forelse($ringkasan['catatan'] as $bagian)
                                <div class="space-y-2 pt-2">
                                    <h5 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                        {{ $bagian['pertanyaan'] }} ({{ $bagian['jumlah'] }})
                                    </h5>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach($bagian['daftar'] as $catatan)
                                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 p-3 text-xs flex flex-col justify-between">
                                                <p class="text-slate-800 dark:text-slate-200 italic whitespace-pre-wrap leading-relaxed">“{{ $catatan['teks'] }}”</p>
                                                <div class="mt-2 pt-2 border-t border-slate-200/50 dark:border-slate-700/50 flex items-center justify-between text-[11px] text-slate-500">
                                                    <span>Frekuensi tulisan</span>
                                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ $catatan['jumlah'] }} siswa</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="py-4 text-center text-xs text-slate-400 italic">
                                    Belum ada catatan tertulis dari siswa untuk peran ini.
                                </div>
                            @endforelse
                        </div>

                        {{-- Rincian Kelas & Target --}}
                        <details class="text-xs group">
                            <summary class="cursor-pointer font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-800 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                Lihat tautan halaman hasil per kelas dan mata pelajaran
                            </summary>
                            <ul class="mt-2 pl-5 space-y-1 text-slate-500">
                                @foreach($ringkasan['targets'] as $target)
                                    <li>
                                        <a class="text-blue-600 hover:underline dark:text-sky-400" href="{{ route('survei.hasil', $target) }}">
                                            {{ $target->kelas_nama }} · {{ $jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama }} →
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endforeach
        </article>
    @empty
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 text-center text-slate-500">
            Periode survei ini belum memiliki data sasaran guru atau respons siswa.
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Render Chart Indikator Horizontal Bar
    document.querySelectorAll('canvas[id^="canvas-indikator-"]').forEach(function (canvas) {
        try {
            const raw = canvas.dataset.chart;
            if (!raw) return;
            const items = JSON.parse(raw);
            if (!items || items.length === 0) return;

            const labels = items.map(function(item) { return item.label; });
            const values = items.map(function(item) { return item.nilai; });
            const bgColors = values.map(function(val) {
                if (val >= 80) return 'rgba(16, 185, 129, 0.85)'; // emerald
                if (val >= 70) return 'rgba(14, 165, 233, 0.85)'; // sky
                return 'rgba(245, 158, 11, 0.85)'; // amber
            });
            const borderColors = values.map(function(val) {
                if (val >= 80) return '#059669';
                if (val >= 70) return '#0284c7';
                return '#d97706';
            });

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nilai (Skala 100)',
                        data: values,
                        backgroundColor: bgColors,
                        borderColor: borderColors,
                        borderWidth: 1.5,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const idx = context[0].dataIndex;
                                    return items[idx].label + ': ' + items[idx].text;
                                },
                                label: function(context) {
                                    const idx = context.dataIndex;
                                    return ' Nilai: ' + items[idx].nilai + ' / 100 (Skor: ' + items[idx].skor + ' / 5 · ' + items[idx].n + ' respons)';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 100,
                            ticks: {
                                stepSize: 20,
                                callback: function(val) { return val + '%'; }
                            },
                            grid: { color: 'rgba(156, 163, 175, 0.15)' }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { weight: 'bold', size: 11 } }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Gagal render chart indikator:', e);
        }
    });

    // 2. Render Chart Karakter Donut
    document.querySelectorAll('canvas[id^="canvas-karakter-"]').forEach(function (canvas) {
        try {
            const raw = canvas.dataset.chart;
            if (!raw) return;
            const items = JSON.parse(raw);
            if (!items || items.length === 0) return;

            const labels = items.map(function(item) { return item.label; });
            const counts = items.map(function(item) { return item.jumlah; });
            const palette = [
                '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899',
                '#06b6d4', '#84cc16', '#6366f1', '#14b8a6', '#f97316'
            ];

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: palette.slice(0, labels.length),
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 10 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const val = context.raw;
                                    const total = counts.reduce(function(a, b){ return a + b; }, 0);
                                    const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                    return ' ' + context.label + ': ' + val + ' siswa (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        } catch (e) {
            console.error('Gagal render chart karakter:', e);
        }
    });
});
</script>
@endpush
