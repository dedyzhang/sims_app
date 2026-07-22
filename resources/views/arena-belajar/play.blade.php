@extends('layouts.app')
@section('title', 'Main — '.$quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
@php $letters = ['A','B','C','D','E','F']; @endphp
<x-arena-focus-lock
    :exit-url="route('classroom.arena.focus-exit', [$classroom, $quiz])"
    context="solo"
    :attempt-id="$attempt->uuid"
    :enabled="auth()->user()->access === 'siswa'"
>
<div class="max-w-lg mx-auto arena-stage"
     x-data="arenaPlay(@js($questionsPayload), @js($savedAnswers), @js($quiz->instant_feedback))"
     x-cloak
     data-arena-focus-target>

    <div class="arena-play-shell space-y-5" x-ref="fsRoot">
        <div class="arena-fs-stack space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 flex items-start gap-2">
                <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}"
                   class="arena-hud-back !bg-white/10 !border-white/15 !text-white !shadow-none hover:!bg-white/20 shrink-0"
                   onclick="if (!confirm('Keluar dari pertandingan? Jawaban tersimpan di perangkat, tapi skor belum dikumpulkan.')) return false; window.arenaFocusMarkSafe && window.arenaFocusMarkSafe(); return true;">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Experience</span>
                </a>
                <div class="min-w-0">
                    <p class="arena-chip"><i data-lucide="swords" class="w-3 h-3"></i> Solo · soal acak · fokus</p>
                    <p class="text-sm font-bold text-white/90 truncate mt-1.5">{{ $quiz->title }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <div class="text-right">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Soal</p>
                    <p class="text-lg font-black tabular-nums" x-text="(current+1) + '/' + questions.length"></p>
                </div>
            </div>
        </div>

        <div class="arena-progress"><span :style="'width:' + progressPct + '%'"></span></div>

        <template x-if="questions[current]">
            <div class="space-y-4" :key="questions[current].uuid">
                <div class="arena-anim-pop rounded-2xl bg-white/5 border border-white/10 p-4 sm:p-5">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2"
                       x-text="typeLabel(questions[current].type)"></p>
                    <p class="text-lg sm:text-xl font-black leading-snug text-white" x-text="questions[current].question_text"></p>
                    <p class="text-xs text-amber-200/90 mt-2 font-semibold" x-show="questions[current].type === 'mcq_complex'">Pilih semua jawaban yang benar</p>
                </div>

                <div class="space-y-2.5" x-show="questions[current].type === 'mcq' || questions[current].type === 'true_false'">
                    <template x-for="(opt, oi) in questions[current].options" :key="opt.uuid">
                        <button type="button"
                                @click="select(opt.uuid)"
                                class="arena-opt arena-anim-in"
                                :style="'animation-delay:' + (oi * 50) + 'ms'"
                                :class="answers[questions[current].uuid] === opt.uuid ? 'is-selected' : ''"
                                :disabled="locking">
                            <span class="arena-opt-letter" x-text="['A','B','C','D','E','F'][oi] || (oi+1)"></span>
                            <span class="flex-1" x-text="opt.option_text"></span>
                        </button>
                    </template>
                </div>

                <div class="space-y-2.5" x-show="questions[current].type === 'mcq_complex'">
                    <template x-for="(opt, oi) in questions[current].options" :key="opt.uuid">
                        <button type="button"
                                @click="toggleComplex(opt.uuid)"
                                class="arena-opt arena-anim-in"
                                :style="'animation-delay:' + (oi * 50) + 'ms'"
                                :class="(multiAnswers[questions[current].uuid] || []).includes(opt.uuid) ? 'is-selected' : ''"
                                :disabled="locking">
                            <span class="arena-opt-letter" x-text="['A','B','C','D','E','F'][oi] || (oi+1)"></span>
                            <span class="flex-1" x-text="opt.option_text"></span>
                        </button>
                    </template>
                    <button type="button" class="arena-cta arena-cta-ghost w-full" @click="persistComplex()" :disabled="locking">
                        Simpan pilihan
                    </button>
                </div>

                <div x-show="questions[current].type === 'short_answer'" class="space-y-2">
                    <input type="text" x-model="answerTexts[questions[current].uuid]"
                           @change="persistLocal()"
                           class="w-full rounded-2xl border border-white/15 bg-white/10 px-4 py-3.5 text-white min-h-[52px] font-semibold placeholder:text-slate-500"
                           placeholder="Ketik jawabanmu…">
                </div>

                <div x-show="questions[current].type === 'match'" class="space-y-2">
                    <template x-for="left in (questions[current].meta?.lefts || [])" :key="left">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <span class="sm:w-1/2 font-bold text-sm py-3" x-text="left"></span>
                            <select x-model="matchMaps[questions[current].uuid][left]"
                                    @change="persistLocal()"
                                    class="sm:w-1/2 rounded-xl border border-white/15 bg-[#152836] px-3 py-3 text-sm min-h-[44px]">
                                <option value="">— pilih —</option>
                                <template x-for="r in (questions[current].meta?.rights || [])" :key="r">
                                    <option :value="r" x-text="r"></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>

                <div x-show="feedbackMsg" class="rounded-xl px-4 py-3 text-center font-black text-base"
                     :class="feedbackOk ? 'arena-feedback-ok bg-emerald-500/15' : 'arena-feedback-bad bg-rose-500/15'"
                     x-text="feedbackMsg"></div>
                <p x-show="feedbackExplain" class="text-sm text-slate-300 text-center" x-text="feedbackExplain"></p>
                <p x-show="offline" class="text-xs text-amber-300 text-center font-semibold">Offline — jawaban tersimpan di perangkat</p>

                <div class="flex gap-2 pt-1">
                    <button type="button" @click="prev" :disabled="current===0"
                            class="arena-cta arena-cta-ghost px-4 disabled:opacity-30">←</button>
                    <button type="button" x-show="current < questions.length - 1" @click="next"
                            class="arena-cta flex-1">Soal berikutnya →</button>
                    <button type="button" x-show="current === questions.length - 1" @click="submitAll"
                            class="arena-cta flex-1" style="background:linear-gradient(135deg,#fbbf24,#f59e0b)">Kumpulkan skor</button>
                </div>
            </div>
        </template>
        </div>
    </div>

    <form id="arena-submit-form" method="POST" action="{{ route('classroom.arena.submit', [$classroom, $quiz, $attempt]) }}" class="hidden" data-arena-focus-safe
          onsubmit="window.arenaFocusMarkSafe && window.arenaFocusMarkSafe()">
        @csrf
        <input type="hidden" name="duration_ms" :value="Date.now() - startedAt">
        <template x-for="(q, idx) in questions" :key="q.uuid">
            <div>
                <input type="hidden" :name="'answers['+idx+'][question_id]'" :value="q.uuid">
                <input type="hidden" :name="'answers['+idx+'][selected_option_id]'" :value="answers[q.uuid] || ''">
                <input type="hidden" :name="'answers['+idx+'][answer_text]'"
                       :value="q.type === 'mcq_complex'
                            ? JSON.stringify(multiAnswers[q.uuid] || [])
                            : (answerTexts[q.uuid] || (matchMaps[q.uuid] ? JSON.stringify(matchMaps[q.uuid]) : ''))">
            </div>
        </template>
    </form>
</div>
</x-arena-focus-lock>
@endsection

@push('scripts')
@include('arena-belajar.partials.fullscreen-script')
<script>
function arenaPlay(questions, saved, instantFeedback) {
    const answers = {};
    const answerTexts = {};
    const matchMaps = {};
    const multiAnswers = {};
    Object.keys(saved || {}).forEach(k => {
        const v = saved[k];
        if (v && typeof v === 'object') {
            answers[k] = v.selected_option_id || null;
            if (v.answer_text) {
                answerTexts[k] = v.answer_text;
                try {
                    const parsed = JSON.parse(v.answer_text);
                    if (Array.isArray(parsed)) {
                        multiAnswers[k] = parsed;
                    } else if (parsed && typeof parsed === 'object') {
                        matchMaps[k] = parsed;
                    }
                } catch (e) {}
            }
        } else {
            answers[k] = v;
        }
    });
    questions.forEach(q => {
        if (q.type === 'match' && !matchMaps[q.uuid]) matchMaps[q.uuid] = {};
        if (q.type === 'mcq_complex' && !multiAnswers[q.uuid]) multiAnswers[q.uuid] = [];
    });
    const storageKey = @js('arena-offline-'.$attempt->uuid);
    try {
        const cached = JSON.parse(localStorage.getItem(storageKey) || '{}');
        Object.assign(answers, cached.answers || {});
        Object.assign(answerTexts, cached.texts || {});
        Object.assign(matchMaps, cached.maps || {});
        Object.assign(multiAnswers, cached.multi || {});
    } catch (e) {}
    return {
        ...window.arenaFullscreenMixin(),
        questions, answers, answerTexts, matchMaps, multiAnswers,
        current: 0,
        startedAt: Date.now(),
        instantFeedback: !!instantFeedback,
        feedbackMsg: '',
        feedbackOk: false,
        feedbackExplain: '',
        offline: !navigator.onLine,
        locking: false,
        submitting: false,
        init() { this.initFs(); },
        destroy() { this.destroyFs(); },
        typeLabel(type) {
            return ({
                mcq_complex: 'Pilihan Ganda Kompleks',
                mcq: 'Pilihan Ganda',
                true_false: 'Benar/Salah',
                short_answer: 'Isian',
                match: 'Mencocokkan',
            })[type] || 'Soal';
        },
        get progressPct() {
            return this.questions.length ? Math.round(((this.current + 1) / this.questions.length) * 100) : 0;
        },
        persistLocal() {
            try {
                localStorage.setItem(storageKey, JSON.stringify({
                    answers: this.answers,
                    texts: this.answerTexts,
                    maps: this.matchMaps,
                    multi: this.multiAnswers,
                }));
            } catch (e) {}
        },
        toggleComplex(optId) {
            const qid = this.questions[this.current].uuid;
            if (!this.multiAnswers[qid]) this.multiAnswers[qid] = [];
            const idx = this.multiAnswers[qid].indexOf(optId);
            if (idx >= 0) this.multiAnswers[qid].splice(idx, 1);
            else this.multiAnswers[qid].push(optId);
            this.answerTexts[qid] = JSON.stringify(this.multiAnswers[qid]);
            this.persistLocal();
            this.clearFeedback();
        },
        async persistComplex() {
            const q = this.questions[this.current];
            const qid = q.uuid;
            const selected = this.multiAnswers[qid] || [];
            this.answerTexts[qid] = JSON.stringify(selected);
            this.persistLocal();
            if (!navigator.onLine) { this.offline = true; return; }
            if (this.locking) return;
            this.locking = true;
            try {
                const res = await fetch(@js(route('classroom.arena.answer', [$classroom, $quiz, $attempt])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ question_id: qid, answer_text: JSON.stringify(selected) }),
                });
                const data = await res.json();
                if (this.instantFeedback && typeof data.is_correct === 'boolean') {
                    this.feedbackOk = data.is_correct;
                    this.feedbackMsg = data.is_correct ? 'Benar!' : 'Belum tepat — pastikan semua kunci terpilih';
                    this.feedbackExplain = data.explanation || '';
                }
            } catch (e) {
                this.offline = true;
            } finally {
                this.locking = false;
            }
        },
        async select(optId) {
            const q = this.questions[this.current];
            const qid = q.uuid;
            this.answers[qid] = optId;
            this.persistLocal();
            this.feedbackMsg = '';
            this.feedbackExplain = '';
            if (!navigator.onLine) { this.offline = true; return; }
            if (this.locking) return;
            this.locking = true;
            try {
                const res = await fetch(@js(route('classroom.arena.answer', [$classroom, $quiz, $attempt])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ question_id: qid, selected_option_id: optId }),
                });
                const data = await res.json();
                if (this.answers[qid] !== optId) return;
                if (this.instantFeedback && typeof data.is_correct === 'boolean') {
                    this.feedbackOk = data.is_correct;
                    this.feedbackMsg = data.is_correct ? 'Benar!' : 'Belum tepat — coba soal berikutnya';
                    this.feedbackExplain = data.explanation || '';
                }
            } catch (e) {
                this.offline = true;
            } finally {
                this.locking = false;
            }
        },
        prev() { if (this.current > 0) { this.current--; this.clearFeedback(); } },
        next() { if (this.current < this.questions.length - 1) { this.current++; this.clearFeedback(); } },
        clearFeedback() { this.feedbackMsg = ''; this.feedbackExplain = ''; },
        async submitAll() {
            if (this.submitting) return;
            if (!confirm('Kumpulkan jawaban dan lihat skor?')) return;
            this.submitting = true;
            window.arenaFocusMarkSafe && window.arenaFocusMarkSafe();
            this.questions.forEach(q => {
                if (!(q.uuid in this.answers)) this.answers[q.uuid] = null;
                if (q.type === 'match') {
                    const map = this.matchMaps[q.uuid] || {};
                    this.answerTexts[q.uuid] = JSON.stringify(map);
                }
                if (q.type === 'mcq_complex') {
                    this.answerTexts[q.uuid] = JSON.stringify(this.multiAnswers[q.uuid] || []);
                }
            });
            this.persistLocal();
            if (!navigator.onLine) {
                this.submitting = false;
                alert('Kamu offline. Jawaban disimpan di perangkat dan akan disinkron saat online.');
                window.addEventListener('online', () => this.syncOffline(true), { once: true });
                return;
            }
            this.$nextTick(() => document.getElementById('arena-submit-form').submit());
        },
        async syncOffline(submit) {
            const payload = {
                answers: this.questions.map(q => ({
                    question_id: q.uuid,
                    selected_option_id: this.answers[q.uuid] || null,
                    answer_text: q.type === 'mcq_complex'
                        ? JSON.stringify(this.multiAnswers[q.uuid] || [])
                        : (this.answerTexts[q.uuid] || (this.matchMaps[q.uuid] ? JSON.stringify(this.matchMaps[q.uuid]) : null)),
                })),
                duration_ms: Date.now() - this.startedAt,
                submit: !!submit,
            };
            try {
                const res = await fetch(@js(route('classroom.arena.sync', [$classroom, $quiz])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.ok && submit) {
                    localStorage.removeItem(storageKey);
                    window.location = @js(route('classroom.arena.show', [$classroom, $quiz]));
                }
            } catch (e) {}
        },
    };
}
</script>
@endpush
