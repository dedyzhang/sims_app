@extends('layouts.app')
@section('title', $ujian->judul)

@php
    $initJawaban = [];
    foreach ($soalTampil as $s) {
        $j = $jawabanTersimpan->get($s['uuid']);
        $initJawaban[$s['uuid']] = match ($s['tipe']) {
            'mcq', 'true_false' => $j->id_opsi_dipilih ?? null,
            'mcq_complex' => $j->opsi_dipilih_multi ?? [],
            'match' => $j->jawaban_pasangan ?? [],
            'essay' => $j->jawaban_esai ?? '',
            default => null,
        };
    }
@endphp

@section('content')
<div id="ujian-root" class="max-w-2xl mx-auto pb-24"
     x-data="ujianKerjakan({
        attemptUuid: {{ Js::from($attempt->uuid) }},
        soal: {{ Js::from($soalTampil) }},
        jawabanAwal: {{ Js::from($initJawaban) }},
        batasWaktu: {{ Js::from($attempt->batas_waktu_pada?->toIso8601String()) }},
        urlJawab: {{ Js::from(route('ujian.siswa.jawab', [$ujian, $attempt])) }},
        urlSinkronBatch: {{ Js::from(route('ujian.siswa.sinkronBatch', [$ujian, $attempt])) }},
        urlStatus: {{ Js::from(route('ujian.siswa.status', [$ujian, $attempt])) }},
        urlKeluar: {{ Js::from(route('ujian.siswa.keluar', [$ujian, $attempt])) }},
        urlSubmit: {{ Js::from(route('ujian.siswa.submit', [$ujian, $attempt])) }},
        urlTerkunci: {{ Js::from(route('ujian.siswa.gate', $ujian)) }},
     })"
     x-init="init()"
     @copy.prevent @cut.prevent @paste.prevent @contextmenu.prevent
>
    {{-- Overlay: wajib layar penuh sebelum mulai (gesture langsung dari klik). Disembunyikan
         juga saat sudah terkunci ATAU sedang mengumpulkan ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â kalau tidak, overlay ini & overlay
         "Ujian Terkunci"/"Mengumpulkan" di bawah bisa tampil bersamaan bertumpuk (semuanya
         fixed inset-0, teks jadi bertabrakan) ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â exitFullscreen() saat submit bikin fsActive
         balik false persis di titik ini. --}}
    <div x-show="!fsActive && !terkunci && !mengumpulkan" x-cloak class="fixed inset-0 z-[9999] bg-slate-900/95 flex items-center justify-center p-6 text-center">
        <div class="max-w-sm space-y-4">
            <i data-lucide="maximize" class="w-14 h-14 text-primary mx-auto"></i>
            <h2 class="text-white text-lg font-bold m-0">Mode Ujian - Layar Penuh</h2>
            <p class="text-slate-300 text-sm m-0 leading-relaxed">Ketuk tombol di bawah untuk masuk layar penuh dan memulai pengerjaan. Waktu sudah berjalan.</p>
            <button type="button" @click="masukLayarPenuh()" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold inline-flex items-center gap-2">
                <i data-lucide="maximize" class="w-4 h-4"></i> Masuk Layar Penuh
            </button>
            <button type="button" @click="lanjutTanpaLayarPenuh()" class="block mx-auto text-xs text-slate-400 hover:text-slate-200 underline">
                HP tidak bisa masuk layar penuh? Lanjutkan tanpa layar penuh
            </button>
        </div>
    </div>

    {{-- Header: countdown + status simpan --}}
    <div class="sticky top-0 z-30 bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 px-4 py-3 -mx-4 mb-4 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs text-slate-400 truncate">{{ $ujian->judul }}</p>
            <p class="text-[11px]" x-show="simpanStatus" x-text="simpanStatus" :class="simpanError ? 'text-rose-500' : 'text-emerald-500'"></p>
        </div>
        <div class="flex items-center gap-1.5 font-mono font-bold text-sm px-3 py-1.5 rounded-lg flex-shrink-0"
             :class="sisaDetik <= 60 ? 'bg-rose-100 dark:bg-rose-950/40 text-rose-600' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200'">
            <i data-lucide="timer" class="w-4 h-4"></i>
            <span x-text="formatWaktu(sisaDetik)"></span>
        </div>
    </div>

    {{-- Navigator nomor soal --}}
    <div class="flex flex-wrap gap-1.5 mb-4">
        <template x-for="(s, i) in soal" :key="s.uuid">
            <button type="button" @click="pindah(i)"
                    class="w-8 h-8 rounded-lg text-xs font-bold flex-shrink-0 border"
                    :class="{
                        'bg-primary text-white border-primary': i === idx,
                        'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-700': i !== idx && sudahDijawab(s),
                        'bg-white dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-600': i !== idx && !sudahDijawab(s),
                    }" x-text="i + 1"></button>
        </template>
    </div>

    {{-- Soal aktif --}}
    <template x-for="(s, i) in soal" :key="'panel-'+s.uuid">
        <div x-show="i === idx" x-cloak class="card p-5 space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-400">Soal <span x-text="i+1"></span> dari <span x-text="soal.length"></span> - <span x-text="s.poin"></span> poin</p>
            </div>
            <div class="text-sm font-medium text-slate-800 dark:text-slate-100 ujian-rich-body" x-html="s.teks_soal"></div>

            {{-- mcq / true_false --}}
            <div x-show="s.tipe==='mcq' || s.tipe==='true_false'" x-cloak class="space-y-2">
                <template x-for="o in (s.opsi||[])" :key="o.uuid">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer"
                           :class="jawaban[s.uuid]===o.uuid ? 'border-primary bg-primary/5' : 'border-slate-200 dark:border-slate-600'">
                        <input type="radio" :name="'opt-'+s.uuid" :checked="jawaban[s.uuid]===o.uuid"
                               @change="jawaban[s.uuid] = o.uuid; tandaiBerubah(s.uuid)" class="text-primary focus:ring-primary flex-shrink-0">
                        <span class="text-sm ujian-rich-body flex-1 break-words min-w-0" x-html="o.teks"></span>
                    </label>
                </template>
            </div>

            {{-- mcq_complex --}}
            <div x-show="s.tipe==='mcq_complex'" x-cloak class="space-y-2">
                <p class="text-xs text-amber-600 dark:text-amber-400">Bisa lebih dari satu jawaban benar.</p>
                <template x-for="o in (s.opsi||[])" :key="o.uuid">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer"
                           :class="(jawaban[s.uuid]||[]).includes(o.uuid) ? 'border-primary bg-primary/5' : 'border-slate-200 dark:border-slate-600'">
                        <input type="checkbox" :checked="(jawaban[s.uuid]||[]).includes(o.uuid)"
                               @change="toggleMulti(s.uuid, o.uuid, $event)"
                               class="w-5 h-5 rounded-md border-2 border-slate-300 dark:border-slate-600 text-primary focus:ring-2 focus:ring-primary/30 transition cursor-pointer flex-shrink-0">
                        <span class="text-sm ujian-rich-body break-words min-w-0 flex-1" x-html="o.teks"></span>
                    </label>
                </template>
            </div>

            {{-- match: kiri/kanan bisa berisi rumus (rich HTML dari TinyMCE), jadi TIDAK bisa
                 dirender pakai <select><option> biasa (formula/gambar tak muncul di dalam
                 option). Kanan ditampilkan sbg kartu pilihan yg diklik per baris kiri. Chip
                 kanan full-width bertumpuk di mobile (tap target lebih besar), wrap spt semula
                 di layar lebar (sm+). --}}
            <div x-show="s.tipe==='match'" x-cloak class="space-y-3">
                <template x-for="(kiri, ki) in (s.kiri||[])" :key="'m-'+s.uuid+'-'+ki">
                    <div class="p-3 rounded-xl border border-slate-200 dark:border-slate-600 space-y-2">
                        <div class="text-sm ujian-rich-body break-words bg-slate-50 dark:bg-slate-700/50 rounded-lg p-2.5" x-html="kiri"></div>
                        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <template x-for="(kanan, kj) in (s.kanan_acak||[])" :key="'m-'+s.uuid+'-'+ki+'-opt-'+kj">
                                <button type="button" @click="setPasangan(s.uuid, kiri, (jawaban[s.uuid]||{})[kiri]===kanan ? '' : kanan)"
                                        :class="(jawaban[s.uuid]||{})[kiri]===kanan ? 'border-primary bg-primary/10' : 'border-slate-200 dark:border-slate-600'"
                                        class="flex items-center gap-2 text-xs px-3 py-2 rounded-lg border ujian-rich-body text-left">
                                    <span class="w-5 h-5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 flex items-center justify-center text-[10px] font-bold flex-shrink-0" x-text="String.fromCharCode(65 + kj)"></span>
                                    <span class="break-words min-w-0" x-html="kanan"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- essay --}}
            <div x-show="s.tipe==='essay'" x-cloak>
                <textarea rows="6" class="form-input" placeholder="Tulis jawaban Anda di sini..."
                          x-model="jawaban[s.uuid]" @input="tandaiBerubah(s.uuid)"></textarea>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="button" @click="pindah(idx-1)" :disabled="idx===0" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 disabled:opacity-30">&larr; Sebelumnya</button>
                <button type="button" x-show="idx < soal.length-1" @click="pindah(idx+1)" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600">Berikutnya &rarr;</button>
                <button type="button" x-show="idx === soal.length-1" @click="konfirmasiSubmit()" class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700">Kumpulkan Ujian</button>
            </div>
        </div>
    </template>

    {{-- Overlay mengumpulkan (submit normal/otomatis) ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â SENGAJA state TERPISAH dari
         "terkunci" (pelanggaran). Sebelumnya kumpulkan() reuse kunci() yg sama dgn
         pelanggaran fullscreen/tab, jadi siswa yg menekan "Kumpulkan Ujian" secara SAH
         sempat melihat overlay "Ujian Terkunci ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Anda keluar dari layar penuh..." yg
         menakutkan & salah konteks (makin kentara kalau fetch submit-nya agak lambat). --}}
    <div x-show="mengumpulkan" x-cloak class="fixed inset-0 z-[9999] bg-slate-900/95 flex items-center justify-center p-6 text-center">
        <div class="max-w-sm space-y-4">
            <i data-lucide="loader-circle" class="w-14 h-14 text-primary mx-auto animate-spin"></i>
            <h2 class="text-white text-lg font-bold m-0">Mengumpulkan Ujian...</h2>
            <p class="text-slate-300 text-sm m-0 leading-relaxed" x-text="mengumpulkanOtomatis ? 'Waktu ujian sudah habis - jawaban Anda sedang dikumpulkan otomatis.' : 'Mohon tunggu sebentar, jangan tutup halaman ini.'"></p>
            {{-- Pesan coba-ulang otomatis saat server padat (lihat jadwalkanKumpulUlang()) --}}
            <p x-show="pesanKumpul" x-text="pesanKumpul" class="text-amber-300 text-xs m-0 leading-relaxed"></p>
        </div>
    </div>

    {{-- Overlay loading 1.5 detik tiap perpindahan/menjawab (sesuai request) --}}
    <div x-show="loadingBlock" x-cloak class="fixed inset-0 z-[99999] bg-slate-900/50 flex items-center justify-center p-6 text-center backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-xl flex flex-col items-center gap-3">
            <i data-lucide="loader-circle" class="w-10 h-10 text-primary animate-spin"></i>
            <p class="text-slate-600 dark:text-slate-300 text-sm font-semibold m-0">Memproses...</p>
        </div>
    </div>

    {{-- Overlay terkunci (client-side, cadangan sebelum reload) --}}
    <div x-show="terkunci" x-cloak class="fixed inset-0 z-[9999] bg-slate-900/95 flex items-center justify-center p-6 text-center">
        <div class="max-w-sm space-y-4">
            <div class="relative inline-block mx-auto">
                <span class="absolute -inset-2 rounded-full bg-rose-500/30 animate-ping"></span>
                <i data-lucide="lock" class="w-14 h-14 text-rose-400 relative mx-auto"></i>
            </div>
            <h2 class="text-white text-lg font-bold m-0">Ujian Terkunci</h2>
            <p class="text-slate-300 text-sm m-0 leading-relaxed">Anda keluar dari layar penuh atau berpindah tab. Hubungi guru/panitia untuk mereset, lalu muat ulang halaman ini.</p>
            <button type="button" @click="window.location.reload()" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold mt-4">
                Muat Ulang Halaman
            </button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .ujian-rich-body {
        font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, sans-serif !important;
        line-height: 1.6;
        overflow-wrap: break-word;
        word-wrap: break-word;
        tab-size: 2;
        -moz-tab-size: 2;
    }
    .ujian-rich-body * {
        font-family: inherit !important;
    }
    .ujian-rich-body pre { white-space: pre-wrap; word-wrap: break-word; }
    .ujian-rich-body p, .ujian-rich-body span { max-width: 100%; }
    .ujian-rich-body p,
    .ujian-rich-body div,
    .ujian-rich-body span:not(.math-svg) {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        text-indent: 0 !important;
    }
    .ujian-rich-body p { margin: 0 0 .5em; }
    .ujian-rich-body p:last-child { margin-bottom: 0; }
    .ujian-rich-body ul {
        list-style-type: disc !important;
        margin: 0.5em 0 0.5em 1.5em !important;
        padding-left: 0.5em !important;
    }
    .ujian-rich-body ol {
        list-style-type: decimal !important;
        margin: 0.5em 0 0.5em 1.5em !important;
        padding-left: 0.5em !important;
    }
    .ujian-rich-body ol[type="A"], .ujian-rich-body ol.list-alpha-upper { list-style-type: upper-alpha !important; }
    .ujian-rich-body ol[type="a"], .ujian-rich-body ol.list-alpha-lower { list-style-type: lower-alpha !important; }
    .ujian-rich-body ol[type="I"], .ujian-rich-body ol.list-roman-upper { list-style-type: upper-roman !important; }
    .ujian-rich-body ol[type="i"], .ujian-rich-body ol.list-roman-lower { list-style-type: lower-roman !important; }
    .ujian-rich-body ol[type="1"], .ujian-rich-body ol.list-decimal { list-style-type: decimal !important; }
    .ujian-rich-body ul ul {
        list-style-type: circle !important;
        margin: 0.25em 0 0.25em 1.25em !important;
    }
    .ujian-rich-body ul ul ul {
        list-style-type: square !important;
    }
    .ujian-rich-body ol ol {
        list-style-type: lower-alpha !important;
        margin: 0.25em 0 0.25em 1.25em !important;
    }
    .ujian-rich-body ol ol ol {
        list-style-type: lower-roman !important;
    }
    .ujian-rich-body li {
        display: list-item !important;
        margin-bottom: 0.25em;
    }
    .ujian-rich-body li:last-child {
        margin-bottom: 0;
    }
    .ujian-rich-body li > p {
        margin: 0 0 0.25em;
    }
    .ujian-rich-body li > p:last-child {
        margin-bottom: 0;
    }
    .ujian-rich-body img.math-svg { display: inline-block; vertical-align: middle; }
    .ujian-rich-body img:not(.math-svg) { max-width: 100%; border-radius: 8px; margin: 6px 0; }
    .ujian-rich-body svg { display: inline-block; vertical-align: middle; }
    .ujian-rich-body .lucide,
    .ujian-rich-body svg.lucide {
        display: inline-block;
        vertical-align: -0.15em;
        width: 1.15em;
        height: 1.15em;
    }
    .ujian-rich-body i[data-lucide] {
        display: inline-block;
        vertical-align: middle;
        min-width: 1em;
        min-height: 1em;
    }
    .ujian-rich-body table { border-collapse: collapse; margin: .5em 0; }
    .ujian-rich-body table td, .ujian-rich-body table th { border: 1px solid #cbd5e1; padding: 4px 8px; }
</style>
@endpush

@push('scripts')
<script>
function getSafeStorage() {
    try {
        const testKey = '__sims_storage_test__';
        window.localStorage.setItem(testKey, testKey);
        window.localStorage.removeItem(testKey);
        return window.localStorage;
    } catch (e) {
        console.warn('localStorage tidak tersedia/dibatasi di browser ini. Beralih ke in-memory storage fallback.');
        const mem = {};
        return {
            getItem: (k) => (Object.prototype.hasOwnProperty.call(mem, k) ? mem[k] : null),
            setItem: (k, v) => { mem[k] = String(v); },
            removeItem: (k) => { delete mem[k]; },
            clear: () => { Object.keys(mem).forEach(k => delete mem[k]); }
        };
    }
}

function ujianKerjakan(cfg) {
    return {
        soal: cfg.soal,
        jawaban: cfg.jawabanAwal || {},
        batasWaktu: cfg.batasWaktu ? new Date(cfg.batasWaktu).getTime() : null,
        idx: 0,
        sisaDetik: 0,
        fsActive: false,
        terkunci: false,
        mengumpulkan: false,
        mengumpulkanOtomatis: false,
        intentional: false,
        simpanStatus: 'Siap',
        simpanError: false,
        loadingBlock: false,
        _audioCtx: null,
        _storage: null,
        _storageKey: 'sims_jawaban_' + (cfg.attemptUuid || 'default'),
        _dirtyQueue: {},
        _isSyncing: false,
        _timerHandle: null,
        _syncTimer: null,
        _debounceTimer: null,
        // Saat server menjawab "sibuk" (503/429/gangguan jaringan), sinkron berikutnya ditahan
        // sampai waktu ini (ms) — jawaban tetap aman di penyimpanan HP selama menunggu.
        _syncJedaSampai: 0,
        // Jumlah gagal sinkron BERUNTUN → jeda naik bertingkat (5, 10, 20, 40… s/d 120 dtk).
        // Data log 26-11-2026: HP yg terus mencoba rapat-rapat saat diblokir membuat firewall
        // hosting memblokir IP-nya — termasuk IP WiFi sekolah yg dipakai belasan siswa lain.
        _syncGagalBeruntun: 0,
        // Server menolak sinkron secara FINAL (422: sudah dikumpulkan / waktu habis) — berhenti
        // mencoba, mengulang tak akan pernah berhasil.
        _sinkronDitutup: false,
        // Sinkron terakhir menjelang waktu habis sudah dikirim (lihat tickCountdown()).
        _flushAkhirDikirim: false,
        // Kumpul otomatis (waktu habis) yg gagal: dicoba ulang dgn jeda bertingkat, BUKAN tiap
        // detik spt dulu (dulu: 1 HP sampai 201 kali "kumpul" dlm beberapa menit).
        _kumpulGagalBeruntun: 0,
        pesanKumpul: '',

        triggerBlock() {
            this.loadingBlock = true;
            setTimeout(() => { this.loadingBlock = false; }, 1500);
        },
        
        _statusHandle: null,
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        renderIcons() {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            } else {
                let attempts = 0;
                const interval = setInterval(() => {
                    attempts++;
                    if (window.lucide && typeof window.lucide.createIcons === 'function') {
                        window.lucide.createIcons();
                        clearInterval(interval);
                    } else if (attempts > 20) {
                        clearInterval(interval);
                    }
                }, 100);
            }
        },

        init() {
            this._storage = getSafeStorage();

            // Pulihkan jawaban tersimpan dari safe storage lokal jika ada
            try {
                const raw = this._storage.getItem(this._storageKey);
                if (raw) {
                    const localData = JSON.parse(raw);
                    if (localData && typeof localData === 'object') {
                        // Merge jawaban lokal dan server (jawaban lokal lebih baru/prioritas)
                        this.jawaban = { ...this.jawaban, ...localData };
                    }
                } else {
                    // Seed awal ke storage lokal dari database
                    this._storage.setItem(this._storageKey, JSON.stringify(this.jawaban));
                }
            } catch (e) {}

            this.tickCountdown();
            this._timerHandle = setInterval(() => this.tickCountdown(), 1000);

            // Timer sinkron batch berkala setiap 25-30 detik (dengan random jitter agar ratusan siswa tidak serentak)
            const syncIntervalMs = 25000 + Math.floor(Math.random() * 5000);
            this._syncTimer = setInterval(() => {
                if (Object.keys(this._dirtyQueue).length > 0) {
                    this.flushPendingAnswers();
                }
            }, syncIntervalMs);

            const syncFs = () => {
                const on = !!(document.fullscreenElement || document.webkitFullscreenElement);
                this.fsActive = on;
                if (!on && !this.intentional) this.laporKeluar('keluar_fullscreen');
                this.$nextTick(() => this.renderIcons());
            };
            document.addEventListener('fullscreenchange', syncFs);
            document.addEventListener('webkitfullscreenchange', syncFs);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && !this.intentional && this.fsActive) {
                    this.laporKeluar('ganti_tab');
                }
                // Kirim jawaban tertunda di background saat tab diminimalkan
                if (document.hidden && Object.keys(this._dirtyQueue).length > 0) {
                    this.flushPendingAnswersBeacon();
                }
            });

            window.addEventListener('beforeunload', () => {
                if (Object.keys(this._dirtyQueue).length > 0) {
                    this.flushPendingAnswersBeacon();
                }
            });

            // Auto-unlock audio saat interaksi pertama siswa agar alarm berbunyi tanpa tertahan browser
            const unlockAudio = () => {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtx && !this._audioCtx) {
                        this._audioCtx = new AudioCtx();
                    }
                    if (this._audioCtx && this._audioCtx.state === 'suspended') {
                        this._audioCtx.resume();
                    }
                } catch (e) {}
            };
            document.addEventListener('click', unlockAudio, { once: true });
            document.addEventListener('touchstart', unlockAudio, { once: true });
            document.addEventListener('keydown', unlockAudio, { once: true });

            this.renderIcons();
            this.$nextTick(() => this.renderIcons());
        },

        masukLayarPenuh() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (AudioCtx && !this._audioCtx) this._audioCtx = new AudioCtx();
                if (this._audioCtx && this._audioCtx.state === 'suspended') this._audioCtx.resume();
            } catch (e) {}
            const el = document.documentElement;
            const req = el.requestFullscreen || el.webkitRequestFullscreen;
            if (req) {
                Promise.resolve(req.call(el)).then(() => { this.fsActive = true; }).catch(() => {});
            } else {
                this.fsActive = true; // browser tanpa Fullscreen API (fallback jujur, bukan proteksi penuh)
            }
        },

        // Sebagian HP siswa gagal/menolak Fullscreen API (requestFullscreen() reject diam2,
        // lihat catch() di atas) ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â tanpa jalan keluar ini siswa tsb terjebak permanen di
        // overlay ini, tak pernah bisa mulai ujian sama sekali. `intentional` dipasang SEBELUM
        // fsActive=true supaya syncFs() (yg jalan lewat event fullscreenchange kalau memang
        // sempat browser sempat masuk fullscreen sebentar lalu gagal) tak salah lapor
        // "keluar_fullscreen" akibat aksi ini sendiri.
        lanjutTanpaLayarPenuh() {
            this.intentional = true;
            this.fsActive = true;
            this.$nextTick(() => {
                this.intentional = false;
                this.renderIcons();
            });
        },

        formatWaktu(detik) {
            detik = Math.max(0, detik);
            const j = Math.floor(detik / 3600);
            const m = Math.floor((detik % 3600) / 60);
            const s = Math.floor(detik % 60);
            const pad = (n) => String(n).padStart(2, '0');
            return j > 0 ? `${j}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
        },

        tickCountdown() {
            if (!this.batasWaktu) return;
            this.sisaDetik = Math.max(0, Math.round((this.batasWaktu - Date.now()) / 1000));
            // Kirim jawaban tertunda SEBELUM waktu habis: begitu lewat batas waktu, server menolak
            // sinkron (422 "Waktu ujian sudah habis"), jadi jawaban terakhir harus sudah terkirim.
            if (this.sisaDetik > 0 && this.sisaDetik <= 5 && !this._flushAkhirDikirim && Object.keys(this._dirtyQueue).length > 0) {
                this._flushAkhirDikirim = true;
                this.flushPendingAnswers(true);
            }
            if (this.sisaDetik <= 0 && !this.terkunci && !this.mengumpulkan) {
                this.intentional = true;
                this.mengumpulkan = true;
                // Jeda acak halus 100ms - 2000ms saat auto-submit waktu habis
                // agar ratusan siswa tidak menembak server di detik yang persis sama
                const autoSubmitJitter = Math.floor(Math.random() * 1900) + 100;
                setTimeout(() => {
                    this.kumpulkan(true);
                }, autoSubmitJitter);
            }
        },
        pindah(i) {
            if (i < 0 || i >= this.soal.length) return;
            this.idx = i;
            this.triggerBlock();
            this.$nextTick(() => this.renderIcons());
        },

        sudahDijawab(s) {
            const j = this.jawaban[s.uuid];
            if (s.tipe === 'mcq_complex') return Array.isArray(j) && j.length > 0;
            if (s.tipe === 'match') return j && Object.keys(j).length > 0;
            return !!j;
        },

        toggleMulti(soalUuid, opsiUuid, event) {
            let arr = [...(this.jawaban[soalUuid] || [])];
            const s = this.soal.find(x => x.uuid === soalUuid);
            const totalOpsi = (s && s.opsi) ? s.opsi.length : 0;

            if (arr.includes(opsiUuid)) {
                arr = arr.filter(x => x !== opsiUuid);
                this.jawaban[soalUuid] = arr;
                this.tandaiBerubah(soalUuid);
            } else {
                // Cegah memilih seluruh opsi yang ada (maksimal N - 1 opsi)
                // Siswa tidak tahu jumlah kunci benar, namun dicegah memborong seluruh pilihan
                if (totalOpsi > 1 && arr.length >= totalOpsi - 1) {
                    if (event && event.target) {
                        event.target.checked = false;
                    }
                    if (window.$ && $.alert) {
                        $.alert({
                            title: 'Batas Pilihan',
                            content: 'Pada pilihan ganda kompleks, Anda tidak boleh memilih seluruh opsi yang ada. Minimal sisakan 1 opsi tidak dipilih.',
                            type: 'orange'
                        });
                    }
                    return;
                }
                arr.push(opsiUuid);
                this.jawaban[soalUuid] = arr;
                this.tandaiBerubah(soalUuid);
            }
        },

        setPasangan(soalUuid, kiri, kanan) {
            let current = { ...(this.jawaban[soalUuid] || {}) };
            if (kanan) current[kiri] = kanan; else delete current[kiri];
            this.jawaban[soalUuid] = current;
            this.tandaiBerubah(soalUuid);
        },

        buildPayload(soalUuid) {
            const s = this.soal.find(x => x.uuid === soalUuid);
            if (!s) return null;
            const payload = { id_soal: soalUuid };
            if (s.tipe === 'mcq' || s.tipe === 'true_false') payload.id_opsi_dipilih = this.jawaban[soalUuid] || null;
            if (s.tipe === 'mcq_complex') payload.opsi_dipilih_multi = this.jawaban[soalUuid] || [];
            if (s.tipe === 'match') payload.jawaban_pasangan = this.jawaban[soalUuid] || {};
            if (s.tipe === 'essay') payload.jawaban_esai = this.jawaban[soalUuid] || '';
            return payload;
        },

        tandaiBerubah(soalUuid) {
            // 1. Simpan ke Safe Storage lokal seketika (0ms)
            try {
                this._storage.setItem(this._storageKey, JSON.stringify(this.jawaban));
            } catch (e) {}

            // 2. Masukkan ke dirty queue untuk dikirim dalam batch
            const payload = this.buildPayload(soalUuid);
            if (payload) {
                this._dirtyQueue[soalUuid] = payload;
                this.simpanStatus = 'Tersimpan di perangkat';
                this.simpanError = false;
            }

            // 3. Debounce sync: jika siswa diam selama 15 detik setelah menjawab, sinkronkan ke
            //    server (dulu 5 dtk). Jawaban SUDAH aman di penyimpanan HP sejak langkah 1, dan
            //    tetap dikirim saat kumpul / pindah tab / tutup halaman — jeda lebih panjang ini
            //    memangkas jumlah request sinkron ±3x saat ratusan siswa mengerjakan bersamaan.
            //    Di 60 detik terakhir jedanya dipendekkan jadi 2 dtk supaya jawaban terakhir sempat
            //    terkirim sebelum batas waktu (setelah itu server menolak sinkron).
            if (this._debounceTimer) clearTimeout(this._debounceTimer);
            const tundaSinkron = (this.sisaDetik > 0 && this.sisaDetik <= 60) ? 2000 : 15000;
            this._debounceTimer = setTimeout(() => {
                this.flushPendingAnswers();
            }, tundaSinkron);
        },

        async flushPendingAnswers(forceWait = false) {
            if (this.terkunci || this._sinkronDitutup) return;
            const keys = Object.keys(this._dirtyQueue);
            if (keys.length === 0) return;

            // Server baru saja bilang sibuk → jangan ditembak lagi sebelum jedanya lewat
            // (kecuali saat kumpul/forceWait). Timer berkala & debounce akan mencoba lagi nanti.
            if (!forceWait && this._syncJedaSampai && Date.now() < this._syncJedaSampai) return;

            if (this._isSyncing && !forceWait) return;
            this._isSyncing = true;

            const itemsToSend = keys.map(k => this._dirtyQueue[k]);

            // Sinkron biasa cukup 1x percobaan — kalau gagal, TIDAK diulang rapat-rapat (dulu
            // 3x dgn jeda 0,35/0,7 dtk = makin membebani server yg sedang penuh); cukup tunggu
            // jeda acak lalu biarkan timer berkala mencoba lagi. Saat kumpul (forceWait) tetap
            // dicoba s/d 3x krn siswa sedang menunggu hasilnya.
            const maxPercobaan = forceWait ? 3 : 1;
            let success = false;
            for (let attempt = 0; attempt < maxPercobaan; attempt++) {
                let res = null;
                try {
                    res = await fetch(cfg.urlSinkronBatch, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this._csrf,
                        },
                        body: JSON.stringify({ jawaban: itemsToSend }),
                    });
                } catch (e) {
                    res = null; // gangguan jaringan
                }

                if (res && res.ok) {
                    keys.forEach(k => {
                        if (this._dirtyQueue[k] && this._dirtyQueue[k].id_soal === k) {
                            delete this._dirtyQueue[k];
                        }
                    });
                    this.simpanStatus = 'Tersimpan';
                    this.simpanError = false;
                    this._syncJedaSampai = 0;
                    this._syncGagalBeruntun = 0;
                    success = true;
                    break;
                }

                // 422 dari abort() server = penolakan FINAL ("Ujian ini sudah dikumpulkan" /
                // "Waktu ujian sudah habis") → berhenti total, mengulang tak akan pernah berhasil.
                // (422 validasi membawa `errors` — itu bukan penolakan final, cukup tak diulang kali ini.)
                if (res && res.status === 422) {
                    const errData = await res.clone().json().catch(() => ({}));
                    if (!errData.errors) {
                        this._sinkronDitutup = true;
                    }
                    break;
                }

                if (res && res.status === 403) {
                    const isJson = res.headers.get('content-type')?.includes('application/json');
                    if (isJson) {
                        try {
                            const errData = await res.clone().json();
                            if (errData && errData.message && errData.message.toLowerCase().includes('terkunci')) {
                                this.kunci();
                                this._isSyncing = false;
                                return;
                            }
                        } catch (err) {}
                    }
                }

                // Gagal (sibuk/diblokir/limit/jaringan): tahan sinkron berikutnya dgn jeda yg NAIK
                // tiap gagal beruntun (5, 10, 20, 40, 80, maks 120 dtk) — minimal sebesar Retry-After
                // dari server — + acak 0–10 dtk supaya ratusan HP tak serempak mencoba lagi.
                this._syncGagalBeruntun++;
                const retryAfter = res ? parseInt(res.headers.get('Retry-After') || '', 10) : NaN;
                const dasar = Math.min(5000 * Math.pow(2, this._syncGagalBeruntun - 1), 120000);
                const jeda = Math.max(dasar, Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter * 1000 : 0)
                    + Math.floor(Math.random() * 10000);
                this._syncJedaSampai = Date.now() + jeda;

                if (attempt < maxPercobaan - 1) {
                    await new Promise(r => setTimeout(r, Math.min(jeda, 4000)));
                }
            }

            this._isSyncing = false;

            if (!success) {
                // Jawaban tetap tersimpan aman di local storage HP!
                this.simpanStatus = 'Tersimpan di perangkat (menunggu sinkron)';
                this.simpanError = false;
            }
        },

        flushPendingAnswersBeacon() {
            try {
                // Jangan menembak saat server sudah menolak final / sedang dalam jeda "sibuk"
                // (tiap ganti aplikasi/layar mati memicu ini — di HP yg sedang diblokir hosting,
                // tembakan tambahan cuma memperpanjang blokirnya).
                if (this._sinkronDitutup || Date.now() < this._syncJedaSampai) return;
                const keys = Object.keys(this._dirtyQueue);
                if (keys.length === 0) return;
                const items = keys.map(k => this._dirtyQueue[k]);
                const payload = JSON.stringify({ jawaban: items });
                
                fetch(cfg.urlSinkronBatch, {
                    method: 'POST',
                    keepalive: true,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this._csrf
                    },
                    body: payload
                }).catch(() => {});
            } catch (e) {}
        },

        laporKeluar(tipe) {
            if (this.terkunci) return;
            this.kunci();
            try {
                const payload = JSON.stringify({ tipe });
                let sent = false;
                if (navigator.sendBeacon) {
                    const blob = new Blob([payload], { type: 'application/json' });
                    sent = navigator.sendBeacon(cfg.urlKeluar + '?_token=' + encodeURIComponent(this._csrf), blob);
                }
                if (!sent) {
                    fetch(cfg.urlKeluar, {
                        method: 'POST', keepalive: true,
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                        body: payload,
                    }).catch(() => {});
                }
            } catch (e) {}
        },

        kunci() {
            if (this.terkunci) return;
            this.terkunci = true;
            this.playAlarmSuara();
            clearInterval(this._timerHandle);
            clearInterval(this._syncTimer);
            if (this._debounceTimer) clearTimeout(this._debounceTimer);
            clearInterval(this._statusHandle);
        },

        playAlarmSuara() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                if (!this._audioCtx) {
                    this._audioCtx = new AudioCtx();
                }
                if (this._audioCtx.state === 'suspended') {
                    this._audioCtx.resume();
                }
                const now = this._audioCtx.currentTime;
                // Pola nada alarm buzzer tajam di perangkat siswa
                const freqs = [880, 587.33, 880, 587.33, 880, 587.33];
                let t = now;
                freqs.forEach((freq) => {
                    const osc = this._audioCtx.createOscillator();
                    const gain = this._audioCtx.createGain();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(freq, t);

                    gain.gain.setValueAtTime(0.001, t);
                    gain.gain.exponentialRampToValueAtTime(0.6, t + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.22);

                    osc.connect(gain);
                    gain.connect(this._audioCtx.destination);

                    osc.start(t);
                    osc.stop(t + 0.24);
                    t += 0.25;
                });
            } catch (e) {
                console.warn('Gagal membunyikan alarm suara ujian:', e);
            }
        },

        konfirmasiSubmit() {
            const self = this;
            $.confirm({
                title: 'Kumpulkan Ujian?',
                content: 'Anda tidak bisa mengubah jawaban setelah ini.',
                type: 'orange',
                buttons: {
                    ya: {
                        text: 'Ya, Kumpulkan', btnClass: 'btn-blue', keys: ['enter'],
                        action: function () { self.intentional = true; self.kumpulkan(false); },
                    },
                    batal: { text: 'Batal' },
                },
            });
        },
        /**
         * Penanganan gagal kumpul. Kumpul OTOMATIS (waktu habis) dulu memicu loop: gagal →
         * timer per-detik dinyalakan lagi → tickCountdown() melihat sisa 0 → kumpul lagi ~1 dtk
         * kemudian, tanpa henti + alert bertumpuk. Data log 26-11-2026: 28 HP mengirim 1.541
         * "kumpul" (1 HP sampai 201x) sampai firewall hosting memblokir IP-nya — termasuk IP WiFi
         * sekolah yg dipakai belasan siswa lain. Kini: coba ulang dgn jeda bertingkat 15→30→60
         * dtk (+acak), pesan di overlay (bukan alert bertumpuk). Server tetap menjadi jaring
         * pengaman: cron ujian:auto-submit memfinalisasi attempt yg lewat waktu tiap menit.
         */
        gagalKumpul(otomatis, judul, isi, tipe) {
            if (otomatis) {
                this._kumpulGagalBeruntun++;
                const jeda = Math.min(15000 * Math.pow(2, this._kumpulGagalBeruntun - 1), 60000) + Math.floor(Math.random() * 10000);
                const sampai = Date.now() + jeda;
                this.mengumpulkan = true;
                this.mengumpulkanOtomatis = true;
                const hitung = () => {
                    const sisa = Math.ceil((sampai - Date.now()) / 1000);
                    if (sisa > 0) {
                        this.pesanKumpul = 'Server sedang padat. Mencoba lagi otomatis dalam ' + sisa + ' detik — jangan tutup aplikasi, jawaban Anda aman.';
                        setTimeout(hitung, 1000);
                        return;
                    }
                    this.pesanKumpul = 'Mencoba mengumpulkan lagi...';
                    this.kumpulkan(true);
                };
                hitung();
                return;
            }

            this.mengumpulkan = false;
            $.alert({ title: judul, content: isi, type: tipe });
            this._timerHandle = setInterval(() => this.tickCountdown(), 1000);
        },

        async kumpulkan(otomatis) {
            // 1. Pastikan seluruh jawaban tertunda di dirtyQueue telah disinkronkan ke server.
            //    Kumpul otomatis yg sedang DIULANG tak perlu sinkron lagi: waktu sudah habis, jadi
            //    server pasti menolaknya — sinkron terakhir sudah dikirim menjelang batas waktu.
            if (Object.keys(this._dirtyQueue).length > 0 && !(otomatis && this._kumpulGagalBeruntun > 0)) {
                this.simpanStatus = 'Menyinkronkan jawaban akhir...';
                await this.flushPendingAnswers(true);
            }

            // SENGAJA tak pakai kunci() di sini ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â kunci() menyalakan `terkunci` yg
            // menampilkan overlay "Ujian Terkunci ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Anda keluar dari layar penuh/berpindah
            // tab", pesan yg salah konteks (& menakutkan) utk submit yg SAH. `mengumpulkan`
            // adalah state terpisah dgn overlay netral sendiri (lihat kerjakan.blade.php).
            this.mengumpulkan = true;
            this.mengumpulkanOtomatis = !!otomatis;
            clearInterval(this._timerHandle);
            clearInterval(this._syncTimer);
            if (this._debounceTimer) clearTimeout(this._debounceTimer);
            clearInterval(this._statusHandle);

            let res = null;
            let isWafBlock = false;
            let serverSibuk = false;

            // 3x Silent Retry otomatis jika server sedang padat saat submit. "Padat" termasuk
            // 408 (LiteSpeed memutus krn antrean terlalu lama), 429, 5xx & 508 (batas resource
            // hosting) — bukan cuma 503. Aman diulang: server memperlakukan "Kumpulkan" yg
            // diulang utk attempt yg sudah terkumpul sbg SUKSES (lihat UjianSiswaController::submit).
            // Kumpul otomatis yg sedang DIULANG cukup 1 percobaan per putaran — jedanya sudah
            // diatur bertingkat oleh gagalKumpul().
            const maksPercobaan = (otomatis && this._kumpulGagalBeruntun > 0) ? 1 : 3;
            for (let submitAttempt = 0; submitAttempt < maksPercobaan; submitAttempt++) {
                try {
                    res = await fetch(cfg.urlSubmit, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                    });

                    isWafBlock = res.status === 403 && !res.headers.get('content-type')?.includes('application/json');
                    serverSibuk = isWafBlock || [408, 429, 500, 502, 503, 504, 508].includes(res.status);

                    if (serverSibuk && submitAttempt < maksPercobaan - 1) {
                        const retryAfter = parseInt(res.headers.get('Retry-After') || '', 10);
                        const jeda = Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter * 1000 : (submitAttempt + 1) * 2000;
                        this.simpanStatus = 'Server padat, mencoba mengumpulkan lagi...';
                        await new Promise(r => setTimeout(r, jeda + Math.floor(Math.random() * 1500)));
                        continue;
                    }

                    break;
                } catch (e) {
                    if (submitAttempt < maksPercobaan - 1) {
                        await new Promise(r => setTimeout(r, (submitAttempt + 1) * 2000 + Math.floor(Math.random() * 1500)));
                        continue;
                    }
                    break;
                }
            }

            try {
                if (!res) {
                    throw new Error('Gagal menghubungi server.');
                }

                // Server padat (408/429/5xx/508) ATAU 403 HTML (diblokir WAF/ModSecurity hosting)
                if (serverSibuk) {
                    this.gagalKumpul(otomatis, 'Server Sedang Sibuk',
                        'Koneksi ke server sedang padat. Seluruh jawaban Anda tetap aman di perangkat. Tunggu sebentar, lalu klik tombol Kumpulkan Ujian sekali lagi.',
                        'orange');
                    return;
                }

                if (document.fullscreenElement || document.webkitFullscreenElement) {
                    try { (document.exitFullscreen || document.webkitExitFullscreen)?.call(document); } catch (e) {}
                }
                
                if (res.redirected) { 
                    try { this._storage.removeItem(this._storageKey); } catch (e) {}
                    window.location.href = res.url; 
                    return; 
                }
                
                if (res.ok) {
                    try { this._storage.removeItem(this._storageKey); } catch (e) {}
                    try {
                        const data = await res.json();
                        if (data && data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                    } catch (err) {}
                    window.location.href = cfg.urlTerkunci;
                    return;
                }
                
                this.gagalKumpul(otomatis, 'Pengumpulan Tertunda',
                    'Koneksi ke server sempat terganggu. Jawaban Anda aman tersimpan. Silakan klik tombol Kumpulkan Ujian sekali lagi.',
                    'orange');
            } catch (e) {
                this.gagalKumpul(otomatis, 'Koneksi Terputus',
                    'Gagal menghubungi server. Periksa koneksi internet Anda lalu klik tombol Kumpulkan Ujian sekali lagi.',
                    'red');
            }
        },
    };
}
</script>
@endpush









