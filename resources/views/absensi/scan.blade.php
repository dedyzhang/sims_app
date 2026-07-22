@extends('layouts.app')
@section('title', 'Presensi Scan Wajah Bersama')

@push('styles')
<style>
    /* ===== Mode layar penuh (kiosk) ===== */
    .scan-stage:fullscreen { width:100vw; height:100vh; border-radius:0; background:#0b1220; }
    .scan-stage:fullscreen video { object-fit:contain; }
    .scan-stage:-webkit-full-screen { width:100vw; height:100vh; border-radius:0; }
    .flash-name { animation: flashName 1.6s ease-out forwards; }
    @keyframes flashName { 0%{opacity:0;transform:scale(.8)} 15%{opacity:1;transform:scale(1)} 80%{opacity:1} 100%{opacity:0;transform:scale(1)} }
    .chip-in { animation: chipIn .3s ease-out; }
    @keyframes chipIn { from{opacity:0;transform:translateY(8px) scale(.9)} to{opacity:1;transform:none} }
    #barcodeReader { width:100%; border-radius:12px; overflow:hidden; }
    #barcodeReader video { border-radius:12px; }
</style>
@endpush

@section('content')
<div class="space-y-5" x-data="faceScan(@js($payload), @js([
    'kelasFilter' => $selectedKelas ?: '',
    'kelasOptions' => $kelasOptions ?? [],
    'telemetryUrl' => route('absensi.faceTelemetry'),
    'markBarcodeUrl' => route('absensi.markBarcode'),
    'tanggal' => $tanggal,
    'kioskToken' => $kioskToken ?? null,
    'isKiosk' => $isKiosk ?? false,
    'hasGuru' => ($gurus ?? collect())->isNotEmpty(),
    'scanKioskMode' => $scanKioskMode ?? 'keduanya',
]))" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Absensi Hadir</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                @if(($scanKioskMode ?? 'keduanya') === 'qr')
                    Arahkan QR kartu pelajar ke kamera — absen otomatis.
                @elseif(($scanKioskMode ?? 'keduanya') === 'wajah')
                    Hadap kamera — absen otomatis. Tidak terbaca? pakai kartu pelajar di bawah.
                @else
                    Hadap kamera atau tunjukkan QR kartu pelajar — satu kamera membaca keduanya.
                @endif
            </p>
        </div>
        @unless($isKiosk ?? false)
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="showDiag = !showDiag" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border transition" :class="showDiag ? 'border-primary text-primary bg-primary-50' : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'">
                <i data-lucide="activity" class="w-4 h-4"></i> Info Diagnostik
            </button>
            <a href="{{ route('wajah.ganda') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-amber-200 text-amber-700 hover:bg-amber-50 transition">
                <i data-lucide="shield-alert" class="w-4 h-4"></i> Wajah Ganda
            </a>
            <a href="{{ route('absensi.wajah') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Registrasi Wajah Siswa
            </a>
        </div>
        @endunless
    </div>

    {{-- Panel diagnostik: penyebab GAGAL pengenalan wajah selama sesi scan ini berjalan (angka
         hidup, di-reset tiap buka halaman). Tujuannya supaya laporan "susah terdeteksi" berikutnya
         bisa disertai data nyata (screenshot panel ini) drpd cuma deskripsi verbal — riwayat
         kalibrasi ambang sudah berkali-kali dinaik-turunkan tanpa data konkret dan tak kunjung
         beres; panel ini memutus siklus tebak-tebakan itu. --}}
    @unless($isKiosk ?? false)
    <div x-show="showDiag" x-cloak x-transition class="card p-4 text-xs space-y-2">
        <p class="font-bold text-slate-600 dark:text-slate-300">Penyebab gagal kenali wajah (sesi ini) — total <span x-text="diagTotal"></span></p>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
            <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-2 text-center">
                <p class="font-bold text-base text-slate-700 dark:text-slate-200" x-text="diag.small_face"></p>
                <p class="text-slate-400">Wajah kecil/jauh</p>
            </div>
            <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-2 text-center">
                <p class="font-bold text-base text-slate-700 dark:text-slate-200" x-text="diag.low_face_score"></p>
                <p class="text-slate-400">Kualitas/cahaya</p>
            </div>
            <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-2 text-center">
                <p class="font-bold text-base text-slate-700 dark:text-slate-200" x-text="diag.low_score"></p>
                <p class="text-slate-400">Skor rendah</p>
            </div>
            <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-2 text-center">
                <p class="font-bold text-base text-slate-700 dark:text-slate-200" x-text="diag.small_margin"></p>
                <p class="text-slate-400">Mirip 2 orang</p>
            </div>
            <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-2 text-center">
                <p class="font-bold text-base text-slate-700 dark:text-slate-200" x-text="diag.low_support"></p>
                <p class="text-slate-400">Sampel kurang</p>
            </div>
        </div>
        <p class="text-slate-400">Kalau angka "Wajah kecil/jauh" paling tinggi → suruh piket mendekat ke kamera. Kalau "Kualitas/cahaya" tinggi → tambah pencahayaan. Kalau "Skor rendah" tinggi terus-menerus utk orang yang SAMA → kemungkinan wajah perlu didaftar ulang (foto lebih jelas, 3 posisi).</p>
    </div>
    @endunless
        <div class="min-w-44 flex-1 max-w-xs">
            <label class="form-label">Kelas</label>
            <select x-model="kelasFilter" @change="onKelasChange()" class="form-select">
                <option value="">Semua kelas</option>
                <template x-for="k in kelasOptions" :key="k.uuid">
                    <option :value="k.uuid" x-text="k.label"></option>
                </template>
            </select>
        </div>
        <p class="text-xs text-slate-400 flex-1 min-w-40" x-show="enrolledSiswaCount > 0">
            <span class="font-semibold text-slate-600 dark:text-slate-300" x-text="enrolledSiswaCount"></span> siswa siap absen
            <span x-show="enrolledCountGuru > 0"> + <span x-text="enrolledCountGuru"></span> guru</span>
        </p>
    </div>

    {{-- Diagnostik hanya ke server (telemetry), tidak ditampilkan ke pengguna --}}

    @if($siswas->isEmpty() && $gurus->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada siswa atau guru yang mendaftarkan wajah.</p>
        <p class="text-sm mt-1">Daftarkan wajah terlebih dahulu untuk menggunakan fitur absensi scan wajah.</p>
    </div>
    @else
    <div class="grid lg:grid-cols-5 gap-5">
        {{-- Kamera --}}
        {{-- min-w-0: tanpa ini, lebar intrinsik video (mis. 1280px) memaksa kolom grid melebar
             melewati viewport di HP (frame kamera "keluar layar"). Video juga diposisikan absolute
             supaya ukuran aslinya tidak pernah memengaruhi layout — tinggi stage dari aspect-video. --}}
        <div class="lg:col-span-3 space-y-3 min-w-0">
            <div x-ref="stage" class="scan-stage card overflow-hidden relative bg-slate-900 aspect-video w-full max-w-full">
                <video x-ref="video" autoplay muted playsinline
                    class="absolute inset-0 w-full h-full object-cover"
                    :class="camOn?'':'opacity-0'"
                    :style="(camOn && previewBrightness > 1 ? `filter: brightness(${previewBrightness.toFixed(2)});` : '') + 'transition:filter .35s ease'"></video>
                <canvas x-ref="canvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>

                {{-- placeholder saat kamera mati --}}
                <div x-show="!camOn" class="absolute inset-0 grid place-items-center text-center text-slate-300">
                    <div>
                        <i data-lucide="loader-2" class="w-9 h-9 mx-auto animate-spin mb-2" x-show="loading"></i>
                        <i data-lucide="scan-face" class="w-12 h-12 mx-auto mb-2 opacity-50" x-show="!loading"></i>
                        <p class="text-sm px-6" x-text="status"></p>
                    </div>
                </div>

                {{-- HUD atas: status, mode, counter — satu baris flex yg boleh melipat (bukan 3 badge
                     absolute terpisah) supaya di layar HP sempit tidak saling tumpuk/terpotong. --}}
                <div class="absolute top-3 inset-x-3 flex flex-col gap-1.5 pointer-events-none">
                    <div class="flex items-start justify-between gap-1.5 flex-wrap">
                        <div class="flex flex-col gap-1.5 items-start pointer-events-auto min-w-0">
                            <div x-show="camOn && loading" class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold whitespace-nowrap">
                                <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin flex-shrink-0"></i> Memuat model AI...
                            </div>
                            <div x-show="scanning" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold whitespace-nowrap">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse flex-shrink-0"></span>
                                <span x-text="faceEnabled ? (qrEnabled ? 'Wajah + QR' : 'Memindai...') : 'Scan QR kartu'"></span>
                            </div>
                        </div>

                        <div x-show="camOn" class="px-3 py-1.5 rounded-full backdrop-blur text-white text-xs font-bold whitespace-nowrap pointer-events-auto" :class="scanMode==='pulang' ? 'bg-amber-600/85' : 'bg-emerald-600/85'">
                            <span x-text="scanMode==='pulang' ? '🏠 Mode Pulang' : '🚪 Mode Masuk'"></span>
                        </div>

                        <div class="flex items-center gap-2 pointer-events-auto">
                            <div x-show="camOn" class="px-3 py-1.5 rounded-full bg-black/55 backdrop-blur text-white text-xs font-semibold flex items-center gap-1 whitespace-nowrap">
                                <i data-lucide="users" class="w-3.5 h-3.5 flex-shrink-0"></i> <span x-text="totalHadir"></span>/<span x-text="totalEnrolled"></span> hadir
                            </div>
                            <button @click="toggleFs()" class="p-2 rounded-full bg-black/55 backdrop-blur text-white hover:bg-black/70 transition flex-shrink-0" :title="fs?'Keluar layar penuh':'Layar penuh'">
                                <i :data-lucide="fs?'minimize-2':'maximize-2'" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div x-show="scanning && lowLight" x-cloak class="self-start flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-500/85 backdrop-blur text-white text-xs font-semibold pointer-events-auto max-w-full">
                        <i data-lucide="sun" class="w-3.5 h-3.5 flex-shrink-0"></i>
                        <span class="truncate" x-text="autoExposureOn ? 'Pencahayaan rendah — auto exposure & kecerahan aktif' : 'Pencahayaan rendah — kecerahan otomatis aktif'"></span>
                    </div>

                    {{-- Muncul kalau beberapa detik beruntun Human sama sekali tidak menemukan
                         wajah di frame — biar pengguna tahu harus berbuat apa, bukan cuma diam
                         menunggu tanpa petunjuk ("susah terdeteksi" tanpa tahu kenapa). --}}
                    <div x-show="scanning && noFaceHint" x-cloak class="self-start flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-rose-500/85 backdrop-blur text-white text-xs font-semibold pointer-events-auto max-w-full">
                        <i data-lucide="scan-face" class="w-3.5 h-3.5 flex-shrink-0"></i>
                        <span class="truncate">Wajah tidak terlihat — pastikan wajah masuk kamera & cukup terang</span>
                    </div>
                </div>

                {{-- Flash nama besar saat dikenali --}}
                <template x-if="lastMatch">
                    <div :key="lastMatch.key" class="flash-name absolute inset-x-0 top-1/2 -translate-y-1/2 flex flex-col items-center pointer-events-none">
                        <div class="px-6 py-3 rounded-2xl text-white text-center shadow-2xl" :class="lastMatch.mode==='pulang' ? 'bg-amber-500/90' : 'bg-emerald-500/90'">
                            <div class="flex items-center justify-center gap-2 text-2xl font-extrabold">
                                <i data-lucide="check-circle-2" class="w-7 h-7"></i>
                                <span x-text="lastMatch.nama"></span>
                            </div>
                            <div class="text-sm font-semibold opacity-90">
                                <span x-text="lastMatch.type === 'siswa' ? 'Kelas ' + lastMatch.kelas : (lastMatch.mode==='pulang' ? 'Guru · Pulang' : 'Guru')"></span>
                                <span x-show="lastMatch.terlambat" class="text-amber-200"> · Terlambat</span>
                                <span class="font-mono" x-text="' ' + lastMatch.jam"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Strip hadir terakhir (bawah, terlihat di fullscreen) --}}
                <div x-show="camOn && recent.length" class="absolute bottom-3 left-3 right-3 flex gap-2 flex-nowrap overflow-hidden pointer-events-none">
                    <template x-for="r in recent" :key="r.key">
                        <div class="chip-in flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-semibold whitespace-nowrap flex-shrink-0" :class="r.mode==='pulang' ? 'bg-amber-500/90' : 'bg-emerald-500/90'">
                            <span class="font-mono font-bold bg-black/20 rounded px-1.5 py-0.5" x-text="r.jam"></span>
                            <span x-text="r.nama"></span>
                            <span class="opacity-80" x-text="r.type === 'siswa' ? '(' + r.kelas + ')' : (r.mode==='pulang' ? '(Pulang)' : '(Guru)')"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-sm text-slate-500">
                        <span class="font-bold text-emerald-600" x-text="totalHadir"></span>/<span x-text="totalEnrolled"></span> hadir
                        &bull; {{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMM') }}
                    </p>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Mode pulang hanya berlaku utk guru via wajah — di mode QR saja tidak relevan --}}
                        <div x-show="hasGuru && faceEnabled" class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800" title="Absen pulang guru — aturan agenda & jam tetap berlaku">
                            <button @click="scanMode='masuk'" :class="scanMode==='masuk' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">Masuk</button>
                            <button @click="scanMode='pulang'" :class="scanMode==='pulang' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-500'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition">Pulang</button>
                        </div>
                        <button x-show="!camOn" @click="start()" :disabled="loading" class="btn-primary px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 disabled:opacity-50">
                            <i data-lucide="play" class="w-4 h-4"></i> <span x-text="loading ? 'Menyiapkan…' : 'Buka kamera'"></span>
                        </button>
                        <button x-show="camOn" @click="stop()" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-2">
                            <i data-lucide="square" class="w-3.5 h-3.5"></i> Tutup
                        </button>
                    </div>
                </div>
                {{-- Kartu pelajar: scanner USB langsung, tanpa modal --}}
                <div class="flex gap-2 items-center">
                    <div class="relative flex-1">
                        <i data-lucide="credit-card" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none"></i>
                        <input type="text" x-model="barcodeInput" @keydown.enter.prevent="submitBarcode(barcodeInput)"
                            class="form-input w-full pl-9 py-2 text-sm" placeholder="Kartu tidak terbaca? tempelkan di scanner…" autocomplete="off"
                            :disabled="barcodeBusy">
                    </div>
                    @if(($scanKioskMode ?? 'keduanya') === 'wajah')
                    {{-- Saat QR sudah dibaca kamera utama, modal terpisah ini tidak diperlukan lagi --}}
                    <button type="button" @click="openBarcodeModal()" title="Scan QR kartu" class="px-3 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center gap-1.5 shrink-0">
                        <i data-lucide="qr-code" class="w-4 h-4"></i> QR
                    </button>
                    @endif
                </div>
                <p class="text-xs text-rose-500" x-show="barcodeError" x-text="barcodeError"></p>
            </div>
        </div>

        {{-- Daftar hadir (Tabs Siswa & Guru) --}}
        <div class="lg:col-span-2 min-w-0">
            <div class="card flex flex-col h-full" style="max-height:72vh">
                {{-- Tab Navs --}}
                <div class="flex border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 rounded-t-xl overflow-hidden">
                    <button @click="activeTab = 'siswa'" :class="activeTab === 'siswa' ? 'bg-white dark:bg-slate-800 text-primary border-b-2 border-primary font-bold' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="flex-1 py-3 text-center text-sm transition focus:outline-none">
                        Siswa (<span x-text="hadirCountSiswa"></span>/<span x-text="enrolledCountSiswa"></span>)
                    </button>
                    <button @click="activeTab = 'guru'" :class="activeTab === 'guru' ? 'bg-white dark:bg-slate-800 text-primary border-b-2 border-primary font-bold' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium'" class="flex-1 py-3 text-center text-sm transition focus:outline-none">
                        Guru (<span x-text="hadirCountGuru"></span>/<span x-text="enrolledCountGuru"></span>)
                    </button>
                </div>

                {{-- Siswa Tab Panel --}}
                <div x-show="activeTab === 'siswa'" class="flex-1 flex flex-col min-h-0">
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <div class="relative">
                            <input type="text" x-model="siswaSearch" placeholder="Cari siswa..." class="form-input w-full pl-9 py-2 text-sm">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                        <template x-for="s in filteredSiswa" :key="s.uuid">
                            <div class="flex items-center gap-3 p-2.5 rounded-xl transition"
                                 :class="s.marked ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0 transition"
                                     :class="s.justMarked ? 'ring-2 ring-emerald-400 scale-110' : ''"
                                     :style="'background:'+(s.jk==='L'?'var(--cp)':'#ec4899')" x-text="s.nama.charAt(0).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate" x-text="s.nama"></p>
                                    <p class="text-xs text-slate-400">Kelas <span x-text="s.kelas"></span> &bull; <span x-text="s.nis"></span></p>
                                </div>
                                <div x-show="s.marked" class="flex items-center">
                                    <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="check" class="w-3 h-3"></i> Hadir <span x-text="s.jam_masuk" class="ml-1 font-mono"></span></span>
                                    <button @click="cancelAbsen(s, 'masuk')" class="text-rose-500 hover:text-rose-700 ml-1 p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-900/30 transition" title="Batalkan absensi"><i data-lucide="x" class="w-4 h-4"></i></button>
                                </div>
                                <span x-show="!s.marked" class="text-xs text-slate-300">—</span>
                            </div>
                        </template>
                        <div x-show="filteredSiswa.length === 0" class="text-center py-8 text-slate-400 text-sm">
                            Tidak ada siswa yang cocok.
                        </div>
                    </div>
                    <div class="p-3 border-t border-slate-100 dark:border-slate-700">
                        <a href="{{ route('absensi.index') }}" class="text-sm text-primary hover:underline flex items-center gap-1 justify-center">
                            <i data-lucide="list-checks" class="w-4 h-4"></i> Lengkapi manual (izin/sakit/alpa)
                        </a>
                    </div>
                </div>

                {{-- Guru Tab Panel --}}
                <div x-show="activeTab === 'guru'" class="flex-1 flex flex-col min-h-0">
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                        <div class="relative">
                            <input type="text" x-model="guruSearch" placeholder="Cari guru..." class="form-input w-full pl-9 py-2 text-sm">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                        <template x-for="g in filteredGuru" :key="g.uuid">
                            <div class="flex items-center gap-3 p-2.5 rounded-xl transition"
                                 :class="g.marked ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800'">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0 transition"
                                     :class="g.justMarked ? 'ring-2 ring-emerald-400 scale-110' : ''"
                                     :style="'background:'+(g.jk==='L'?'var(--cp)':'#ec4899')" x-text="g.nama.charAt(0).toUpperCase()"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate" x-text="g.nama"></p>
                                    <p class="text-xs text-slate-400" x-text="g.nip || 'Guru'"></p>
                                </div>
                                <div class="flex flex-col gap-1 items-end">
                                    <div x-show="g.marked" class="flex items-center">
                                        <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 flex items-center gap-1"><i data-lucide="log-in" class="w-3 h-3"></i> M: <span x-text="g.jam_masuk" class="font-mono"></span></span>
                                        <button @click="cancelAbsen(g, 'masuk')" class="text-rose-500 hover:text-rose-700 ml-1 p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-900/30 transition" title="Batalkan masuk"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                                    </div>
                                    <div x-show="g.pulangMarked" class="flex items-center">
                                        <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 flex items-center gap-1"><i data-lucide="log-out" class="w-3 h-3"></i> P: <span x-text="g.jam_pulang" class="font-mono"></span></span>
                                        <button @click="cancelAbsen(g, 'pulang')" class="text-rose-500 hover:text-rose-700 ml-1 p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-900/30 transition" title="Batalkan pulang"><i data-lucide="x" class="w-3.5 h-3.5"></i></button>
                                    </div>
                                </div>
                                <span x-show="!g.marked && !g.pulangMarked" class="text-xs text-slate-300">—</span>
                            </div>
                        </template>
                        <div x-show="filteredGuru.length === 0" class="text-center py-8 text-slate-400 text-sm">
                            Tidak ada guru yang cocok.
                        </div>
                    </div>
                    <div class="p-3 border-t border-slate-100 dark:border-slate-700">
                        <a href="{{ route('presensi-guru.index') }}" class="text-sm text-primary hover:underline flex items-center gap-1 justify-center">
                            <i data-lucide="list-checks" class="w-4 h-4"></i> Lengkapi manual (izin/sakit/alpa)
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif

    {{-- Modal QR kartu (hanya bila tidak punya scanner USB) --}}
    <div x-show="showBarcodeModal" x-cloak class="modal-backdrop" style="display:none" @keydown.escape.window="closeBarcodeModal()">
        <div class="modal-box max-w-sm w-full" @click.stop>
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Scan QR kartu</h3>
                <button type="button" @click="closeBarcodeModal()" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-4 space-y-3">
                <div x-show="barcodeScanning" class="rounded-xl overflow-hidden bg-slate-900">
                    <div id="barcodeReader"></div>
                </div>
                <div x-show="!barcodeScanning && !barcodeBusy" class="text-center py-8 text-slate-400 text-sm">
                    <i data-lucide="loader-2" class="w-7 h-7 mx-auto animate-spin mb-2"></i> Membuka kamera…
                </div>
                <p class="text-xs text-rose-500 text-center" x-show="barcodeError" x-text="barcodeError"></p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
@if(($scanKioskMode ?? 'keduanya') !== 'qr')
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/human/dist/human.js"></script>
@endif
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
@if(($scanKioskMode ?? 'keduanya') !== 'wajah')
{{-- Fallback decode QR di browser tanpa BarcodeDetector (mis. Chrome desktop) --}}
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
@endif
<script>
// ===== Human (WebGPU) — pengenalan wajah modern, asinkron, UI tak freeze =====
let human=null, humanReady=false, humanBackend='';
async function loadHuman(){
    if(humanReady) return human;
    const HumanLib = window.Human?.Human || window.Human?.default || window.Human;
    const backend = (typeof navigator!=='undefined' && navigator.gpu) ? 'webgpu' : 'webgl';
    human = new HumanLib({
        modelBasePath:'https://vladmandic.github.io/human-models/models/',
        backend: backend, cacheSensitivity: 0, warmup:'none',
        // minConfidence DIKEMBALIKAN ke 0.45 (sempat diturunkan ke 0.35 utk kasus "kotak sama
        // sekali tak muncul", tapi laporan lapangan setelahnya justru "makin susah/gray dash
        // makin sering") — ambang 0.35 rupanya meloloskan terlalu banyak deteksi kotak wajah
        // BERKUALITAS RENDAH (blur/miring/sebagian) sbg kandidat, yang lalu gagal di tahap
        // KECOCOKAN (embedding dari kotak buruk pasti mirip siapa pun dgn skor rendah) —
        // hasilnya wajah asli yg jelas pun "tenggelam" di antara deteksi sampah yg lebih sering
        // muncul drpd sebelumnya. 0.45 adalah nilai lama yg terbukti bertahun-tahun sebelum
        // sesi ini mulai mengubahnya — JANGAN turunkan lagi tanpa data diagnostik nyata (lihat
        // panel "Info Diagnostik" di halaman ini) yg menunjukkan `small_face`/deteksi kosong
        // sbg penyebab dominan, BUKAN cuma laporan verbal "susah terdeteksi".
        face:{ enabled:true, detector:{ maxDetected:5, minConfidence:0.45 }, mesh:{enabled:true}, iris:{enabled:false},
               description:{enabled:true}, emotion:{enabled:false}, antispoof:{enabled:false}, liveness:{enabled:false} },
        body:{enabled:false}, hand:{enabled:false}, object:{enabled:false}, gesture:{enabled:false},
        filter:{enabled:false}, segmentation:{enabled:false},
    });
    await human.load();
    humanBackend = human.tf.getBackend();
    humanReady = true;
    return human;
}
// kemiripan embedding — pakai Human (terkalibrasi 0..1), fallback cosine
function faceSim(a, b){
    if(human && human.match && typeof human.match.similarity==='function'){ try { return human.match.similarity(a, b); } catch(e){} }
    let dot=0, na=0, nb=0; const n=Math.min(a.length,b.length);
    for(let i=0;i<n;i++){ dot+=a[i]*b[i]; na+=a[i]*a[i]; nb+=b[i]*b[i]; }
    return dot / (Math.sqrt(na*nb) + 1e-8);
}
function normalizeFaceDescriptors(desc){
    if(!Array.isArray(desc)) return [];
    if(desc.length && typeof desc[0] === 'number') return [desc.map(Number)];
    return desc.filter(v => Array.isArray(v) && v.length >= 64).map(v => v.map(Number));
}

function faceScan(data, opts={}){
    return {
        loading:false, camOn:false, scanning:false, busy:false, fs:false, lowLight:false,
        previewBrightness:1, autoExposureOn:false,
        _lastExposureAdjustAt:0, _lastAvgLuma:128,
        status:'Menyiapkan kamera…',
        attendees: data.map(s=>({ ...s, marked: s.status==='hadir', justMarked:false, pulangMarked: !!s.pulangDone, jam_masuk: s.jam_masuk, jam_pulang: s.jam_pulang })),
        enrolled:[], stream:null, timer:null,
        // ===== Ambang pencocokan =====
        // Strategi: SKOR longgar + KONSISTENSI ketat. Riwayat kalibrasi:
        // (1) ambang ketat → "susah terdeteksi"; (2) dilonggarkan + konfirmasi 1 frame →
        // "mengabsenkan ORANG LAIN"; (3) semua dinaikkan → "susah terdeteksi" lagi.
        // Pelajaran: pengaman salah-orang paling efektif adalah KONFIRMASI 2 FRAME pada orang
        // yang sama (match salah tidak stabil antar frame, match benar stabil) + margin ke
        // kandidat kedua — BUKAN ambang skor tinggi. Maka ambang skor dikembalikan ke level
        // yang terbukti mudah mendeteksi, sementara confirmFrames:2 dipertahankan.
        threshold:0.66,
        confidentThreshold:0.82,
        supportThreshold:0.62,
        minSampleSupport:2,
        singleSampleTop1:0.72, // 1 sampel cukup bila top1 sangat yakin
        margin:0.06,           // jarak minimal ke kandidat kedua — nama mirip tidak boleh menang tipis
        minFaceFrac:0.12,      // wajah boleh sedikit lebih jauh dari kamera (dulu 0.14)
        minFaceScore:0.55,
        confirmFrames:2,       // JANGAN turunkan ke 1 — ini penahan utama "salah orang"
        _streak:{},
        _faceLocked:{},
        _scanPauseUntil:0,
        recent:[], lastMatch:null, _seq:0, audioCtx:null,
        _noFaceStreak:0, noFaceHint:false,
        showDiag:false,
        scanMode:'masuk',
        activeTab: 'siswa',
        siswaSearch: '',
        guruSearch: '',
        kelasFilter: opts.kelasFilter || '',
        kelasOptions: opts.kelasOptions || [],
        telemetryUrl: opts.telemetryUrl || '',
        markBarcodeUrl: opts.markBarcodeUrl || '',
        tanggal: opts.tanggal || '',
        kioskToken: opts.kioskToken || null,
        isKiosk: !!opts.isKiosk,
        hasGuru: !!opts.hasGuru,
        showBarcodeModal:false, barcodeInput:'', barcodeError:'', barcodeBusy:false,
        barcodeScanning:false, barcodeScanner:null, _faceWasOn:false, _scanGen:0,
        // Mode kamera kiosk (dari Pengaturan → Absensi): 'wajah' | 'qr' | 'keduanya'
        scanKioskMode: opts.scanKioskMode || 'keduanya',
        _lastQrTryAt:0, _lastQrCode:'', _lastQrCodeAt:0, _qrDetector:undefined,
        failStreak:0,
        diag:{ low_score:0, small_margin:0, low_support:0, small_face:0, low_face_score:0 },
        _lastDiagAt:0,

        get faceEnabled(){ return this.scanKioskMode !== 'qr'; },
        get qrEnabled(){ return this.scanKioskMode !== 'wajah'; },
        get diagTotal(){ return Object.values(this.diag).reduce((a,b)=>a+b,0); },
        get enrolledSiswaCount(){ return this.enrolledCountSiswa; },
        get hadirCountSiswa(){ return this.attendees.filter(s=>s.type==='siswa' && s.marked && this.inKelasScope(s)).length; },
        // Saat QR aktif, semua siswa bisa absen (via kartu) — bukan cuma yang punya wajah terdaftar.
        get enrolledCountSiswa(){ return this.attendees.filter(s=>s.type==='siswa' && (this.qrEnabled || (s.desc && s.desc.length)) && this.inKelasScope(s)).length; },
        get hadirCountGuru(){ return this.attendees.filter(s=>s.type==='guru' && s.marked).length; },
        // Mode QR saja: desc guru tidak dikirim server — tampilkan total guru di daftar, bukan 0.
        get enrolledCountGuru(){ return this.attendees.filter(s=>s.type==='guru' && (!this.faceEnabled || (s.desc && s.desc.length))).length; },
        get totalHadir(){ return this.attendees.filter(s=>s.marked && (s.type==='guru' || this.inKelasScope(s))).length; },
        get totalEnrolled(){ return this.qrEnabled ? this.enrolledCountSiswa + this.enrolledCountGuru : this.enrolled.length; },

        inKelasScope(s){
            if(s.type==='guru') return true;
            if(!this.kelasFilter) return true;
            return s.id_kelas === this.kelasFilter;
        },

        get filteredSiswa(){
            const q = this.siswaSearch.toLowerCase();
            return this.attendees.filter(s=>s.type==='siswa' && this.inKelasScope(s) && s.nama.toLowerCase().includes(q));
        },
        get filteredGuru(){
            return this.attendees.filter(s=>s.type==='guru' && s.nama.toLowerCase().includes(this.guruSearch.toLowerCase()));
        },

        init(){
            this.attendees = this.attendees.map(s => ({ ...s, desc: normalizeFaceDescriptors(s.desc) }));
            this.rebuildEnrolled();
            document.addEventListener('fullscreenchange', ()=>{
                this.fs = !!document.fullscreenElement;
                setTimeout(()=> window.lucide && lucide.createIcons(), 60);
            });
            // Buka kamera otomatis — pengguna tidak perlu klik tombol dulu.
            // Mode dengan QR: kamera tetap berguna walau belum ada wajah terdaftar.
            if(this.enrolled.length > 0 || this.qrEnabled){
                this.$nextTick(()=> setTimeout(()=> this.start(), this.isKiosk ? 150 : 500));
            } else {
                this.status = 'Belum ada wajah terdaftar.';
            }
        },

        rebuildEnrolled(){
            this.enrolled = this.attendees.filter(s=>s.desc && s.desc.length && this.inKelasScope(s));
            this._streak = {};
        },
        isFaceLocked(uuid){
            const s = this.attendees.find(x=>x.uuid===uuid);
            return !!(this._faceLocked[uuid] || s?.marked || s?.pulangMarked);
        },
        afterFaceMarkSuccess(){
            this._scanPauseUntil = Date.now() + 900;
            this._streak = {};
        },
        onKelasChange(){
            this.rebuildEnrolled();
            const url = new URL(window.location.href);
            if(this.kelasFilter) url.searchParams.set('kelas', this.kelasFilter);
            else url.searchParams.delete('kelas');
            history.replaceState({}, '', url);
            setTimeout(()=> window.lucide && lucide.createIcons(), 40);
        },

        enterFs(){
            const el=this.$refs.stage;
            if(el && el.requestFullscreen && !document.fullscreenElement){ el.requestFullscreen().catch(()=>{}); }
        },
        toggleFs(){
            if(document.fullscreenElement){ document.exitFullscreen?.(); }
            else { this.enterFs(); }
        },
        nowHM(){ const d=new Date(); return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); },

        robustPersonSimilarity(faceEmbedding, descriptors){
            const sims = [];
            for(const e of descriptors || []) sims.push(faceSim(faceEmbedding, e));
            sims.sort((a,b)=>b-a);
            if(!sims.length) return { score:0, top1:0, top2:0, support:0 };
            const top1 = sims[0] || 0;
            const top2 = sims[1] || 0;
            const support = sims.filter(v => v >= this.supportThreshold).length;
            // Skor = sampel TERBAIK orang ini, bukan dirata-rata dgn sampel ke-2 (dulu top1*0.58+top2*0.42).
            // Rata-rata itu bikin kecocokan kuat pada 1 sampel terdaftar "diseret turun" krn sampel lain
            // beda sudut/cahaya — makin banyak wajah didaftarkan (3 posisi), makin sering nyangkut di
            // "Perjelas wajah" walau top1 sudah sangat mirip. Korroborasi tetap dijaga lewat
            // hasEnoughSampleAgreement() (support>=2 sampel ATAU top1 sangat tinggi) sbg gate terpisah.
            const score = top1;
            return { score, top1, top2, support };
        },

        hasEnoughSampleAgreement(match){
            if(!match) return false;
            if((match.sampleCount || 0) <= 1) return match.top1 >= this.threshold;
            if(match.top1 >= this.singleSampleTop1) return true;
            return match.support >= this.minSampleSupport || match.top1 >= this.confidentThreshold;
        },

        recordDiag(reason, meta={}){
            if(this.diag[reason] !== undefined) this.diag[reason]++;
            const now = Date.now();
            if(!this.telemetryUrl || now - this._lastDiagAt < 3000) return;
            this._lastDiagAt = now;
            try {
                fetch(this.telemetryUrl, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({
                        reason,
                        top1: meta.top1 ?? null,
                        gap: meta.gap ?? null,
                        support: meta.support ?? null,
                        kelas: this.kelasFilter || null,
                        _kiosk: this.kioskToken,
                    }),
                    keepalive:true,
                });
            } catch(e){}
        },

        // bunyi "ting" sukses absen (Web Audio, tanpa file)
        playDing(){
            try {
                const ctx=this.audioCtx; if(!ctx) return;
                if(ctx.state==='suspended') ctx.resume();
                const now=ctx.currentTime;
                [ [880,0], [1318.5,0.12] ].forEach(([freq,at])=>{   // dua nada: A5 → E6 (chime ceria)
                    const osc=ctx.createOscillator(), gain=ctx.createGain();
                    osc.type='sine'; osc.frequency.value=freq;
                    gain.gain.setValueAtTime(0.0001, now+at);
                    gain.gain.exponentialRampToValueAtTime(0.25, now+at+0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now+at+0.35);
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.start(now+at); osc.stop(now+at+0.37);
                });
            } catch(e){}
        },

        // bunyi "error" penolakan absen — nada turun & kasar, beda dari sukses
        playError(){
            try {
                const ctx=this.audioCtx; if(!ctx) return;
                if(ctx.state==='suspended') ctx.resume();
                const now=ctx.currentTime;
                [ [392,0], [261.6,0.17] ].forEach(([freq,at])=>{   // G4 → C4 (turun = ditolak)
                    const osc=ctx.createOscillator(), gain=ctx.createGain();
                    osc.type='square'; osc.frequency.value=freq;
                    gain.gain.setValueAtTime(0.0001, now+at);
                    gain.gain.exponentialRampToValueAtTime(0.16, now+at+0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now+at+0.26);
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.start(now+at); osc.stop(now+at+0.28);
                });
            } catch(e){}
        },

        // Umpan balik penolakan (siswa & guru): suara error + toast + suara bicara
        rejectFeedback(nama, msg){
            this.playError();
            showToast((nama ? nama.split(' ')[0]+': ' : '') + (msg || 'Absen tidak dapat diproses'), 'error');
            this.speak('tolak', nama);
        },

        // Suara sapaan: masuk → "Selamat datang, nama", pulang → "Terima kasih, nama"
        speak(label, nama){
            try {
                if(!('speechSynthesis' in window)) return;
                const panggil = (nama||'').split(',')[0].trim();
                let teks;
                if(label === 'pulang') teks = 'Terima kasih, ' + panggil;
                else if(label === 'tolak') teks = 'Maaf ' + panggil + ', absen tidak dapat diproses';
                else teks = 'Selamat datang, ' + panggil;
                const u = new SpeechSynthesisUtterance(teks);
                u.lang='id-ID'; u.rate=0.97; u.pitch=1;
                const id = speechSynthesis.getVoices().find(v => v.lang && v.lang.toLowerCase().startsWith('id'));
                if(id) u.voice = id;
                speechSynthesis.speak(u);   // antre — tiap orang tersapa berurutan
            } catch(e){}
        },

        async start(){
            this.enterFs(); // panggil dalam gesture klik (sebelum await) agar fullscreen diizinkan
            try { this.audioCtx = this.audioCtx || new (window.AudioContext||window.webkitAudioContext)(); } catch(e){}
            try { if('speechSynthesis' in window){ speechSynthesis.getVoices(); speechSynthesis.speak(new SpeechSynthesisUtterance(' ')); } } catch(e){} // buka izin suara (gesture)
            this.rebuildEnrolled();
            const faceActive = this.faceEnabled && this.enrolled.length > 0;
            if(!faceActive && !this.qrEnabled){ this.status='Belum ada wajah terdaftar.'; return; }
            if(this.camOn || this.loading) return;
            this.loading=true; this.status='Mengaktifkan kamera...';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(this.getVideoConstraints());
                const v=this.$refs.video; v.srcObject=this.stream;
                await new Promise(r=> v.onloadedmetadata = r); v.play();
                this.camOn=true;
                this.applyAutoExposure(); // exposure/WB kontinu + kompensasi awal bila didukung hardware
                if(faceActive){
                    this.status='Memuat model AI (pertama kali agak lama, lalu tersimpan)...';
                    await loadHuman();
                }
                this.loading=false; this.scanning=true;
                this.status = faceActive
                    ? (this.qrEnabled ? 'Hadap kamera atau tunjukkan QR kartu' : 'Hadap kamera')
                    : 'Tunjukkan QR kartu pelajar ke kamera';
                this.tick();
            } catch(e){
                this.loading=false; this.camOn=false;
                this.status='Gagal: '+(e.name==='NotAllowedError'?'akses kamera ditolak':e.message);
            }
        },

        // Constraint kamera: minta exposure/WB/focus kontinu sejak awal (browser boleh abaikan yg tak didukung).
        getVideoConstraints(){
            return {
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    exposureMode: { ideal: 'continuous' },
                    whiteBalanceMode: { ideal: 'continuous' },
                    focusMode: { ideal: 'continuous' },
                },
            };
        },

        // Estimasi kecerahan rata-rata frame (luma) — sampling jarang utk hemat CPU.
        sampleAvgLuma(imageData, w, h){
            const px = imageData.data;
            let sum=0, n=0;
            const stride = Math.max(160, Math.floor((w * h * 4) / 4000) * 4);
            for(let i=0; i<px.length; i+=stride){ sum += 0.299*px[i] + 0.587*px[i+1] + 0.114*px[i+2]; n++; }
            return n ? sum/n : 128;
        },

        softwareBrightnessBoost(avgLuma){
            if(avgLuma >= 90) return 1;
            return Math.min(2.8, 1 + (90 - avgLuma) / 50);
        },

        // Hardware auto exposure: mode kontinu + kompensasi/ISO/brightness adaptif saat gelap.
        applyAutoExposure(avgLuma){
            try {
                const track = this.stream?.getVideoTracks()?.[0];
                if(!track?.getCapabilities) return Promise.resolve();
                const caps = track.getCapabilities();
                const adv = {};
                const luma = avgLuma ?? this._lastAvgLuma ?? 128;
                const dark = luma < 90;
                const darkT = dark ? Math.max(0, Math.min(1, (90 - luma) / 90)) : 0;

                if(caps.exposureMode?.includes('continuous')) adv.exposureMode = 'continuous';
                if(caps.whiteBalanceMode?.includes('continuous')) adv.whiteBalanceMode = 'continuous';
                if(caps.focusMode?.includes('continuous')) adv.focusMode = 'continuous';

                if(caps.exposureCompensation && (dark || adv.exposureMode)){
                    const { min, max, step } = caps.exposureCompensation;
                    const span = max - min;
                    const target = dark
                        ? min + span * darkT
                        : min + span * 0.35;
                    const stepVal = step || 0.1;
                    adv.exposureCompensation = Math.round(target / stepVal) * stepVal;
                }

                if(dark && caps.brightness){
                    const { min, max } = caps.brightness;
                    adv.brightness = min + (max - min) * darkT * 0.75;
                }

                if(dark && !adv.exposureMode && caps.iso){
                    const { min, max } = caps.iso;
                    adv.iso = Math.round(min + (max - min) * (0.35 + darkT * 0.55));
                }

                if(!Object.keys(adv).length) return Promise.resolve();
                return track.applyConstraints({ advanced:[adv] }).then(()=>{ this.autoExposureOn = true; }).catch(()=>{});
            } catch(e){
                return Promise.resolve();
            }
        },

        // Sesuaikan ulang exposure hardware tiap beberapa detik selama masih gelap.
        maybeAdjustHardwareExposure(avgLuma){
            this._lastAvgLuma = avgLuma;
            const now = Date.now();
            if(avgLuma >= 95){
                if(this.lowLight) this.applyAutoExposure(avgLuma);
                return;
            }
            if(avgLuma >= 90 || now - this._lastExposureAdjustAt < 2500) return;
            this._lastExposureAdjustAt = now;
            this.applyAutoExposure(avgLuma);
        },

        // Pencerahan otomatis berbasis software (jalan di semua kamera/browser, tak tergantung dukungan hardware).
        // Sampling cepat kecerahan rata-rata frame → kalau gelap, naikkan brightness sebelum deteksi wajah.
        // CATATAN: sengaja TIDAK dicampur dgn contrast() — contrast linear di sekitar titik tengah 128 justru
        // menekan piksel gelap balik ke bawah, melawan efek brightness yg baru dinaikkan.
        enhanceFrame(video){
            const w = video.videoWidth, h = video.videoHeight;
            if(!w || !h) return video;
            if(!this._ecv){ this._ecv = document.createElement('canvas'); this._ectx = this._ecv.getContext('2d', { willReadFrequently:true }); }
            const cv=this._ecv, ctx=this._ectx;
            cv.width=w; cv.height=h;
            ctx.filter = 'none';
            ctx.drawImage(video, 0, 0, w, h);

            const avgLuma = this.sampleAvgLuma(ctx.getImageData(0, 0, w, h), w, h);
            this.lowLight = avgLuma < 90;
            this.previewBrightness = this.softwareBrightnessBoost(avgLuma);
            this.maybeAdjustHardwareExposure(avgLuma);

            if(this.lowLight){
                ctx.filter = `brightness(${this.previewBrightness.toFixed(2)})`;
                ctx.drawImage(video, 0, 0, w, h);
                ctx.filter = 'none';
            }
            return cv;
        },

        async tick(){
            if(!this.scanning) return;
            if(Date.now() < this._scanPauseUntil){
                if(this.scanning) this.timer=setTimeout(()=>this.tick(), 180);
                return;
            }
            const gen = this._scanGen;
            if(this.busy){ if(this.scanning && gen === this._scanGen) this.timer=setTimeout(()=>this.tick(), 120); return; }
            const v=this.$refs.video;
            if(!v || !v.videoWidth){ if(this.scanning && gen === this._scanGen) this.timer=setTimeout(()=>this.tick(), 300); return; }
            this.busy=true;
            const t0=performance.now();
            const faceActive = this.faceEnabled && humanReady && this.enrolled.length > 0;
            try {
                if(faceActive){
                    const frame = this.enhanceFrame(v);
                    const res = await human.detect(frame);
                    if(gen !== this._scanGen || !this.scanning) return;
                    this.render(res);
                }
                // QR kartu dibaca dari kamera yang SAMA — throttle terpisah supaya deteksi wajah
                // tidak melambat, dan tidak menumpuk submit saat satu kartu masih diproses.
                if(this.qrEnabled && !this.barcodeBusy && Date.now() - this._lastQrTryAt >= 350){
                    this._lastQrTryAt = Date.now();
                    const code = await this.detectQrFromVideo(v);
                    if(gen !== this._scanGen || !this.scanning) return;
                    if(code) this.onCameraQr(code);
                }
            } catch(e){ /* skip frame */ }
            finally {
                if(gen === this._scanGen) this.busy = false;
            }
            if(gen !== this._scanGen || !this.scanning) return;
            const dt = performance.now()-t0;
            const allDone = this.enrolled.every(s=>s.marked);
            const delay = faceActive
                ? ((allDone && !this.qrEnabled) ? 1500 : Math.min(1200, Math.max(200, Math.round(dt*0.7))))
                : 300; // mode QR saja: decode ringan, poll lebih rapat biar responsif
            this.timer=setTimeout(()=>this.tick(), delay);
        },

        // ===== QR kartu langsung dari kamera wajah (tanpa modal terpisah) =====
        // BarcodeDetector (native, cepat) bila browser mendukung; fallback jsQR (murni JS).
        async detectQrFromVideo(v){
            try {
                if(!v || !v.videoWidth) return null;
                if(!this._qcv){ this._qcv = document.createElement('canvas'); this._qctx = this._qcv.getContext('2d', { willReadFrequently:true }); }
                const scale = Math.min(1, 640 / v.videoWidth);
                const w = Math.max(1, Math.round(v.videoWidth * scale));
                const h = Math.max(1, Math.round(v.videoHeight * scale));
                this._qcv.width = w; this._qcv.height = h;
                this._qctx.drawImage(v, 0, 0, w, h);

                if(this._qrDetector === undefined){
                    this._qrDetector = null;
                    if('BarcodeDetector' in window){
                        try { this._qrDetector = new BarcodeDetector({ formats:['qr_code'] }); } catch(e){}
                    }
                }
                if(this._qrDetector){
                    const codes = await this._qrDetector.detect(this._qcv);
                    return (codes && codes.length) ? codes[0].rawValue : null;
                }
                if(typeof jsQR === 'function'){
                    const img = this._qctx.getImageData(0, 0, w, h);
                    const q = jsQR(img.data, w, h, { inversionAttempts:'dontInvert' });
                    return q ? q.data : null;
                }
            } catch(e){}
            return null;
        },

        onCameraQr(code){
            const c = String(code || '').trim();
            if(!c || this.barcodeBusy) return;
            // Debounce "selama masih terlihat": kartu yang sama TIDAK di-submit ulang selama terus
            // berada di depan kamera (tiap deteksi memperpanjang jedanya), tapi begitu kartu
            // disingkirkan ±2 detik, scan ulang langsung diproses lagi. Dulu pakai blokir datar
            // 5 detik tanpa perpanjangan — scan kedua kartu yang sama tertelan diam-diam dan
            // kiosk terasa "mati" (dilaporkan: "tidak bisa scan untuk kedua kali").
            const now = Date.now();
            if(this._lastQrCode === c && now - this._lastQrCodeAt < 2000){
                this._lastQrCodeAt = now; // masih di depan kamera — perpanjang, jangan submit ulang
                return;
            }
            this._lastQrCode = c; this._lastQrCodeAt = now;
            this.barcodeBusy = true;
            this.submitBarcode(c);
        },

        render(res){
            const v=this.$refs.video, c=this.$refs.canvas;
            c.width=v.videoWidth; c.height=v.videoHeight;
            const ctx=c.getContext('2d'); ctx.clearRect(0,0,c.width,c.height);

            const seenThisFrame = new Set();   // uuid yang lolos gate di frame ini (utk konfirmasi lintas-frame)

            // Tidak ada wajah SAMA SEKALI terdeteksi Human (bukan soal cocok/tidak cocok) —
            // sebelum ini pengguna tidak dapat petunjuk apa pun saat kasus ini terjadi terus-
            // menerus (kamera gelap/wajah di luar frame/tertutup masker-hijab menutup dagu&dahi).
            if(!(res.face||[]).length){
                this._noFaceStreak = (this._noFaceStreak||0) + 1;
                this.noFaceHint = this._noFaceStreak >= 10; // ~beberapa detik beruntun tanpa wajah
            } else {
                this._noFaceStreak = 0;
                this.noFaceHint = false;
            }

            (res.face||[]).forEach(f=>{
                if(!f.embedding || !f.box) return;
                const b=f.box; // [x,y,w,h]

                // Cari kandidat terbaik DAN kedua dengan skor robust per-orang.
                // Satu sampel buruk tidak boleh membuat satu nama terus menang sebagai false positive.
                let bestUuid=null, bestSim=0, secondSim=0, bestMatch=null;
                for(const s of this.enrolled){
                    if(this.isFaceLocked(s.uuid)) continue;
                    const match = this.robustPersonSimilarity(f.embedding, s.desc);
                    match.sampleCount = (s.desc || []).length;
                    if(match.score>bestSim){ secondSim=bestSim; bestSim=match.score; bestUuid=s.uuid; bestMatch=match; }
                    else if(match.score>secondSim){ secondSim=match.score; }
                }

                // ===== Gate berlapis (harus lolos SEMUA baru dianggap cocok) =====
                const faceScore = (f.faceScore ?? f.score ?? f.boxScore ?? 1);
                const bigEnough = Math.min(b[2], b[3]) >= (c.height * this.minFaceFrac);
                const gap = bestSim - secondSim;
                const clearGap  = gap >= this.margin || bestSim >= this.confidentThreshold;
                const sampleAgreement = this.hasEnoughSampleAgreement(bestMatch);
                const strongMatch = bestSim >= this.threshold && clearGap && sampleAgreement && bigEnough && faceScore >= this.minFaceScore;

                let label, color;
                if(strongMatch){
                    const s=this.attendees.find(z=>z.uuid===bestUuid);
                    label=(s?s.nama.split(' ')[0]:'?'); color='#10b981';
                    seenThisFrame.add(bestUuid);
                    this.failStreak = 0;
                } else {
                    this.failStreak++;
                    const meta = { top1: bestMatch?.top1 ?? bestSim, gap, support: bestMatch?.support ?? 0 };
                    if(!bigEnough){ this.recordDiag('small_face', meta); }
                    else if(faceScore < this.minFaceScore){ this.recordDiag('low_face_score', meta); }
                    else if(bestSim < this.threshold){ this.recordDiag('low_score', meta); }
                    else if(!clearGap){ this.recordDiag('small_margin', meta); }
                    else if(!sampleAgreement){ this.recordDiag('low_support', meta); }

                    // Label sesuai gate yang SEBENARNYA gagal — sebelumnya kasus paling umum di
                    // lapangan (wajah masih terlalu kecil/jauh dari kamera, bigEnough=false) malah
                    // jatuh ke '—' polos tanpa petunjuk apa pun, sementara "Dekatkan wajah" (yang
                    // seharusnya soal jarak) hanya muncul saat wajah SUDAH cukup besar. Pengguna
                    // yang berdiri di jarak wajar dari kiosk tidak pernah diberi tahu utk mendekat.
                    const top1Val = bestMatch?.top1 ?? bestSim;
                    if(!bigEnough){
                        label='Mendekat ke kamera'; color='#f59e0b';
                    } else if(faceScore < this.minFaceScore){
                        label='Tahan diam, perbaiki cahaya'; color='#f59e0b';
                    } else if(top1Val >= this.supportThreshold){
                        label='Perjelas wajah'; color='#f59e0b';
                    } else if(top1Val >= 0.45){
                        // Skor sedang (0.45–0.62) — kemungkinan besar orang yg SAMA tapi sudut/
                        // cahaya kurang pas, BUKAN "tidak dikenali sama sekali". Dulu kasus ini
                        // ikut jatuh ke '—' polos, tak beda dgn wajah yg benar2 tak dikenal.
                        label='Coba lagi, hadap lurus'; color='#f59e0b';
                    } else {
                        label='—'; color='#94a3b8';
                    }
                }

                ctx.strokeStyle=color; ctx.lineWidth=3; ctx.strokeRect(b[0], b[1], b[2], b[3]);
                ctx.font='bold 20px sans-serif'; const tw=ctx.measureText(label).width+14;
                ctx.fillStyle=color; ctx.fillRect(b[0], b[1]-30, tw, 28);
                ctx.fillStyle='#fff'; ctx.fillText(label, b[0]+7, b[1]-10);
            });

            // ===== Hijau = langsung kunci & absen (1 frame), tanpa scan berulang =====
            seenThisFrame.forEach(uuid=>{
                if(this.isFaceLocked(uuid)) return;
                this._streak[uuid] = (this._streak[uuid]||0) + 1;
                if(this._streak[uuid] >= this.confirmFrames){
                    this._faceLocked[uuid] = true;
                    this.onMatch(uuid);
                }
            });
            // Luruh (bukan reset total) untuk yang tidak lolos gate frame ini — satu frame buram/silau
            // tak boleh menghapus progres streak yang sudah bagus (itu yang bikin "kedetect lalu
            // Perjelas wajah lagi" terasa lama). False positif sekejap tetap tak menumpuk krn meluruh turun.
            for(const uuid in this._streak){ if(!seenThisFrame.has(uuid)) this._streak[uuid] = Math.max(0, (this._streak[uuid]||0) - 1); }
        },

        onMatch(uuid){
            const s=this.attendees.find(x=>x.uuid===uuid);
            if(!s) return;
            if(this.isFaceLocked(uuid) && (s.marked || s.pulangMarked || s._masukBusy || s._pulangBusy)) return;
            this._faceLocked[uuid] = true;

            // ===== Mode PULANG (khusus guru) =====
            if(this.scanMode==='pulang'){
                if(s.type!=='guru'){ delete this._faceLocked[uuid]; return; }
                if(s.pulangMarked) return;
                if(s._pulangBusy) return;
                if(s._pulangBlockedAt && (Date.now()-s._pulangBlockedAt) < 8000) return; // jeda setelah ditolak
                s._pulangBusy=true;
                // Cek server DULU (agenda wajib lengkap) — baru tampilkan konfirmasi bila lolos.
                fetch('{{ route('presensi-guru.mark') }}', {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ id_guru: uuid, tanggal: '{{ $tanggal }}', mode:'pulang', _kiosk: @json($kioskToken ?? null) })
                }).then(r=>r.json()).then(d=>{
                    s._pulangBusy=false;
                    if(!d || d.success===false){
                        s._pulangBlockedAt = Date.now();
                        delete this._faceLocked[uuid];
                        this.rejectFeedback(s.nama, (d&&d.message) ? d.message : 'Tidak bisa absen pulang.');
                        return;
                    }
                    // Lolos → tampilkan konfirmasi pulang
                    s.pulangMarked=true; s.justMarked=true;
                    const k=++this._seq;
                    const jamK=d.jam || this.nowHM();
                    s.jam_pulang = jamK;
                    this.playDing();
                    this.speak('pulang', s.nama);
                    this.afterFaceMarkSuccess();
                    this.lastMatch={ key, nama:s.nama, type:s.type, kelas:'Guru', mode:'pulang', jam:jamK, terlambat:false };
                    this.recent.unshift({ key:k, nama:s.nama.split(' ')[0], type:s.type, kelas:'Pulang', mode:'pulang', jam:jamK });
                    if(this.recent.length>5) this.recent.pop();
                    setTimeout(()=> window.lucide && lucide.createIcons(), 40);
                    setTimeout(()=>{ if(this.lastMatch && this.lastMatch.key===k) this.lastMatch=null; }, 1700);
                    setTimeout(()=>{ s.justMarked=false; }, 1600);
                    setTimeout(()=>{ this.recent = this.recent.filter(x=>x.key!==k); }, 6000); // auto-hilang
                }).catch(()=>{ s._pulangBusy=false; delete this._faceLocked[uuid]; });
                return;
            }

            // ===== Mode MASUK =====
            if(s.marked) return;

            // GURU: cek server dulu (metode wajah harus aktif) — baru konfirmasi.
            if(s.type==='guru'){
                if(s._masukBusy) return;
                if(s._masukBlockedAt && (Date.now()-s._masukBlockedAt) < 8000) return; // jeda setelah ditolak
                s._masukBusy=true;
                fetch('{{ route('presensi-guru.mark') }}', {
                    method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({ id_guru: uuid, tanggal: '{{ $tanggal }}', status:'hadir', mode:'masuk', _kiosk: @json($kioskToken ?? null) })
                }).then(r=>r.json()).then(d=>{
                    s._masukBusy=false;
                    if(!d || d.success===false){
                        s._masukBlockedAt = Date.now();
                        delete this._faceLocked[uuid];
                        this.rejectFeedback(s.nama, (d&&d.message) || 'Tidak bisa absen');
                        return;
                    }
                    s.marked=true; s.justMarked=true;
                    const key=++this._seq; const jam=d.jam || this.nowHM();
                    s.jam_masuk = jam;
                    this.playDing(); this.speak('masuk', s.nama);
                    this.afterFaceMarkSuccess();
                    this.lastMatch={ key, nama:s.nama, type:'guru', kelas:'Guru', mode:'masuk', jam, terlambat:!!d.terlambat };
                    this.recent.unshift({ key, nama:s.nama.split(' ')[0], type:'guru', kelas:'Guru', mode:'masuk', jam });
                    if(this.recent.length>5) this.recent.pop();
                    setTimeout(()=> window.lucide && lucide.createIcons(), 40);
                    setTimeout(()=>{ if(this.lastMatch && this.lastMatch.key===key) this.lastMatch=null; }, 1700);
                    setTimeout(()=>{ s.justMarked=false; }, 1600);
                    setTimeout(()=>{ this.recent = this.recent.filter(x=>x.key!==key); }, 6000);
                }).catch(()=>{ s._masukBusy=false; delete this._faceLocked[uuid]; });
                return;
            }

            // SISWA: cek server dulu (metode & kalender) — baru tampilkan konfirmasi.
            if(s._masukBusy) return;
            if(s._masukBlockedAt && (Date.now()-s._masukBlockedAt) < 8000) return; // jeda setelah ditolak
            s._masukBusy=true;
            fetch('{{ route('absensi.mark') }}', {
                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                body: JSON.stringify({ id_siswa: uuid, id_kelas: s.id_kelas, tanggal: '{{ $tanggal }}', status: 'hadir', _kiosk: @json($kioskToken ?? null) })
            }).then(r=>r.json()).then(d=>{
                s._masukBusy=false;
                if(!d || d.success===false){
                    s._masukBlockedAt = Date.now();
                    delete this._faceLocked[uuid];
                    this.rejectFeedback(s.nama, (d&&d.message) || 'Absensi tidak dibuka');
                    return;
                }
                s.marked=true; s.justMarked=true;
                const key=++this._seq; const jam=d.jam || this.nowHM();
                s.jam_masuk = jam;
                this.playDing(); this.speak('masuk', s.nama);
                this.afterFaceMarkSuccess();
                this.lastMatch={ key, nama:s.nama, type:'siswa', kelas:s.kelas, mode:'masuk', jam, terlambat:!!d.terlambat };
                this.recent.unshift({ key, nama:s.nama.split(' ')[0], type:'siswa', kelas:s.kelas, mode:'masuk', jam });
                if(this.recent.length>5) this.recent.pop();
                setTimeout(()=> window.lucide && lucide.createIcons(), 40);
                setTimeout(()=>{ if(this.lastMatch && this.lastMatch.key===key) this.lastMatch=null; }, 1700);
                setTimeout(()=>{ s.justMarked=false; }, 1600);
                setTimeout(()=>{ this.recent = this.recent.filter(x=>x.key!==key); }, 6000);
            }).catch(()=>{ s._masukBusy=false; delete this._faceLocked[uuid]; });
        },

        stop(){
            this.scanning=false; this.camOn=false;
            this.lowLight=false; this.previewBrightness=1; this.autoExposureOn=false;
            if(this.timer) clearTimeout(this.timer);
            if(this.stream){ this.stream.getTracks().forEach(t=>t.stop()); this.stream=null; }
            if(document.fullscreenElement){ document.exitFullscreen?.(); }
            const c=this.$refs.canvas; if(c){ c.getContext('2d').clearRect(0,0,c.width,c.height); }
            this.recent=[]; this.lastMatch=null;
            this.status='Pemindaian dihentikan. '+this.totalHadir+' hadir. Klik Mulai Scan untuk lanjut.';
        },

        cancelAbsen(s, mode) {
            $.confirm({
                title: 'Konfirmasi Pembatalan',
                content: 'Batalkan absen ' + mode + ' untuk <b>' + s.nama + '</b>?',
                theme: 'material',
                type: 'red',
                buttons: {
                    ok: {
                        text: 'Batalkan',
                        btnClass: 'btn-red',
                        action: () => {
                            s._masukBusy = true; // reuse busy state
                            let url = s.type === 'guru' ? '{{ route('presensi-guru.cancel') }}' : '{{ route('absensi.cancel') }}';
                            let body = s.type === 'guru' ? { id_guru: s.uuid, tanggal: '{{ $tanggal }}', mode: mode, _kiosk: @json($kioskToken ?? null) } : { id_siswa: s.uuid, tanggal: '{{ $tanggal }}', _kiosk: @json($kioskToken ?? null) };
                            
                            fetch(url, {
                                method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                                body: JSON.stringify(body)
                            }).then(r=>r.json()).then(d=>{
                                s._masukBusy=false;
                                if(d && d.success) {
                                    delete this._faceLocked[s.uuid];
                                    if (mode === 'masuk') {
                                        s.marked = false;
                                        s.jam_masuk = null;
                                        if(s.type === 'siswa') s.pulangMarked = false; // reset
                                    } else if (mode === 'pulang') {
                                        s.pulangMarked = false;
                                        s.jam_pulang = null;
                                        s.pulangDone = false;
                                    }
                                    showToast('Absensi dibatalkan', 'success');
                                    setTimeout(()=> window.lucide && lucide.createIcons(), 60);
                                } else {
                                    showToast('Gagal membatalkan', 'error');
                                }
                            }).catch(()=>{ s._masukBusy=false; showToast('Gagal membatalkan', 'error'); });
                        }
                    },
                    batal: {
                        text: 'Batal',
                        btnClass: 'btn-default'
                    }
                }
            });
        },

        pauseFaceForBarcode(){
            this._scanGen++;
            if(this.timer){ clearTimeout(this.timer); this.timer=null; }
            this.scanning = false;
            this.busy = false;
            this._faceWasOn = this.camOn || this.loading || !!this.stream;
            this.failStreak = 0;
            if(this.stream){
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
                this.camOn = false;
            }
        },

        async resumeFaceAfterBarcode(){
            if(!this._faceWasOn) return;
            this._faceWasOn = false;
            if(this.loading && !this.camOn){
                await this.start();
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(this.getVideoConstraints());
                const v=this.$refs.video; v.srcObject=this.stream;
                await new Promise(r=> v.onloadedmetadata = r); v.play();
                this.camOn = true;
                this.applyAutoExposure();
                this.scanning = true;
                this.tick();
            } catch(e){
                this.status = 'Gagal memulihkan kamera wajah: ' + e.message;
            }
        },

        async openBarcodeModal(){
            this.barcodeError = '';
            this.showBarcodeModal = true;
            this.pauseFaceForBarcode();
            this.$nextTick(()=>{
                this.startBarcodeScan();
                setTimeout(()=> window.lucide && lucide.createIcons(), 40);
            });
        },

        async closeBarcodeModal(){
            await this.stopBarcodeScan();
            this.showBarcodeModal = false;
            await this.resumeFaceAfterBarcode();
        },

        startBarcodeScan(){
            if(typeof Html5Qrcode === 'undefined'){ this.barcodeError = 'Pemindai QR belum dimuat.'; return; }
            const el = document.getElementById('barcodeReader');
            if(!el) return;
            if(this.barcodeScanner) return;
            this.barcodeScanning = true;
            this.barcodeScanner = new Html5Qrcode('barcodeReader');
            this.barcodeScanner.start(
                { facingMode:'environment' },
                { fps:12, qrbox:{ width:240, height:240 }, aspectRatio:1.0 },
                (text)=> this.onBarcodeScanned(text),
                ()=>{}
            ).catch(()=>{
                this.barcodeScanning = false;
                this.barcodeError = 'Tidak bisa membuka kamera kartu — gunakan scanner USB di bawah.';
            });
        },

        stopBarcodeScan(){
            if(!this.barcodeScanner) return Promise.resolve();
            const s = this.barcodeScanner;
            this.barcodeScanner = null;
            this.barcodeScanning = false;
            return s.stop().then(()=>{ try{ s.clear(); }catch(e){} }).catch(()=>{});
        },

        onBarcodeScanned(raw){
            const code = String(raw || '').trim();
            if(!code || this.barcodeBusy) return;
            this.barcodeBusy = true;
            this.stopBarcodeScan().then(()=> this.submitBarcode(code));
        },

        async submitBarcode(code){
            const barcode = String(code || this.barcodeInput || '').trim();
            this.barcodeError = '';
            if(!barcode){ this.barcodeError = 'Kode kartu kosong.'; this.barcodeBusy = false; return; }
            if(!this.markBarcodeUrl){ this.barcodeError = 'Endpoint kartu pelajar tidak tersedia.'; this.barcodeBusy = false; return; }
            // Kartu hanya berlaku sekali — kalau orangnya sudah tercatat hadir (siswa) atau
            // sudah absen di mode saat ini (guru: masuk/pulang terpisah) di daftar, tolak
            // langsung tanpa ke server (server tetap punya guard yang sama utk kartu yang
            // tidak ada di daftar lokal, mis. payload NISN/NIP yang belum sinkron).
            const knownSiswa = this.attendees.find(x => x.type==='siswa' && (x.uuid === barcode || String(x.nis) === barcode));
            if(knownSiswa && knownSiswa.marked){
                this.barcodeError = knownSiswa.nama + ' sudah absen' + (knownSiswa.jam_masuk ? ' ' + knownSiswa.jam_masuk : '') + ' — kartu hanya berlaku sekali.';
                this.playError();
                showToast(this.barcodeError, 'error');
                this.barcodeBusy = false;
                if(this.showBarcodeModal && !this.barcodeScanner) this.startBarcodeScan();
                return;
            }
            const knownGuru = this.attendees.find(x => x.type==='guru' && (x.uuid === barcode || String(x.nip||'') === barcode));
            if(knownGuru && (this.scanMode==='pulang' ? knownGuru.pulangMarked : knownGuru.marked)){
                const jamSudah = this.scanMode==='pulang' ? knownGuru.jam_pulang : knownGuru.jam_masuk;
                this.barcodeError = knownGuru.nama + ' sudah absen ' + (this.scanMode==='pulang' ? 'pulang ' : '') + (jamSudah||'') + ' — kartu hanya berlaku sekali.';
                this.playError();
                showToast(this.barcodeError, 'error');
                this.barcodeBusy = false;
                if(this.showBarcodeModal && !this.barcodeScanner) this.startBarcodeScan();
                return;
            }
            if(!this.barcodeBusy) this.barcodeBusy = true;
            try {
                const res = await fetch(this.markBarcodeUrl, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'),Accept:'application/json'},
                    body: JSON.stringify({
                        barcode,
                        tanggal: this.tanggal || '{{ $tanggal }}',
                        id_kelas: this.kelasFilter || null,
                        mode: this.scanMode, // relevan utk kartu ID guru (masuk/pulang)
                        _kiosk: this.kioskToken,
                    }),
                });
                const d = await res.json();
                if(!d || d.success===false){
                    this.barcodeError = (d && d.message) ? d.message : 'Gagal menandai hadir.';
                    this.playError();
                    showToast(this.barcodeError, 'error');
                    // Server bilang duplikat → sinkronkan daftar lokal (mis. scan pakai NISN/NIP yang
                    // tidak cocok dgn field di daftar) supaya cek lokal ikut menolak scan berikutnya.
                    if(d && d.duplicate && d.uuid){
                        const dup = this.attendees.find(x => x.uuid === d.uuid);
                        if(dup){
                            if(d.type==='guru' && d.mode==='pulang'){ dup.pulangMarked = true; if(d.jam) dup.jam_pulang = d.jam; }
                            else { dup.marked = true; if(d.jam) dup.jam_masuk = d.jam; }
                        }
                    }
                    this.barcodeBusy = false;
                    if(this.showBarcodeModal && !this.barcodeScanner) this.startBarcodeScan();
                    return;
                }
                let s = this.attendees.find(x => x.uuid === d.uuid)
                    || this.attendees.find(x => x.type==='siswa' && (String(x.nis)===barcode || x.uuid===barcode));
                if(!s && d.uuid && d.type !== 'guru'){
                    s = {
                        uuid: d.uuid, type: 'siswa', nama: d.nama || barcode, nis: d.nis || barcode,
                        kelas: d.kelas || '-', id_kelas: d.id_kelas, desc: [], marked: false,
                    };
                    this.attendees.push(s);
                }
                if(s && d.type === 'guru'){
                    // Kartu ID Guru — hormati toggle Masuk/Pulang yang sedang aktif di kiosk.
                    const jamBaru = d.jam || this.nowHM();
                    if(d.mode==='pulang'){ s.pulangMarked = true; s.jam_pulang = jamBaru; }
                    else { s.marked = true; s.jam_masuk = jamBaru; }
                    s.justMarked = true;
                    this._faceLocked[d.uuid] = true;
                    const key=++this._seq;
                    this.playDing(); this.speak(d.mode==='pulang' ? 'pulang' : 'masuk', s.nama);
                    this.lastMatch={ key, nama:s.nama, type:'guru', kelas:'Guru', mode:d.mode, jam:jamBaru, terlambat:!!d.terlambat };
                    this.recent.unshift({ key, nama:s.nama.split(' ')[0], type:'guru', kelas: d.mode==='pulang' ? 'Pulang' : 'Guru', mode:d.mode, jam:jamBaru });
                    if(this.recent.length>5) this.recent.pop();
                    setTimeout(()=>{ s.justMarked=false; }, 1600);
                } else if(s){
                    s.marked = true; s.justMarked = true; s.jam_masuk = d.jam || this.nowHM();
                    this._faceLocked[d.uuid] = true;
                    const key=++this._seq;
                    this.playDing(); this.speak('masuk', s.nama);
                    this.lastMatch={ key, nama:s.nama, type:'siswa', kelas:s.kelas, mode:'masuk', jam:s.jam_masuk, terlambat:!!d.terlambat };
                    this.recent.unshift({ key, nama:s.nama.split(' ')[0], type:'siswa', kelas:s.kelas, mode:'masuk', jam:s.jam_masuk });
                    if(this.recent.length>5) this.recent.pop();
                    setTimeout(()=>{ s.justMarked=false; }, 1600);
                }
                this.barcodeInput = '';
                this.failStreak = 0;
                this.afterFaceMarkSuccess();
                const wasModal = this.showBarcodeModal;
                if(wasModal){
                    this.showBarcodeModal = false;
                    await this.stopBarcodeScan();
                    await this.resumeFaceAfterBarcode();
                }
            } catch(e){
                this.barcodeError = 'Jaringan gagal. Coba lagi.';
                if(this.showBarcodeModal && !this.barcodeScanner) this.startBarcodeScan();
            }
            this.barcodeBusy = false;
            setTimeout(()=> window.lucide && lucide.createIcons(), 40);
        }
    }
}
</script>
@endpush
@endsection
