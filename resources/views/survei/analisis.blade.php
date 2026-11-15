@extends('layouts.app')
@section('title', $lihatSemua ? 'Analisis Survei per Guru' : 'Rekap Survei Saya')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <a href="{{ auth()->user()->isAdmin() ? route('survei.periode', $periode) : route('survei.index') }}" class="text-blue-700 underline">← Kembali ke rekap</a>
    <header class="rounded-2xl bg-slate-900 p-5 text-white sm:p-7">
        <p class="text-sm font-medium text-sky-200">{{ $periode->judul }}</p>
        <h1 class="mt-2 text-2xl font-bold">{{ $lihatSemua ? 'Analisis Survei per Guru' : 'Rekap Survei Saya' }}</h1>
        <p class="mt-2 text-sm text-slate-200">Ringkasan rahasia untuk meninjau kekuatan dan menentukan tindak lanjut pembelajaran.</p>
    </header>

    @if($lihatSemua)
        <section class="card flex flex-wrap items-center justify-between gap-3 p-4">
            <div>
                <h2 class="font-semibold">Rekap untuk kepala sekolah dan waka</h2>
                <p class="text-sm">Nilai otomatis, rincian per guru, kekuatan, dan area perbaikan dalam satu PDF.</p>
                <p class="text-xs">{{ $lihatCatatan ? 'Termasuk rekap catatan siswa. Tinjau teks sebelum membagikan karena siswa bisa menuliskan data pribadi.' : 'Versi skor tanpa komentar asli. Pembuat survei dapat menyiapkan versi dengan catatan siswa.' }}</p>
            </div>
            <a class="btn-primary" href="{{ route('survei.laporan-guru', $periode) }}">Unduh PDF rekap guru</a>
        </section>
    @endif

    @if($kelas->isNotEmpty())
        <section class="card space-y-3 p-4" aria-labelledby="laporan-kelas">
            <h2 id="laporan-kelas" class="font-semibold">Laporan PDF per kelas</h2>
            <p class="text-sm">Unduh ringkasan skor kelas dan analisis guru untuk kepala sekolah.</p>
            <ul class="flex flex-wrap gap-2">
                @foreach($kelas as $kelasId => $namaKelas)
                    <li><a class="inline-block rounded-lg border px-3 py-2 text-sm font-semibold text-blue-700 underline dark:text-sky-300" href="{{ route('survei.laporan-kelas', [$periode, $kelasId]) }}">Unduh PDF · {{ $namaKelas }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    <details class="card p-4">
        <summary class="cursor-pointer font-semibold">Cara membaca skor</summary>
        <p class="mt-3 text-sm">Skor 1–5 adalah rata-rata jawaban yang dapat dinilai. “Tidak tahu” dikeluarkan dan skor butir negatif dibalik. Setiap butir harus punya minimal lima jawaban yang dapat dinilai per kelas dan mapel atau wali kelas sebelum masuk analisis. Ringkasan menampilkan hingga tiga kekuatan (≥ 4,00) dan tiga area perbaikan (&lt; 3,50), dihitung sebelum pembulatan.</p>
        <p class="mt-2 text-sm">Nilai skala 100 = skor rata-rata × 20. Contoh: 4,00 / 5 menjadi 80,00 / 100; nilai terendah 20. Pembobotan mengikuti jumlah jawaban butir yang memenuhi syarat, bukan rata-rata sederhana antar kelas. Peran guru mata pelajaran dan wali kelas dihitung terpisah. Catatan tertulis tidak memengaruhi nilai.</p>
    </details>

    @if($gurus->count() > 1)
        <nav aria-label="Daftar guru" class="card flex flex-wrap gap-2 p-4">
            @foreach($gurus as $guruId => $guru)
                <a href="#guru-{{ $guruId }}" class="rounded-lg border px-3 py-2 text-sm underline">{{ $guru['nama'] }}</a>
            @endforeach
        </nav>
    @endif

    @forelse($gurus as $guruId => $guru)
        <section class="card space-y-4 p-4 sm:p-6" id="guru-{{ $guruId }}">
            <h2 class="text-xl font-bold">{{ $guru['nama'] }}</h2>
            @foreach($guru['peran'] as $jenis => $ringkasan)
                <div class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold">{{ $jenis === 'wali' ? 'Wali kelas' : 'Guru mata pelajaran' }}</h3>
                        @if($ringkasan['skor'] !== null)
                            <span class="rounded-lg bg-sky-100 px-3 py-2 text-sm font-semibold text-sky-950">
                                Skor {{ number_format($ringkasan['skor'], 2, ',', '.') }} / 5
                                · Nilai {{ number_format($ringkasan['nilai'], 2, ',', '.') }} / 100
                            </span>
                        @endif
                    </div>
                    @if($ringkasan['skor'] === null)
                        <p class="text-sm">Belum cukup jawaban yang dapat dinilai.</p>
                    @else
                        <p class="text-sm">{{ $ringkasan['kelas'] }} kelas · {{ $ringkasan['isian'] }} isian survei diterima · {{ number_format($ringkasan['jawaban_dinilai'], 0, ',', '.') }} jawaban butir masuk perhitungan. Isian bukan jumlah siswa unik.</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach(['kekuatan' => 'Kekuatan', 'perbaikan' => 'Area perbaikan'] as $key => $label)
                                <div @class(['rounded-lg p-4 text-sm', 'bg-emerald-50 text-emerald-950' => $key === 'kekuatan', 'bg-amber-50 text-amber-950' => $key === 'perbaikan'])>
                                    <h4 class="font-bold">{{ $label }}</h4>
                                    <ul class="mt-2 space-y-2">
                                        @forelse($ringkasan[$key] as $butir)
                                            <li>
                                                <strong>{{ number_format($butir['rata'], 2, ',', '.') }} / 5</strong>
                                                <p>{{ $butir['text'] }}</p>
                                                @if($butir['negatif'])
                                                    <p class="text-xs">Skor dibalik. Skor tinggi berarti perilaku lebih jarang terjadi.</p>
                                                @endif
                                                <p class="text-xs">{{ $butir['n'] }} jawaban dinilai</p>
                                            </li>
                                        @empty
                                            <li>Belum ada butir yang memenuhi kriteria.</li>
                                        @endforelse
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                        <details class="text-sm">
                            <summary class="cursor-pointer font-semibold">Rincian skoring per indikator</summary>
                            <div class="mt-3 space-y-2">
                                @foreach($ringkasan['butir'] as $butir)
                                    <div class="flex items-start justify-between gap-3 border-b py-2">
                                        <div><p>{{ $butir['text'] }}</p>@if($butir['negatif'])<small>Skor dibalik; nilai tinggi berarti perilaku lebih jarang terjadi.</small>@endif</div>
                                        <div class="shrink-0 text-right"><strong>{{ number_format($butir['rata'] * 20, 2, ',', '.') }} / 100</strong><p class="text-xs">{{ $butir['n'] }} jawaban</p></div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    @if($lihatCatatan)
                        <details class="text-sm">
                            <summary class="cursor-pointer font-semibold">Catatan siswa · {{ $ringkasan['catatan']->sum('jumlah') }} catatan</summary>
                            <p class="mt-2 text-xs">Teks asli dikelompokkan berdasarkan pertanyaan dan kesamaan tulisan, tanpa tafsir otomatis. Jumlah catatan bukan jumlah siswa unik. Kelompok dengan kurang dari lima respons tidak disertakan.</p>
                            @forelse($ringkasan['catatan'] as $bagian)
                                <div class="mt-3">
                                    <h4 class="font-semibold">{{ $bagian['pertanyaan'] }}</h4>
                                    <ul class="mt-2 space-y-2">
                                        @foreach($bagian['daftar'] as $catatan)
                                            <li class="rounded-lg border p-3"><p class="whitespace-pre-wrap break-words">{{ $catatan['teks'] }}</p><small>{{ $catatan['jumlah'] }} catatan dengan tulisan sama</small></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @empty
                                <p class="mt-2">Belum ada catatan yang dapat ditampilkan.</p>
                            @endforelse
                        </details>
                    @endif
                    <details class="text-sm">
                        <summary class="cursor-pointer font-semibold">Rincian kelas dan mata pelajaran</summary>
                        <ul class="mt-2 space-y-2">
                            @foreach($ringkasan['targets'] as $target)
                                <li><a class="text-blue-700 underline dark:text-sky-300" href="{{ route('survei.hasil', $target) }}">{{ $target->kelas_nama }} · {{ $jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama }}</a></li>
                            @endforeach
                        </ul>
                    </details>
                </div>
            @endforeach
        </section>
    @empty
        <p class="card p-4">Periode ini belum memiliki sasaran survei.</p>
    @endforelse
</div>
@endsection
