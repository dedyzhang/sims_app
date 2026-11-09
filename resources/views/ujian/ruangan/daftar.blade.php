@extends('layouts.app')
@section('title', $viewMode === 'pilih_paket' ? 'Pilih Paket — Ruang Ujian' : 'Ruang Ujian Hari Ini')

@section('content')
<div class="max-w-4xl mx-auto space-y-6"
     x-data="{
        open: false,
        error: '',
        scanner: null,
        fallbackUrl: '',
        search: '',
        matches(namaRuang, searchData) {
            if (this.search.trim() !== '') {
                const q = this.search.toLowerCase().trim();
                if (!searchData.includes(q)) return false;
            }
            return true;
        },
        mulai(url) {
            this.fallbackUrl = url;
            this.open = true;
            this.error = '';
            this.$nextTick(() => {
                if (typeof QrScanner === 'undefined') {
                    this.error = 'Fitur scan QR belum siap dimuat — coba muat ulang halaman, atau pakai tombol \'Masuk tanpa scan\' di bawah.';
                    return;
                }
                if (!QrScanner.WORKER_PATH) {
                    QrScanner.WORKER_PATH = 'https://cdn.jsdelivr.net/npm/qr-scanner@1.4.2/qr-scanner-worker.min.js';
                }
                this.scanner = new QrScanner(this.$refs.video, (result) => this.hasil(result), {
                    highlightScanRegion: true,
                    highlightCodeOutline: true,
                    preferredCamera: 'environment',
                });
                this.scanner.start().catch((e) => {
                    this.error = 'Tidak bisa mengakses kamera: ' + (e?.message || e) + ' — pakai tombol \'Masuk tanpa scan\' di bawah kalau kamera tak tersedia.';
                });
            });
        },
        hasil(result) {
            const teks = (typeof result === 'string') ? result : (result?.data || '');
            if (/^https?:\/\/[^\s\/]+\/ujian\/ruangan\/[0-9a-f-]+\/scan(\?.*)?$/i.test(teks)) {
                this.tutup();
                window.location.href = teks;
            }
        },
        tutup() {
            this.open = false;
            if (this.scanner) { this.scanner.destroy(); this.scanner = null; }
        },
     }">

    @if($viewMode === 'pilih_paket')
        {{-- ========================================================================= --}}
        {{-- HALAMAN 1: PILIH PAKET UJIAN UNTUK RUANGAN                              --}}
        {{-- ========================================================================= --}}
        <div class="space-y-4">
            <div>
                <nav class="text-xs text-slate-400 mb-1">
                    <span>Ujian</span> / Ruang Ujian
                </nav>
                <h1 class="page-title text-2xl font-bold text-slate-800 dark:text-slate-100 m-0">Pilih Paket Ujian</h1>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                    Pilih periode ujian untuk melihat daftar ruangan yang sedang aktif, memantau kehadiran siswa, dan mengisi berita acara pengawasan.
                </p>
            </div>
        </div>

        {{-- Grid Kartu Paket Ujian --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($daftarPaket as $pkt)
                @php
                    $adaJadwalHariIni = in_array($pkt->uuid, $idPaketHariIni);
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
                                <i data-lucide="door-open" class="w-5 h-5"></i>
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if($adaJadwalHariIni)
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 animate-pulse">
                                        ● Ada Jadwal Hari Ini
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                        Tidak Ada Jadwal Hari Ini
                                    </span>
                                @endif
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

                        <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-800 text-center">
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Ruangan</span>
                                <strong class="text-sm font-black text-slate-800 dark:text-slate-200">{{ $pkt->ruangan_count }} Ruang</strong>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 uppercase font-bold block">Mapel Ujian</span>
                                <strong class="text-sm font-black text-slate-800 dark:text-slate-200">{{ $pkt->ujian_count }} Mapel</strong>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60">
                        <a href="{{ route('ujian.ruangan.saya', ['paket' => $pkt->uuid]) }}"
                           class="btn-primary w-full py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-sm group-hover:shadow-md transition">
                            <span>Buka Ruangan Ujian</span>
                            <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full card p-10 text-center text-slate-400">
                    <i data-lucide="folder-x" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                    <p class="text-sm font-medium">Belum ada paket ujian yang memiliki ruangan terdaftar.</p>
                </div>
            @endforelse
        </div>

        {{-- Opsi Tampilkan Semua --}}
        @if($daftarPaket->count() > 1)
        <div class="pt-2 text-center">
            <a href="{{ route('ujian.ruangan.saya', ['paket' => 'all']) }}" class="text-xs font-semibold text-slate-500 hover:text-primary transition inline-flex items-center gap-1.5">
                <i data-lucide="list" class="w-3.5 h-3.5"></i>
                <span>Lihat seluruh ruangan ujian sekaligus (tanpa filter paket) &rarr;</span>
            </a>
        </div>
        @endif

    @else
        {{-- ========================================================================= --}}
        {{-- HALAMAN 2: DAFTAR RUANGAN DALAM PAKET TERPILIH                           --}}
        {{-- ========================================================================= --}}
        <div class="space-y-4">
            {{-- Header Navigasi --}}
            <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 border border-slate-200/80 dark:border-slate-700/60 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        @if($daftarPaket->count() > 1)
                        <div class="flex items-center gap-2 mb-2">
                            <a href="{{ route('ujian.ruangan.saya') }}"
                               class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 transition inline-flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                                <span>Ganti Paket Ujian</span>
                            </a>
                            <span class="text-xs text-slate-300 dark:text-slate-600">&bull;</span>
                            <span class="text-xs text-slate-400 font-medium">
                                {{ $activePaket ? $activePaket->nama : 'Semua Paket' }}
                            </span>
                        </div>
                        @endif

                        <h1 class="page-title text-2xl font-black text-slate-900 dark:text-slate-100 m-0">
                            Ruang Ujian Hari Ini
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                            {{ $activePaket ? 'Paket: ' . $activePaket->nama . ' — ' : '' }}Pilih ruangan Anda atau scan poster QR di depan pintu ruangan untuk mulai mengawasi.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Bilah Pencarian Realtime & Filter --}}
            <div class="card p-3 sm:p-4 space-y-3">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    {{-- Input Pencarian --}}
                    <div class="relative flex-1">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="text"
                               x-model="search"
                               placeholder="Cari ruangan (mis. Ruang 1), mapel ujian, atau sesi..."
                               class="form-input w-full pl-10 pr-9 py-2 text-xs sm:text-sm bg-slate-50 dark:bg-slate-900/50 rounded-xl border-slate-200 dark:border-slate-700 focus:bg-white transition">
                        <button type="button" x-show="search.length > 0" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" x-cloak>
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    {{-- Badge Status Jumlah Ruangan Aktif --}}
                    @if($ruanganList->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>{{ $ruanganList->count() }} Ruangan Aktif Hari Ini</span>
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Grid Kartu Ruangan --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($ruanganList as $r)
                    @php
                        $searchData = strtolower(implode(' ', [
                            $r->nama,
                            $r->paket?->nama ?? '',
                            $r->mapelHariIni->pluck('mapel')->implode(' '),
                            $r->mapelHariIni->pluck('sesi')->implode(' '),
                        ]));
                    @endphp

                    <div x-show="matches('{{ $r->nama }}', '{{ addslashes($searchData) }}')"
                         x-transition
                         class="card p-5 flex flex-col justify-between gap-4 border border-slate-200/80 dark:border-slate-700/60 hover:border-primary hover:shadow-md transition bg-white dark:bg-slate-800 rounded-2xl group">
                        
                        <div class="space-y-3">
                            {{-- Header Kartu Ruangan --}}
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black">
                                        <i data-lucide="door-open" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-black text-base text-slate-800 dark:text-slate-100 group-hover:text-primary transition">
                                            {{ $r->nama }}
                                        </h3>
                                        <p class="text-xs text-slate-400 truncate max-w-[200px]" title="{{ $r->paket?->nama }}">
                                            {{ $r->paket?->nama ?? 'Tanpa Paket' }}
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                                        Ujian Hari Ini
                                    </span>
                                </div>
                            </div>

                            {{-- Info Peserta --}}
                            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <i data-lucide="users" class="w-4 h-4 text-slate-400"></i>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ $r->peserta_count }} Siswa</span>
                                <span>terdaftar di ruangan ini</span>
                            </div>

                            {{-- Rincian Sesi & Mapel Hari Ini --}}
                            @if($r->mapelHariIni->isNotEmpty())
                                <div class="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 text-xs space-y-1.5">
                                    <div class="text-[10px] font-extrabold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider flex items-center gap-1 mb-1">
                                        <i data-lucide="clock" class="w-3 h-3"></i> Jadwal Sesi Hari Ini:
                                    </div>
                                    @foreach($r->mapelHariIni as $sesi)
                                        <div class="flex items-center justify-between gap-2 border-b border-emerald-100/60 dark:border-emerald-900/20 pb-1 last:border-0 last:pb-0">
                                            <span class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $sesi['mapel'] }}</span>
                                            <span class="font-mono text-[11px] text-emerald-700 dark:text-emerald-400 shrink-0 font-semibold">{{ $sesi['jam'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-400 flex items-center gap-1.5">
                                    <i data-lucide="calendar-x" class="w-4 h-4 text-slate-400 shrink-0"></i>
                                    <span>Tidak ada jadwal ujian pada tanggal hari ini.</span>
                                </div>
                            @endif
                        </div>

                        {{-- Footer Aksi --}}
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-2">
                            <a href="{{ route('ujian.ruangan.monitor', $r) }}"
                               class="text-xs font-semibold text-slate-500 hover:text-primary transition flex items-center gap-1">
                                <span>Buka Monitor</span>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </a>

                            <button type="button" @click="mulai('{{ route('ujian.ruangan.scan', $r) }}')"
                                    class="px-4 py-2 rounded-xl text-xs font-bold bg-primary text-white hover:opacity-95 shadow-sm flex items-center gap-1.5 transition">
                                <i data-lucide="qr-code" class="w-4 h-4"></i>
                                <span>Scan &amp; Masuk</span>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white dark:bg-slate-800 rounded-3xl p-8 sm:p-12 text-center border border-slate-200/80 dark:border-slate-700/60 shadow-sm space-y-3">
                        <div class="w-16 h-16 rounded-3xl bg-slate-100 dark:bg-slate-900 text-slate-400 flex items-center justify-center mx-auto shadow-inner">
                            <i data-lucide="calendar-x" class="w-8 h-8 opacity-60"></i>
                        </div>
                        <div class="space-y-1">
                            <h3 class="font-bold text-base text-slate-800 dark:text-slate-100">
                                Tidak Ada Ruang Ujian yang Dijadwalkan Hari Ini
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                                Tidak ada jadwal pelaksanaan ujian pada tanggal hari ini ({{ now()->translatedFormat('l, d F Y') }}). Ruang ujian akan tampil secara otomatis saat tanggal pelaksanaan ujian tiba.
                            </p>
                        </div>
                        @if(auth()->user()->isAdmin() || auth()->user()->canAccess('manage_ujian'))
                            <div class="pt-2">
                                <a href="{{ route('ujian.paket.index') }}" class="btn-outline text-xs font-semibold inline-flex items-center gap-1.5 px-4 py-2 rounded-xl">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <span>Kelola Jadwal di Paket Ujian</span>
                                </a>
                            </div>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Modal Scan QR Kamera --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
         @keydown.escape.window="tutup()">
        <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden w-full max-w-sm" @click.outside="tutup()">
            <div class="p-4 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
                <p class="font-bold text-sm">Scan QR Ruangan</p>
                <button type="button" @click="tutup()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="relative aspect-square bg-black">
                <video x-ref="video" class="w-full h-full object-cover" muted playsinline></video>
            </div>
            <div class="p-4 space-y-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">Arahkan kamera ke QR yang tertempel fisik di ruangan.</p>
                <p x-show="error" x-text="error" x-cloak class="text-xs text-rose-600 dark:text-rose-400"></p>
                <button type="button" @click="window.location.href = fallbackUrl"
                        class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 underline">
                    Tidak bisa scan? Masuk tanpa scan
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
