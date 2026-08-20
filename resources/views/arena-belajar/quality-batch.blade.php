@extends('layouts.app')

@section('title', 'Pemeriksa Kualitas Soal')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
<style>
.quality-page { --quality-navy:#12345b; --quality-teal:#00a99d; }
.quality-page, .quality-page button, .quality-page a, .quality-page input, .quality-page textarea { font-family:'Fredoka','Plus Jakarta Sans',system-ui,sans-serif; }
.quality-card { border:3px solid rgba(18,52,91,.08); border-radius:1.25rem; background:rgba(255,255,255,.96); box-shadow:0 7px 0 rgba(18,52,91,.1); }
.dark .quality-card { border-color:#334155; background:rgba(15,23,42,.96); box-shadow:0 7px 0 rgba(0,0,0,.32); }
.quality-label { display:block; margin-bottom:.4rem; color:#3d5678; font-size:.78rem; font-weight:800; }
.dark .quality-label { color:#94a3b8; }
.quality-input { width:100%; min-height:2.8rem; border:2px solid rgba(18,52,91,.12); border-radius:1rem; background:#fff; padding:.7rem .9rem; color:#1e293b; font-size:.9rem; font-weight:600; box-shadow:0 3px 0 rgba(18,52,91,.06); }
.quality-input:focus { outline:0; border-color:var(--quality-teal); box-shadow:0 3px 0 rgba(0,169,157,.25),0 0 0 3px rgba(0,169,157,.12); }
.dark .quality-input { border-color:#334155; background:#0f172a; color:#f1f5f9; box-shadow:0 3px 0 rgba(0,0,0,.25); }
.quality-question { border:2px solid rgba(18,52,91,.08); border-radius:1rem; background:#f8fafc; padding:.8rem; }
.dark .quality-question { border-color:#334155; background:#111c30; }
.quality-result { border:2px solid rgba(18,52,91,.1); border-radius:1.1rem; background:#fff; padding:1rem; }
.dark .quality-result { border-color:#334155; background:#0f172a; }
.quality-save-card { border:2px solid rgba(18,52,91,.08); border-radius:.95rem; background:rgba(255,255,255,.96); padding:.75rem; box-shadow:0 3px 0 rgba(18,52,91,.08); }
.dark .quality-save-card { border-color:#334155; background:rgba(15,23,42,.96); box-shadow:0 3px 0 rgba(0,0,0,.28); }
.quality-save-card .quality-input { min-height:0; height:auto; overflow:hidden; resize:none; border-radius:.75rem; padding:.55rem .7rem; font-size:.82rem; line-height:1.35; }
</style>
@endpush

@php
    $qualityQuestions = $quiz->questions->map(fn ($q) => [
        'type' => $q->type,
        'question_text' => $q->question_text,
        'points' => $q->points,
        'time_limit_seconds' => $q->time_limit_seconds,
        'explanation' => $q->explanation,
        'options' => $q->options->map(fn ($o) => [
            'option_text' => $o->option_text,
            'is_correct' => (bool) $o->is_correct,
        ])->values()->all(),
        'meta' => [
            'answers' => $q->meta['answers'] ?? [''],
            'pairs' => $q->meta['pairs'] ?? [['left' => '', 'right' => ''], ['left' => '', 'right' => '']],
        ],
    ])->values()->all();
@endphp

@section('content')
<div class="quality-page arena-stage mx-auto w-full max-w-[1600px] space-y-5 px-3 pb-8 sm:px-5 lg:px-7"
     x-data="qualityBatchPage(@js($qualityQuestions), @js([
        'batchUrl' => route('classroom.arena.quality-checker.batch', $classroom),
        'gradeLevel' => $defaultGradeLevel,
        'subject' => $defaultSubject,
        'objective' => $quiz->learning_objective ?: ($quiz->instructions ?: $quiz->title),
     ]))"
     x-cloak>
    <header class="quality-card overflow-hidden bg-gradient-to-br from-[#dffaf5] via-white to-[#eaf3ff] p-5 sm:p-7 dark:from-[#12333a] dark:via-[#0f172a] dark:to-[#15253a]">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="mb-3 inline-flex items-center gap-1 text-xs font-black text-slate-500 hover:text-teal-600 dark:text-slate-400">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i> Kembali ke Arena
                </a>
                <p class="m-0 text-[11px] font-black uppercase tracking-[.14em] text-teal-600 dark:text-teal-300">Arena Belajar · Workspace pemeriksaan</p>
                <h1 class="m-0 mt-1 text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100 sm:text-3xl">Pemeriksa kualitas soal</h1>
                <p class="m-0 mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400"><span>{{ $quiz->title }}</span> · <span x-text="questions.length"></span> soal diperiksa kolektif</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1 rounded-lg bg-teal-100 px-3 py-2 text-xs font-black text-teal-700 dark:bg-teal-950/40 dark:text-teal-300"><i data-lucide="layers-3" class="h-4 w-4"></i> Batch checker</span>
                <button type="submit" form="quality-save-form" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-black text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-700"><i data-lucide="save" class="h-4 w-4"></i> Simpan ke Arena</button>
            </div>
        </div>
    </header>

    <form id="quality-save-form" method="POST" action="{{ route('classroom.arena.update', [$classroom, $quiz]) }}" @submit="prepareSave">
        @csrf
        <input type="hidden" name="title" value="{{ $quiz->title }}">
        <input type="hidden" name="instructions" value="{{ $quiz->instructions }}">
        <input type="hidden" name="scoring_mode" value="{{ $quiz->scoring_mode }}">
        <input type="hidden" name="play_mode" value="{{ $quiz->play_mode }}">
        <input type="hidden" name="template" value="{{ $quiz->template }}">
        <input type="hidden" name="max_score" value="{{ $quiz->max_score }}">
        <input type="hidden" name="hide_scores" value="{{ $quiz->hide_scores ? 1 : 0 }}">
        <input type="hidden" name="show_leaderboard" value="{{ $quiz->show_leaderboard ? 1 : 0 }}">
        <input type="hidden" name="instant_feedback" value="{{ $quiz->instant_feedback ? 1 : 0 }}">
        <input type="hidden" name="opens_at" value="{{ optional($quiz->opens_at)->format('Y-m-d H:i:s') }}">
        <input type="hidden" name="due_at" value="{{ optional($quiz->due_at)->format('Y-m-d H:i:s') }}">
        <input type="hidden" name="assign_self" value="1">

        <div class="grid min-w-0 gap-5 xl:grid-cols-[390px_minmax(0,1fr)]">
            <aside class="quality-card min-w-0 p-4 sm:p-5 xl:sticky xl:top-5 xl:self-start">
                <div class="rounded-xl border border-teal-200 bg-teal-50/70 p-3 text-xs text-teal-800 dark:border-teal-800 dark:bg-teal-950/25 dark:text-teal-200">
                    <strong><span x-text="questions.length"></span> soal siap diperiksa</strong>
                    <p class="m-0 mt-1">Data soal berasal dari pratinjau kuis ini. Hasil tidak keluar dari workspace Arena.</p>
                </div>
                <div class="mt-4 space-y-3">
                    <div><label class="quality-label">Kelas / jenjang</label><input type="text" x-model="quality.form.grade_level" class="quality-input"></div>
                    <div><label class="quality-label">Mata pelajaran</label><input type="text" x-model="quality.form.subject" class="quality-input"></div>
                    <div><label class="quality-label">Materi / tujuan pembelajaran</label><textarea name="learning_objective" x-model="quality.form.learning_objective" rows="4" class="quality-input resize-y"></textarea><p class="m-0 mt-1 text-[11px] font-semibold text-slate-400">Diambil dari pengaturan sebelum generate soal. Bisa disesuaikan sebelum cek.</p></div>
                </div>
                <div class="mt-4 max-h-[38vh] space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-2 dark:border-slate-700">
                    <template x-for="(q, qi) in questions" :key="'preview-' + qi">
                        <div class="quality-question">
                            <div class="flex items-center gap-2 text-[11px] font-black uppercase text-slate-400"><span class="grid h-5 w-5 place-items-center rounded bg-teal-600 text-white" x-text="qi + 1"></span><span x-text="qualityTypeLabel(q.type)"></span></div>
                            <p class="m-0 mt-1 line-clamp-3 text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="q.question_text"></p>
                        </div>
                    </template>
                </div>
                <button type="button" @click="runBatch" :disabled="quality.loading || !questions.length" class="mt-4 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-teal-600/20 transition hover:-translate-y-0.5 hover:bg-teal-700 disabled:cursor-wait disabled:opacity-60">
                    <i :data-lucide="quality.loading ? 'loader-circle' : 'scan-search'" class="h-5 w-5" :class="quality.loading && 'animate-spin'"></i><span x-text="quality.loading ? 'Memeriksa semua soal…' : 'Jalankan cek kolektif'"></span>
                </button>
                <div x-show="quality.error" x-cloak class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700" x-text="quality.error"></div>
                <div x-show="quality.message" x-cloak class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700" x-text="quality.message"></div>
            </aside>

            <main class="quality-card min-w-0 p-4 sm:p-5 lg:p-6" aria-live="polite">
                <div x-show="!quality.results.length && !quality.loading" class="flex min-h-[480px] flex-col items-center justify-center text-center text-slate-400" x-cloak>
                    <i data-lucide="clipboard-check" class="mb-3 h-12 w-12"></i><p class="m-0 text-sm font-bold">Hasil pemeriksaan kolektif akan tampil di sini.</p><p class="m-0 mt-1 text-xs">Jalankan cek kolektif dari panel kiri.</p>
                </div>
                <div x-show="quality.loading" class="flex min-h-[480px] flex-col items-center justify-center text-center text-slate-500" x-cloak>
                    <i data-lucide="loader-circle" class="mb-3 h-9 w-9 animate-spin text-teal-600"></i><p class="m-0 text-sm font-bold">Memeriksa <span x-text="questions.length"></span> soal…</p><p class="m-0 mt-1 text-xs">Hasil akan tampil setelah seluruh batch selesai.</p>
                </div>
                <div x-show="quality.results.length && !quality.loading" x-cloak class="space-y-4">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl bg-slate-100 p-3 dark:bg-slate-800"><p class="m-0 text-[10px] font-black uppercase text-slate-400">Rata-rata</p><p class="m-0 mt-1 text-2xl font-black text-slate-800 dark:text-slate-100"><span x-text="quality.summary.average_score"></span>/100</p></div>
                        <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950/30"><p class="m-0 text-[10px] font-black uppercase text-emerald-600">Layak</p><p class="m-0 mt-1 text-2xl font-black text-emerald-700 dark:text-emerald-300" x-text="quality.summary.layak"></p></div>
                        <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-950/30"><p class="m-0 text-[10px] font-black uppercase text-amber-600">Revisi</p><p class="m-0 mt-1 text-2xl font-black text-amber-700 dark:text-amber-300" x-text="quality.summary.perlu_revisi"></p></div>
                        <div class="rounded-xl bg-rose-50 p-3 dark:bg-rose-950/30"><p class="m-0 text-[10px] font-black uppercase text-rose-600">Tidak layak</p><p class="m-0 mt-1 text-2xl font-black text-rose-700 dark:text-rose-300" x-text="quality.summary.tidak_layak"></p></div>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="m-0 text-xs font-semibold text-slate-500 dark:text-slate-400" x-show="quality.results.length && !qualityCurrent()" x-cloak>Pratinjau sudah berubah. Jalankan cek kolektif ulang sebelum menerbitkan ke siswa.</p>
                        <button type="button" @click="applyAll" x-show="quality.results.some(item => hasImprovement(item))" class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-emerald-700 sm:w-auto"><i data-lucide="check-check" class="h-5 w-5"></i> Terapkan semua versi perbaikan</button>
                    </div>
                    <div class="space-y-3">
                        <template x-for="item in quality.results" :key="'result-' + item.index">
                            <article class="quality-result">
                                <div class="flex items-start justify-between gap-3"><div class="min-w-0 flex-1"><p class="m-0 text-[11px] font-black uppercase text-slate-400">Soal <span x-text="Number(item.index) + 1"></span></p><p class="m-0 mt-1 text-sm font-bold text-slate-800 dark:text-slate-100" x-text="item.question_text"></p></div><div class="shrink-0 text-right"><strong class="text-2xl text-slate-800 dark:text-slate-100" x-text="item.data?.score"></strong><span class="text-xs text-slate-400">/100</span><span class="mt-1 block rounded px-2 py-1 text-[10px] font-bold" :class="qualityStatusClass(item.data?.status)" x-text="qualityStatusLabel(item.data?.status)"></span><span class="mt-1 block text-[10px] font-bold text-slate-400" x-text="qualitySourceLabel(item.data?.source)"></span></div></div>
                                <div class="mt-3 grid gap-4 text-xs md:grid-cols-2"><div><strong class="text-slate-700 dark:text-slate-200">Temuan</strong><ul class="mt-1 list-disc space-y-1 pl-4 text-slate-600 dark:text-slate-300"><template x-for="(issue, i) in (item.data?.issues || [])" :key="'issue-' + item.index + '-' + i"><li x-text="issue.message || issue"></li></template></ul></div><div><strong class="text-slate-700 dark:text-slate-200">Saran</strong><ul class="mt-1 list-disc space-y-1 pl-4 text-slate-600 dark:text-slate-300"><template x-for="(suggestion, i) in (item.data?.suggestions || [])" :key="'suggestion-' + item.index + '-' + i"><li x-text="suggestion"></li></template></ul></div></div>
                                <div x-show="Object.keys(item.data?.criteria || {}).length" x-cloak class="mt-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700"><strong class="text-xs text-slate-700 dark:text-slate-200">Kriteria</strong><div class="mt-2 grid gap-2 sm:grid-cols-2"><template x-for="(criterion, criterionKey) in (item.data?.criteria || {})" :key="'criterion-' + item.index + '-' + criterionKey"><div class="rounded bg-slate-50 px-2 py-1.5 dark:bg-slate-800"><div class="flex items-center justify-between gap-2"><span class="text-[11px] font-bold text-slate-600 dark:text-slate-300" x-text="criterionKey"></span><span class="text-[10px] font-bold text-slate-400" x-text="(criterion.score || 0) + '/100'"></span></div><p x-show="criterion.note" x-cloak class="m-0 mt-1 text-[10px] leading-4 text-slate-400" x-text="criterion.note"></p></div></template></div></div>
                                <p x-show="item.data?.recommended_answer" x-cloak class="mt-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[11px] font-semibold leading-5 text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-200"><strong>Rekomendasi jawaban:</strong> <span x-text="item.data?.recommended_answer"></span></p>
                                <p x-show="item.data?.fallback_reason" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] font-semibold leading-5 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200" x-text="item.data?.fallback_reason"></p>
                                <div x-show="item.data?.improved_question" x-cloak class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-800 dark:bg-emerald-950/20"><strong class="text-xs text-emerald-800 dark:text-emerald-200">Versi perbaikan</strong><p class="m-0 mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-200" x-text="item.data?.improved_question"></p><ol x-show="(item.data?.improved_options || []).length" class="mb-0 mt-2 list-[upper-alpha] space-y-1 pl-5 text-xs text-slate-600 dark:text-slate-300"><template x-for="(option, i) in (item.data?.improved_options || [])" :key="'option-' + item.index + '-' + i"><li x-text="option"></li></template></ol><span x-show="quality.appliedIndexes.includes(Number(item.index))" x-cloak class="mt-2 block text-[11px] font-black text-emerald-700 dark:text-emerald-300">Sudah diterapkan ke pratinjau soal.</span></div>
                            </article>
                        </template>
                    </div>
                </div>
            </main>
        </div>

        <div id="quality-save-preview" class="mt-5 space-y-3">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><h2 class="m-0 text-base font-black text-slate-800 dark:text-slate-100">Pratinjau &amp; simpan perubahan</h2><p class="m-0 text-[11px] font-semibold text-slate-500">Periksa hasil perbaikan, lalu simpan ke Arena.</p></div>
            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="(q, qi) in questions" :key="'save-question-' + qi">
                    <article class="quality-save-card min-w-0"><div class="mb-1.5 flex items-center gap-2"><span class="grid h-6 w-6 place-items-center rounded-md bg-teal-600 text-[11px] font-black text-white" x-text="qi + 1"></span><span class="text-[11px] font-black uppercase text-slate-400" x-text="qualityTypeLabel(q.type)"></span></div><textarea x-model="q.question_text" :name="'questions['+qi+'][question_text]'" :data-question-index="qi" rows="3" required class="quality-input" data-auto-resize x-init="$nextTick(() => resizeQuestion($el))" @input="markDirty(); resizeQuestion($event.target)"></textarea><input type="hidden" :name="'questions['+qi+'][type]'" :value="q.type"><input type="hidden" :name="'questions['+qi+'][points]'" :value="q.points || 1"><input type="hidden" :name="'questions['+qi+'][time_limit_seconds]'" :value="q.time_limit_seconds || ''"><input type="hidden" :name="'questions['+qi+'][explanation]'" :value="q.explanation || ''"><template x-for="(option, oi) in (q.options || [])" :key="'save-option-'+qi+'-'+oi"><span><input type="hidden" :name="'questions['+qi+'][options]['+oi+'][option_text]'" :value="option.option_text || ''"><input type="hidden" :name="'questions['+qi+'][options]['+oi+'][is_correct]'" :value="option.is_correct ? 1 : 0"></span></template><template x-for="(answer, ai) in (q.meta?.answers || [])" :key="'save-answer-'+qi+'-'+ai"><input type="hidden" :name="'questions['+qi+'][meta][answers]['+ai+']" :value="answer || ''"></template><template x-for="(pair, pi) in (q.meta?.pairs || [])" :key="'save-pair-'+qi+'-'+pi"><span><input type="hidden" :name="'questions['+qi+'][meta][pairs]['+pi+'][left]'" :value="pair.left || ''"><input type="hidden" :name="'questions['+qi+'][meta][pairs]['+pi+'][right]'" :value="pair.right || ''"></span></template></article>
                </template>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function qualityBatchPage(initial, config = {}) {
    return {
        questions: initial,
        quality: { form: { grade_level: config.gradeLevel || '', subject: config.subject || '', learning_objective: config.objective || '' }, loading: false, error: '', message: '', results: [], summary: { total: initial.length, average_score: 0, layak: 0, perlu_revisi: 0, tidak_layak: 0 }, appliedIndexes: [], checkedSignature: '' },
        qualityStatusLabel(status) { return ({ layak: 'Layak', perlu_revisi: 'Perlu revisi', tidak_layak: 'Tidak layak' })[status] || '-'; },
        qualitySourceLabel(source) { return source === 'ai' ? 'Analisis AI' : 'Analisis Dasar'; },
        qualityStatusClass(status) { return ({ layak: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200', perlu_revisi: 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-200', tidak_layak: 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-200' })[status] || 'bg-slate-100 text-slate-600'; },
        qualityTypeLabel(type) { return ({ mcq: 'Pilihan Ganda', mcq_complex: 'PG Kompleks', true_false: 'Benar / Salah', short_answer: 'Isian', match: 'Menjodohkan' })[type] || type || 'Soal'; },
        resizeQuestion(element) {
            const textarea = element?.target || element;
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(textarea.scrollHeight, 72) + 'px';
        },
        resizeAllQuestions() {
            this.$nextTick(() => document.querySelectorAll('#quality-save-preview textarea[data-auto-resize]').forEach(textarea => this.resizeQuestion(textarea)));
        },
        questionsSignature() {
            return JSON.stringify(this.questions.map((question) => ({
                type: question.type || '',
                question_text: question.question_text || '',
                options: (question.options || []).map(option => option.option_text || ''),
                answers: question.meta?.answers || [],
                pairs: question.meta?.pairs || [],
            })));
        },
        qualityCurrent() {
            return this.quality.checkedSignature !== '' && this.quality.checkedSignature === this.questionsSignature();
        },
        markDirty() {
            if (this.quality.checkedSignature) this.quality.checkedSignature = '';
        },
        hasImprovement(item) {
            const data = item?.data || {};
            return Boolean((typeof data.improved_question === 'string' && data.improved_question.trim()) || (Array.isArray(data.improved_options) && data.improved_options.some(option => String(option || '').trim())));
        },
        payload(q) {
            const correct = (q.options || []).map((option, index) => option.is_correct ? String.fromCharCode(65 + index) : '').filter(Boolean);
            const key = q.type === 'short_answer' ? (q.meta?.answers || []).filter(Boolean).join(', ') : q.type === 'match' ? (q.meta?.pairs || []).filter(pair => pair.left && pair.right).map(pair => pair.left + ' = ' + pair.right).join('; ') : q.type === 'true_false' ? ((q.options || []).find(option => option.is_correct)?.option_text || '') : correct.join(', ');
            return { ...this.quality.form, question_type: q.type || 'mcq', question_text: q.question_text || '', options: (q.options || []).map(option => option.option_text || '').filter(Boolean), answer_key: key };
        },
        async runBatch() {
            if (this.quality.loading || !config.batchUrl) return;
            this.quality.loading = true; this.quality.error = ''; this.quality.message = ''; this.quality.results = [];
            try {
                const response = await fetch(config.batchUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }, body: JSON.stringify({ questions: this.questions.map(q => this.payload(q)) }) });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.ok) { this.quality.error = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Pemeriksaan kolektif gagal.'; return; }
                this.quality.summary = payload.data.summary || this.quality.summary; this.quality.results = payload.data.results || [];
                this.quality.checkedSignature = this.questionsSignature();
                this.quality.message = 'Cek kolektif selesai. Terapkan perbaikan bila perlu, lalu simpan perubahan.';
            } catch (_) { this.quality.error = 'Tidak dapat terhubung ke server.'; } finally { this.quality.loading = false; this.$nextTick(() => window.lucide && lucide.createIcons()); }
        },
        applyOne(index) {
            const qi = Number(index); const item = this.quality.results.find(result => Number(result.index) === qi); const q = this.questions[qi]; const improved = item?.data;
            if (!q || !improved) return false;
            let changed = false;
            if (typeof improved.improved_question === 'string' && improved.improved_question.trim() && improved.improved_question !== q.question_text) {
                q.question_text = improved.improved_question;
                changed = true;
            }
            if (Array.isArray(improved.improved_options) && Array.isArray(q.options)) improved.improved_options.forEach((text, oi) => {
                if (q.options[oi] && typeof text === 'string' && text.trim() && q.options[oi].option_text !== text) {
                    q.options[oi].option_text = text;
                    changed = true;
                }
            });
            if (!this.quality.appliedIndexes.includes(qi)) this.quality.appliedIndexes.push(qi);
            return changed;
        },
        applyAll() {
            let applied = 0; this.quality.results.forEach(item => { if (this.hasImprovement(item) && this.applyOne(item.index)) applied++; });
            if (applied) this.quality.checkedSignature = '';
            this.quality.message = applied ? applied + ' versi perbaikan diterapkan ke pratinjau. Simpan perubahan, lalu cek ulang sebelum menerbitkan.' : 'Tidak ada versi perbaikan yang dapat diterapkan.';
            this.resizeAllQuestions();
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        prepareSave() { this.questions.forEach(q => { if (['mcq', 'true_false'].includes(q.type)) { const index = (q.options || []).findIndex(option => option.is_correct); (q.options || []).forEach((option, oi) => option.is_correct = oi === (index >= 0 ? index : 0)); } }); },
    };
}
</script>
@endpush
