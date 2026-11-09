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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pengerjaan Pre-Test — {{ $pretest->judul ?? $pretest->ujian->judul }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('build/assets/app-GtwHpXZD.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/lucide@0.468.0"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .ujian-rich-body { line-height: 1.6; overflow-wrap: break-word; word-wrap: break-word; }
        .ujian-rich-body p { margin: 0 0 .5em; }
        .ujian-rich-body p:last-child { margin-bottom: 0; }
        .ujian-rich-body ul { list-style-type: disc !important; margin: 0.5em 0 0.5em 1.5em !important; }
        .ujian-rich-body ol { list-style-type: decimal !important; margin: 0.5em 0 0.5em 1.5em !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen antialiased flex flex-col justify-between"
      x-data="pretestKerjakan({
          soal: {{ Js::from($soalTampil) }},
          jawabanAwal: {{ Js::from($initJawaban) }},
          batasWaktu: {{ Js::from($peserta->batas_waktu_pada?->toIso8601String()) }},
          urlJawab: '{{ route('pretest.public.jawab', ['token' => $pretest->token, 'p' => $peserta->guest_token]) }}',
          urlSubmit: '{{ route('pretest.public.kumpul', ['token' => $pretest->token, 'p' => $peserta->guest_token]) }}',
          guestToken: '{{ $peserta->guest_token }}',
          csrfToken: '{{ csrf_token() }}'
      })"
      x-init="init()"
>
    {{-- Header Bar --}}
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200 px-4 py-3 shadow-sm">
        <div class="max-w-4xl mx-auto flex items-center justify-between gap-3">
            <div class="min-w-0">
                <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600 block">Pre-Test Mandiri</span>
                <h1 class="text-sm sm:text-base font-extrabold text-slate-900 truncate m-0">
                    {{ $pretest->judul ?? $pretest->ujian->judul }}
                </h1>
                <p class="text-xs text-slate-500 truncate mt-0.5">
                    {{ $peserta->nama }} ({{ $peserta->identitas }})
                </p>
            </div>

            <div class="flex items-center gap-3 flex-shrink-0">
                {{-- Countdown Timer --}}
                <template x-if="sisaDetik !== null">
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border font-mono font-bold text-xs"
                         :class="sisaDetik < 300 ? 'bg-rose-50 border-rose-200 text-rose-600 animate-pulse' : 'bg-slate-50 border-slate-200 text-slate-700'">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        <span x-text="formatWaktu(sisaDetik)"></span>
                    </div>
                </template>

                {{-- Status Simpan --}}
                <div class="hidden sm:flex items-center gap-1 text-[11px] text-slate-400">
                    <span class="w-2 h-2 rounded-full" :class="isSyncing ? 'bg-amber-400 animate-ping' : 'bg-emerald-500'"></span>
                    <span x-text="isSyncing ? 'Menyimpan...' : 'Tersimpan'"></span>
                </div>

                {{-- Buka Palet Nomor --}}
                <button type="button" @click="openPalet = !openPalet"
                        class="p-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 transition flex items-center gap-1 text-xs font-semibold">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Daftar Soal</span>
                </button>
            </div>
        </div>

        {{-- Dropdown Palet Nomor Soal --}}
        <div x-show="openPalet" @click.outside="openPalet = false" x-cloak
             class="max-w-4xl mx-auto mt-3 p-4 bg-white rounded-2xl border border-slate-200 shadow-xl transition space-y-3">
            <div class="flex items-center justify-between text-xs font-bold text-slate-700 pb-2 border-b border-slate-100">
                <span>Daftar Butir Soal</span>
                <span class="text-slate-400 text-[11px]" x-text="'Terjawab: ' + hitungTerjawab() + ' / ' + soal.length"></span>
            </div>
            <div class="grid grid-cols-5 sm:grid-cols-10 gap-2">
                <template x-for="(s, i) in soal" :key="'palet-'+s.uuid">
                    <button type="button" @click="pindah(i); openPalet = false"
                            class="py-2 rounded-xl text-xs font-bold border transition text-center"
                            :class="i === idx ? 'ring-2 ring-blue-600 bg-blue-50 border-blue-600 text-blue-700' : (sudahDijawab(s.uuid) ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100')">
                        <span x-text="i + 1"></span>
                    </button>
                </template>
            </div>
        </div>
    </header>

    {{-- Container Pengerjaan Soal --}}
    <main class="max-w-3xl w-full mx-auto p-4 sm:p-6 my-auto">
        <template x-for="(s, i) in soal" :key="'soal-'+s.uuid">
            <div x-show="i === idx" x-cloak class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200 space-y-6">
                {{-- Header Soal --}}
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Soal Nomor <span x-text="i + 1" class="text-slate-900 text-sm"></span> dari <span x-text="soal.length"></span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700">
                        Bobot: <span x-text="s.poin"></span> Poin
                    </span>
                </div>

                {{-- Teks Soal --}}
                <div class="text-base font-medium text-slate-900 ujian-rich-body leading-relaxed" x-html="s.teks_soal"></div>

                {{-- Form Pilihan Ganda Biasa (MCQ / True_False) --}}
                <div x-show="s.tipe === 'mcq' || s.tipe === 'true_false'" x-cloak class="space-y-2.5 pt-2">
                    <template x-for="(o, oi) in (s.opsi || [])" :key="o.uuid">
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                               :class="jawaban[s.uuid] === o.uuid ? 'border-blue-600 bg-blue-50/70 shadow-sm' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="radio" :name="'opsi-'+s.uuid" :checked="jawaban[s.uuid] === o.uuid"
                                   @change="jawaban[s.uuid] = o.uuid; tandaiBerubah(s.uuid)"
                                   class="w-4 h-4 text-blue-600 focus:ring-blue-500 border-slate-300">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0"
                                  :class="jawaban[s.uuid] === o.uuid ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
                                  x-text="String.fromCharCode(65 + oi)"></span>
                            <span class="text-sm ujian-rich-body flex-1 text-slate-800" x-html="o.teks"></span>
                        </label>
                    </template>
                </div>

                {{-- Form Pilihan Ganda Kompleks (MCQ Complex) --}}
                <div x-show="s.tipe === 'mcq_complex'" x-cloak class="space-y-2.5 pt-2">
                    <p class="text-xs text-amber-600 font-medium">Pilih satu atau lebih jawaban yang benar:</p>
                    <template x-for="(o, oi) in (s.opsi || [])" :key="o.uuid">
                        <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                               :class="(jawaban[s.uuid] || []).includes(o.uuid) ? 'border-blue-600 bg-blue-50/70 shadow-sm' : 'border-slate-200 hover:bg-slate-50'">
                            <input type="checkbox" :checked="(jawaban[s.uuid] || []).includes(o.uuid)"
                                   @change="toggleMulti(s.uuid, o.uuid, s, $event)"
                                   class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0"
                                  :class="(jawaban[s.uuid] || []).includes(o.uuid) ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'"
                                  x-text="String.fromCharCode(65 + oi)"></span>
                            <span class="text-sm ujian-rich-body flex-1 text-slate-800" x-html="o.teks"></span>
                        </label>
                    </template>
                </div>

                {{-- Form Menjodohkan (Match) --}}
                <div x-show="s.tipe === 'match'" x-cloak class="space-y-3 pt-2">
                    <p class="text-xs text-slate-500">Pasangkan pernyataan di kolom kiri dengan pilihan yang tepat di kolom kanan:</p>
                    <template x-for="(kiri, ki) in (s.kiri || [])" :key="'m-'+s.uuid+'-'+ki">
                        <div class="p-3.5 rounded-2xl border border-slate-200 space-y-2 bg-slate-50/50">
                            <div class="text-sm font-semibold text-slate-800 ujian-rich-body" x-html="kiri"></div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="(kanan, kj) in (s.kanan_acak || [])" :key="'m-'+s.uuid+'-'+ki+'-'+kj">
                                    <button type="button"
                                            @click="setPasangan(s.uuid, kiri, (jawaban[s.uuid] || {})[kiri] === kanan ? '' : kanan)"
                                            class="px-3 py-1.5 rounded-xl text-xs border font-medium transition text-left"
                                            :class="(jawaban[s.uuid] || {})[kiri] === kanan ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100'">
                                        <span class="font-bold mr-1" x-text="String.fromCharCode(65 + kj) + '.'"></span>
                                        <span x-html="kanan"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Form Esai --}}
                <div x-show="s.tipe === 'essay'" x-cloak class="space-y-2 pt-2">
                    <label class="block text-xs font-bold text-slate-700">Tulis Jawaban Anda:</label>
                    <textarea rows="5" x-model="jawaban[s.uuid]" @input="tandaiBerubah(s.uuid)"
                              placeholder="Tuliskan uraian jawaban di sini..."
                              class="w-full p-4 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition shadow-sm"></textarea>
                </div>

                {{-- Tombol Navigasi Bawah --}}
                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <button type="button" @click="pindah(idx - 1)" :disabled="idx === 0"
                            class="px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-200 hover:bg-slate-50 disabled:opacity-30 disabled:pointer-events-none transition flex items-center gap-1.5">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Sebelumnya
                    </button>

                    <button type="button" x-show="idx < soal.length - 1" @click="pindah(idx + 1)"
                            class="btn-primary px-5 py-2.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white transition flex items-center gap-1.5 shadow-sm">
                        Selanjutnya <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </button>

                    <button type="button" x-show="idx === soal.length - 1" @click="openKonfirmasi = true"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition flex items-center gap-1.5 shadow-lg shadow-emerald-600/20">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i> Kumpulkan Jawaban
                    </button>
                </div>
            </div>
        </template>
    </main>

    {{-- Footer --}}
    <footer class="text-center text-xs text-slate-400 py-4 border-t border-slate-200/60 bg-white">
        Pre-Test Mandiri &copy; {{ date('Y') }} SIMS Edutive
    </footer>

    {{-- Modal Konfirmasi Kumpulkan --}}
    <div x-show="openKonfirmasi" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-sm w-full space-y-4 shadow-2xl text-center"
             @click.outside="openKonfirmasi = false">
            <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto shadow-inner">
                <i data-lucide="check-circle-2" class="w-7 h-7"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-900 m-0">Kumpulkan Jawaban?</h3>
            <p class="text-xs text-slate-500 leading-relaxed m-0">
                Anda telah menjawab <strong x-text="hitungTerjawab()"></strong> dari <strong x-text="soal.length"></strong> soal. Apakah Anda yakin ingin mengakhiri sesi pre-test ini?
            </p>
            <div class="flex items-center justify-center gap-2 pt-2">
                <button type="button" @click="openKonfirmasi = false" class="px-4 py-2.5 rounded-xl text-xs font-bold border border-slate-200 hover:bg-slate-50 text-slate-600">
                    Batal
                </button>
                <button type="button" @click="kumpulkan()" :disabled="mengumpulkan"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-600/20 flex items-center gap-1.5">
                    <span x-text="mengumpulkan ? 'Menyimpan...' : 'Ya, Kumpulkan'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Script Alpine.js --}}
    <script>
        function pretestKerjakan(cfg) {
            return {
                soal: cfg.soal || [],
                jawaban: cfg.jawabanAwal || {},
                idx: 0,
                openPalet: false,
                openKonfirmasi: false,
                isSyncing: false,
                mengumpulkan: false,
                sisaDetik: null,
                _timerInterval: null,
                _syncTimeout: null,
                _dirtyQueue: {},

                init() {
                    // Inisialisasi hitung mundur
                    if (cfg.batasWaktu) {
                        const target = new Date(cfg.batasWaktu).getTime();
                        const updateTimer = () => {
                            const now = new Date().getTime();
                            const diff = Math.max(0, Math.floor((target - now) / 1000));
                            this.sisaDetik = diff;
                            if (diff <= 0) {
                                clearInterval(this._timerInterval);
                                this.kumpulkan();
                            }
                        };
                        updateTimer();
                        this._timerInterval = setInterval(updateTimer, 1000);
                    }

                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                pindah(newIdx) {
                    if (newIdx >= 0 && newIdx < this.soal.length) {
                        this.idx = newIdx;
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    }
                },

                sudahDijawab(uuid) {
                    const j = this.jawaban[uuid];
                    if (j === null || j === undefined || j === '') return false;
                    if (Array.isArray(j) && j.length === 0) return false;
                    if (typeof j === 'object' && Object.keys(j).length === 0) return false;
                    return true;
                },

                hitungTerjawab() {
                    return this.soal.filter(s => this.sudahDijawab(s.uuid)).length;
                },

                formatWaktu(detik) {
                    if (detik === null) return '';
                    const m = Math.floor(detik / 60);
                    const s = detik % 60;
                    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                },

                tandaiBerubah(uuid) {
                    this._dirtyQueue[uuid] = {
                        id_soal: uuid,
                        id_opsi_dipilih: typeof this.jawaban[uuid] === 'string' ? this.jawaban[uuid] : null,
                        opsi_dipilih_multi: Array.isArray(this.jawaban[uuid]) ? this.jawaban[uuid] : null,
                        jawaban_pasangan: typeof this.jawaban[uuid] === 'object' && !Array.isArray(this.jawaban[uuid]) ? this.jawaban[uuid] : null,
                        jawaban_esai: typeof this.jawaban[uuid] === 'string' ? this.jawaban[uuid] : null,
                    };

                    clearTimeout(this._syncTimeout);
                    this._syncTimeout = setTimeout(() => this.syncDirtyAnswers(), 600);
                },

                toggleMulti(soalUuid, opsiUuid, soalItem, event) {
                    let list = this.jawaban[soalUuid] || [];
                    if (!Array.isArray(list)) list = [];

                    if (list.includes(opsiUuid)) {
                        list = list.filter(id => id !== opsiUuid);
                    } else {
                        // Anti-spekulasi
                        const totalOpsi = (soalItem.opsi || []).length;
                        if (totalOpsi > 1 && list.length + 1 >= totalOpsi) {
                            if (event && event.target) event.target.checked = false;
                            alert('Perhatian: Anda tidak dapat memilih seluruh opsi yang ada pada soal ini.');
                            return;
                        }
                        list.push(opsiUuid);
                    }

                    this.jawaban[soalUuid] = list;
                    this.tandaiBerubah(soalUuid);
                },

                setPasangan(soalUuid, kiri, kanan) {
                    let map = this.jawaban[soalUuid] || {};
                    if (typeof map !== 'object' || Array.isArray(map)) map = {};

                    if (kanan === '') {
                        delete map[kiri];
                    } else {
                        map[kiri] = kanan;
                    }

                    this.jawaban[soalUuid] = { ...map };
                    this.tandaiBerubah(soalUuid);
                },

                async syncDirtyAnswers() {
                    const keys = Object.keys(this._dirtyQueue);
                    if (keys.length === 0) return;

                    this.isSyncing = true;
                    const items = keys.map(k => this._dirtyQueue[k]);

                    try {
                        const res = await fetch(cfg.urlJawab, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken
                            },
                            body: JSON.stringify({ jawaban: items })
                        });

                        if (res.ok) {
                            keys.forEach(k => delete this._dirtyQueue[k]);
                        }
                    } catch (e) {}

                    this.isSyncing = false;
                },

                async kumpulkan() {
                    if (this.mengumpulkan) return;
                    this.mengumpulkan = true;

                    // Flush sisa antrean jawaban jika ada
                    await this.syncDirtyAnswers();

                    try {
                        const res = await fetch(cfg.urlSubmit, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken
                            }
                        });

                        if (res.ok) {
                            const data = await res.json().catch(() => ({}));
                            if (data.redirect) {
                                window.location.href = data.redirect;
                                return;
                            }
                        }
                    } catch (e) {}

                    window.location.reload();
                }
            };
        }
    </script>
</body>
</html>
