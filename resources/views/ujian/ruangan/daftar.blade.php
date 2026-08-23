@extends('layouts.app')
@section('title', 'Ruang Ujian Hari Ini')

@section('content')
<div class="max-w-2xl mx-auto space-y-5"
     x-data="{
        open: false,
        error: '',
        scanner: null,
        fallbackUrl: '',
        mulai(url) {
            this.fallbackUrl = url;
            this.open = true;
            this.error = '';
            this.$nextTick(() => {
                if (typeof QrScanner === 'undefined') {
                    this.error = 'Fitur scan QR belum siap dimuat — coba muat ulang halaman, atau pakai tombol \'Masuk tanpa scan\' di bawah.';
                    return;
                }
                // qr-scanner dimuat lewat <script> klasik dari CDN (bukan bundler ES module),
                // jadi dynamic import() bawaannya utk cari worker GAGAL resolve base URL —
                // wajib set WORKER_PATH eksplisit ke file worker yg sama versinya di CDN.
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
            // Poster QR fisik ruangan berisi URL absolut route('ujian.ruangan.scan', $ruangan) —
            // cuma navigasi kalau memang cocok pola itu, supaya QR lain yg tak sengaja terbaca
            // kamera (mis. QR di kertas lain) tak nyasar buka URL sembarangan.
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
    <div>
        <h1 class="page-title">Ruang Ujian Hari Ini</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Ruangan dengan ujian dijadwalkan hari ini. Tap "Scan &amp; Masuk", lalu arahkan kamera ke QR yang tertempel fisik di ruangan — langsung tercatat sebagai pengawas sesi yang sedang berjalan, tanpa perlu admin menjadwalkan pengawas dulu.</p>
    </div>

    <div class="grid gap-2.5">
        @forelse($ruanganList as $r)
        <div class="card p-4 flex items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-sm">{{ $r->nama }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $r->paket?->nama }}</p>
            </div>
            <button type="button" @click="mulai('{{ route('ujian.ruangan.scan', $r) }}')"
                    class="flex items-center gap-1.5 text-xs font-bold text-primary shrink-0">
                <i data-lucide="qr-code" class="w-4 h-4"></i> Scan &amp; Masuk
            </button>
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="door-open" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Belum ada ruangan dengan ujian dijadwalkan hari ini.</p>
        </div>
        @endforelse
    </div>

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
