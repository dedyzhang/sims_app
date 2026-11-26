@extends('layouts.app')
@section('title', $lihatSemua ? 'Analisis Survei per Guru' : 'Rekap Survei Saya')

@push('styles')
<style>
@media print {
    /* Sembunyikan elemen antarmuka, sidebar, header, dan navigasi saat mencetak langsung halaman */
    aside, .app-sidebar, header.app-header, nav, footer,
    .no-print, button, a[href*="laporan"] {
        display: none !important;
    }
    body, main, .mx-auto {
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
        background: #fff !important;
        color: #000 !important;
    }
    article {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>
@endpush

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
            @if($guruAktif && $lihatSemua)
                <a class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition" href="{{ route('survei.laporan-guru', array_filter(['periode' => $periode, 'guru' => $guruAktifId, 'tingkat' => $tingkatDipilih])) }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Unduh PDF: {{ $guruAktif['nama'] }}
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
                <span class="text-3xl font-black text-sky-300">{{ $totalGurus }}</span>
                <span class="text-[11pt] text-slate-300">{{ $periode->mulai->format('d M') }} — {{ $periode->selesai->format('d M Y') }}</span>
            </div>
        </div>
    </header>

    {{-- Laporan PDF per Kelas & Laporan PDF per Guru --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @if($kelas->isNotEmpty())
            <section class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            Laporan PDF per Kelas
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Unduh dokumen rekap skor per kelas.</p>
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

        @if($lihatSemua && $totalGurus >= 1)
            <details class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 sm:p-5 shadow-sm space-y-3 group {{ $kelas->isEmpty() ? 'md:col-span-2' : '' }}">
                <summary class="cursor-pointer font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Laporan PDF per Guru (Satu per Satu — {{ $totalGurus }} Guru)</span>
                    </div>
                    <span class="text-xs text-sky-600 dark:text-sky-400 group-open:rotate-180 transition">▼</span>
                </summary>
                <p class="text-xs text-slate-500 dark:text-slate-400 pt-1">
                    Unduh berkas PDF evaluasi terpisah per guru (tidak digabung menjadi 1 dokumen):
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 max-h-60 overflow-y-auto pr-1">
                    @foreach($gurus as $gId => $gItem)
                        <a class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:border-emerald-300 dark:hover:border-emerald-700 hover:text-emerald-700 dark:hover:text-emerald-300 transition"
                           href="{{ route('survei.laporan-guru', ['periode' => $periode, 'guru' => $gId]) }}"
                           title="Unduh PDF untuk {{ $gItem['nama'] }}">
                            <span class="truncate">{{ $loop->iteration }}. {{ $gItem['nama'] }}</span>
                            <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                Unduh
                            </span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endif
    </div>

    {{-- Penjelasan Skoring --}}
    <details class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm group">
        <summary class="cursor-pointer font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center justify-between">
            <span>Cara membaca skor & indikator</span>
            <span class="text-xs text-sky-600 dark:text-sky-400 group-open:rotate-180 transition">▼</span>
        </summary>
        <div class="mt-3 text-xs sm:text-sm text-slate-600 dark:text-slate-300 space-y-2 border-t border-slate-100 dark:border-slate-800 pt-3">
            <p>Skor 1–5 adalah rata-rata jawaban yang dapat dinilai. “Tidak tahu” dikeluarkan dan skor butir negatif dibalik. Setiap butir dianalisis secara transparan berdasarkan jawaban yang masuk per kelas dan mapel atau wali kelas. Ringkasan menampilkan hingga tiga kekuatan (≥ 4,00) dan tiga area perbaikan (&lt; 3,50), dihitung sebelum pembulatan.</p>
            <p>Nilai skala 100 = skor rata-rata × 20. Contoh: 4,00 / 5 menjadi 80,00 / 100; nilai terendah 20. Pembobotan mengikuti jumlah jawaban butir yang memenuhi syarat, bukan rata-rata sederhana antar kelas. Peran guru mata pelajaran dan wali kelas dihitung terpisah. Catatan tertulis tidak memengaruhi nilai.</p>
        </div>
    </details>

    {{-- Pengalih Halaman / Navigasi Antar Guru (1 Guru per Halaman) --}}
    @if($lihatSemua && $totalGurus >= 1)
        <nav aria-label="Navigasi Guru" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                {{-- Dropdown Pilih Guru --}}
                <div class="flex items-center gap-2">
                    <label for="pilih-guru" class="text-xs font-bold text-slate-600 dark:text-slate-300 whitespace-nowrap">
                        Pilih Guru:
                    </label>
                    <select id="pilih-guru"
                            onchange="if(this.value) window.location.href=this.value;"
                            class="rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-800 dark:text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 max-w-xs sm:max-w-md">
                        @foreach($gurus as $gId => $gItem)
                            <option value="{{ route('survei.analisis', ['periode' => $periode, 'page' => $loop->iteration]) }}" {{ $gId === $guruAktifId ? 'selected' : '' }}>
                                {{ $loop->iteration }}. {{ $gItem['nama'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status & Tombol Prev / Next Atas --}}
                <div class="flex items-center justify-between sm:justify-end gap-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        Guru <strong class="text-slate-800 dark:text-slate-100">{{ $page }}</strong> dari {{ $totalGurus }}
                    </span>

                    <div class="flex items-center gap-1.5">
                        @if($prevPage)
                            <a href="{{ route('survei.analisis', ['periode' => $periode, 'page' => $prevPage]) }}"
                               title="{{ $prevGuru['nama'] ?? '' }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <span>Sebelumnya</span>
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-900 cursor-not-allowed">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <span>Sebelumnya</span>
                            </span>
                        @endif

                        @if($nextPage)
                            <a href="{{ route('survei.analisis', ['periode' => $periode, 'page' => $nextPage]) }}"
                               title="{{ $nextGuru['nama'] ?? '' }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-sm transition">
                                <span>Berikutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-400 dark:text-slate-600 bg-slate-50 dark:bg-slate-900 cursor-not-allowed">
                                <span>Berikutnya</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Nomor Halaman Cepat (Pill Scrollable) --}}
            @if($totalGurus > 1)
                <div class="flex items-center gap-1.5 overflow-x-auto pt-2 border-t border-slate-100 dark:border-slate-800 scrollbar-thin">
                    <span class="text-[11px] font-bold text-slate-400 whitespace-nowrap mr-1">Halaman:</span>
                    @foreach($gurus as $gId => $gItem)
                        @php $pNum = $loop->iteration; $isCur = $pNum === $page; @endphp
                        <a href="{{ route('survei.analisis', ['periode' => $periode, 'page' => $pNum]) }}"
                           title="{{ $gItem['nama'] }}"
                           class="inline-flex items-center justify-center min-w-[28px] h-7 px-2 rounded-lg text-xs font-bold transition whitespace-nowrap {{ $isCur ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-sky-100 dark:hover:bg-slate-700' }}">
                            {{ $pNum }}
                        </a>
                    @endforeach
                </div>
            @endif
        </nav>
    @endif

    {{-- Tampilan 1 Guru per Halaman --}}
    @if($guruAktif)
        <article class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden space-y-6 p-5 sm:p-7 scroll-mt-6" id="guru-{{ $guruAktifId }}">
            {{-- Header Profil Guru --}}
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-sky-600 to-indigo-700 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        {{ strtoupper(mb_substr($guruAktif['nama'], 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900 dark:text-slate-50">{{ $guruAktif['nama'] }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Hasil survei persepsi siswa semester ini
                            @if($tingkatDipilih)
                                <span class="ml-1 font-bold text-sky-600 dark:text-sky-400">(Khusus Tingkat {{ $tingkatDipilih }})</span>
                            @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if($lihatSemua)
                        <a class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm transition"
                           href="{{ route('survei.laporan-guru', array_filter(['periode' => $periode, 'guru' => $guruAktifId, 'tingkat' => $tingkatDipilih])) }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Unduh PDF Guru Ini</span>
                        </a>
                    @endif
                    @if($lihatSemua && $totalGurus >= 1)
                        <div class="text-xs font-semibold text-slate-500 bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg">
                            Guru {{ $page }} dari {{ $totalGurus }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Filter Tingkat Hasil Khusus Guru Ini (Jika Guru Mengajar 2 atau 3 Tingkat) --}}
            @if(count($tingkatGuruList) > 1)
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/50 p-4 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
                                Filter Hasil Berdasarkan Tingkat (Guru ini mengajar di {{ count($tingkatGuruList) }} tingkat):
                            </span>
                        </div>
                        @if($tingkatDipilih)
                            <a href="{{ route('survei.analisis', array_filter(['periode' => $periode, 'page' => $page, 'guru' => $guruAktifId])) }}"
                               class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400 font-semibold inline-flex items-center gap-1">
                                ✕ Tampilkan Semua Tingkat
                            </a>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <a href="{{ route('survei.analisis', array_filter(['periode' => $periode, 'page' => $page, 'guru' => $guruAktifId])) }}"
                           class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition shadow-sm {{ empty($tingkatDipilih) ? 'bg-sky-600 text-white shadow-sky-200 dark:shadow-none' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 border border-slate-200 dark:border-slate-700' }}">
                            <span>Semua Tingkat</span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ empty($tingkatDipilih) ? 'bg-sky-800/60 text-sky-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                {{ $totalKelasGuru }} Kelas
                            </span>
                        </a>

                        @foreach($tingkatGuruList as $tVal => $jumlahKelasTingkat)
                            @php $isAct = (string)$tingkatDipilih === (string)$tVal; @endphp
                            <a href="{{ route('survei.analisis', array_filter(['periode' => $periode, 'page' => $page, 'guru' => $guruAktifId, 'tingkat' => $tVal])) }}"
                               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold transition shadow-sm {{ $isAct ? 'bg-sky-600 text-white shadow-sky-200 dark:shadow-none' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:bg-slate-100 border border-slate-200 dark:border-slate-700' }}">
                                <span>Tingkat {{ $tVal }}</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $isAct ? 'bg-sky-800/60 text-sky-100' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                    {{ $jumlahKelasTingkat }} Kelas
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @elseif(count($tingkatGuruList) === 1)
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    <span>Mengajar khusus di <strong>Tingkat {{ array_key_first($tingkatGuruList) }}</strong> ({{ $totalKelasGuru }} Kelas)</span>
                </div>
            @endif

            @foreach($guruAktif['peran'] as $jenis => $ringkasan)
                @php
                    $chartKey = 'chart_'.Str::slug($guruAktifId).'_'.$jenis;
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
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-sky-100 dark:bg-sky-950/60 px-3 py-1 text-xs font-bold text-sky-950 dark:text-sky-200">
                                    Skor {{ number_format($ringkasan['skor'], 2, ',', '.') }} / 5 · Nilai {{ number_format($ringkasan['nilai'], 2, ',', '.') }} / 100
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border {{ $badgeKategori[1] }}">
                                    Predikat: {{ $badgeKategori[0] }}
                                </span>
                            </div>
                        @endif
                    </div>

                    @if($ringkasan['skor'] === null)
                        <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-6 text-center text-sm text-slate-500">
                            Belum ada isian survei yang masuk untuk peran ini{{ $tingkatDipilih ? ' di Tingkat '.$tingkatDipilih : '' }}.
                        </div>
                    @else
                        {{-- 4 Kotak Ringkasan Angka --}}
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                                <span class="text-xs font-medium text-slate-500 uppercase">Nilai Skala 100</span>
                                <div class="mt-1 text-2xl font-black text-blue-600 dark:text-sky-400">
                                    {{ number_format($ringkasan['nilai'], 2, ',', '.') }}
                                    <span class="text-xs font-normal text-slate-400">/100</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                                <span class="text-xs font-medium text-slate-500 uppercase">Skor Rata-Rata</span>
                                <div class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">
                                    {{ number_format($ringkasan['skor'], 2, ',', '.') }}
                                    <span class="text-xs font-normal text-slate-400">/5,00</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                                <span class="text-xs font-medium text-slate-500 uppercase">Isian Masuk</span>
                                <div class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">
                                    {{ $ringkasan['isian'] }}
                                    <span class="text-xs font-normal text-slate-400">respon</span>
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                                <span class="text-xs font-medium text-slate-500 uppercase">Cakupan Kelas</span>
                                <div class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">
                                    {{ $ringkasan['kelas'] }}
                                    <span class="text-xs font-normal text-slate-400">kelas</span>
                                </div>
                            </div>
                        </div>

                        {{-- Layout 2 Kolom: Grafik Nilai per Indikator & Donut Chart Karakter Guru --}}
                        <div class="grid gap-6 lg:grid-cols-3">
                            {{-- Kolom 1 & 2: Grafik Batang Horizontal per Butir Indikator --}}
                            <div class="lg:col-span-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-3">
                                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">Grafik Nilai per Indikator (Skala 100)</h4>
                                        <p class="text-xs text-slate-500 mt-0.5">
                                            Visualisasi pencapaian instrumen penilaian siswa
                                            @if($tingkatDipilih) (Tingkat {{ $tingkatDipilih }}) @endif
                                        </p>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-400">Chart Digital</span>
                                </div>
                                <div class="relative h-64 sm:h-72 w-full pt-2">
                                    <canvas id="canvas-indikator-{{ $chartKey }}" data-chart="{{ json_encode($chartDataIndikator) }}"></canvas>
                                </div>
                            </div>

                            {{-- Kolom 3: Grafik Donut Kesan Karakter Guru --}}
                            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-3 flex flex-col justify-between">
                                <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">Kesan Karakter</h4>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300">Persepsi Siswa</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">“Menurut kamu, guru ini termasuk guru yang ...”</p>
                                </div>

                                @if($chartDataKarakter->isNotEmpty())
                                    <div class="relative h-56 w-full my-auto flex items-center justify-center">
                                        <canvas id="canvas-karakter-{{ $chartKey }}" data-chart="{{ json_encode($chartDataKarakter) }}"></canvas>
                                    </div>
                                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800 space-y-1">
                                        @foreach($chartDataKarakter->take(3) as $kItem)
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-slate-600 dark:text-slate-400 truncate max-w-[160px]">{{ $kItem['label'] }}</span>
                                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $kItem['jumlah'] }} suara</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="py-12 text-center text-xs text-slate-400 italic">
                                        Belum ada pilihan karakter dari siswa untuk guru ini{{ $tingkatDipilih ? ' di Tingkat '.$tingkatDipilih : '' }}.
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Kekuatan & Area Perbaikan --}}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/70 dark:bg-emerald-950/20 p-4 text-emerald-950 dark:text-emerald-200">
                                <div class="flex items-center gap-2 mb-2 font-bold text-sm text-emerald-900 dark:text-emerald-300">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Kekuatan (Skor ≥ 4,00)
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
                                    Area perbaikan (Skor &lt; 3,50)
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
                                        <li class="text-slate-500 italic">Tidak ada indikator yang berada di bawah skor 3,50. Performa sangat baik!</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>

                        {{-- Tabel Lengkap Indikator Penilaian --}}
                        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-slate-100">Rincian Skoring Seluruh Indikator</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Analisis kuantitatif butir pertanyaan instrumen penilaian</p>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs text-left">
                                    <thead>
                                        <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase tracking-wider text-[11px]">
                                            <th class="py-2 px-3 text-center w-12">No</th>
                                            <th class="py-2 px-3">Pernyataan Instrumen</th>
                                            <th class="py-2 px-3 text-center w-24">Jumlah Respons</th>
                                            <th class="py-2 px-3 text-right w-24">Skor (1–5)</th>
                                            <th class="py-2 px-3 text-right w-36">Nilai (Skala 100)</th>
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
                        @if($lihatCatatan)
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
                        @endif

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

        {{-- Navigasi Bawah Antar Guru --}}
        @if($lihatSemua && $totalGurus > 1)
            <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <div>
                    @if($prevPage)
                        <a href="{{ route('survei.analisis', ['periode' => $periode, 'page' => $prevPage]) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            <span>Sebelumnya: <strong>{{ $prevGuru['nama'] ?? '' }}</strong></span>
                        </a>
                    @endif
                </div>

                <div class="text-center">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                        Guru {{ $page }} dari {{ $totalGurus }}
                    </span>
                </div>

                <div>
                    @if($nextPage)
                        <a href="{{ route('survei.analisis', ['periode' => $periode, 'page' => $nextPage]) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold shadow-sm transition">
                            <span>Berikutnya: <strong>{{ $nextGuru['nama'] ?? '' }}</strong></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    @else
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-8 text-center text-slate-500 space-y-3">
            <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm font-semibold">Tidak ada data guru yang ditemukan untuk kriteria ini.</p>
        </div>
    @endif
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
