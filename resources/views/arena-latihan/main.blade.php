@extends('layouts.app')
@section('title', 'Latihan — '.$quiz->title)

@php($isKiosk = true)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="arena-stage arena-rx space-y-4"
     x-data="arenaLatihanTamu({
        participantId: @js($participant->uuid),
        guestToken: @js($participant->guest_token),
        stateUrl: @js($stateUrl),
        boardUrl: @js($boardUrl),
        answerUrl: @js($answerUrl),
        csrf: @js(csrf_token()),
     })"
     x-init="boot()"
     x-cloak>

    <header class="arena-lobby-hud !mt-0">
        <span class="arena-hud-back" style="pointer-events:none;opacity:.7">
            <i data-lucide="flask-conical" class="w-4 h-4"></i>
            <span class="truncate">Latihan</span>
        </span>
        <div class="flex flex-wrap items-center gap-2">
            <span class="arena-rx-flag" style="background:var(--ca,#7ba088)">Mode uji coba</span>
            <span class="arena-rx-flag arena-rx-flag-live" x-text="session?.status_label || 'Menunggu'"></span>
        </div>
    </header>

    <div class="relative z-[2]">
        <p class="arena-lobby-kicker m-0" style="color:var(--ca,#7ba088)">Halo, {{ $participant->guest_name }}</p>
        <h1 class="m-0 text-2xl sm:text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight" style="font-family:'Fredoka',sans-serif">{{ $quiz->title }}</h1>
        <p class="m-0 mt-1 text-sm font-semibold text-slate-500">Ini sesi LATIHAN — skor & jawabanmu tidak masuk nilai/hasil asli.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 relative z-[2]">
        <div class="lg:col-span-2 arena-rx-live-stage p-5 sm:p-8 flex flex-col justify-center relative overflow-hidden">
            <div class="arena-rx-live-stage-grid" aria-hidden="true"></div>
            <div class="relative z-[1] arena-fs-stack">
                <p class="arena-rx-flag arena-rx-flag-live mb-4 inline-flex" x-text="session?.status_label || 'Menunggu sesi'"></p>

                <template x-if="!session || session.status === 'lobby'">
                    <div class="text-center space-y-4 arena-anim-pop py-8">
                        <div class="w-24 h-24 mx-auto rounded-[1.4rem] grid place-items-center bg-white/10 border-2 border-white/20 shadow-[0_8px_0_rgba(0,0,0,.25)]">
                            <i data-lucide="users" class="w-10 h-10"></i>
                        </div>
                        <p class="text-4xl sm:text-5xl font-black tracking-tight" style="font-family:'Fredoka',sans-serif">Lobi Latihan</p>
                        <p class="text-slate-300 text-sm max-w-sm mx-auto font-semibold">
                            <span x-text="(session?.online_count ?? 0) + ' peserta online'"></span>
                            · Guru akan memulai soal sebentar lagi.
                        </p>
                        <div class="flex justify-center gap-1.5 pt-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse" style="animation-delay:.2s"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse" style="animation-delay:.4s"></span>
                        </div>
                    </div>
                </template>

                <template x-if="session && (session.status === 'question' || session.status === 'reveal') && session.question">
                    <div class="space-y-5 arena-anim-in" :key="session.current_question_id">
                        <div class="flex items-center justify-between text-sm font-black uppercase tracking-wide text-teal-200/90">
                            <span x-text="'Soal ' + (session.question_index + 1)"></span>
                            <span class="flex items-center gap-2">
                                <span x-show="session.status==='question' && session.joined_count !== null" x-cloak
                                      class="normal-case tracking-normal text-[11px] font-bold px-2 py-0.5 rounded-full bg-white/10"
                                      x-text="(session.answered_count ?? 0) + '/' + (session.joined_count ?? 0) + ' sudah jawab'"></span>
                                <span x-show="session.status==='question' && countdown !== null" x-cloak
                                      class="text-[11px] font-black px-2 py-0.5 rounded-full"
                                      :class="countdown <= 5 ? 'bg-rose-500/80 text-white' : 'bg-white/10'"
                                      x-text="countdown + ' dtk'"></span>
                                <span x-text="session.question_index + 1 + ' / ' + session.question_total"></span>
                            </span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black leading-snug" style="font-family:'Fredoka',sans-serif" x-text="session.question.question_text"></p>

                        <div class="space-y-2.5" x-show="session.question.type === 'mcq' || session.question.type === 'true_false'">
                            <template x-for="(opt, oi) in session.question.options" :key="opt.uuid">
                                <button type="button"
                                        @click="session.status==='question' && !answered && !submitting && answerMcq(opt.uuid)"
                                        class="arena-opt"
                                        :class="{
                                            'is-selected': selected === opt.uuid,
                                            'is-correct': session.status==='reveal' && opt.is_correct,
                                        }"
                                        x-bind:disabled="session.status!=='question' || answered || submitting">
                                    <span class="arena-opt-letter" x-text="['A','B','C','D','E','F'][oi]"></span>
                                    <span x-text="opt.option_text"></span>
                                </button>
                            </template>
                        </div>

                        <div class="space-y-2.5" x-show="session.question.type === 'mcq_complex'">
                            <p class="text-xs font-semibold text-amber-200/90">Pilih semua jawaban yang benar, lalu kirim.</p>
                            <template x-for="(opt, oi) in session.question.options" :key="opt.uuid">
                                <button type="button"
                                        @click="session.status==='question' && !answered && !submitting && toggleComplex(opt.uuid)"
                                        class="arena-opt"
                                        :class="{
                                            'is-selected': selectedMulti.includes(opt.uuid),
                                            'is-correct': session.status==='reveal' && opt.is_correct,
                                        }"
                                        x-bind:disabled="session.status!=='question' || answered || submitting">
                                    <span class="arena-opt-letter" x-text="['A','B','C','D','E','F'][oi]"></span>
                                    <span x-text="opt.option_text"></span>
                                </button>
                            </template>
                            <button type="button" x-show="session.status==='question' && !answered"
                                    @click="answerComplex" :disabled="submitting || selectedMulti.length === 0"
                                    class="arena-rx-cta-big solo !w-auto">Kirim jawaban</button>
                        </div>

                        <div x-show="session.question.type === 'short_answer'" class="space-y-2">
                            <input type="text" x-model="shortText" :disabled="session.status!=='question' || answered || submitting"
                                   class="w-full rounded-2xl border-2 border-white/20 bg-white/10 px-4 py-3.5 text-white min-h-[52px] font-semibold"
                                   placeholder="Ketik jawaban…">
                            <button type="button" x-show="session.status==='question' && !answered" @click="answerShort"
                                    :disabled="submitting" class="arena-rx-cta-big solo !w-auto">Kirim jawaban</button>
                            <p x-show="session.status==='reveal' && session.question.correct_meta" class="text-sm text-emerald-300 font-semibold"
                               x-text="'Kunci: ' + (session.question.correct_meta?.answers || []).join(' / ')"></p>
                        </div>

                        <div x-show="session.question.type === 'match'" class="space-y-3">
                            <template x-for="left in (session.question.meta?.lefts || [])" :key="left">
                                <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                    <span class="sm:w-1/2 font-bold text-sm" x-text="left"></span>
                                    <select x-model="matchMap[left]" :disabled="session.status!=='question' || answered || submitting"
                                            class="sm:w-1/2 rounded-xl border-2 border-white/20 bg-[#152836] px-3 py-3 text-sm min-h-[44px]">
                                        <option value="">— pilih —</option>
                                        <template x-for="r in (session.question.meta?.rights || [])" :key="r">
                                            <option :value="r" x-text="r"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <button type="button" x-show="session.status==='question' && !answered" @click="answerMatch"
                                    :disabled="submitting" class="arena-rx-cta-big solo !w-auto">Kirim pasangan</button>
                        </div>

                        <div x-show="feedback" class="text-center font-black text-lg"
                             :class="feedbackOk === null ? 'text-slate-200' : (feedbackOk ? 'arena-feedback-ok' : 'arena-feedback-bad')" x-text="feedback"></div>
                        <p x-show="session.status==='reveal' && session.question.explanation" class="text-sm text-slate-300 text-center font-semibold" x-text="session.question.explanation"></p>
                    </div>
                </template>

                <template x-if="session && session.status === 'standings'">
                    <div class="text-center py-4 arena-anim-pop space-y-4">
                        <p class="text-3xl sm:text-4xl font-black" style="font-family:'Fredoka',sans-serif">Papan Peringkat</p>
                        <p class="text-slate-300 text-xs font-semibold">Lanjut ke soal berikutnya sebentar lagi…</p>
                        <ol class="max-w-md mx-auto space-y-2 text-left">
                            <template x-for="(row, i) in leaderboard.slice(0,10)" :key="row.participant_id">
                                <li class="flex items-center gap-3 rounded-xl px-3 py-2.5"
                                    :class="i < 3 ? 'bg-amber-400/20 border-2 border-amber-300/50' : 'bg-white/10'">
                                    <span class="w-7 h-7 rounded-full grid place-items-center font-black text-sm flex-shrink-0"
                                          :class="i===0?'bg-amber-400 text-amber-900':(i===1?'bg-slate-300 text-slate-800':(i===2?'bg-orange-400 text-orange-900':'bg-white/20 text-white'))"
                                          x-text="i+1"></span>
                                    <span class="flex-1 truncate font-bold text-white" x-text="row.name"></span>
                                    <span class="font-black tabular-nums text-white" x-text="row.score"></span>
                                </li>
                            </template>
                        </ol>
                        <p x-show="!leaderboard.length" class="text-slate-400 text-sm font-bold">Belum ada skor.</p>
                    </div>
                </template>

                <template x-if="session && session.status === 'ended'">
                    <div class="text-center py-10 arena-anim-pop">
                        <p class="text-4xl font-black" style="font-family:'Fredoka',sans-serif">Sesi latihan selesai</p>
                        <p class="text-slate-300 text-sm mt-2 font-semibold">Terima kasih sudah ikut uji coba. Cek podium akhir di samping.</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="arena-rx-detail-panel space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-black text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0">
                    <span class="arena-online-dot is-on !w-2.5 !h-2.5"></span>
                    Peserta online
                </h2>
                <span class="text-xs font-black tabular-nums text-emerald-600 dark:text-emerald-400"
                      x-text="(session?.online_count ?? 0) + ' online'"></span>
            </div>
            <ul class="space-y-1.5 m-0 p-0 list-none max-h-40 overflow-y-auto">
                <template x-for="p in (session?.participants || [])" :key="'side-'+p.participant_id">
                    <li class="flex items-center gap-2 text-sm rounded-lg px-2 py-1.5"
                        :class="p.online ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'opacity-60'">
                        <span class="arena-online-dot" :class="p.online ? 'is-on' : 'is-off'"></span>
                        <span class="flex-1 truncate font-bold text-slate-700 dark:text-slate-200" x-text="p.name"></span>
                        <span class="text-[10px] font-black uppercase tracking-wide"
                              :class="p.online ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                              x-text="p.online ? 'Online' : 'Away'"></span>
                    </li>
                </template>
            </ul>
            <p x-show="!(session?.participants || []).length" class="text-xs font-bold text-slate-400 text-center py-3 m-0">
                Belum ada peserta lain.
            </p>

            <h2 class="font-black text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0 pt-2 border-t-2 border-slate-100 dark:border-slate-700">
                <i data-lucide="trophy" class="w-5 h-5 text-amber-500"></i> Podium latihan
            </h2>
            <ol class="space-y-2 m-0 p-0 list-none">
                <template x-for="(row, i) in leaderboard" :key="row.participant_id">
                    <li class="flex items-center gap-2.5 text-sm rounded-xl px-2 py-2.5 transition border-2 border-transparent"
                        :class="i < 3 ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200/60 dark:border-amber-700/40' : ''">
                        <span class="arena-rank" :class="i===0?'arena-rank-1':(i===1?'arena-rank-2':(i===2?'arena-rank-3':''))" x-text="i+1"></span>
                        <span class="flex-1 truncate font-bold text-slate-700 dark:text-slate-200" x-text="row.name"></span>
                        <span class="font-black tabular-nums text-teal-600 dark:text-teal-300" x-text="row.score"></span>
                    </li>
                </template>
            </ol>
            <p x-show="!leaderboard.length" class="text-sm text-slate-400 text-center py-8 font-bold m-0">Menunggu skor pertama…</p>
            <p x-show="me" class="text-xs font-bold text-slate-500 border-t-2 border-slate-100 dark:border-slate-700 pt-3 m-0">
                Skormu: <span class="text-teal-600 dark:text-teal-300 text-sm" x-text="me?.score"></span>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function arenaLatihanTamu(cfg) {
    return {
        ...cfg,
        session: null,
        leaderboard: [],
        selected: null,
        selectedMulti: [],
        shortText: '',
        matchMap: {},
        answered: false,
        submitting: false,
        feedback: '',
        feedbackOk: null,
        timer: null,
        countdownTimer: null,
        countdown: null,
        pollSeq: 0,
        pollMs: 4000,
        lastBoardFetch: 0,
        get me() {
            return this.leaderboard.find(r => r.participant_id === this.participantId) || null;
        },
        boot() {
            this.poll();
            this.timer = window.simsPollInterval(() => this.poll(), this.pollMs, 'arena_latihan_tamu'); // bisa dimatikan lewat Performa Server
            this.countdownTimer = setInterval(() => this.tickCountdown(), 1000);
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        tickCountdown() {
            if (!this.session || this.session.status !== 'question' || !this.session.question_deadline_at) {
                this.countdown = null;
                return;
            }
            const remain = Math.ceil((new Date(this.session.question_deadline_at).getTime() - Date.now()) / 1000);
            this.countdown = Math.max(0, remain);
        },
        withGuest(url) {
            const u = new URL(url, window.location.origin);
            u.searchParams.set('g', this.guestToken);
            return u.toString();
        },
        async poll() {
            const seq = ++this.pollSeq;
            // Pola sama live.blade.php: state & podium di-fetch TERPISAH — gangguan di podium
            // (endpoint lebih berat) tak boleh menghalangi update status/soal.
            try {
                const sRes = await fetch(this.withGuest(this.stateUrl), { headers: { Accept: 'application/json' } });
                if (seq !== this.pollSeq) return;
                if (!sRes.ok) return;
                const sData = await sRes.json();
                if (seq !== this.pollSeq) return;
                const prevQ = this.session?.current_question_id;
                this.session = sData.session;
                if (this.session?.current_question_id !== prevQ) {
                    this.selected = null;
                    this.selectedMulti = [];
                    this.shortText = '';
                    this.matchMap = {};
                    this.answered = false;
                    this.submitting = false;
                    this.feedback = '';
                    this.feedbackOk = null;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            } catch (e) {
                return;
            }

            const now = Date.now();
            const wantBoard = !this.lastBoardFetch
                || (now - this.lastBoardFetch) >= 12000
                || ['standings', 'ended'].includes(this.session?.status);
            if (!wantBoard) return;
            try {
                const bRes = await fetch(this.withGuest(this.boardUrl), { headers: { Accept: 'application/json' } });
                if (seq !== this.pollSeq) return;
                if (bRes.ok) {
                    const bData = await bRes.json();
                    if (seq !== this.pollSeq) return;
                    this.leaderboard = bData.leaderboard || [];
                    this.lastBoardFetch = now;
                }
            } catch (e) {}
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
                    body: JSON.stringify({ ...body, g: this.guestToken }),
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
            this.postAnswer({ question_id: this.session.current_question_id, selected_option_id: optId });
        },
        toggleComplex(optId) {
            const idx = this.selectedMulti.indexOf(optId);
            if (idx >= 0) this.selectedMulti.splice(idx, 1);
            else this.selectedMulti.push(optId);
        },
        answerComplex() {
            this.postAnswer({ question_id: this.session.current_question_id, answer_text: JSON.stringify(this.selectedMulti) });
        },
        answerShort() {
            this.postAnswer({ question_id: this.session.current_question_id, answer_text: this.shortText });
        },
        answerMatch() {
            this.postAnswer({ question_id: this.session.current_question_id, answer_text: JSON.stringify(this.matchMap) });
        },
    };
}
</script>
@endpush
