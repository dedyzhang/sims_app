@props(['label' => 'Scan QR Ruangan'])
{{-- Scan QR ruangan langsung dari dalam website (siswa) — kamera device via browser,
     decode client-side (qr-scanner, sudah dimuat global di layouts/app.blade.php), lalu
     navigasi ke URL hasil scan cuma kalau memang cocok pola route ujian.ruangan.scan
     (supaya QR lain yg tak sengaja terbaca kamera tak nyasar buka URL sembarangan) —
     pola sama persis dgn scanner guru di ujian/ruangan/daftar.blade.php. --}}
<div x-data="{
        open: false,
        error: '',
        scanner: null,
        mulai() {
            this.open = true;
            this.error = '';
            this.$nextTick(() => {
                if (typeof QrScanner === 'undefined') {
                    this.error = 'Fitur scan QR belum siap dimuat — coba muat ulang halaman.';
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
                    this.error = 'Tidak bisa mengakses kamera: ' + (e?.message || e);
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
    <button type="button" @click="mulai()" class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white" style="background:var(--cp)">
        <i data-lucide="qr-code" class="w-4 h-4"></i> {{ $label }}
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" @keydown.escape.window="tutup()">
        <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden w-full max-w-sm" @click.outside="tutup()">
            <div class="p-4 flex items-center justify-between border-b border-slate-100 dark:border-slate-700">
                <p class="font-bold text-sm">Scan QR Ruangan</p>
                <button type="button" @click="tutup()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="relative aspect-square bg-black">
                <video x-ref="video" class="w-full h-full object-cover" muted playsinline></video>
            </div>
            <div class="p-4 space-y-2">
                <p class="text-xs text-slate-500 dark:text-slate-400">Arahkan kamera ke QR yang tertempel fisik di ruangan ujian Anda.</p>
                <p x-show="error" x-text="error" x-cloak class="text-xs text-rose-600 dark:text-rose-400"></p>
            </div>
        </div>
    </div>
</div>
