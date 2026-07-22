{{-- Modal pindai QR gabung Arena (siswa) --}}
<div x-show="showScan" x-cloak
     class="fixed inset-0 z-[60] grid place-items-center bg-slate-900/60 p-4"
     @keydown.escape.window="closeScan()">
    <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 overflow-hidden"
         @click.outside="closeScan()">
        <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
            <h3 class="m-0 text-base font-black text-slate-800 dark:text-slate-100">Pindai QR / Barcode Arena</h3>
            <button type="button" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400" @click="closeScan()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="p-4 space-y-3">
            <div x-show="scanning" class="rounded-xl overflow-hidden bg-slate-900 min-h-[220px]">
                <div id="arena-join-qr-reader"></div>
            </div>
            <div x-show="!scanning && !scanError" class="text-center py-10 text-slate-400 text-sm">
                <i data-lucide="loader-2" class="w-7 h-7 mx-auto animate-spin mb-2"></i> Membuka kamera…
            </div>
            <p class="text-xs text-rose-500 text-center m-0" x-show="scanError" x-text="scanError"></p>
            <p class="text-xs text-slate-400 text-center m-0">Arahkan ke QR atau barcode yang ditampilkan guru di layar kelas.</p>
        </div>
    </div>
</div>

@once
@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
function parseArenaJoinScan(raw) {
    const s = String(raw || '').trim();
    if (!s) return null;
    try {
        const u = new URL(s, window.location.origin);
        const token = u.searchParams.get('t') || u.searchParams.get('solo_token') || u.searchParams.get('token');
        const join = u.searchParams.get('join');
        if (token && join === 'live') return { type: 'live', token: String(token).toUpperCase().slice(0, 8), url: u.pathname + u.search };
        if (token) return { type: 'solo', token: String(token).toUpperCase().slice(0, 8) };
        if (u.pathname.includes('/live')) return { type: 'live', url: u.pathname + u.search };
        if (u.pathname.includes('/arena-belajar/')) return { type: 'show', url: u.pathname + u.search };
    } catch (e) {}
    const mSolo = s.match(/SIMS-ARENA[:\-]SOLO[:\-]([A-Z0-9]{4,8})/i);
    if (mSolo) return { type: 'solo', token: mSolo[1].toUpperCase() };
    const mLive = s.match(/SIMS-ARENA[:\-]LIVE[:\-]([A-Z0-9]{4,8})/i);
    if (mLive) return { type: 'live', token: mLive[1].toUpperCase() };
    if (/^[A-Z0-9]{4,8}$/i.test(s)) return { type: 'solo', token: s.toUpperCase() };
    return null;
}

function arenaSoloJoin(opts = {}) {
    return {
        showToken: !!opts.autoOpen,
        showLiveToken: !!opts.autoOpenLive,
        showScan: false,
        soloToken: opts.prefillToken || '',
        liveToken: opts.prefillLiveToken || '',
        barcodeWedge: '',
        barcodeError: '',
        scanError: '',
        scanner: null,
        scanning: false,
        liveUrl: opts.liveUrl || '',
        joinTokenUrl: opts.joinTokenUrl || '',
        liveRedirect: opts.liveRedirect || opts.liveUrl || '',

        init() {
            if (this.soloToken && opts.autoOpen) this.showToken = true;
            if (this.liveToken && opts.autoOpenLive) this.showLiveToken = true;
        },

        openScan() {
            this.showScan = true;
            this.scanError = '';
            this.$nextTick(() => { this.startScan(); setTimeout(() => window.lucide && lucide.createIcons(), 40); });
        },

        closeScan() {
            this.stopScan();
            this.showScan = false;
            this.scanError = '';
        },

        async startScan() {
            if (typeof Html5Qrcode === 'undefined') {
                this.scanError = 'Pemindai QR tidak tersedia.';
                return;
            }
            if (this.scanner) return;
            this.scanning = true;
            try {
                this.scanner = new Html5Qrcode('arena-join-qr-reader');
                await this.scanner.start(
                    { facingMode: 'environment' },
                    { fps: 8, qrbox: { width: 220, height: 220 } },
                    (text) => this.onScanned(text),
                    () => {}
                );
            } catch (e) {
                this.scanError = 'Tidak bisa membuka kamera. Izinkan akses kamera.';
                this.scanning = false;
            }
        },

        stopScan() {
            if (!this.scanner) return Promise.resolve();
            const s = this.scanner;
            this.scanner = null;
            this.scanning = false;
            return s.stop().then(() => { try { s.clear(); } catch (e) {} }).catch(() => {});
        },

        onScanned(raw) {
            this.applyScanResult(parseArenaJoinScan(raw));
        },

        applyScanResult(r) {
            if (!r) {
                this.scanError = 'Kode tidak dikenali. Pindai QR/barcode dari guru Arena.';
                this.barcodeError = 'Barcode tidak dikenali.';
                return;
            }
            this.stopScan();
            this.showScan = false;
            this.scanError = '';
            this.barcodeError = '';
            this.barcodeWedge = '';
            if (r.type === 'solo') {
                this.soloToken = r.token;
                this.showToken = true;
                return;
            }
            if (r.type === 'live' && r.token) {
                this.liveToken = r.token;
                this.showLiveToken = true;
                return;
            }
            if (r.url) window.location.href = r.url;
        },

        submitBarcodeWedge(e) {
            const raw = String((e && e.target && e.target.value) || this.barcodeWedge || '').trim();
            if (!raw) return;
            this.applyScanResult(parseArenaJoinScan(raw));
        },
    };
}
</script>
@endpush
@endonce
