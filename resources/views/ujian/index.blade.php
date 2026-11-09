@extends('layouts.app')
@section('title', $viewMode === 'pilih_paket' ? 'Pilih Paket Ujian' : 'Kelola Ujian — ' . ($activePaket ? $activePaket->nama : 'Mata Pelajaran'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    @if($viewMode === 'pilih_paket')
        {{-- ========================================================================= --}}
        {{-- HALAMAN 1: PILIH PAKET UJIAN                                             --}}
        {{-- ========================================================================= --}}
        <div class="space-y-4" x-data="{ openRestore: false }">
            <div>
                <nav class="text-xs text-slate-400 mb-1">
                    <span>Ujian</span> / Pilih Paket
                </nav>
                <h1 class="page-title text-2xl font-bold text-slate-800 dark:text-slate-100 m-0">Pilih Paket Ujian</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                    Pilih periode ujian untuk mulai mengelola butir soal, mata pelajaran, dan jadwal pelaksanaan.
                </p>
            </div>

            {{-- Tombol Aksi di Bawah Tulisan --}}
            <div class="flex flex-wrap items-center gap-2.5 pt-1">
                <a href="{{ route('ujian.create') }}" class="btn-primary px-4 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Buat Ujian</span>
                </a>

                @can('manage_ujian')
                <a href="{{ route('ujian.paket.create') }}" class="btn-secondary px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="folder-plus" class="w-4 h-4 text-primary"></i>
                    <span>Buat Paket Ujian</span>
                </a>
                @endcan

                <button type="button" @click="openRestore = true" class="btn-secondary px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 shadow-sm">
                    <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                    <span>Restore Backup</span>
                </button>

                <form method="POST" action="{{ route('ujian.mode-ujian.toggle') }}" class="m-0">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-2 border transition {{ \App\Support\ModeUjian::aktif() ? 'bg-amber-500 hover:bg-amber-600 text-slate-900 border-amber-600' : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-600' }}" title="Sakelar Mode Ujian Massal">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                        <span>Mode Ujian: {{ \App\Support\ModeUjian::aktif() ? 'AKTIF' : 'NONAKTIF' }}</span>
                    </button>
                </form>
            </div>

            {{-- Modal Restore Backup --}}
            <div x-show="openRestore" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" style="display: none;" x-cloak>
                <form method="POST" action="{{ route('ujian.restore') }}" enctype="multipart/form-data" class="bg-white dark:bg-slate-800 rounded-2xl p-6 max-w-md w-full space-y-4 shadow-xl" @click.outside="openRestore = false">
                    @csrf
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Restore Ujian dari Backup</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Pilih file <code>.json</code> hasil backup ujian sebelumnya untuk memulihkan data ujian beserta nilai siswa.</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">File Backup (.json)</label>
                            <input type="file" name="backup_file" accept=".json" required class="form-input w-full p-2 border border-slate-300 rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Password Admin</label>
                            <input type="password" name="password" required class="form-input w-full p-2 border border-slate-300 rounded" placeholder="Password Anda">
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="openRestore = false" class="btn-secondary px-4 py-2 rounded-xl text-sm">Batal</button>
                        <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-bold">Mulai Restore</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Grid Kartu Paket Ujian --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($daftarPaket as $pkt)
                @php
                    $countMapel = $ujians->where('id_ujian_paket', $pkt->uuid)->count();
                    $statusPaketBadge = match($pkt->status) {
                        'berjalan' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200',
                        'selesai'  => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border-slate-200',
                        default    => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200',
                    };
                @endphp
                <div class="card p-5 flex flex-col justify-between gap-4 border border-slate-200/80 dark:border-slate-700/60 hover:border-primary hover:shadow-lg transition group bg-white dark:bg-slate-800 rounded-2xl">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold shadow-inner">
                                <i data-lucide="package" class="w-5 h-5"></i>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                    {{ strtoupper($pkt->jenis) }}
                                </span>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $statusPaketBadge }}">
                                    {{ ucfirst($pkt->status ?? 'draf') }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <h2 class="font-black text-lg text-slate-900 dark:text-slate-100 group-hover:text-primary transition leading-snug">
                                {{ $pkt->nama }}
                            </h2>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 flex items-center gap-1">
                                <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                                @if($pkt->tanggal_mulai)
                                    {{ $pkt->tanggal_mulai->format('d M Y') }} — {{ $pkt->tanggal_selesai?->format('d M Y') ?? 'Selesai' }}
                                @else
                                    Jadwal belum ditentukan
                                @endif
                            </p>
                        </div>

                        {{-- Ringkasan Metrik --}}
                        <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-800 text-center">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Pelajaran</span>
                                <strong class="text-sm font-black text-slate-800 dark:text-slate-200">{{ $countMapel }} Mapel</strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Ruangan</span>
                                <strong class="text-sm font-black text-slate-800 dark:text-slate-200">{{ $pkt->ruangan_count }} Ruangan</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Tombol Buka Paket --}}
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 space-y-2">
                        <a href="{{ route('ujian.index', ['paket' => $pkt->uuid]) }}"
                           class="btn-primary w-full py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm group-hover:shadow-md transition">
                            <span>Buka &amp; Kelola Ujian</span>
                            <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        @can('manage_ujian')
                        <div class="text-center">
                            <a href="{{ route('ujian.paket.show', $pkt) }}" class="text-[11px] font-semibold text-slate-400 hover:text-primary transition inline-flex items-center gap-1">
                                <i data-lucide="settings" class="w-3 h-3"></i> Pengaturan Ruangan &amp; Jadwal
                            </a>
                        </div>
                        @endcan
                    </div>
                </div>
            @endforeach

            {{-- Kartu Ujian Mandiri / Tanpa Paket --}}
            @if($totalTanpaPaket > 0)
                <div class="card p-5 flex flex-col justify-between gap-4 border border-dashed border-slate-300 dark:border-slate-700 hover:border-primary hover:shadow-lg transition group bg-white dark:bg-slate-800 rounded-2xl">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="w-10 h-10 rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                <i data-lucide="file-question" class="w-5 h-5"></i>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200">
                                Mandiri / Lepas
                            </span>
                        </div>

                        <div>
                            <h2 class="font-black text-lg text-slate-900 dark:text-slate-100 group-hover:text-primary transition leading-snug">
                                Ujian Mandiri (Tanpa Paket)
                            </h2>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                Ujian ulangan harian atau kuis yang dibuat mandiri tanpa bergabung ke paket formal.
                            </p>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-800 text-center">
                            <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Ujian</span>
                            <strong class="text-sm font-black text-slate-800 dark:text-slate-200">{{ $totalTanpaPaket }} Ujian Mandiri</strong>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                        <a href="{{ route('ujian.index', ['paket' => 'none']) }}"
                           class="btn-secondary w-full py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition">
                            <span>Buka Ujian Mandiri</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Opsi Tampilkan Semua --}}
        <div class="pt-4 text-center">
            <a href="{{ route('ujian.index', ['paket' => 'all']) }}" class="text-xs font-semibold text-slate-500 hover:text-primary transition inline-flex items-center gap-1.5">
                <i data-lucide="list" class="w-3.5 h-3.5"></i>
                <span>Atau lihat seluruh daftar mata pelajaran sekaligus (tanpa filter paket) &rarr;</span>
            </a>
        </div>

    @else
        {{-- ========================================================================= --}}
        {{-- HALAMAN 2: KELOLA UJIAN / MATA PELAJARAN DALAM PAKET TERPILIH            --}}
        {{-- ========================================================================= --}}
        <div class="space-y-5"
             x-data="{
                search: '',
                selectedTingkat: 'all',
                selectedStatus: 'all',
                matches(tingkatStr, status, searchData) {
                    if (this.selectedTingkat !== 'all') {
                        const tingkats = tingkatStr.split(',').filter(Boolean);
                        if (!tingkats.includes(this.selectedTingkat)) return false;
                    }
                    if (this.selectedStatus !== 'all' && status !== this.selectedStatus) {
                        return false;
                    }
                    if (this.search.trim() !== '') {
                        const q = this.search.toLowerCase().trim();
                        if (!searchData.includes(q)) return false;
                    }
                    return true;
                },
                resetFilters() {
                    this.search = '';
                    this.selectedTingkat = 'all';
                    this.selectedStatus = 'all';
                }
             }">

            {{-- Header Navigasi & Info Paket Terpilih --}}
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 sm:p-6 border border-slate-200/80 dark:border-slate-700/60 shadow-sm space-y-4">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <a href="{{ route('ujian.index') }}"
                           class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition inline-flex items-center gap-1.5 shadow-sm whitespace-nowrap">
                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                            <span>Ganti Paket Ujian</span>
                        </a>
                        <span class="text-xs text-slate-300 dark:text-slate-600">&bull;</span>
                        <span class="text-xs text-slate-400 font-medium">
                            {{ $activePaket ? 'Periode: ' . strtoupper($activePaket->jenis) : ($activePaketId === 'none' ? 'Ujian Mandiri' : 'Semua Paket') }}
                        </span>
                    </div>

                    <h1 class="page-title text-2xl font-black text-slate-900 dark:text-slate-100 m-0">
                        {{ $activePaket ? $activePaket->nama : ($activePaketId === 'none' ? 'Ujian Mandiri (Tanpa Paket)' : 'Semua Ujian & Pelajaran') }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        Pilih mata pelajaran di bawah ini untuk langsung menyusun butir soal, pratinjau, atau uji coba pre-test.
                    </p>
                </div>

                {{-- Tombol Aksi di Bawah Tulisan --}}
                <div class="flex items-center gap-2.5 flex-wrap pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <a href="{{ route('ujian.create') }}" class="btn-primary px-4 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah Ujian
                    </a>

                    @if($activePaket)
                        @can('manage_ujian')
                        <a href="{{ route('ujian.paket.show', $activePaket) }}" class="btn-secondary px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-1.5 shadow-sm">
                            <i data-lucide="settings" class="w-4 h-4 text-slate-500"></i> Ruangan &amp; Jadwal
                        </a>
                        @endcan
                    @endif
                </div>
            </div>

            {{-- Bilah Pencarian & Filter Cepat --}}
            <div class="card p-3.5 sm:p-4 space-y-3">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    {{-- Input Pencarian Realtime --}}
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="text"
                               x-model="search"
                               placeholder="Cari mata pelajaran, kelas, atau judul (misal: Pancasila, Kelas 8)..."
                               class="form-input w-full pl-10 pr-9 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-900/50 rounded-xl border-slate-200 dark:border-slate-700 focus:bg-white transition">
                        <button type="button" x-show="search.length > 0" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" x-cloak>
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    {{-- Filter Dropdowns --}}
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                        <select x-model="selectedTingkat" class="form-select text-xs py-2 pl-3 pr-8 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                            <option value="all">Semua Tingkat</option>
                            @foreach($daftarTingkat as $tingkat)
                                <option value="{{ $tingkat }}">Tingkat Kelas {{ $tingkat }}</option>
                            @endforeach
                        </select>

                        <select x-model="selectedStatus" class="form-select text-xs py-2 pl-3 pr-8 rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                            <option value="all">Semua Status</option>
                            <option value="draft">Draf</option>
                            <option value="published">Diterbitkan</option>
                            <option value="closed">Ditutup</option>
                        </select>

                        <button type="button"
                                x-show="search !== '' || selectedTingkat !== 'all' || selectedStatus !== 'all'"
                                @click="resetFilters()"
                                x-cloak
                                class="px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-800 transition flex items-center gap-1 whitespace-nowrap">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            {{-- Grid Kartu Pelajaran --}}
            @php
                // Filter ujian yang sesuai dengan paket terpilih
                $filteredUjians = $ujians->when($activePaketId && $activePaketId !== 'all', function ($collection) use ($activePaketId) {
                    if ($activePaketId === 'none') {
                        return $collection->whereNull('id_ujian_paket');
                    }
                    return $collection->where('id_ujian_paket', $activePaketId);
                });
            @endphp

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @forelse($filteredUjians as $ujian)
                    @php
                        $tingkatList = $ujian->kelas->pluck('kelas.tingkat')->filter()->unique()->values()->all();
                        $tingkatStr = implode(',', $tingkatList);
                        $kelasLabelList = $ujian->kelas->map(fn($k) => ($k->kelas?->tingkat ?? '') . ($k->kelas?->kelas ?? ''))->implode(', ');
                        $searchData = strtolower(implode(' ', [
                            $ujian->judul,
                            $ujian->pelajaran?->nama ?? '',
                            $ujian->jenisLabel(),
                            $kelasLabelList,
                            'kelas ' . implode(' ', $tingkatList),
                        ]));

                        $statusBadge = match($ujian->status) {
                            'published' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200',
                            'closed' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 border-slate-300',
                            default => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200',
                        };
                    @endphp

                    <div x-show="matches('{{ $tingkatStr }}', '{{ $ujian->status }}', '{{ addslashes($searchData) }}')"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 transform scale-95"
                         x-transition:enter-end="opacity-100 transform scale-100"
                         class="card p-5 flex flex-col justify-between gap-4 border border-slate-200/80 dark:border-slate-700/60 hover:border-primary hover:shadow-md transition group bg-white dark:bg-slate-800 rounded-2xl">

                        <div class="space-y-3">
                            {{-- Header Kartu --}}
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-2 flex-wrap">
                                    {{-- Nama Pelajaran (Sangat Menonjol) --}}
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-black bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/70 dark:border-indigo-800/60">
                                        <i data-lucide="book-open" class="w-3.5 h-3.5 text-indigo-500"></i>
                                        {{ $ujian->pelajaran?->nama ?? 'Pelajaran Umum' }}
                                    </span>

                                    {{-- Tingkat Kelas --}}
                                    @if(!empty($tingkatList))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600">
                                            <i data-lucide="graduation-cap" class="w-3.5 h-3.5 text-slate-400"></i>
                                            Kelas {{ implode(', ', $tingkatList) }}
                                        </span>
                                    @endif

                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ $ujian->jenisLabel() }}
                                    </span>
                                </div>

                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusBadge }}">
                                    {{ $ujian->statusLabel() }}
                                </span>
                            </div>

                            {{-- Judul Ujian --}}
                            <div>
                                <a href="{{ route('ujian.show', $ujian) }}" class="font-black text-base text-slate-900 dark:text-slate-100 group-hover:text-primary transition line-clamp-2 block leading-snug">
                                    {{ $ujian->judul }}
                                </a>
                            </div>

                            {{-- Target Kelas --}}
                            <div class="bg-slate-50 dark:bg-slate-900/40 rounded-xl p-2.5 border border-slate-100 dark:border-slate-800 text-xs space-y-1">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span class="font-semibold uppercase tracking-wider">Target Kelas ({{ $ujian->kelas->count() }} Kelas):</span>
                                    <span>Target: <strong class="text-slate-600 dark:text-slate-300">{{ strtoupper($ujian->target_nilai) }}</strong></span>
                                </div>
                                <div class="font-bold text-slate-700 dark:text-slate-200 truncate" title="{{ $kelasLabelList ?: 'Belum ada kelas' }}">
                                    {{ $kelasLabelList ?: '— Belum ada kelas yang ditetapkan —' }}
                                </div>
                            </div>

                            {{-- Metrik Soal & Waktu --}}
                            <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                                <span class="flex items-center gap-1.5 font-semibold text-slate-700 dark:text-slate-300">
                                    <i data-lucide="file-text" class="w-4 h-4 text-primary"></i>
                                    <strong>{{ $ujian->soal_count }}</strong> Butir Soal
                                </span>
                                <span class="flex items-center gap-1.5 font-semibold text-slate-700 dark:text-slate-300">
                                    <i data-lucide="clock" class="w-4 h-4 text-amber-500"></i>
                                    <strong>{{ $ujian->durasi_menit }}</strong> Menit
                                </span>
                            </div>
                        </div>

                        {{-- Tombol Aksi Langsung (Susun Soal Front & Center) --}}
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2 flex-wrap">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                {{-- Tombol Langsung Susun Soal --}}
                                <a href="{{ route('ujian.edit', $ujian) }}"
                                   class="btn-primary py-2 px-3.5 rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm">
                                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                                    <span>Susun Soal ({{ $ujian->soal_count }})</span>
                                </a>

                                {{-- Pratinjau Siswa --}}
                                <a href="{{ route('ujian.pratinjau', $ujian) }}" target="_blank" rel="noopener"
                                   class="px-2.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition flex items-center gap-1"
                                   title="Pratinjau tampilan soal siswa">
                                    <i data-lucide="smartphone" class="w-3.5 h-3.5"></i>
                                    <span>Pratinjau</span>
                                </a>
                            </div>

                            <div class="flex items-center gap-1.5">
                                @can('manage', $ujian)
                                    @if($ujian->susulans_exists)
                                        <a href="{{ route('ujian.susulan.index', $ujian) }}" class="px-2.5 py-1.5 rounded-xl text-xs font-semibold border border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400 dark:hover:bg-indigo-900/30">
                                            Susulan
                                        </a>
                                    @endif

                                    @if($ujian->status === 'published')
                                        @if($ujian->kelas->count() > 0)
                                        <form method="POST" action="{{ route('ujian.token.reset', $ujian) }}" onsubmit="return confirmAction(this, 'Buat token baru untuk SEMUA kelas ujian ini? Token lama tidak berlaku lagi.', 'orange')">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300">
                                                Reset Token
                                            </button>
                                        </form>
                                        @endif

                                        @can('close', $ujian)
                                        <form method="POST" action="{{ route('ujian.close', $ujian) }}" onsubmit="return confirmAction(this, 'Tutup ujian ini? Siswa tidak bisa lagi memulai/melanjutkan.', 'red')">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700">
                                                Tutup
                                            </button>
                                        </form>
                                        @endcan
                                    @endif
                                @endcan

                                <a href="{{ route('ujian.show', $ujian) }}"
                                   class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 dark:hover:text-slate-200 dark:hover:bg-slate-700/60 transition"
                                   title="Lihat Detail &amp; Pengaturan Ujian">
                                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full card p-12 text-center text-slate-400 dark:text-slate-500 space-y-3">
                        <i data-lucide="file-check-2" class="w-12 h-12 mx-auto opacity-30 text-slate-400"></i>
                        <h3 class="text-base font-bold text-slate-700 dark:text-slate-300">Belum Ada Pelajaran di Paket Ini</h3>
                        <p class="text-xs text-slate-400 max-w-sm mx-auto">Tambahkan mata pelajaran ke dalam paket ini untuk mulai menyusun butir soal.</p>
                        @can('create', \App\Models\Ujian::class)
                        <div class="pt-2">
                            <a href="{{ route('ujian.create') }}" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold inline-flex items-center gap-1.5">
                                <i data-lucide="plus" class="w-4 h-4"></i> Buat Ujian Pertama
                            </a>
                        </div>
                        @endcan
                    </div>
                @endforelse
            </div>
        </div>
    @endif

</div>
@endsection
