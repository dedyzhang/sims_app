@extends('layouts.app')
@section('title', 'Narasi Data AI')

@section('content')
<div class="space-y-5" x-data="analyzeAi()">

    {{-- Header --}}
    <div>
        <h1 class="page-title flex items-center gap-2"><i data-lucide="chart-line" class="w-6 h-6 text-primary"></i> Narasi Data AI</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Sistem menghitung angkanya, AI menyusun narasinya. Cocok untuk laporan pimpinan & orang tua.</p>
    </div>

    @unless($schoolAiConfigured ?? false)
    <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-200">
        Narasi Data memakai <strong>kunci AI sekolah</strong> di server, bukan API key pribadi Asisten Guru. Minta admin mengisi <code>GEMINI_API_KEY</code> / OpenRouter di <code>.env</code>.
    </div>
    @endunless

    {{-- Tab --}}
    <div class="flex flex-wrap gap-2">
        <template x-for="t in tabs" :key="t.key">
            <button type="button" @click="tab = t.key; reset()"
                    :class="tab === t.key ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition">
                <i :data-lucide="t.icon" class="w-4 h-4"></i><span x-text="t.label"></span>
            </button>
        </template>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- ─── Pemilih Lingkup ─── --}}
        <div class="card p-5">
            {{-- Nilai --}}
            <div x-show="tab === 'nilai'" class="space-y-4">
                <div>
                    <label class="form-label">Kelas <span class="text-rose-500">*</span></label>
                    <select x-model="nilai.kelas_id" class="form-input">
                        <option value="">— pilih kelas —</option>
                        @foreach($kelasList as $k)
                            <option value="{{ $k->uuid }}">{{ $k->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Semester <span class="text-rose-500">*</span></label>
                    <select x-model="nilai.semester_id" class="form-input">
                        <option value="">— pilih semester —</option>
                        @foreach($semesterList as $s)
                            <option value="{{ $s->id }}">{{ $s->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" @click="submit('nilai')" :disabled="loading || !nilai.kelas_id || !nilai.semester_id" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> Buat Narasi AI
                </button>
            </div>

            {{-- Absensi --}}
            <div x-show="tab === 'absensi'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Kelas (kosongkan = semua kelas)</label>
                    <select x-model="absensi.kelas_id" class="form-input">
                        <option value="">Semua kelas</option>
                        @foreach($kelasList as $k)
                            <option value="{{ $k->uuid }}">{{ $k->nama_lengkap }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Dari <span class="text-rose-500">*</span></label>
                        <input type="date" x-model="absensi.dari" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Sampai <span class="text-rose-500">*</span></label>
                        <input type="date" x-model="absensi.sampai" class="form-input">
                    </div>
                </div>
                <button type="button" @click="submit('absensi')" :disabled="loading || !absensi.dari || !absensi.sampai" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> Buat Narasi AI
                </button>
            </div>

            {{-- Keuangan --}}
            <div x-show="tab === 'keuangan'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Tahun Ajaran <span class="text-rose-500">*</span></label>
                    <select x-model="keuangan.tahun_ajaran" class="form-input">
                        @foreach($tahunAjaran as $ta)
                            <option value="{{ $ta }}" @selected($ta === $taAktif)>{{ $ta }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" @click="submit('keuangan')" :disabled="loading || !keuangan.tahun_ajaran" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> Buat Narasi AI
                </button>
                <p class="text-[11px] text-slate-400">Nominal dikirim apa adanya dari sistem; AI tidak menghitung ulang uang.</p>
            </div>
        </div>

        {{-- ─── Hasil ─── --}}
        <div class="card p-5 flex flex-col min-h-[320px]">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> Narasi</h2>
                <button type="button" @click="copy()" x-show="answer" x-cloak class="flex items-center gap-1.5 text-xs text-slate-500 hover:text-primary transition">
                    <i :data-lucide="copied ? 'check' : 'copy'" class="w-4 h-4"></i><span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                </button>
            </div>

            <div x-show="loading" x-cloak class="flex-1 grid place-items-center text-slate-400">
                <div class="text-center">
                    <i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin"></i>
                    <p class="text-sm mt-2">Menghitung & menyusun narasi…</p>
                </div>
            </div>

            <div x-show="error && !loading" x-cloak class="rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800 px-4 py-3 text-sm" x-text="error"></div>

            <div x-show="!loading && !answer && !error" x-cloak class="flex-1 grid place-items-center text-slate-300 dark:text-slate-600">
                <div class="text-center">
                    <i data-lucide="chart-line" class="w-10 h-10 mx-auto opacity-40"></i>
                    <p class="text-sm mt-2">Pilih lingkup lalu buat narasi.</p>
                </div>
            </div>

            <template x-if="answer && !loading">
                <div class="flex-1 overflow-auto space-y-4">
                    <div class="ai-answer break-words text-sm text-slate-800 dark:text-slate-100" x-html="renderAiMarkdown(answer)"></div>

                    {{-- Angka sumber (transparansi) --}}
                    <details class="rounded-xl bg-slate-50 dark:bg-slate-800/60 ring-1 ring-slate-200 dark:ring-slate-700">
                        <summary class="cursor-pointer select-none px-3 py-2 text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                            <i data-lucide="table-2" class="w-3.5 h-3.5"></i> Angka sumber (dari sistem)
                        </summary>
                        <pre class="px-3 pb-3 text-[11px] text-slate-600 dark:text-slate-300 whitespace-pre-wrap break-words" x-text="source"></pre>
                    </details>
                </div>
            </template>
        </div>
    </div>
</div>

@include('partials.ai-markdown')

<script>
    function analyzeAi() {
        return {
            tab: 'nilai',
            loading: false,
            answer: '',
            source: '',
            error: '',
            copied: false,
            tabs: [
                { key: 'nilai',    label: 'Ringkasan Nilai',  icon: 'graduation-cap' },
                { key: 'absensi',  label: 'Tren Absensi',      icon: 'clipboard-check' },
                @if($keuanganModulAktif ?? true)
                { key: 'keuangan', label: 'Catatan SPP',  icon: 'wallet' },
                @endif
            ],
            schoolAiConfigured: @js((bool) ($schoolAiConfigured ?? false)),
            nilai:    { kelas_id: '', semester_id: '' },
            absensi:  { kelas_id: '', dari: '', sampai: '' },
            keuangan: { tahun_ajaran: '{{ $taAktif }}' },
            urls: {
                nilai:    '{{ route('ai.analyze.nilai') }}',
                absensi:  '{{ route('ai.analyze.absensi') }}',
                keuangan: '{{ route('ai.analyze.keuangan') }}',
            },

            reset() { this.answer = ''; this.source = ''; this.error = ''; },

            async submit(kind) {
                if (this.loading) return;
                this.loading = true;
                this.reset();
                try {
                    const r = await fetch(this.urls[kind], {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                        body: JSON.stringify(this[kind]),
                    });
                    const d = await r.json();
                    if (r.ok && d.ok) {
                        this.answer = d.answer;
                        this.source = d.source;
                    } else if (r.status === 422 && d.errors) {
                        this.error = 'Periksa isian: ' + Object.values(d.errors).flat().join(' ');
                    } else {
                        this.error = d.message || 'Terjadi kesalahan. Coba lagi.';
                    }
                } catch (_) {
                    this.error = 'Gagal terhubung. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.loading = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            copy() {
                navigator.clipboard.writeText(this.answer).then(() => {
                    this.copied = true;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                    setTimeout(() => { this.copied = false; this.$nextTick(() => window.lucide && lucide.createIcons()); }, 2000);
                });
            },
        }
    }
</script>
@endsection
