@extends('layouts.app')
@section('title', 'Arena Belajar')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@php
    $quizCount = $quizzes->count();
    $missionCount = $missionAssignments->count();
    $artPairs = [
        ['#00c2b2', '#0b3d6e'],
        ['#ffb020', '#e85d75'],
        ['#3ecf8e', '#0b3d6e'],
        ['#ff6b8a', '#00c2b2'],
        ['#4da3ff', '#0b3d6e'],
        ['#ffb020', '#00c2b2'],
    ];
    $playerName = auth()->user()->displayName();
    $playerInitial = auth()->user()->initial();
@endphp

@section('content')
@php
    $defaultMode = request('mode');
    if (! in_array($defaultMode, ['kuis', 'misi'], true)) {
        $defaultMode = $missionAssignments->isNotEmpty() && $quizzes->isEmpty() ? 'misi' : 'kuis';
    }
@endphp
<div class="arena-stage arena-lobby"
     x-data="{ mode: '{{ $defaultMode }}', jenjang: 'semua', hanyaTren: false, entered: false }"
     x-init="setTimeout(() => entered = true, 80)">

    {{-- World backdrop --}}
    <div class="arena-lobby-world" aria-hidden="true">
        <div class="arena-lobby-sky"></div>
        <div class="arena-lobby-grid"></div>
        <span class="arena-float-block arena-fb-a"></span>
        <span class="arena-float-block arena-fb-b"></span>
        <span class="arena-float-block arena-fb-c"></span>
        <span class="arena-float-block arena-fb-d"></span>
        <span class="arena-float-coin arena-fc-a"></span>
        <span class="arena-float-coin arena-fc-b"></span>
    </div>

    {{-- Top HUD --}}
    <header class="arena-lobby-hud arena-anim-in">
        <a href="{{ route('jagat-misi.index') }}" class="arena-hud-back">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span class="truncate">Kembali</span>
        </a>
        <div class="arena-hud-player">
            <span class="arena-hud-avatar" aria-hidden="true">{{ $playerInitial }}</span>
            <div class="min-w-0">
                <p class="arena-hud-name truncate">{{ $playerName }}</p>
                <p class="arena-hud-role">{{ $canManage ? 'Host · Guru' : 'Player · Siswa' }}</p>
            </div>
        </div>
    </header>

    @if(session('success'))
    <div class="relative z-[2] rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 border-2 border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="relative z-[2] rounded-2xl bg-rose-50 dark:bg-rose-900/40 border-2 border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm font-bold">{{ session('error') }}</div>
    @endif

    {{-- Lobby welcome --}}
    <section class="arena-lobby-welcome" :class="{ 'is-in': entered }">
        <div class="arena-lobby-mascot" aria-hidden="true">
            <div class="arena-mascot-ring"></div>
            <div class="arena-mascot-core">
                <span>AB</span>
            </div>
            <div class="arena-mascot-badge">ONLINE</div>
        </div>
        <p class="arena-lobby-kicker">Lobby kelas · Arena Belajar</p>
        <h1 class="arena-lobby-brand">Arena Belajar</h1>
        <p class="arena-lobby-tagline">Kuis cepat &amp; live, atau misi petualangan — skor bisa masuk rapor.</p>

        <div class="arena-lobby-stats">
            <div class="arena-chip3d">
                <strong>{{ $quizCount }}</strong>
                <span>Kuis</span>
            </div>
            <div class="arena-chip3d arena-chip3d-amber">
                <strong>{{ $missionCount }}</strong>
                <span>Misi ditugaskan</span>
            </div>
            <div class="arena-chip3d arena-chip3d-sky">
                <strong>LIVE</strong>
                <span>Sesi langsung</span>
            </div>
        </div>

        <div class="arena-lobby-actions">
            @if($canManage)
            <a href="{{ route('classroom.arena.create', $classroom) }}" class="arena-play-btn">
                <i data-lucide="plus" class="w-5 h-5"></i> Buat kuis
            </a>
            <a href="{{ route('jagat-misi.index') }}" class="arena-play-btn arena-play-btn-amber">
                <i data-lucide="compass" class="w-5 h-5"></i> Katalog misi
            </a>
            @else
            <button type="button" class="arena-play-btn" @click="mode='kuis'; $refs.discover?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                <i data-lucide="play" class="w-5 h-5"></i> Main sekarang
            </button>
            <a href="{{ route('jagat-misi.progress') }}" class="arena-play-btn arena-play-btn-ghost">
                <i data-lucide="trophy" class="w-5 h-5"></i> Progres saya
            </a>
            @endif
        </div>
        @if($canManage)
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-3 max-w-lg mx-auto leading-relaxed">
            <strong>Kuis Arena</strong> = penilaian cepat (bisa impor dari Asisten Guru).
            <strong>Misi</strong> = petualangan multi-langkah dari katalog.
            <strong>Nalar Guru</strong> di Asisten Guru = chat AI, bukan permainan.
        </p>
        @endif
    </section>

    {{-- Mode switch --}}
    <div class="arena-world-portals" role="tablist">
        <button type="button" class="arena-portal" :class="{ active: mode === 'kuis' }" @click="mode='kuis'" role="tab">
            <span class="arena-portal-thumb arena-portal-thumb-kuis" aria-hidden="true">
                <i data-lucide="gamepad-2" class="w-10 h-10"></i>
                <span class="arena-portal-shine"></span>
            </span>
            <span class="arena-portal-body">
                <span class="arena-portal-label">Mode</span>
                <span class="arena-portal-title">Kuis Arena</span>
                <span class="arena-portal-meta">{{ $quizCount }} kuis · async &amp; live</span>
            </span>
            <span class="arena-portal-join" x-show="mode === 'kuis'">Aktif</span>
        </button>
        <button type="button" class="arena-portal" :class="{ active: mode === 'misi' }" @click="mode='misi'" role="tab">
            <span class="arena-portal-thumb arena-portal-thumb-misi" aria-hidden="true">
                <i data-lucide="compass" class="w-10 h-10"></i>
                <span class="arena-portal-shine"></span>
            </span>
            <span class="arena-portal-body">
                <span class="arena-portal-label">Mode</span>
                <span class="arena-portal-title">Misi petualangan</span>
                <span class="arena-portal-meta">{{ $missionCount }} ditugaskan · cerita · keputusan · puzzle</span>
            </span>
            <span class="arena-portal-join" x-show="mode === 'misi'">Aktif</span>
        </button>
    </div>

    {{-- ===== DISCOVER: KUIS ===== --}}
    <section x-ref="discover" x-show="mode==='kuis'" x-cloak class="arena-discover space-y-4">
        <div class="arena-discover-head">
            <div>
                <p class="arena-lobby-kicker" style="color:var(--arena-teal)">Kuis</p>
                <h2 class="arena-discover-title">Kuis di kelas ini</h2>
            </div>
            @if($canManage)
            <a href="{{ route('classroom.arena.create', $classroom) }}" class="arena-mini-cta">
                <i data-lucide="plus" class="w-4 h-4"></i> Baru
            </a>
            @endif
        </div>

        <div class="arena-xp-grid">
            @forelse($quizzes as $q)
            @php [$a, $b] = $artPairs[$loop->index % count($artPairs)]; @endphp
            <a href="{{ route('classroom.arena.show', [$classroom, $q]) }}"
               class="arena-xp-card arena-anim-in"
               style="animation-delay: {{ $loop->index * 50 }}ms; --art-a:{{ $a }};--art-b:{{ $b }}">
                <div class="arena-xp-thumb">
                    <span class="arena-xp-blocks" aria-hidden="true"></span>
                    <i data-lucide="gamepad-2" class="w-11 h-11"></i>
                    <span class="arena-xp-play"><i data-lucide="play" class="w-4 h-4 fill-current"></i></span>
                    <span class="arena-xp-status">{{ $q->statusLabel() }}</span>
                </div>
                <div class="arena-xp-info">
                    <h3 class="arena-xp-title">{{ $q->title }}</h3>
                    <p class="arena-xp-meta">
                        <span>{{ $q->questions_count }} soal</span>
                        <span>·</span>
                        <span>{{ $q->max_score }} XP</span>
                        @if($q->due_at)
                        <span>·</span>
                        <span>{{ $q->due_at->locale('id')->translatedFormat('d M') }}</span>
                        @endif
                    </p>
                    <span class="arena-xp-cta">Mainkan</span>
                </div>
            </a>
            @empty
            <div class="arena-xp-empty">
                <div class="arena-xp-empty-ico"><i data-lucide="gamepad-2" class="w-9 h-9"></i></div>
                <p class="font-black text-lg text-slate-800 dark:text-slate-100">Belum ada kuis</p>
                <p class="text-sm text-slate-500 mt-1">Buat kuis baru, atau impor dari Asisten Guru (Generator Soal / Nalar Guru).</p>
                @if($canManage)
                <a href="{{ route('classroom.arena.create', $classroom) }}" class="arena-play-btn mt-4 inline-flex">Buat kuis</a>
                @endif
            </div>
            @endforelse
        </div>
    </section>

    {{-- ===== MISI ===== --}}
    <section x-show="mode==='misi'" x-cloak class="arena-discover space-y-4">
        <div class="arena-discover-head">
            <div>
                <p class="arena-lobby-kicker" style="color:var(--arena-amber)">Misi</p>
                <h2 class="arena-discover-title">Misi petualangan</h2>
            </div>
            @if($canManage)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('jagat-misi.index') }}" class="arena-mini-cta">Katalog</a>
                @can('create', \App\Models\Mission::class)
                <a href="{{ route('jagat-misi.builder.index') }}" class="arena-mini-cta arena-mini-cta-ghost">Kelola katalog</a>
                @endcan
                @can('viewAnalytics', \App\Models\Mission::class)
                <a href="{{ route('jagat-misi.analytics') }}" class="arena-mini-cta arena-mini-cta-ghost">Analitik</a>
                @endcan
            </div>
            @endif
        </div>

        @if($canManage)
        <div class="arena-panel arena-jenjang-panel arena-lobby-panel">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
                <div>
                    <p class="arena-lobby-kicker" style="color:var(--arena-teal)">Katalog siap main</p>
                    <h3 class="font-black text-slate-800 dark:text-slate-100 m-0 text-lg">Filter jenjang</h3>
                </div>
                <div class="arena-jenjang-filters" role="group" aria-label="Filter jenjang">
                    <button type="button" class="arena-jenjang-chip" :class="{ active: jenjang === 'semua' && !hanyaTren }" @click="jenjang='semua'; hanyaTren=false">Semua</button>
                    <button type="button" class="arena-jenjang-chip arena-jenjang-sd" :class="{ active: jenjang === 'sd' }" @click="jenjang='sd'; hanyaTren=false">SD</button>
                    <button type="button" class="arena-jenjang-chip arena-jenjang-smp" :class="{ active: jenjang === 'smp' }" @click="jenjang='smp'; hanyaTren=false">SMP</button>
                    <button type="button" class="arena-jenjang-chip arena-jenjang-sma" :class="{ active: jenjang === 'sma' }" @click="jenjang='sma'; hanyaTren=false">SMA/SMK</button>
                    <button type="button" class="arena-jenjang-chip arena-jenjang-tren" :class="{ active: hanyaTren }" @click="hanyaTren=true; jenjang='semua'">Tren</button>
                </div>
            </div>

            @php $katalog = ($katalogMisi ?? collect()); @endphp
            @if($katalog->isNotEmpty())
            <div class="arena-jenjang-grid">
                @foreach(['sd', 'smp', 'sma', 'umum'] as $key)
                @php
                    $items = $katalog->filter(fn ($m) => $m->jenjangKey() === $key);
                    $label = $key === 'umum' ? 'Umum' : \App\Support\ArenaJenjang::label($key);
                    $hasTren = $items->contains(fn ($m) => $m->isTren());
                @endphp
                @continue($items->isEmpty())
                <div class="arena-jenjang-card arena-jenjang-{{ $key === 'umum' ? 'smp' : $key }}"
                     x-show="(!hanyaTren && (jenjang === 'semua' || jenjang === '{{ $key }}')) || (hanyaTren && {{ $hasTren ? 'true' : 'false' }})">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="arena-pill arena-pill-jenjang arena-pill-{{ $key === 'umum' ? 'umum' : $key }}">{{ $label }}</span>
                        <span class="text-[11px] font-bold text-slate-400">{{ $items->count() }} misi</span>
                    </div>
                    <ul class="arena-jenjang-list">
                        @foreach($items as $m)
                        <li x-show="!hanyaTren || {{ $m->isTren() ? 'true' : 'false' }}">
                            <strong>{{ $m->title }}</strong>
                            <span>{{ $m->mechanicLabel() }} · {{ $m->subject }}{{ $m->isTren() ? ' · Tren' : '' }}</span>
                            <em>{{ $m->steps_count }} langkah · siap ditugaskan</em>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-500 m-0">Belum ada misi siap main di katalog. Jalankan seeder misi atau minta admin mengisi langkah permainan.</p>
            @endif

            <details class="mt-4 rounded-xl border border-slate-200 dark:border-slate-700 p-3 bg-white/70 dark:bg-slate-900/40">
                <summary class="cursor-pointer text-sm font-bold text-slate-600 dark:text-slate-300">Ide tambahan (belum otomatis jadi misi)</summary>
                <p class="text-xs text-slate-500 mt-2 mb-3">Inspirasi topik — bukan tombol main. Buat kuis di Kuis Arena, atau pilih misi katalog yang sesuai.</p>
                <div class="arena-jenjang-grid">
                    @foreach(($ideJenjang ?? []) as $key => $items)
                    @php $label = \App\Support\ArenaJenjang::label($key); @endphp
                    <div class="arena-jenjang-card arena-jenjang-{{ $key }}">
                        <span class="arena-pill arena-pill-jenjang arena-pill-{{ $key }} mb-2 inline-flex">{{ $label }}</span>
                        <ul class="arena-jenjang-list">
                            @foreach($items as $rec)
                            <li>
                                <strong>{{ $rec['title'] }}</strong>
                                <span>{{ $rec['mechanic'] }} · {{ $rec['subject'] }}</span>
                                <em>{{ $rec['why'] }}</em>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>
            </details>
        </div>
        @endif

        @if($canManage && $availableMissions->isNotEmpty())
        <div class="arena-panel arena-lobby-panel">
            <p class="arena-lobby-kicker" style="color:var(--arena-teal)">Untuk guru</p>
            <h3 class="font-black text-slate-800 dark:text-slate-100 mb-3 text-lg">Tugaskan misi ke kelas</h3>
            <form method="POST" action="{{ route('classroom.jagat.assign', $classroom) }}" class="space-y-3">
                @csrf
                <select name="mission_id" required class="w-full rounded-2xl border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[48px] font-semibold">
                    <option value="">— Pilih misi siap main —</option>
                    @foreach($availableMissions as $m)
                    <option value="{{ $m->uuid }}">[{{ $m->jenjangLabel() }}] {{ $m->title }} · {{ $m->mechanicLabel() }}</option>
                    @endforeach
                </select>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-slate-500">Buka mulai</label>
                        <input type="datetime-local" name="opens_at" class="w-full rounded-2xl border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Batas waktu</label>
                        <input type="datetime-local" name="due_at" class="w-full rounded-2xl border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm mt-1">
                    </div>
                </div>
                <button type="submit" class="arena-play-btn">Tugaskan misi</button>
            </form>
        </div>
        @endif

        <div class="arena-xp-grid">
            @forelse($missionAssignments as $a)
            @php $mission = $a->mission; @endphp
            @continue(! $mission)
            @php
                $playable = $mission->isPlayable();
                [$artA, $artB] = $artPairs[$loop->index % count($artPairs)];
                $mechanicLabel = $mission->mechanicLabel();
                $jenjangKey = $mission->jenjangKey();
                $jenjangLabel = $mission->jenjangLabel();
                $isTren = $mission->isTren();
            @endphp
            @if(auth()->user()->access === 'siswa' && ! $playable)
                @continue
            @endif
            @if($playable)
            <a href="{{ route('classroom.jagat.show', [$classroom, $mission]) }}"
               class="arena-xp-card arena-anim-in"
               style="animation-delay: {{ $loop->index * 50 }}ms; --art-a:{{ $artA }};--art-b:{{ $artB }}"
               x-show="(!hanyaTren || {{ $isTren ? 'true' : 'false' }}) && (jenjang === 'semua' || jenjang === '{{ $jenjangKey }}')"
               x-cloak>
            @else
            <div class="arena-xp-card arena-anim-in opacity-80"
               style="animation-delay: {{ $loop->index * 50 }}ms; --art-a:{{ $artA }};--art-b:{{ $artB }}"
               x-show="(!hanyaTren || {{ $isTren ? 'true' : 'false' }}) && (jenjang === 'semua' || jenjang === '{{ $jenjangKey }}')"
               x-cloak>
            @endif
                <div class="arena-xp-thumb">
                    <span class="arena-xp-blocks" aria-hidden="true"></span>
                    <i data-lucide="compass" class="w-11 h-11"></i>
                    @if($playable)
                    <span class="arena-xp-play"><i data-lucide="play" class="w-4 h-4 fill-current"></i></span>
                    @endif
                    <span class="arena-xp-status {{ $a->isOpen() ? '' : 'is-closed' }}">{{ $a->isOpen() ? 'Aktif' : 'Tutup' }}</span>
                </div>
                <div class="arena-xp-info">
                    <div class="flex flex-wrap gap-1 mb-1">
                        <span class="arena-pill arena-pill-jenjang arena-pill-{{ $jenjangKey }}">{{ $jenjangLabel }}</span>
                        @if($isTren)<span class="arena-pill arena-pill-tren">Tren</span>@endif
                        <span class="arena-pill">{{ $mechanicLabel }}</span>
                        @unless($playable)
                        <span class="arena-pill" style="background:#fef3c7;color:#92400e">Metadata saja</span>
                        @endunless
                    </div>
                    <h3 class="arena-xp-title">{{ $mission->title }}</h3>
                    <p class="arena-xp-meta">
                        <span>{{ $mission->subject }}</span>
                        @if($a->due_at)
                        <span>·</span>
                        <span>{{ $a->due_at->locale('id')->translatedFormat('d M') }}</span>
                        @endif
                    </p>
                    @if(auth()->user()->access === 'siswa')
                        @php $att = $myMissionAttempts[$a->uuid] ?? null; @endphp
                        <p class="text-xs font-black m-0 mt-1">
                            @if($att && $att->status === 'completed')
                                <span class="text-emerald-600">★ Selesai {{ $att->score }}%</span>
                            @elseif($att)
                                <span class="text-sky-600">▶ Sedang dikerjakan</span>
                            @else
                                <span class="text-amber-600">● Belum main</span>
                            @endif
                        </p>
                    @endif
                    <span class="arena-xp-cta">{{ $playable ? (auth()->user()->access === 'siswa' ? 'Masuk misi' : 'Lihat misi') : 'Belum siap dimainkan' }}</span>
                </div>
            @if($playable)
            </a>
            @else
            </div>
            @endif
            @empty
            <div class="arena-xp-empty">
                <div class="arena-xp-empty-ico" style="background:#fff4e0;color:#9a6700"><i data-lucide="compass" class="w-9 h-9"></i></div>
                <p class="font-black text-lg text-slate-800 dark:text-slate-100">Belum ada misi ditugaskan</p>
                @if($canManage)
                <p class="text-sm text-slate-500 mt-1">Pilih misi siap main dari form di atas atau <a href="{{ route('jagat-misi.index') }}" class="font-bold" style="color:var(--arena-teal)">katalog</a>.</p>
                @else
                <p class="text-sm text-slate-500 mt-1">Guru belum menugaskan misi untuk kelas ini.</p>
                @endif
            </div>
            @endforelse
        </div>
    </section>
</div>

@endsection
