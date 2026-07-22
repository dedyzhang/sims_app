@extends('layouts.app')
@section('title', 'Live — '.$quiz->title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
@php
    $isSiswaLive = auth()->user()->access === 'siswa';
@endphp
<x-arena-focus-lock
    :exit-url="route('classroom.arena.focus-exit', [$classroom, $quiz])"
    context="live"
    :session-id="optional($session)->uuid"
    :enabled="$isSiswaLive"
>
<div class="arena-stage arena-rx space-y-4"
     data-arena-focus-target
     x-data="arenaLive({
        canHost: @js($canHost),
        isSiswa: @js($isSiswaLive),
        tokenReady: @js($canHost || !($requiresJoinToken ?? false) || ($hasJoinUnlock ?? true)),
        stateUrl: @js(route('classroom.arena.live.state', [$classroom, $quiz])),
        boardUrl: @js(route('classroom.arena.live.leaderboard', [$classroom, $quiz])),
        advanceUrl: @js(route('classroom.arena.live.advance', [$classroom, $quiz])),
        answerUrl: @js(route('classroom.arena.live.answer', [$classroom, $quiz])),
        csrf: @js(csrf_token()),
     })"
     x-init="boot()"
     x-cloak>

    <header class="arena-lobby-hud !mt-0">
        <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back" data-arena-focus-safe
           onclick="window.arenaFocusMarkSafe && window.arenaFocusMarkSafe()">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span class="truncate">Experience</span>
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <span class="arena-rx-flag arena-rx-flag-live">Live arena</span>
            <span class="arena-rx-flag">{{ $quiz->scoringModeLabel() }}</span>
        </div>
    </header>

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 relative z-[2]">
        <div>
            <p class="arena-lobby-kicker m-0" style="color:var(--rx-live,#ff2d55)">Party mode</p>
            <h1 class="m-0 text-2xl sm:text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight" style="font-family:'Fredoka',sans-serif">{{ $quiz->title }}</h1>
            <p class="m-0 mt-1 text-sm font-semibold text-slate-500">Host kontrol soal · pemain jawab real-time · podium langsung</p>
        </div>
        @if($canHost)
        <div class="flex gap-2 flex-wrap">
            @if(!$session || !$session->isActive())
            <form method="POST" action="{{ route('classroom.arena.live.start', [$classroom, $quiz]) }}">@csrf
                <button type="submit" class="arena-rx-cta-big live !min-h-[3rem] !w-auto !px-5">
                    <i data-lucide="radio" class="w-4 h-4"></i> Mulai Live
                </button>
            </form>
            @else
            <button type="button" @click="advance" class="arena-rx-cta-big solo !min-h-[3rem] !w-auto !px-5"
                    x-text="advanceLabel"></button>
            <form method="POST" action="{{ route('classroom.arena.live.end', [$classroom, $quiz]) }}">@csrf
                <button type="submit" class="arena-rx-manage-btn !border-rose-300 !text-rose-600">Akhiri</button>
            </form>
            @endif
        </div>
        @endif
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 border-2 border-emerald-300 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-2xl bg-rose-50 dark:bg-rose-900/40 border-2 border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm font-bold">{{ session('error') }}</div>
    @endif

    @if($isSiswaLive && ($requiresJoinToken ?? false) && !($hasJoinUnlock ?? true))
    <div class="fixed inset-0 z-[70] grid place-items-center bg-slate-900/70 p-4"
         x-data="arenaSoloJoin({
            prefillLiveToken: @js($prefillLiveToken ?? ''),
            autoOpenLive: true,
            liveUrl: @js($liveJoinUrl ?? ''),
            joinTokenUrl: @js(route('classroom.arena.join-token', [$classroom, $quiz])),
            liveRedirect: @js(route('classroom.arena.live', [$classroom, $quiz])),
         })"
         x-init="init()">
        <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-5 space-y-4">
            <div>
                <h3 class="m-0 text-lg font-black text-slate-800 dark:text-slate-100">Token Live Arena</h3>
                <p class="m-0 mt-1 text-xs font-semibold text-slate-500">Ketik, pindai QR, atau scan barcode dari guru mapel.</p>
            </div>
            <form method="POST" :action="joinTokenUrl" class="space-y-3">
                @csrf
                <input type="hidden" name="redirect" :value="liveRedirect">
                <input type="text" name="join_token" x-model="liveToken"
                       maxlength="8" autocomplete="off"
                       class="form-input font-mono text-center text-xl tracking-[0.35em] uppercase"
                       placeholder="ABCD" required autofocus>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary flex-1 rounded-xl py-2.5 text-sm font-bold"
                            @click="openScan()">
                        <i data-lucide="qr-code" class="w-4 h-4 inline"></i> Pindai
                    </button>
                    <button type="submit" class="btn-primary flex-1 rounded-xl py-2.5 text-sm font-bold">
                        Masuk Live
                    </button>
                </div>
            </form>
            @include('arena-belajar.partials.join-barcode-wedge')
        </div>
        @include('arena-belajar.partials.join-qr-scanner')
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 relative z-[2]">
        <div class="lg:col-span-2 arena-rx-live-stage p-5 sm:p-8 flex flex-col justify-center relative overflow-hidden" x-ref="fsRoot">
            <div class="arena-rx-live-stage-grid" aria-hidden="true"></div>
            <div class="absolute top-3 right-3 z-[2] flex items-center gap-2 flex-wrap justify-end">
                @if($canHost)
                <template x-if="session && (session.status === 'lobby' || session.status === 'question' || session.status === 'reveal')">
                    <div class="flex gap-2">
                        <button type="button" @click="advance" class="arena-rx-btn arena-rx-btn-solo !min-h-[2.5rem]"
                                x-text="advanceLabel"></button>
                        <form method="POST" action="{{ route('classroom.arena.live.end', [$classroom, $quiz]) }}">@csrf
                            <button type="submit" class="arena-rx-btn !bg-rose-500/20 !text-rose-100 !shadow-none border border-rose-300/40 !min-h-[2.5rem]">Akhiri</button>
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

            <div class="relative z-[1] arena-fs-stack">
                <p class="arena-rx-flag arena-rx-flag-live mb-4 inline-flex" x-text="session?.status_label || 'Menunggu sesi'"></p>

                <template x-if="!session || session.status === 'lobby' || session.status === 'idle'">
                    <div class="text-center space-y-4 arena-anim-pop py-8">
                        <div class="w-24 h-24 mx-auto rounded-[1.4rem] grid place-items-center bg-white/10 border-2 border-white/20 shadow-[0_8px_0_rgba(0,0,0,.25)]">
                            <i data-lucide="users" class="w-10 h-10"></i>
                        </div>
                        <p class="text-4xl sm:text-5xl font-black tracking-tight" style="font-family:'Fredoka',sans-serif">Lobi Arena</p>
                        <p class="text-slate-300 text-sm max-w-sm mx-auto font-semibold">
                            <span x-text="(session?.online_count ?? 0) + ' pemain online'"></span>
                            · Host akan memulai soal sebentar lagi.
                        </p>
                        <div class="flex justify-center gap-1.5 pt-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse" style="animation-delay:.2s"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse" style="animation-delay:.4s"></span>
                        </div>
                        <ul class="max-w-md mx-auto mt-4 space-y-2 text-left list-none m-0 p-0"
                            x-show="(session?.participants || []).length">
                            <template x-for="p in (session?.participants || [])" :key="p.user_id">
                                <li class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 bg-white/10 border border-white/10">
                                    <span class="arena-online-dot" :class="p.online ? 'is-on' : 'is-off'"
                                          :title="p.online ? 'Online' : 'Offline'"></span>
                                    <span class="flex-1 truncate font-bold text-white text-sm" x-text="p.name"></span>
                                    <span class="text-[10px] font-black uppercase tracking-wide"
                                          :class="p.online ? 'text-[#39ff14]' : 'text-slate-400'"
                                          x-text="p.online ? 'Online' : 'Offline'"></span>
                                </li>
                            </template>
                        </ul>
                        <p x-show="session && session.status === 'lobby' && !(session.participants || []).length"
                           class="text-xs font-semibold text-slate-400 m-0">Menunggu siswa membuka halaman Live…</p>
                        @if($canHost && ($liveJoinQrSvg ?? null))
                        <div class="arena-rx-join-qr-live mx-auto mt-6 max-w-xs"
                             x-init="$nextTick(() => window.arenaRenderJoinBarcodes && arenaRenderJoinBarcodes($el))">
                            <p class="text-[11px] font-black uppercase tracking-wider text-teal-200/80 mb-2">QR &amp; barcode gabung siswa</p>
                            <div class="arena-rx-join-qr-box inline-block bg-white p-2 rounded-xl">{!! $liveJoinQrSvg !!}</div>
                            @include('arena-belajar.partials.join-barcode-display', ['payload' => $liveBarcodePayload ?? null])
                            <p class="text-xs font-semibold text-slate-400 mt-2 m-0">Siswa pindai QR atau barcode untuk masuk lobi Live</p>
                        </div>
                        @endif
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

                        <div class="space-y-2.5" x-show="session.question.type === 'mcq_complex'">
                            <p class="text-xs font-semibold text-amber-200/90">Pilih semua jawaban yang benar, lalu kirim.</p>
                            <template x-for="(opt, oi) in session.question.options" :key="opt.uuid">
                                <button type="button"
                                        @click="isSiswa && session.status==='question' && !answered && !submitting && toggleComplex(opt.uuid)"
                                        class="arena-opt"
                                        :class="{
                                            'is-selected': selectedMulti.includes(opt.uuid),
                                            'is-correct': session.status==='reveal' && opt.is_correct,
                                        }"
                                        x-bind:disabled="!isSiswa || session.status!=='question' || answered || submitting">
                                    <span class="arena-opt-letter" x-text="['A','B','C','D','E','F'][oi]"></span>
                                    <span x-text="opt.option_text"></span>
                                </button>
                            </template>
                            <button type="button" x-show="isSiswa && session.status==='question' && !answered"
                                    @click="answerComplex" :disabled="submitting || selectedMulti.length === 0"
                                    class="arena-rx-cta-big solo !w-auto">Kirim jawaban</button>
                        </div>

                        <div x-show="session.question.type === 'short_answer'" class="space-y-2">
                            <input type="text" x-model="shortText" :disabled="!isSiswa || session.status!=='question' || answered || submitting"
                                   class="w-full rounded-2xl border-2 border-white/20 bg-white/10 px-4 py-3.5 text-white min-h-[52px] font-semibold"
                                   placeholder="Ketik jawaban…">
                            <button type="button" x-show="isSiswa && session.status==='question' && !answered" @click="answerShort"
                                    :disabled="submitting" class="arena-rx-cta-big solo !w-auto">Kirim jawaban</button>
                            <p x-show="session.status==='reveal' && session.question.correct_meta" class="text-sm text-emerald-300 font-semibold"
                               x-text="'Kunci: ' + (session.question.correct_meta?.answers || []).join(' / ')"></p>
                        </div>

                        <div x-show="session.question.type === 'match'" class="space-y-3">
                            <template x-for="left in (session.question.meta?.lefts || [])" :key="left">
                                <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                                    <span class="sm:w-1/2 font-bold text-sm" x-text="left"></span>
                                    <select x-model="matchMap[left]" :disabled="!isSiswa || session.status!=='question' || answered || submitting"
                                            class="sm:w-1/2 rounded-xl border-2 border-white/20 bg-[#152836] px-3 py-3 text-sm min-h-[44px]">
                                        <option value="">— pilih —</option>
                                        <template x-for="r in (session.question.meta?.rights || [])" :key="r">
                                            <option :value="r" x-text="r"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <button type="button" x-show="isSiswa && session.status==='question' && !answered" @click="answerMatch"
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
                            <template x-for="(row, i) in leaderboard.slice(0,10)" :key="row.student_id">
                                <li class="flex items-center gap-3 rounded-xl px-3 py-2.5"
                                    :class="i < 3 ? 'bg-amber-400/20 border-2 border-amber-300/50' : 'bg-white/10'">
                                    <span class="w-7 h-7 rounded-full grid place-items-center font-black text-sm flex-shrink-0"
                                          :class="i===0?'bg-amber-400 text-amber-900':(i===1?'bg-slate-300 text-slate-800':(i===2?'bg-orange-400 text-orange-900':'bg-white/20 text-white'))"
                                          x-text="i+1"></span>
                                    <span class="arena-online-dot shrink-0" :class="isOnline(row.student_id) ? 'is-on' : 'is-off'"></span>
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
                        <p class="text-4xl font-black" style="font-family:'Fredoka',sans-serif">Sesi selesai</p>
                        <p class="text-slate-300 text-sm mt-2 font-semibold">Cek podium juara di samping.</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="arena-rx-detail-panel space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-black text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0">
                    <span class="arena-online-dot is-on !w-2.5 !h-2.5"></span>
                    Pemain online
                </h2>
                <span class="text-xs font-black tabular-nums text-emerald-600 dark:text-emerald-400"
                      x-text="(session?.online_count ?? 0) + ' online'"></span>
            </div>
            <ul class="space-y-1.5 m-0 p-0 list-none max-h-40 overflow-y-auto">
                <template x-for="p in (session?.participants || [])" :key="'side-'+p.user_id">
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
                Belum ada siswa di sesi Live.
            </p>

            <h2 class="font-black text-slate-800 dark:text-slate-100 flex items-center gap-2 m-0 pt-2 border-t-2 border-slate-100 dark:border-slate-700">
                <i data-lucide="trophy" class="w-5 h-5 text-amber-500"></i> Podium live
            </h2>
            <ol class="space-y-2 m-0 p-0 list-none">
                <template x-for="(row, i) in leaderboard" :key="row.student_id">
                    <li class="flex items-center gap-2.5 text-sm rounded-xl px-2 py-2.5 transition border-2 border-transparent"
                        :class="i < 3 ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200/60 dark:border-amber-700/40' : ''">
                        <span class="arena-rank" :class="i===0?'arena-rank-1':(i===1?'arena-rank-2':(i===2?'arena-rank-3':''))" x-text="i+1"></span>
                        <span class="arena-online-dot shrink-0" :class="isOnline(row.student_id) ? 'is-on' : 'is-off'"></span>
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
</x-arena-focus-lock>
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
        pollBackoffMs: 0,
        lastBoardFetch: 0,
        get advanceLabel() {
            if (!this.session) return 'Maju';
            if (this.session.status === 'lobby') return 'Mulai soal 1';
            if (this.session.status === 'question') return 'Tampilkan pembahasan';
            if (this.session.status === 'reveal') return 'Tampilkan papan peringkat';
            if (this.session.status === 'standings') {
                return (this.session.question_index + 1 >= this.session.question_total) ? 'Selesai' : 'Soal berikutnya';
            }
            return 'Maju';
        },
        isOnline(userId) {
            const list = this.session?.participants || [];
            const hit = list.find(p => p.user_id === userId);
            return !!(hit && hit.online);
        },
        boot() {
            this.initFs();
            if (!this.tokenReady) return;
            this.poll();
            this.timer = setInterval(() => this.poll(), this.pollMs);
            this.countdownTimer = setInterval(() => this.tickCountdown(), 1000);
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
            if (this.countdownTimer) {
                clearInterval(this.countdownTimer);
                this.countdownTimer = null;
            }
            this.destroyFs();
        },
        tickCountdown() {
            if (!this.session || this.session.status !== 'question' || !this.session.question_deadline_at) {
                this.countdown = null;
                return;
            }
            const remain = Math.ceil((new Date(this.session.question_deadline_at).getTime() - Date.now()) / 1000);
            this.countdown = Math.max(0, remain);
        },
        async poll() {
            if (!this.tokenReady) return;
            const seq = ++this.pollSeq;
            const now = Date.now();
            const wantBoard = !this.lastBoardFetch
                || (now - this.lastBoardFetch) >= 12000
                || ['standings', 'ended'].includes(this.session?.status);
            try {
                const fetches = [
                    fetch(this.stateUrl, { headers: { Accept: 'application/json' } }),
                ];
                if (wantBoard) {
                    fetches.push(fetch(this.boardUrl, { headers: { Accept: 'application/json' } }));
                }
                const results = await Promise.all(fetches);
                if (seq !== this.pollSeq) return;
                const sRes = results[0];
                if (sRes.status === 429) {
                    this.scheduleBackoff(15000);
                    return;
                }
                if (sRes.status === 403) {
                    const err = await sRes.json().catch(() => ({}));
                    if (err.requires_token) this.tokenReady = false;
                    return;
                }
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
                if (wantBoard && results[1]) {
                    const bRes = results[1];
                    if (bRes.status === 429) {
                        this.scheduleBackoff(15000);
                    } else if (bRes.ok) {
                        const bData = await bRes.json();
                        if (seq !== this.pollSeq) return;
                        this.leaderboard = bData.leaderboard || [];
                        this.me = bData.me;
                        this.lastBoardFetch = now;
                    }
                }
                if (this.pollBackoffMs > 0) this.pollBackoffMs = 0;
                const focusRoot = document.getElementById('arena-focus-root');
                if (focusRoot && this.session?.uuid) {
                    focusRoot.dataset.sessionId = this.session.uuid;
                }
            } catch (e) {}
        },
        scheduleBackoff(ms) {
            this.pollBackoffMs = ms;
            if (this.timer) clearInterval(this.timer);
            this.timer = setTimeout(() => {
                this.timer = setInterval(() => this.poll(), this.pollMs);
                this.poll();
            }, ms);
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
        toggleComplex(optId) {
            const idx = this.selectedMulti.indexOf(optId);
            if (idx >= 0) this.selectedMulti.splice(idx, 1);
            else this.selectedMulti.push(optId);
        },
        answerComplex() {
            this.postAnswer({
                question_id: this.session.current_question_id,
                answer_text: JSON.stringify(this.selectedMulti),
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
