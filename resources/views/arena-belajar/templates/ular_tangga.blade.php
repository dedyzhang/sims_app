@extends('layouts.app')
@section('title', 'Ular Tangga — '.$quiz->title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@php
    $boardSize = 30;
    $ladders = [3 => 11, 7 => 17, 10 => 22, 15 => 25, 19 => 28];
    $snakes = [12 => 4, 16 => 6, 21 => 9, 24 => 13, 27 => 14, 29 => 18];
    $playQuestions = $quiz->questions
        ->filter(fn ($q) => in_array($q->type, ['mcq', 'true_false', 'short_answer'], true))
        ->values()
        ->map(function ($q) {
            $correctIds = $q->options->where('is_correct', true)->pluck('uuid')->values()->all();
            $answers = collect($q->meta['answers'] ?? [])
                ->map(fn ($a) => mb_strtolower(trim((string) $a)))
                ->filter()
                ->values()
                ->all();

            return [
                'uuid' => $q->uuid,
                'type' => $q->type,
                'text' => $q->question_text,
                'options' => $q->options->map(fn ($o) => [
                    'uuid' => $o->uuid,
                    'text' => $o->option_text,
                ])->values()->all(),
                'correct_ids' => $correctIds,
                'answers' => $answers,
                'explain' => $q->explanation,
            ];
        });
@endphp

@section('content')
<div class="arena-stage arena-rx arena-snake max-w-3xl mx-auto space-y-4"
     x-data="arenaUlarTangga({
        size: {{ $boardSize }},
        ladders: @js($ladders),
        snakes: @js($snakes),
        questions: @js($playQuestions),
     })"
     x-init="$nextTick(() => window.lucide?.createIcons?.())"
     x-cloak>

    <header class="arena-lobby-hud !mt-0">
        <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span>Experience</span>
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <span class="arena-rx-flag" style="background:linear-gradient(180deg,#86efac,#22c55e);color:#052e16">Ular tangga</span>
            <span class="arena-rx-flag">{{ $playQuestions->count() }} soal</span>
        </div>
    </header>

    <div class="arena-snake-hero">
        <div class="arena-snake-hero-grid" aria-hidden="true"></div>
        <div class="relative z-[1] space-y-2">
            <p class="m-0 text-[11px] font-black uppercase tracking-[0.14em] text-emerald-200/90">Mode permainan · Arena</p>
            <h1 class="m-0 text-2xl sm:text-3xl font-black tracking-tight" style="font-family:'Fredoka',sans-serif">{{ $quiz->title }}</h1>
            <p class="m-0 text-sm font-semibold text-slate-300 max-w-xl">
                Lempar dadu, jawab soal, naik tangga jika benar — atau digigit ular jika salah.
                Sampai petak {{ $boardSize }} untuk menang.
            </p>
        </div>
    </div>

    @if($playQuestions->isEmpty())
    <div class="arena-rx-detail-panel">
        <p class="m-0 text-sm font-bold text-amber-700 dark:text-amber-300">
            Ular tangga butuh soal pilihan ganda, benar/salah, atau isian singkat. Tambahkan soal di Edit dulu.
        </p>
    </div>
    @else

    <div class="arena-snake-stats">
        <div class="arena-snake-stat">
            <span>Posisi</span>
            <strong x-text="pos === 0 ? 'Start' : pos"></strong>
        </div>
        <div class="arena-snake-stat">
            <span>Benar</span>
            <strong class="text-emerald-600" x-text="correct"></strong>
        </div>
        <div class="arena-snake-stat">
            <span>Salah</span>
            <strong class="text-rose-500" x-text="wrong"></strong>
        </div>
        <div class="arena-snake-stat">
            <span>Giliran</span>
            <strong x-text="turns"></strong>
        </div>
    </div>

    <div class="arena-snake-board-wrap">
        <div class="arena-snake-board" role="grid" aria-label="Papan ular tangga">
            <template x-for="cell in boardCells" :key="cell.n">
                <div class="arena-snake-cell"
                     :class="{
                        'is-odd': cell.n % 2 === 1,
                        'is-even': cell.n % 2 === 0,
                        'is-start': cell.n === 1,
                        'is-finish': cell.n === size,
                        'has-ladder': !!ladders[cell.n],
                        'has-snake': !!snakes[cell.n],
                        'has-token': pos === cell.n,
                     }"
                     :style="'order:' + cell.order"
                     role="gridcell">
                    <span class="arena-snake-num" x-text="cell.n"></span>
                    <span class="arena-snake-mark" x-show="ladders[cell.n]" title="Tangga">
                        <i data-lucide="arrow-up-right"></i>
                        <em x-text="'→ ' + ladders[cell.n]"></em>
                    </span>
                    <span class="arena-snake-mark snake" x-show="snakes[cell.n]" title="Ular">
                        <i data-lucide="arrow-down-left"></i>
                        <em x-text="'→ ' + snakes[cell.n]"></em>
                    </span>
                    <span class="arena-snake-token" x-show="pos === cell.n" aria-label="Posisi pemain">
                        <i data-lucide="smile"></i>
                    </span>
                </div>
            </template>
        </div>
        <div class="arena-snake-legend">
            <span><i class="lg"></i> Tangga (naik jika jawab benar)</span>
            <span><i class="sn"></i> Ular (turun jika jawab salah)</span>
        </div>
    </div>

    <div class="arena-snake-controls">
        <div class="arena-snake-dice" :class="{ rolling: rolling }" aria-live="polite">
            <span x-text="diceFace"></span>
        </div>
        <div class="flex-1 space-y-2 min-w-0">
            <p class="m-0 text-sm font-bold text-slate-700 dark:text-slate-200" x-text="statusMsg"></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="arena-rx-cta-big solo !w-auto !min-h-[3rem] !px-5"
                        @click="roll()"
                        :disabled="rolling || asking || won || questions.length === 0">
                    <i data-lucide="dices" class="w-5 h-5"></i>
                    <span x-text="won ? 'Selesai!' : (asking ? 'Jawab dulu…' : 'Lempar dadu')"></span>
                </button>
                <button type="button" class="arena-rx-btn arena-rx-btn-ghost !min-h-[3rem]"
                        @click="reset()"
                        :disabled="rolling">
                    Main ulang
                </button>
            </div>
        </div>
    </div>

    {{-- Question modal --}}
    <div class="arena-snake-modal" x-show="asking" x-transition.opacity>
        <div class="arena-snake-modal-card" @click.stop>
            <p class="arena-lobby-kicker m-0" style="color:var(--arena-amber)">Tantangan petak <span x-text="pendingPos"></span></p>
            <h2 class="m-0 text-lg font-black text-slate-800 dark:text-slate-100" x-text="currentQ?.text"></h2>

            <template x-if="currentQ && (currentQ.type === 'mcq' || currentQ.type === 'true_false')">
                <div class="space-y-2 mt-3">
                    <template x-for="(opt, oi) in currentQ.options" :key="opt.uuid">
                        <button type="button" class="arena-snake-opt"
                                :class="{ picked: picked === opt.uuid, ok: feedback && opt.uuid === correctPick, bad: feedback && picked === opt.uuid && picked !== correctPick }"
                                @click="answerOption(opt.uuid)"
                                :disabled="feedback"
                                x-text="opt.text"></button>
                    </template>
                </div>
            </template>

            <template x-if="currentQ && currentQ.type === 'short_answer'">
                <div class="mt-3 space-y-2">
                    <input type="text" class="arena-snake-input" x-model="typed" placeholder="Ketik jawaban…"
                           :disabled="feedback" @keydown.enter.prevent="answerText()">
                    <button type="button" class="arena-rx-cta-big solo !min-h-[2.8rem]"
                            @click="answerText()" :disabled="feedback || !typed.trim()">Cek jawaban</button>
                </div>
            </template>

            <p class="m-0 mt-3 text-sm font-bold" x-show="feedbackMsg"
               :class="lastCorrect ? 'text-emerald-600' : 'text-rose-600'"
               x-text="feedbackMsg"></p>
            <p class="m-0 mt-1 text-xs font-semibold text-slate-500" x-show="feedback && currentQ?.explain" x-text="currentQ.explain"></p>

            <button type="button" class="arena-rx-cta-big live !mt-4 !min-h-[2.8rem]"
                    x-show="feedback" @click="resolveLanding()">
                Lanjut main
            </button>
        </div>
    </div>

    <div class="arena-snake-win" x-show="won" x-transition>
        <div class="arena-snake-win-card">
            <span class="arena-rx-flag arena-rx-flag-ok">Finish!</span>
            <h2 class="m-0 text-2xl font-black" style="font-family:'Fredoka',sans-serif">Kamu juara papan!</h2>
            <p class="m-0 text-sm font-semibold text-slate-600 dark:text-slate-300">
                <span x-text="correct"></span> benar · <span x-text="wrong"></span> salah · <span x-text="turns"></span> giliran
            </p>
            <button type="button" class="arena-rx-cta-big solo !w-auto !mt-2" @click="reset()">Main lagi</button>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function arenaUlarTangga({ size, ladders, snakes, questions }) {
    const faces = ['⚀','⚁','⚂','⚃','⚄','⚅'];
    const cells = [];
    const cols = 5;
    const rows = Math.ceil(size / cols);
    for (let r = 0; r < rows; r++) {
        const start = r * cols + 1;
        const end = Math.min((r + 1) * cols, size);
        const nums = [];
        for (let n = start; n <= end; n++) nums.push(n);
        // baris dari bawah: reverse row index for visual bottom-up
        const visualRowFromTop = rows - 1 - r;
        const LTR = r % 2 === 0;
        const ordered = LTR ? nums : [...nums].reverse();
        ordered.forEach((n, i) => {
            cells.push({ n, order: visualRowFromTop * cols + i + 1 });
        });
    }

    return {
        size, ladders, snakes, questions,
        boardCells: cells,
        pos: 0,
        dice: 1,
        rolling: false,
        asking: false,
        feedback: false,
        won: false,
        correct: 0,
        wrong: 0,
        turns: 0,
        pendingPos: 0,
        currentQ: null,
        picked: null,
        typed: '',
        lastCorrect: false,
        feedbackMsg: '',
        statusMsg: 'Lempar dadu untuk mulai dari Start.',
        qIndex: 0,

        get diceFace() { return faces[this.dice - 1] || '⚀'; },
        get correctPick() {
            return this.currentQ?.correct_ids?.[0] || null;
        },

        reset() {
            this.pos = 0;
            this.dice = 1;
            this.rolling = false;
            this.asking = false;
            this.feedback = false;
            this.won = false;
            this.correct = 0;
            this.wrong = 0;
            this.turns = 0;
            this.pendingPos = 0;
            this.currentQ = null;
            this.picked = null;
            this.typed = '';
            this.lastCorrect = false;
            this.feedbackMsg = '';
            this.statusMsg = 'Lempar dadu untuk mulai dari Start.';
            this.qIndex = 0;
            this.$nextTick(() => window.lucide?.createIcons?.());
        },

        async roll() {
            if (this.rolling || this.asking || this.won) return;
            this.rolling = true;
            this.statusMsg = 'Dadu berputar…';
            for (let i = 0; i < 8; i++) {
                this.dice = 1 + Math.floor(Math.random() * 6);
                await new Promise(r => setTimeout(r, 60 + i * 12));
            }
            const step = this.dice;
            this.rolling = false;
            this.turns++;

            let next = this.pos + step;
            if (next > this.size) {
                this.statusMsg = 'Butuh tepat ' + (this.size - this.pos) + ' — tunggu giliran berikutnya.';
                return;
            }
            this.pendingPos = next;
            this.openQuestion();
        },

        openQuestion() {
            if (!this.questions.length) {
                this.pos = this.pendingPos;
                this.applyBoardEffects(true);
                return;
            }
            this.currentQ = this.questions[this.qIndex % this.questions.length];
            this.qIndex++;
            this.picked = null;
            this.typed = '';
            this.feedback = false;
            this.feedbackMsg = '';
            this.asking = true;
            this.statusMsg = 'Jawab soal di petak ' + this.pendingPos;
            this.$nextTick(() => window.lucide?.createIcons?.());
        },

        answerOption(id) {
            if (this.feedback) return;
            this.picked = id;
            const ok = (this.currentQ.correct_ids || []).includes(id);
            this.finishAnswer(ok);
        },

        answerText() {
            if (this.feedback) return;
            const val = (this.typed || '').trim().toLowerCase();
            if (!val) return;
            const ok = (this.currentQ.answers || []).some(a => a === val);
            this.finishAnswer(ok);
        },

        finishAnswer(ok) {
            this.lastCorrect = ok;
            this.feedback = true;
            if (ok) {
                this.correct++;
                this.feedbackMsg = 'Benar! Siap lanjut di papan.';
            } else {
                this.wrong++;
                this.feedbackMsg = 'Belum tepat. Efek ular bisa aktif.';
            }
        },

        resolveLanding() {
            this.asking = false;
            this.feedback = false;
            this.pos = this.pendingPos;
            this.applyBoardEffects(this.lastCorrect);
            this.currentQ = null;
            this.$nextTick(() => window.lucide?.createIcons?.());
        },

        applyBoardEffects(answeredCorrect) {
            const here = this.pos;
            // Petak finish: tetap menang meskipun jawaban salah (tidak dihukum mundur).
            if (here >= this.size) {
                this.pos = this.size;
                this.won = true;
                this.statusMsg = answeredCorrect
                    ? 'Finish! Jawaban benar — kamu juara papan.'
                    : 'Finish! Kamu sampai petak akhir.';
                return;
            }
            if (answeredCorrect && this.ladders[here]) {
                const to = this.ladders[here];
                this.statusMsg = 'Naik tangga! ' + here + ' → ' + to;
                this.pos = to;
            } else if (!answeredCorrect && this.snakes[here]) {
                const to = this.snakes[here];
                this.statusMsg = 'Digigit ular… ' + here + ' → ' + to;
                this.pos = to;
            } else if (!answeredCorrect && here > 1) {
                const back = Math.max(1, here - 2);
                this.statusMsg = 'Mundur ke petak ' + back;
                this.pos = back;
            } else {
                this.statusMsg = 'Berada di petak ' + this.pos + '. Lempar lagi!';
            }
            if (this.pos >= this.size) {
                this.pos = this.size;
                this.won = true;
                this.statusMsg = 'Finish! Kamu menyelesaikan papan.';
            }
        },
    };
}
</script>
@endpush
