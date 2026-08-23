@extends('layouts.app')

@section('title', 'Pemeriksa Kualitas Soal')

@section('content')
<div class="mx-auto w-full max-w-7xl space-y-5"
     x-data="questionQualityChecker({
        endpoint: @js(route('classroom.arena.quality-checker.check', $classroom)),
        gradeLevel: @js($defaultGradeLevel),
        subject: @js($defaultSubject),
     })">
    <header class="flex flex-col gap-3 border-b border-slate-200 pb-4 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <a href="{{ route('classroom.arena.index', $classroom) }}" class="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-primary">
                <i data-lucide="chevron-left" class="h-4 w-4"></i>
                Arena Belajar
            </a>
            <h1 class="m-0 text-2xl font-bold text-slate-800 dark:text-slate-100">Pemeriksa Kualitas Soal</h1>
            <p class="m-0 mt-1 text-sm text-slate-500">{{ $classroom->title }}</p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold {{ $aiAvailable ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300' }}">
            <i data-lucide="{{ $aiAvailable ? 'sparkles' : 'shield-check' }}" class="h-4 w-4"></i>
            {{ $aiAvailable ? 'AI tersedia' : 'Mode demo rule-based' }}
        </span>
    </header>

    <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.9fr)]">
        <form @submit.prevent="submit" class="min-w-0 space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="quality-grade" class="form-label">Kelas / jenjang</label>
                    <input id="quality-grade" type="text" x-model="form.grade_level" class="form-input" placeholder="Contoh: Kelas 7 SMP">
                </div>
                <div>
                    <label for="quality-subject" class="form-label">Mata pelajaran</label>
                    <input id="quality-subject" type="text" x-model="form.subject" class="form-input" placeholder="Contoh: Matematika">
                </div>
            </div>

            <div>
                <label for="quality-objective" class="form-label">Materi atau tujuan pembelajaran</label>
                <textarea id="quality-objective" x-model="form.learning_objective" rows="3" class="form-input resize-y" placeholder="Contoh: Siswa mampu membandingkan pecahan dengan penyebut berbeda."></textarea>
            </div>

            <div>
                <label for="quality-type" class="form-label">Tipe soal</label>
                <select id="quality-type" x-model="form.question_type" class="form-input">
                    <option value="mcq">Pilihan Ganda</option>
                    <option value="mcq_complex">Pilihan Ganda Kompleks</option>
                    <option value="true_false">Benar / Salah</option>
                    <option value="short_answer">Isian Singkat</option>
                    <option value="essay">Esai</option>
                    <option value="match">Menjodohkan</option>
                </select>
            </div>

            <div>
                <label for="quality-question" class="form-label">Teks soal</label>
                <textarea id="quality-question" x-model="form.question_text" rows="6" class="form-input resize-y" placeholder="Tuliskan satu soal yang ingin diperiksa."></textarea>
                <p class="m-0 mt-1 text-right text-[11px] text-slate-400" x-text="form.question_text.length + ' / 5000 karakter'"></p>
            </div>

            <div x-show="usesOptions" x-cloak>
                <label for="quality-options" class="form-label">Opsi jawaban</label>
                <textarea id="quality-options" x-model="form.options_text" rows="5" class="form-input resize-y" placeholder="Satu opsi per baris&#10;3/4&#10;4/5&#10;5/6&#10;6/7"></textarea>
            </div>

            <div>
                <label for="quality-key" class="form-label">Kunci jawaban <span class="font-normal text-slate-400">(opsional)</span></label>
                <input id="quality-key" type="text" x-model="form.answer_key" class="form-input" placeholder="Contoh: B, Benar, atau teks jawaban">
            </div>

            <p class="m-0 flex items-start gap-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                <i data-lucide="shield-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
                Jangan masukkan nama, NIS, atau data pribadi siswa. Isi form dapat dikirim ke penyedia AI saat layanan tersedia.
            </p>

            <div x-show="error" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-300" role="alert" x-text="error"></div>

            <button type="submit" :disabled="loading" class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-bold disabled:cursor-wait disabled:opacity-50">
                <i :data-lucide="loading ? 'loader-circle' : 'scan-search'" class="h-4 w-4" :class="loading ? 'animate-spin' : ''"></i>
                <span x-text="loading ? 'Memeriksa soal...' : 'Cek Kualitas Soal'"></span>
            </button>
        </form>

        <section class="min-w-0 border-l-0 border-slate-200 dark:border-slate-700 lg:border-l lg:pl-6" aria-live="polite">
            <div x-show="!result && !loading" class="flex min-h-72 flex-col items-center justify-center text-center text-slate-400" x-cloak>
                <i data-lucide="clipboard-check" class="mb-3 h-10 w-10"></i>
                <p class="m-0 text-sm font-semibold">Hasil pemeriksaan akan tampil di sini.</p>
            </div>

            <div x-show="loading" class="flex min-h-72 items-center justify-center" x-cloak>
                <i data-lucide="loader-circle" class="h-7 w-7 animate-spin text-primary"></i>
            </div>

            <div x-show="result && !loading" x-cloak class="space-y-5 break-words [overflow-wrap:anywhere]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="m-0 text-xs font-semibold uppercase text-slate-400">Skor kualitas</p>
                        <p class="m-0 mt-1 text-4xl font-bold text-slate-800 dark:text-slate-100"><span x-text="result?.score"></span><span class="text-base text-slate-400">/100</span></p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="rounded-lg px-2.5 py-1 text-xs font-bold" :class="statusClass" x-text="statusLabel"></span>
                        <span class="text-[11px] font-semibold text-slate-400" x-text="result?.source === 'ai' ? 'Analisis AI' : 'Analisis Dasar'"></span>
                    </div>
                </div>

                <div class="h-2 overflow-hidden rounded bg-slate-200 dark:bg-slate-700">
                    <div class="h-full rounded transition-all duration-500" :class="scoreBarClass" :style="'width:' + Math.max(0, Math.min(100, result?.score || 0)) + '%' "></div>
                </div>

                <div class="grid grid-cols-2 gap-3 border-y border-slate-200 py-3 dark:border-slate-700">
                    <div>
                        <p class="m-0 text-[11px] text-slate-400">Level kognitif</p>
                        <p class="m-0 mt-1 text-sm font-bold text-slate-700 dark:text-slate-200"><span x-text="result?.bloom_level?.level"></span><span class="font-normal text-slate-400"> · </span><span x-text="result?.bloom_level?.label"></span></p>
                        <p class="m-0 mt-1 text-[11px] leading-4 text-slate-400" x-text="result?.bloom_level?.reason"></p>
                    </div>
                    <div>
                        <p class="m-0 text-[11px] text-slate-400">Tingkat kesulitan</p>
                        <p class="m-0 mt-1 text-sm font-bold capitalize text-slate-700 dark:text-slate-200" x-text="result?.difficulty?.level"></p>
                        <p class="m-0 mt-1 text-[11px] leading-4 text-slate-400" x-text="result?.difficulty?.reason"></p>
                    </div>
                </div>

                <div>
                    <h2 class="m-0 text-sm font-bold text-slate-700 dark:text-slate-200">Kriteria pemeriksaan</h2>
                    <div class="mt-2 space-y-2">
                        <template x-for="(criterion, criterionKey) in (result?.criteria || {})" :key="'criterion-' + criterionKey">
                            <div class="rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200" x-text="criterionKey"></span>
                                    <span class="rounded px-2 py-1 text-[10px] font-bold uppercase text-slate-500 dark:text-slate-300" x-text="(criterion.score || 0) + '/100'"></span>
                                </div>
                                <p x-show="criterion.note" x-cloak class="m-0 mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="criterion.note"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <h2 class="m-0 text-sm font-bold text-slate-700 dark:text-slate-200">Masalah yang ditemukan</h2>
                    <ul class="mt-2 space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                        <template x-for="(issue, index) in (result?.issues || [])" :key="'issue-' + index">
                            <li x-text="issue.message || issue"></li>
                        </template>
                    </ul>
                </div>

                <div>
                    <h2 class="m-0 text-sm font-bold text-slate-700 dark:text-slate-200">Saran perbaikan</h2>
                    <ul class="mt-2 space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                        <template x-for="(suggestion, index) in (result?.suggestions || [])" :key="'suggestion-' + index">
                            <li x-text="suggestion"></li>
                        </template>
                    </ul>
                </div>

                <div class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-800 dark:bg-emerald-950/20">
                    <h2 class="m-0 text-sm font-bold text-emerald-800 dark:text-emerald-200">Versi soal yang diperbaiki</h2>
                    <p class="m-0 mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700 dark:text-slate-200" x-text="result?.improved_question"></p>
                    <ol x-show="(result?.improved_options || []).length" class="mb-0 mt-3 list-[upper-alpha] space-y-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
                        <template x-for="(option, index) in (result?.improved_options || [])" :key="'option-' + index">
                            <li x-text="option"></li>
                        </template>
                    </ol>
                </div>

                <div class="rounded-lg border border-indigo-200 bg-indigo-50/70 p-3 text-sm text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/20 dark:text-indigo-200">
                    <strong>Rekomendasi jawaban</strong>
                    <p class="m-0 mt-1 leading-5" x-text="result?.recommended_answer"></p>
                </div>

                <div class="rounded-lg border border-sky-200 bg-sky-50/70 p-3 text-sm text-sky-800 dark:border-sky-800 dark:bg-sky-950/20 dark:text-sky-200">
                    <div class="flex items-start gap-2">
                        <i data-lucide="notebook-pen" class="mt-0.5 h-4 w-4 shrink-0"></i>
                        <div>
                            <strong>Catatan untuk guru</strong>
                            <p class="m-0 mt-1 leading-5" x-text="result?.teacher_note"></p>
                        </div>
                    </div>
                </div>

                <p class="m-0 text-[11px] leading-5 text-slate-400" x-text="result?.fallback_reason || result?.notice"></p>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function questionQualityChecker(config) {
    return {
        form: {
            grade_level: config.gradeLevel || '',
            subject: config.subject || '',
            learning_objective: '',
            question_type: 'mcq',
            question_text: '',
            options_text: '',
            answer_key: '',
        },
        result: null,
        loading: false,
        error: '',
        get usesOptions() {
            return ['mcq', 'mcq_complex', 'match'].includes(this.form.question_type);
        },
        get statusLabel() {
            return ({ layak: 'Layak', perlu_revisi: 'Perlu revisi', tidak_layak: 'Tidak layak' })[this.result?.status] || '-';
        },
        get statusClass() {
            return ({
                layak: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200',
                perlu_revisi: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-200',
                tidak_layak: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-200',
            })[this.result?.status] || 'bg-slate-100 text-slate-600';
        },
        get scoreBarClass() {
            const score = Number(this.result?.score || 0);
            return score >= 80 ? 'bg-emerald-500' : (score >= 60 ? 'bg-amber-500' : 'bg-rose-500');
        },
        async submit() {
            this.loading = true;
            this.error = '';
            this.result = null;
            const options = this.form.options_text.split(/\r?\n/).map(item => item.trim()).filter(Boolean);
            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ ...this.form, options: this.usesOptions ? options : [] }),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) {
                    const firstError = Object.values(payload.errors || {}).flat()[0];
                    this.error = firstError || payload.message || 'Pemeriksaan soal gagal. Coba lagi.';
                    return;
                }
                this.result = payload.data;
            } catch (_) {
                this.error = 'Tidak dapat terhubung ke server.';
            } finally {
                this.loading = false;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            }
        },
    };
}
</script>
@endpush
