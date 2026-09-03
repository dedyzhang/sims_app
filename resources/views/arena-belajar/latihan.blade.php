@extends('layouts.app')
@section('title', 'Latihan — '.$quiz->title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="arena-stage arena-rx space-y-4"
     x-data="arenaLatihanGuru({
        stateUrl: @js(route('classroom.arena.latihan.state', [$classroom, $quiz])),
        boardUrl: @js(route('classroom.arena.latihan.leaderboard', [$classroom, $quiz])),
        advanceUrl: @js(route('classroom.arena.latihan.advance', [$classroom, $quiz])),
        csrf: @js(csrf_token()),
     })"
     x-init="boot()"
     x-cloak>

    <header class="arena-lobby-hud !mt-0">
        <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span class="truncate">Experience</span>
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <span class="arena-rx-flag" style="background:var(--ca,#7ba088)">Mode uji coba</span>
            <span class="arena-rx-flag">{{ $quiz->scoringModeLabel() }}</span>
        </div>
    </header>

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 relative z-[2]">
        <div>
            <p class="arena-lobby-kicker m-0" style="color:var(--ca,#7ba088)">Latihan (uji coba)</p>
            <h1 class="m-0 text-2xl sm:text-3xl font-black text-slate-800 dark:text-slate-100 tracking-tight" style="font-family:'Fredoka',sans-serif">{{ $quiz->title }}</h1>
            <p class="m-0 mt-1 text-sm font-semibold text-slate-500">Guru/siswa gabung tanpa login lewat QR/kode — skor & data di sini tidak masuk hasil asli.</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            @if(!$session || !$session->isActive())
            <form method="POST" action="{{ route('classroom.arena.latihan.start', [$classroom, $quiz]) }}">@csrf
                <button type="submit" class="arena-rx-cta-big solo !min-h-[3rem] !w-auto !px-5">
                    <i data-lucide="flask-conical" class="w-4 h-4"></i> Mulai Latihan
                </button>
            </form>
            @else
            <button type="button" @click="advance" class="arena-rx-cta-big solo !min-h-[3rem] !w-auto !px-5"
                    x-text="advanceLabel"></button>
            <form method="POST" action="{{ route('classroom.arena.latihan.end', [$classroom, $quiz]) }}"
                  onsubmit="return confirmAction(this, 'Akhiri sesi latihan ini?', 'orange')">@csrf
                <button type="submit" class="arena-rx-manage-btn !border-rose-300 !text-rose-600">Akhiri</button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 border-2 border-emerald-300 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 relative z-[2]">
        <div class="lg:col-span-2 arena-rx-live-stage p-5 sm:p-8 flex flex-col justify-center relative overflow-hidden">
            <div class="arena-rx-live-stage-grid" aria-hidden="true"></div>
            <div class="relative z-[1] arena-fs-stack">
                <p class="arena-rx-flag arena-rx-flag-live mb-4 inline-flex" x-text="session?.status_label || 'Menunggu sesi'"></p>

                <template x-if="!session || session.status === 'lobby'">
                    <div class="text-center space-y-4 arena-anim-pop py-8">
                        <p class="text-4xl sm:text-5xl font-black tracking-tight" style="font-family:'Fredoka',sans-serif">Lobi Latihan</p>
                        <p class="text-slate-300 text-sm max-w-sm mx-auto font-semibold">
                            <span x-text="(session?.online_count ?? 0) + ' peserta online'"></span>
                            · Klik "Mulai Latihan" lalu bagikan QR/kode di bawah.
                        </p>
                        <ul class="max-w-md mx-auto mt-4 space-y-2 text-left list-none m-0 p-0"
                            x-show="(session?.participants || []).length">
                            <template x-for="p in (session?.participants || [])" :key="p.participant_id">
                                <li class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 bg-white/10 border border-white/10">
                                    <span class="arena-online-dot" :class="p.online ? 'is-on' : 'is-off'"></span>
                                    <span class="flex-1 truncate font-bold text-white text-sm" x-text="p.name"></span>
                                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-400" x-text="p.role || ''"></span>
                                </li>
                            </template>
                        </ul>
                        <p x-show="session && session.status === 'lobby' && !(session.participants || []).length"
                           class="text-xs font-semibold text-slate-400 m-0">Menunggu peserta memindai QR/kode…</p>
                        @if($session && $joinQrSvg)
                        <div class="arena-rx-join-qr-live mx-auto mt-6 max-w-xs">
                            <p class="text-[11px] font-black uppercase tracking-wider text-teal-200/80 mb-2">QR &amp; kode gabung latihan</p>
                            <div class="arena-rx-join-qr-box inline-block bg-white p-2 rounded-xl">{!! $joinQrSvg !!}</div>
                            @include('arena-belajar.partials.join-barcode-display', ['payload' => $joinBarcodePayload])
                            <p class="text-sm font-mono tracking-[0.3em] text-white mt-3 mb-1">{{ $session->join_token }}</p>
                            <p class="text-xs font-semibold text-slate-400 mt-1 m-0">Pindai QR/barcode, atau buka <span class="font-mono">{{ $joinUrl }}</span> — tanpa login.</p>
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
                                <span x-text="session.question_index + 1 + ' / ' + session.question_total"></span>
                            </span>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black leading-snug" style="font-family:'Fredoka',sans-serif" x-text="session.question.question_text"></p>
                        <p class="text-sm text-slate-300 font-semibold" x-show="session.status==='reveal' && session.question.explanation" x-text="session.question.explanation"></p>
                    </div>
                </template>

                <template x-if="session && session.status === 'standings'">
                    <div class="text-center py-4 arena-anim-pop space-y-4">
                        <p class="text-3xl sm:text-4xl font-black" style="font-family:'Fredoka',sans-serif">Papan Peringkat</p>
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
                        <p class="text-4xl font-black" style="font-family:'Fredoka',sans-serif">Latihan selesai</p>
                        <p class="text-slate-300 text-sm mt-2 font-semibold">Cek podium akhir di samping. Klik "Mulai Latihan" lagi kapan pun untuk uji coba ulang.</p>
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
                Belum ada peserta di sesi Latihan.
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
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function arenaLatihanGuru(cfg) {
    return {
        ...cfg,
        session: null,
        leaderboard: [],
        timer: null,
        pollSeq: 0,
        pollMs: 4000,
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
        boot() {
            this.poll();
            this.timer = window.simsPollInterval(() => this.poll(), this.pollMs); // ikut mode darurat hemat server (hanya ujian yg dikecualikan)
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        async poll() {
            const seq = ++this.pollSeq;
            try {
                const sRes = await fetch(this.stateUrl, { headers: { Accept: 'application/json' } });
                if (seq !== this.pollSeq) return;
                if (!sRes.ok) return;
                const sData = await sRes.json();
                if (seq !== this.pollSeq) return;
                this.session = sData.session;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            } catch (e) {
                return;
            }

            const now = Date.now();
            const wantBoard = !this.lastBoardFetch
                || (now - this.lastBoardFetch) >= 12000
                || ['standings', 'ended'].includes(this.session?.status);
            if (!wantBoard) return;
            try {
                const bRes = await fetch(this.boardUrl, { headers: { Accept: 'application/json' } });
                if (seq !== this.pollSeq) return;
                if (bRes.ok) {
                    const bData = await bRes.json();
                    if (seq !== this.pollSeq) return;
                    this.leaderboard = bData.leaderboard || [];
                    this.lastBoardFetch = now;
                }
            } catch (e) {}
        },
        async advance() {
            await fetch(this.advanceUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
            });
            await this.poll();
        },
    };
}
</script>
@endpush
