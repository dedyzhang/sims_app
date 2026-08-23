@extends('layouts.app')
@section('title', 'Pratinjau — ' . $ujian->judul)

@section('content')
<div class="max-w-4xl mx-auto space-y-4">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.edit', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Pratinjau
        </nav>
        <h1 class="page-title">Pratinjau Tampilan Siswa</h1>
    </div>

    <div class="card p-3.5 flex items-start gap-2.5 border-l-4 !border-l-amber-400 bg-amber-50/60 dark:bg-amber-950/20">
        <i data-lucide="eye" class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5"></i>
        <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
            Ini <strong>bukan</strong> ujian sungguhan — apa pun yang Anda ubah di sini <strong>tidak tersimpan</strong> dan tidak terkirim ke server. Form pratinjau di bawah otomatis terisi <strong>jawaban benar</strong> (bisa dimatikan lewat tombol "Jawaban Benar") — pilih ukuran layar utk cek bagaimana soal ini akan terlihat oleh siswa.
        </p>
    </div>

    @if($soalTampil->isEmpty())
    <div class="card p-8 text-center text-slate-400">
        <i data-lucide="file-question" class="w-10 h-10 mx-auto mb-2"></i>
        Belum ada soal utk ditampilkan — susun soal dulu.
    </div>
    @else
    <div id="ujian-pratinjau-root" x-data="ujianPratinjau({{ Js::from($soalTampil) }})" x-init="init()">
        {{-- Toggle ukuran layar: Desktop/Tablet/HP — mengubah LEBAR container pratinjau saja,
             bukan viewport browser sungguhan, jadi guru bisa bandingkan tanpa resize jendela. --}}
        <div class="flex items-center justify-center gap-2 mb-3">
            <template x-for="opt in ukuranOpsi" :key="opt.nilai">
                <button type="button" @click="ukuran = opt.nilai"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border flex items-center gap-1.5"
                        :class="ukuran === opt.nilai ? 'bg-primary text-white border-primary' : 'border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                    <i :data-lucide="opt.ikon" class="w-3.5 h-3.5"></i>
                    <span x-text="opt.label"></span>
                </button>
            </template>
        </div>

        {{-- Toggle kunci jawaban: form pratinjau LANGSUNG terisi jawaban yang benar (guru cek
             sekilas tanpa klik satu-satu) — bisa dimatikan utk lihat versi kosong spt siswa. --}}
        <div class="flex items-center justify-center mb-4">
            <button type="button" @click="toggleKunci()"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border flex items-center gap-1.5"
                    :class="tampilkanKunci ? 'bg-emerald-500 text-white border-emerald-500' : 'border-slate-200 dark:border-slate-600 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                <span x-text="tampilkanKunci ? 'Jawaban Benar Ditampilkan' : 'Tampilkan Jawaban Benar'"></span>
            </button>
        </div>

        <div class="mx-auto transition-all duration-200"
             :class="{ 'max-w-full': ukuran === 'desktop', 'max-w-[768px]': ukuran === 'tablet', 'max-w-[400px]': ukuran === 'hp' }">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-4">
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
                                           @change="jawaban[s.uuid] = o.uuid" class="text-primary focus:ring-primary flex-shrink-0">
                                    <span class="text-sm ujian-rich-body flex-1" x-html="o.teks"></span>
                                    <span x-show="tampilkanKunci && o.benar" class="text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 flex items-center gap-0.5 flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3"></i> Kunci
                                    </span>
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
                                           @change="toggleMulti(s.uuid, o.uuid)"
                                           class="w-5 h-5 rounded-md border-2 border-slate-300 dark:border-slate-600 text-primary focus:ring-2 focus:ring-primary/30 transition cursor-pointer flex-shrink-0">
                                    <span class="text-sm ujian-rich-body break-words min-w-0 flex-1" x-html="o.teks"></span>
                                    <span x-show="tampilkanKunci && o.benar" class="text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 flex items-center gap-0.5 flex-shrink-0">
                                        <i data-lucide="check" class="w-3 h-3"></i> Kunci
                                    </span>
                                </label>
                            </template>
                        </div>

                        {{-- match --}}
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
                        <div x-show="s.tipe==='essay'" x-cloak class="space-y-2">
                            <textarea rows="6" class="form-input" placeholder="Tulis jawaban Anda di sini..." x-model="jawaban[s.uuid]"></textarea>
                            <p x-show="tampilkanKunci && s.kunci_esai" class="text-xs text-emerald-600 dark:text-emerald-400 flex items-start gap-1.5">
                                <i data-lucide="key-round" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
                                <span><strong>Kunci/Rubrik:</strong> <span x-text="s.kunci_esai"></span></span>
                            </p>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <button type="button" @click="pindah(idx-1)" :disabled="idx===0" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 disabled:opacity-30">← Sebelumnya</button>
                            <button type="button" x-show="idx < soal.length-1" @click="pindah(idx+1)" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600">Berikutnya →</button>
                            <span x-show="idx === soal.length-1" class="text-xs text-slate-400 italic">— soal terakhir (pratinjau) —</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    @endif

    <div class="text-center">
        <a href="{{ route('ujian.edit', $ujian) }}" class="text-sm text-primary hover:underline">← Kembali ke Susun Soal</a>
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
function ujianPratinjau(soal) {
    return {
        soal: soal,
        jawaban: {},
        idx: 0,
        ukuran: 'hp',
        ukuranOpsi: [
            { nilai: 'desktop', label: 'Desktop', ikon: 'monitor' },
            { nilai: 'tablet', label: 'Tablet', ikon: 'tablet' },
            { nilai: 'hp', label: 'HP', ikon: 'smartphone' },
        ],
        // Default AKTIF — pratinjau langsung terisi kunci jawaban begitu dibuka, guru
        // tinggal matikan kalau mau lihat versi kosong persis spt yg dilihat siswa.
        tampilkanKunci: true,

        init() {
            this.isiJawaban();
            window.lucide && lucide.createIcons();
        },

        isiJawaban() {
            this.soal.forEach(s => {
                this.jawaban[s.uuid] = this.tampilkanKunci ? this.jawabanKunci(s) : this.jawabanKosong(s);
            });
        },

        jawabanKosong(s) {
            return s.tipe === 'mcq_complex' ? [] : (s.tipe === 'match' ? {} : (s.tipe === 'essay' ? '' : null));
        },

        /** Bentuk jawaban yg PERSIS benar, dari flag opsi.benar / pasangan kiri<->kanan_acak yg sudah sejajar-index (lihat UjianController::pratinjau()). */
        jawabanKunci(s) {
            if (s.tipe === 'mcq' || s.tipe === 'true_false') {
                const benar = (s.opsi || []).find(o => o.benar);
                return benar ? benar.uuid : null;
            }
            if (s.tipe === 'mcq_complex') {
                return (s.opsi || []).filter(o => o.benar).map(o => o.uuid);
            }
            if (s.tipe === 'match') {
                const pasangan = {};
                (s.kiri || []).forEach((kiri, i) => { pasangan[kiri] = (s.kanan_acak || [])[i]; });
                return pasangan;
            }
            return ''; // essay: tak ada jawaban otomatis, rubrik/kunci ditampilkan terpisah
        },

        toggleKunci() {
            this.tampilkanKunci = !this.tampilkanKunci;
            this.isiJawaban();
        },

        pindah(i) {
            if (i < 0 || i >= this.soal.length) return;
            this.idx = i;
            this.$nextTick(() => window.lucide && lucide.createIcons());
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
        },
    };
}
</script>
@endpush
