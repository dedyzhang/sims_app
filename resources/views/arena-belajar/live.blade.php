@extends('layouts.app')
@section('title', 'Live — '.$quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
<style>
.arena-live-stage {
    background:
        radial-gradient(ellipse 100% 80% at 50% -20%, color-mix(in srgb, var(--cp) 40%, transparent), transparent 55%),
        linear-gradient(165deg, #071018 0%, #0f2430 45%, #0a1820 100%);
    border-radius: 1.35rem;
    color: #f8fafc;
    min-height: min(62vh, 28rem);
}
.arena-podium-card {
    background: linear-gradient(180deg, #fff, color-mix(in srgb, var(--cp) 6%, white));
    border-radius: 1.25rem;
    border: 1px solid color-mix(in srgb, var(--cp) 20%, transparent);
}
.dark .arena-podium-card {
    background: #0f172a;
    border-color: color-mix(in srgb, var(--cp) 30%, transparent);
}
</style>
@endpush

@section('content')
<div class="space-y-4 arena-stage"
     x-data="arenaLive({
        canHost: @js($canHost),
        isSiswa: @js(auth()->user()->access === 'siswa'),
        stateUrl: @js(route('classroom.arena.live.state', [$classroom, $quiz])),
        boardUrl: @js(route('classroom.arena.live.leaderboard', [$classroom, $quiz])),
        advanceUrl: @js(route('classroom.arena.live.advance', [$classroom, $quiz])),
        answerUrl: @js(route('classroom.arena.live.answer', [$classroom, $quiz])),
        csrf: @js(csrf_token()),
     })"
     x-init="boot()"
     x-cloak>

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="text-xs text-slate-500 inline-flex items-center gap-1 mb-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Kembali
            </a>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">{{ $quiz->title }}</h1>
            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wide mt-0.5">Live Arena · {{ $quiz->scoringModeLabel() }}</p>
        </div>
        @if($canHost)
        <div class="flex gap-2 flex-wrap">
            @if(!$session || !$session->isActive())
            <form method="POST" action="{{ route('classroom.arena.live.start', [$classroom, $quiz]) }}">@csrf
                <button class="arena-cta" style="background:linear-gradient(135deg,var(--cp),#0c1a24);color:#fff">
                    <i data-lucide="radio" class="w-4 h-4"></i> Mulai Live
                </button>
            </form>
            @else
            <button type="button" @click="advance" class="arena-cta" style="background:linear-gradient(135deg,#fbbf24,#f59e0b)"
                    x-text="advanceLabel"></button>
            <form method="POST" action="{{ route('classroom.arena.live.end', [$classroom, $quiz]) }}">@csrf
                <button class="px-4 py-3 rounded-xl text-sm font-bold border border-rose-200 text-rose-600 min-h-[48px]">Akhiri</button>
            </form>
            @endif
        </div>
        @endif
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 arena-live-stage p-5 sm:p-8 flex flex-col justify-center relative overflow-hidden" x-ref="fsRoot">
            <div class="absolute top-3 right-3 z-[2] flex items-center gap-2">
                @if($canHost)
                <template x-if="session && (session.status === 'lobby' || session.status === 'question' || session.status === 'reveal')">
                    <div class="flex gap-2">
                        <button type="button" @click="advance" class="arena-cta text-xs px-3 min-h-[2.5rem]"
                                style="background:linear-gradient(135deg,#fbbf24,#f59e0b)"
                                x-text="advanceLabel"></button>
                        <form method="POST" action="{{ route('classroom.arena.live.end', [$classroom, $quiz]) }}">@csrf
                            <button class="px-3 py-2 rounded-xl text-xs font-bold border border-rose-300/50 text-rose-200 min-h-[2.5rem] bg-rose-500/10">Akhiri</button>
                        </form>
                    </div>
                </template>
                @endif
                <button type="button" class="arena-fs-btn" @click="toggleFullscreen"
                        :title="isFullscreen ? 'Keluar layar penuh' : 'Layar penuh'"
                        :aria-pressed="isFullscreen.toString()">
                    <i data-lucide="maximize" x-show="!isFullscreen"></i>
                    <i data-lucide="minimize" x-show="isFullscreen" x-cloak></i>
                    <span class="hidden sm:inline" x-text="isFullscreen ? 'Keluar' : 'Layar penuh'"></span>
                </button>
            </div>
            <div class="absolute inset-0 opacity-30 pointer-events-none"
                 style="background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:32px 32px"></div>

            <div class="relative z-[1] arena-fs-stack">
                <p class="arena-chip mb-4" x-text="session?.status_label || 'Menunggu sesi'"></p>

                <template x-if="!session || session.status === 'lobby' || session.status === 'idle'">
                    <div class="text-center space-y-4 arena-anim-pop py-8">
                        <div class="w-20 h-20 mx-auto rounded-3xl grid place-items-center bg-white/10 border border-white/10">
                            <i data-lucide="users" class="w-9 h-9"></i>
                        </div>
                        <p class="text-4xl sm:text-5xl font-black tracking-tight">Lobi Arena</p>
                        <p class="text-slate-300 text-sm max-w-sm mx-auto">Pemain sudah masuk. Guru akan memulai soal sebentar lagi.</p>
                        <div class="flex justify-center gap-1.5 pt-2">
                            <span class="w-2 h-2 rounded-full bg-white/40 animate-pulse"></span>
                            <span class="w-2 h-2 rounded-full bg-white/40 animate-pulse" style="animation-delay:.2s"></span>
                            <span class="w-2 h-2 rounded-full bg-white/40 animate-pulse" style="animation-delay:.4s"></span>
                        </div>
                    </div>
                </template>

                <template x-if="session && (session.status === 'question' || session.status === 'reveal') && session.question">
                    <div class="space-y-5 arena-anim-in" :key="session.current_question_id">
                        <div class="flex items-center justify-between text-sm text-slate-400 font-bold">
                            <span x-text="'SOAL ' + (session.question_index + 1)"></span>
                            <span x-text="session.question_index + 1 + ' / ' + session.question_total"></span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black leading-snug" x-text="session.question.question_text"></p>

                        <div class="space-y-2.5" x-show="session.question.type === 'mcq' || session.question.type === 'true_false'">
                            <template x-for="(opt, oi) in session.question.options" :key="opt.uuid">
                                <button type="button"
                                        @click="isSiswa && session.status==='question' && !answered && !submitting && answerMcq(opt.uuid)"
                                        class="arena-opt"
                                        :class="{
                                            'is-selected': selected === opt.uuid,
                                            'is-correct': session.status==='reveal' && opt.is_correct,
                                        }"
                                        x-bind:disabled="!isSiswa || session.status!=='question' || answered || submitting">
                                    <span class="arena-opt-letter" x-text="['A','B','C','D','E','F'][oi]"></span>
                                    <span x-text="opt.option_text"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="session.question.type === 'short_answer'" class="space-y-2">
                            <input type="text" x-model="shortText" :disabled="!isSiswa || session.status!=='question' || answered || submitting"
                                   class="w-full rounded-2xl border border-white/15 bg-white/10 px-4 py-3.5 text-white min-h-[52px] font-semibold"
                                   placeholder="Ketik jawaban…">
                            <button type="button" x-show="isSiswa && session.status==='question' && !answered" @click="answerShort"
                                    :disabled="submitting" class="arena-cta">Kirim jawaban</button>
                            <p x-show="session.status==='reveal' && session.question.correct_meta" class="text-sm text-emerald-300 font-semibold"
                               x-text="'Kunci: ' + (session.question.correct_meta?.answers || []).join(' / ')"></p>
                        </div>

                        <div x-show="session.question.type === 'match'" class="space-y-3">
                            <template x-for="left in (session.question.meta?.lefts || [])" :key="left">
                                <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                    <span class="sm:w-1/2 font-bold text-sm" x-text="left"></span>
                                    <select x-model="matchMap[left]" :disabled="!isSiswa || session.status!=='question' || answered || submitting"
                                            class="sm:w-1/2 rounded-xl border border-white/15 bg-[#152836] px-3 py-3 text-sm min-h-[44px]">
                                        <option value="">— pilih —</option>
                                        <template x-for="r in (session.question.meta?.rights || [])" :key="r">
                                            <option :value="r" x-text="r"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <button type="button" x-show="isSiswa && session.status==='question' && !answered" @click="answerMatch"
                                    :disabled="submitting" class="arena-cta">Kirim pasangan</button>
                        </div>

                        <div x-show="feedback" class="text-center font-black text-lg"
                             :class="feedbackOk === null ? 'text-slate-200' : (feedbackOk ? 'arena-feedback-ok' : 'arena-feedback-bad')" x-text="feedback"></div>
                        <p x-show="session.status==='reveal' && session.question.explanation" class="text-sm text-slate-300 text-center" x-text="session.question.explanation"></p>
                    </div>
                </template>

                <template x-if="session && session.status === 'ended'">
                    <div class="text-center py-10 arena-anim-pop">
                        <p class="text-4xl font-black">Sesi selesai</p>
                        <p class="text-slate-300 text-sm mt-2">Cek podium juara di samping.</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="arena-podium-card p-4 space-y-3">
            <h2 class="font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="trophy" class="w-4 h-4" style="color:var(--cp)"></i> Podium live
            </h2>
            <ol class="space-y-2">
                <template x-for="(row, i) in leaderboard" :key="row.student_id">
                    <li class="flex items-center gap-2.5 text-sm rounded-xl px-2 py-2 transition"
                        :class="i < 3 ? 'bg-primary/5' : ''">
                        <span class="arena-rank" :class="i===0?'arena-rank-1':(i===1?'arena-rank-2':(i===2?'arena-rank-3':''))" x-text="i+1"></span>
                        <span class="flex-1 truncate font-semibold text-slate-700 dark:text-slate-200" x-text="row.name"></span>
                        <span class="font-black tabular-nums" style="color:var(--cp)" x-text="row.score"></span>
                    </li>
                </template>
            </ol>
            <p x-show="!leaderboard.length" class="text-sm text-slate-400 text-center py-8 font-semibold">Menunggu skor pertama…</p>
            <p x-show="me" class="text-xs font-bold text-slate-500 border-t border-slate-100 dark:border-slate-700 pt-3">
                Skormu: <span style="color:var(--cp)" x-text="me?.score"></span>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('arena-belajar.partials.fullscreen-script')
<script>
function arenaLive(cfg) {
    return {
        ...window.arenaFullscreenMixin(),
        ...cfg,
        session: null,
        leaderboard: [],
        me: null,
        selected: null,
        shortText: '',
        matchMap: {},
        answered: false,
        submitting: false,
        feedback: '',
        feedbackOk: null,
        timer: null,
        pollSeq: 0,
        get advanceLabel() {
            if (!this.session) return 'Maju';
            if (this.session.status === 'lobby') return 'Mulai soal 1';
            if (this.session.status === 'question') return 'Tampilkan pembahasan';
            if (this.session.status === 'reveal') {
                return (this.session.question_index + 1 >= this.session.question_total) ? 'Selesai' : 'Soal berikutnya';
            }
            return 'Maju';
        },
        boot() {
            this.initFs();
            this.poll();
            this.timer = setInterval(() => this.poll(), 3000);
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            this.destroyFs();
        },
        async poll() {
            const seq = ++this.pollSeq;
            try {
                const [sRes, bRes] = await Promise.all([
                    fetch(this.stateUrl, { headers: { Accept: 'application/json' } }),
                    fetch(this.boardUrl, { headers: { Accept: 'application/json' } }),
                ]);
                if (seq !== this.pollSeq) return;
                const sData = await sRes.json();
                const bData = await bRes.json();
                if (seq !== this.pollSeq) return;
                const prevQ = this.session?.current_question_id;
                this.session = sData.session;
                if (this.session?.current_question_id !== prevQ) {
                    this.selected = null;
                    this.shortText = '';
                    this.matchMap = {};
                    this.answered = false;
                    this.submitting = false;
                    this.feedback = '';
                    this.feedbackOk = null;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
                this.leaderboard = bData.leaderboard || [];
                this.me = bData.me;
            } catch (e) {}
        },
        async advance() {
            if (!this.canHost) return;
            await fetch(this.advanceUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
            });
            await this.poll();
        },
        async postAnswer(body) {
            if (this.submitting || this.answered) return;
            this.submitting = true;
            const qid = body.question_id;
            try {
                const res = await fetch(this.answerUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (this.session?.current_question_id !== qid) return;
                if (res.status === 409) {
                    this.answered = true;
                    this.feedbackOk = null;
                    this.feedback = 'Jawaban sudah terkunci';
                    return;
                }
                if (data.ok) {
                    this.answered = true;
                    if (typeof data.is_correct === 'boolean') {
                        this.feedbackOk = data.is_correct;
                        this.feedback = data.is_correct ? ('Benar! +' + data.points) : 'Belum tepat';
                    } else {
                        this.feedbackOk = null;
                        this.feedback = 'Jawaban tersimpan';
                    }
                    await this.poll();
                }
            } finally {
                this.submitting = false;
            }
        },
        answerMcq(optId) {
            if (this.answered || this.submitting) return;
            this.selected = optId;
            this.postAnswer({
                question_id: this.session.current_question_id,
                selected_option_id: optId,
            });
        },
        answerShort() {
            this.postAnswer({
                question_id: this.session.current_question_id,
                answer_text: this.shortText,
            });
        },
        answerMatch() {
            this.postAnswer({
                question_id: this.session.current_question_id,
                answer_text: JSON.stringify(this.matchMap),
            });
        },
    };
}
</script>
@endpush
