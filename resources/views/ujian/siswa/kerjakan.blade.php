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
        soal: {{ Js::from($soalTampil) }},
        jawabanAwal: {{ Js::from($initJawaban) }},
        batasWaktu: {{ Js::from($attempt->batas_waktu_pada?->toIso8601String()) }},
        urlJawab: {{ Js::from(route('ujian.siswa.jawab', [$ujian, $attempt])) }},
        urlStatus: {{ Js::from(route('ujian.siswa.status', [$ujian, $attempt])) }},
        urlKeluar: {{ Js::from(route('ujian.siswa.keluar', [$ujian, $attempt])) }},
        urlSubmit: {{ Js::from(route('ujian.siswa.submit', [$ujian, $attempt])) }},
        urlTerkunci: {{ Js::from(route('ujian.siswa.gate', $ujian)) }},
     })"
     x-init="init()"
     @copy.prevent @cut.prevent @paste.prevent @contextmenu.prevent
>
    {{-- Overlay: wajib layar penuh sebelum mulai (gesture langsung dari klik). Disembunyikan
         juga saat sudah terkunci ATAU sedang mengumpulkan — kalau tidak, overlay ini & overlay
         "Ujian Terkunci"/"Mengumpulkan" di bawah bisa tampil bersamaan bertumpuk (semuanya
         fixed inset-0, teks jadi bertabrakan) — exitFullscreen() saat submit bikin fsActive
         balik false persis di titik ini. --}}
    <div x-show="!fsActive && !terkunci && !mengumpulkan" x-cloak class="fixed inset-0 z-[9999] bg-slate-900/95 flex items-center justify-center p-6 text-center">
        <div class="max-w-sm space-y-4">
            <i data-lucide="maximize" class="w-14 h-14 text-primary mx-auto"></i>
            <h2 class="text-white text-lg font-bold m-0">Mode Ujian — Layar Penuh</h2>
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
                <p class="text-xs text-slate-400">Soal <span x-text="i+1"></span> dari <span x-text="soal.length"></span> · <span x-text="s.poin"></span> poin</p>
            </div>
            <div class="text-sm font-medium text-slate-800 dark:text-slate-100 ujian-rich-body" x-html="s.teks_soal"></div>

            {{-- mcq / true_false --}}
            <div x-show="s.tipe==='mcq' || s.tipe==='true_false'" x-cloak class="space-y-2">
                <template x-for="o in (s.opsi||[])" :key="o.uuid">
                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer"
                           :class="jawaban[s.uuid]===o.uuid ? 'border-primary bg-primary/5' : 'border-slate-200 dark:border-slate-600'">
                        <input type="radio" :name="'opt-'+s.uuid" :checked="jawaban[s.uuid]===o.uuid"
                               @change="jawaban[s.uuid] = o.uuid; simpan(s.uuid)" class="text-primary focus:ring-primary flex-shrink-0">
                        <span class="text-sm ujian-rich-body" x-html="o.teks"></span>
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
                               @change="toggleMulti(s.uuid, o.uuid); simpan(s.uuid)"
                               class="w-5 h-5 rounded-md border-2 border-slate-300 dark:border-slate-600 text-primary focus:ring-2 focus:ring-primary/30 transition cursor-pointer flex-shrink-0">
                        <span class="text-sm ujian-rich-body break-words min-w-0" x-html="o.teks"></span>
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
                            <template x-for="(kanan, kj) in (s.kanan_acak||[])" :key="'m-'+s.uuid+'-'+ki+'-opt-'+kanan">
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
                          x-model="jawaban[s.uuid]" @input.debounce.900ms="simpan(s.uuid)"></textarea>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="button" @click="pindah(idx-1)" :disabled="idx===0" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 disabled:opacity-30">← Sebelumnya</button>
                <button type="button" x-show="idx < soal.length-1" @click="pindah(idx+1)" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600">Berikutnya →</button>
                <button type="button" x-show="idx === soal.length-1" @click="konfirmasiSubmit()" class="px-5 py-2 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-700">Kumpulkan Ujian</button>
            </div>
        </div>
    </template>

    {{-- Overlay mengumpulkan (submit normal/otomatis) — SENGAJA state TERPISAH dari
         "terkunci" (pelanggaran). Sebelumnya kumpulkan() reuse kunci() yg sama dgn
         pelanggaran fullscreen/tab, jadi siswa yg menekan "Kumpulkan Ujian" secara SAH
         sempat melihat overlay "Ujian Terkunci — Anda keluar dari layar penuh..." yg
         menakutkan & salah konteks (makin kentara kalau fetch submit-nya agak lambat). --}}
    <div x-show="mengumpulkan" x-cloak class="fixed inset-0 z-[9999] bg-slate-900/95 flex items-center justify-center p-6 text-center">
        <div class="max-w-sm space-y-4">
            <i data-lucide="loader-circle" class="w-14 h-14 text-primary mx-auto animate-spin"></i>
            <h2 class="text-white text-lg font-bold m-0">Mengumpulkan Ujian…</h2>
            <p class="text-slate-300 text-sm m-0 leading-relaxed" x-text="mengumpulkanOtomatis ? 'Waktu ujian sudah habis — jawaban Anda sedang dikumpulkan otomatis.' : 'Mohon tunggu sebentar, jangan tutup halaman ini.'"></p>
        </div>
    </div>

    {{-- Overlay terkunci (client-side, cadangan sebelum reload) --}}
    <div x-show="terkunci" x-cloak class="fixed inset-0 z-[9999] bg-slate-900/95 flex items-center justify-center p-6 text-center">
        <div class="max-w-sm space-y-4">
            <i data-lucide="lock" class="w-14 h-14 text-rose-400 mx-auto"></i>
            <h2 class="text-white text-lg font-bold m-0">Ujian Terkunci</h2>
            <p class="text-slate-300 text-sm m-0 leading-relaxed">Anda keluar dari layar penuh atau berpindah tab. Hubungi guru/panitia untuk membuka kembali.</p>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .ujian-rich-body { line-height: 1.6; }
    .ujian-rich-body p { margin: 0 0 .5em; }
    .ujian-rich-body p:last-child { margin-bottom: 0; }
    .ujian-rich-body ul, .ujian-rich-body ol { margin: 0 0 .5em 1.4em; }
    .ujian-rich-body img.math-svg { display: inline-block; vertical-align: middle; }
    .ujian-rich-body img:not(.math-svg) { max-width: 100%; border-radius: 8px; margin: 6px 0; }
    .ujian-rich-body table { border-collapse: collapse; margin: .5em 0; }
    .ujian-rich-body table td, .ujian-rich-body table th { border: 1px solid #cbd5e1; padding: 4px 8px; }
</style>
@endpush

@push('scripts')
<script>
function ujianKerjakan(cfg) {
    return {
        soal: cfg.soal,
        jawaban: cfg.jawabanAwal,
        batasWaktu: cfg.batasWaktu ? new Date(cfg.batasWaktu).getTime() : null,
        idx: 0,
        sisaDetik: 0,
        fsActive: false,
        terkunci: false,
        mengumpulkan: false,
        mengumpulkanOtomatis: false,
        intentional: false,
        simpanStatus: '',
        simpanError: false,
        _timerHandle: null,
        _statusHandle: null,
        _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

        init() {
            this.tickCountdown();
            this._timerHandle = setInterval(() => this.tickCountdown(), 1000);
            this._statusHandle = setInterval(() => this.cekStatus(), 15000);

            const syncFs = () => {
                const on = !!(document.fullscreenElement || document.webkitFullscreenElement);
                this.fsActive = on;
                if (!on && !this.intentional) this.laporKeluar('keluar_fullscreen');
                this.$nextTick(() => window.lucide && lucide.createIcons());
            };
            document.addEventListener('fullscreenchange', syncFs);
            document.addEventListener('webkitfullscreenchange', syncFs);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden && !this.intentional && this.fsActive) this.laporKeluar('ganti_tab');
            });
            window.lucide && lucide.createIcons();
        },

        masukLayarPenuh() {
            const el = document.documentElement;
            const req = el.requestFullscreen || el.webkitRequestFullscreen;
            if (req) {
                Promise.resolve(req.call(el)).then(() => { this.fsActive = true; }).catch(() => {});
            } else {
                this.fsActive = true; // browser tanpa Fullscreen API (fallback jujur, bukan proteksi penuh)
            }
        },

        // Sebagian HP siswa gagal/menolak Fullscreen API (requestFullscreen() reject diam2,
        // lihat catch() di atas) — tanpa jalan keluar ini siswa tsb terjebak permanen di
        // overlay ini, tak pernah bisa mulai ujian sama sekali. `intentional` dipasang SEBELUM
        // fsActive=true supaya syncFs() (yg jalan lewat event fullscreenchange kalau memang
        // sempat browser sempat masuk fullscreen sebentar lalu gagal) tak salah lapor
        // "keluar_fullscreen" akibat aksi ini sendiri.
        lanjutTanpaLayarPenuh() {
            this.intentional = true;
            this.fsActive = true;
            this.$nextTick(() => { this.intentional = false; });
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
            if (this.sisaDetik <= 0 && !this.terkunci && !this.mengumpulkan) {
                this.intentional = true;
                this.kumpulkan(true);
            }
        },

        pindah(i) {
            if (i < 0 || i >= this.soal.length) return;
            this.idx = i;
        },

        sudahDijawab(s) {
            const v = this.jawaban[s.uuid];
            if (s.tipe === 'mcq_complex') return Array.isArray(v) && v.length > 0;
            if (s.tipe === 'match') return v && Object.keys(v).length > 0;
            if (s.tipe === 'essay') return !!(v && String(v).trim().length);
            return !!v;
        },

        toggleMulti(soalUuid, opsiUuid) {
            const cur = this.jawaban[soalUuid] || [];
            const i = cur.indexOf(opsiUuid);
            if (i === -1) cur.push(opsiUuid); else cur.splice(i, 1);
            this.jawaban[soalUuid] = cur;
        },

        setPasangan(soalUuid, kiri, kanan) {
            const cur = { ...(this.jawaban[soalUuid] || {}) };
            if (kanan) cur[kiri] = kanan; else delete cur[kiri];
            this.jawaban[soalUuid] = cur;
            this.simpan(soalUuid);
        },

        async simpan(soalUuid) {
            if (this.terkunci || this.mengumpulkan) return;
            const s = this.soal.find(x => x.uuid === soalUuid);
            if (!s) return;
            const payload = { id_soal: soalUuid };
            if (s.tipe === 'mcq' || s.tipe === 'true_false') payload.id_opsi_dipilih = this.jawaban[soalUuid] || null;
            if (s.tipe === 'mcq_complex') payload.opsi_dipilih_multi = this.jawaban[soalUuid] || [];
            if (s.tipe === 'match') payload.jawaban_pasangan = this.jawaban[soalUuid] || {};
            if (s.tipe === 'essay') payload.jawaban_esai = this.jawaban[soalUuid] || '';

            try {
                const res = await fetch(cfg.urlJawab, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                    body: JSON.stringify(payload),
                });
                if (res.status === 403) { this.kunci(); return; }
                if (!res.ok) throw new Error('gagal simpan');
                this.simpanStatus = 'Tersimpan';
                this.simpanError = false;
            } catch (e) {
                this.simpanStatus = 'Gagal tersimpan — periksa koneksi';
                this.simpanError = true;
            }
        },

        laporKeluar(tipe) {
            if (this.terkunci) return;
            try {
                const payload = JSON.stringify({ tipe });
                if (navigator.sendBeacon) {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon(cfg.urlKeluar + '?_token=' + encodeURIComponent(this._csrf), blob);
                }
                fetch(cfg.urlKeluar, {
                    method: 'POST', keepalive: true,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                    body: payload,
                }).catch(() => {});
            } catch (e) {}
            this.kunci();
        },

        kunci() {
            this.terkunci = true;
            clearInterval(this._timerHandle);
            clearInterval(this._statusHandle);
        },

        async cekStatus() {
            try {
                const res = await fetch(cfg.urlStatus, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (data.batas_waktu_pada) this.batasWaktu = new Date(data.batas_waktu_pada).getTime();
                if (data.dikunci) { this.kunci(); return; }
                if (data.status !== 'in_progress') { window.location.href = cfg.urlTerkunci; }
            } catch (e) {}
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

        async kumpulkan(otomatis) {
            // SENGAJA tak pakai kunci() di sini — kunci() menyalakan `terkunci` yg
            // menampilkan overlay "Ujian Terkunci — Anda keluar dari layar penuh/berpindah
            // tab", pesan yg salah konteks (& menakutkan) utk submit yg SAH. `mengumpulkan`
            // adalah state terpisah dgn overlay netral sendiri (lihat kerjakan.blade.php).
            this.mengumpulkan = true;
            this.mengumpulkanOtomatis = !!otomatis;
            clearInterval(this._timerHandle);
            clearInterval(this._statusHandle);
            try {
                const res = await fetch(cfg.urlSubmit, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf },
                });
                if (document.fullscreenElement || document.webkitFullscreenElement) {
                    try { (document.exitFullscreen || document.webkitExitFullscreen)?.call(document); } catch (e) {}
                }
                if (res.redirected) { window.location.href = res.url; return; }
                window.location.href = cfg.urlTerkunci;
            } catch (e) {
                window.location.href = cfg.urlTerkunci;
            }
        },
    };
}
</script>
@endpush
