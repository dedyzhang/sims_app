@extends('layouts.app')
@section('title', $quiz->title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@php
    $isLive = $quiz->hasActiveLiveSession($classroom);
    $playerName = auth()->user()->displayName();
@endphp

@section('content')
<div class="arena-stage arena-rx arena-rx-detail space-y-5">
    <div class="arena-rx-detail-hero p-5 sm:p-8 relative">
        <div class="arena-rx-detail-hero-grid" aria-hidden="true"></div>
        <div class="relative z-[1] space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'kuis']) }}"
                   class="arena-hud-back !bg-white/15 !border-white/20 !text-white !shadow-none hover:!bg-white/25">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    <span>Lobby kelas</span>
                </a>
                <div class="flex flex-wrap gap-2">
                    @if($isLive)
                    <span class="arena-rx-flag arena-rx-flag-live">Live now</span>
                    @endif
                    <span class="arena-rx-flag {{ $quiz->status === 'published' ? 'arena-rx-flag-ok' : ($quiz->status === 'closed' ? 'arena-rx-flag-closed' : 'arena-rx-flag-draft') }}">{{ $quiz->statusLabel() }}</span>
                    <span class="arena-rx-flag">{{ $quiz->scoringModeLabel() }}</span>
                    <span class="arena-rx-flag">{{ $quiz->questions->count() }} soal</span>
                </div>
            </div>

            <p class="m-0 text-xs font-bold uppercase tracking-[0.14em] text-teal-200/90">Experience · Kuis Arena</p>
            <h1 class="m-0 text-3xl sm:text-4xl font-black tracking-tight leading-none" style="font-family:'Fredoka',sans-serif">{{ $quiz->title }}</h1>
            <p class="m-0 text-sm font-semibold text-slate-300 max-w-xl">
                {{ $playerName }}, pilih mode: solo challenge atau live bareng kelas.
                Nilai maks <strong class="text-white">{{ $quiz->max_score }} XP</strong>
                @if($quiz->due_at)
                · Batas {{ $quiz->due_at->locale('id')->translatedFormat('d M Y H:i') }}
                @endif
            </p>

            <div class="arena-rx-cta-row pt-1">
                @if(auth()->user()->access === 'siswa')
                    @if($myAttempt && $myAttempt->isSubmitted())
                        <a href="{{ route('classroom.arena.result', [$classroom, $quiz, $myAttempt]) }}" class="arena-rx-cta-big solo">
                            <i data-lucide="trophy" class="w-5 h-5"></i> Lihat skor
                        </a>
                        @if($quiz->allowsLive())
                        <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="arena-rx-cta-big live">
                            <i data-lucide="radio" class="w-5 h-5"></i> {{ $isLive ? 'Gabung Live' : 'Lobby Live' }}
                        </a>
                        @endif
                    @elseif($quiz->allowsSolo() && $quiz->isPublished() && $quiz->isOpenNow($assignment))
                        <form method="POST" action="{{ route('classroom.arena.start', [$classroom, $quiz]) }}" class="m-0 w-full">
                            @csrf
                            <button type="submit" class="arena-rx-cta-big solo">
                                <i data-lucide="play" class="w-5 h-5"></i>
                                {{ $myAttempt ? 'Lanjut solo' : 'Main solo' }}
                            </button>
                        </form>
                        @if($quiz->allowsLive())
                        <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="arena-rx-cta-big live">
                            <i data-lucide="radio" class="w-5 h-5"></i> {{ $isLive ? 'Masuk Live' : 'Gabung Live' }}
                        </a>
                        @endif
                    @elseif($quiz->allowsLive())
                        <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="arena-rx-cta-big live sm:col-span-2">
                            <i data-lucide="radio" class="w-5 h-5"></i> {{ $isLive ? 'Masuk Live sekarang' : 'Cek Lobby Live' }}
                        </a>
                        @unless($quiz->isPublished())
                        <p class="m-0 text-sm font-bold text-amber-200 sm:col-span-2">Kuis masih draft — tunggu guru menerbitkan.</p>
                        @else
                        <p class="m-0 text-sm font-bold text-amber-200 sm:col-span-2">
                            {{ $quiz->allowsSolo() ? 'Jendela solo belum dibuka / sudah ditutup. Live tetap bisa dicoba jika host membuka sesi.' : 'Kuis ini disetel live saja — tunggu host membuka sesi.' }}
                        </p>
                        @endunless
                    @else
                        <p class="m-0 text-sm font-bold text-amber-200 sm:col-span-2">
                            @unless($quiz->isPublished())
                            Kuis masih draft — tunggu guru menerbitkan.
                            @else
                            Jendela solo belum dibuka / sudah ditutup.
                            @endunless
                        </p>
                    @endif
                @elseif($canManage)
                    <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="arena-rx-cta-big live">
                        <i data-lucide="radio" class="w-5 h-5"></i> {{ $isLive ? 'Lanjut host Live' : 'Buka Live Arena' }}
                    </a>
                    <a href="{{ route('classroom.arena.results', [$classroom, $quiz]) }}" class="arena-rx-cta-big solo">
                        <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Podium &amp; hasil
                    </a>
                @endif
            </div>
            @if(auth()->user()->access === 'siswa')
            <p class="m-0 text-xs font-semibold text-slate-400">Cara main: {{ $quiz->playModeLabel() }}</p>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 border-2 border-emerald-300 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-2xl bg-rose-50 dark:bg-rose-900/40 border-2 border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm font-bold">{{ session('error') }}</div>
    @endif

    @if($quiz->instructions)
    <div class="arena-rx-detail-panel">
        <p class="arena-lobby-kicker m-0 mb-2" style="color:var(--arena-teal)">Petunjuk world</p>
        <div class="text-sm text-slate-700 dark:text-slate-200 font-medium leading-relaxed">
            @include('classroom.partials.richbody', ['html' => $quiz->instructions])
        </div>
    </div>
    @endif

    @if($canManage)
    <div class="arena-rx-detail-panel arena-rx-host-deck space-y-5"
         @if($quiz->status !== 'published')
         x-data="{ showPublishCoach: true }"
         x-init="
            $nextTick(() => {
                const el = document.getElementById('arena-publish-spot');
                if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'center' }), 450);
            });
         "
         @endif>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="arena-lobby-kicker m-0" style="color:var(--arena-amber)">Host controls</p>
                <h2 class="m-0 text-lg font-black text-slate-800 dark:text-slate-100">Panel guru</h2>
                <p class="m-0 mt-1 text-xs font-semibold text-slate-500">Kelola experience, pantau skor, dan ekspor soal.</p>
            </div>
            <span class="arena-rx-host-badge">
                <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                {{ $quiz->questions->count() }} soal · {{ $quiz->statusLabel() }}
            </span>
        </div>

        <div class="arena-rx-manage-section" @if($quiz->status !== 'published') style="padding-top: 5.5rem" @endif>
            <p class="arena-rx-manage-label">Aksi cepat</p>
            <div class="arena-rx-manage-grid">
                @if($quiz->status !== 'published')
                <div class="arena-rx-publish-spot" id="arena-publish-spot" :class="{ 'is-coaching': showPublishCoach }">
                    <div class="arena-rx-point-coach" x-show="showPublishCoach" x-cloak aria-hidden="true">
                        <div class="arena-rx-point-bubble">
                            <strong>Langkah berikutnya</strong>
                            <span>Klik <em>Terbitkan</em> agar siswa bisa main</span>
                            <button type="button" class="arena-rx-point-dismiss" @click="showPublishCoach = false" aria-label="Tutup petunjuk">×</button>
                        </div>
                        <div class="arena-rx-point-finger" title="Klik Terbitkan">
                            <svg viewBox="0 0 64 96" width="48" height="72" aria-hidden="true">
                                {{-- Jari menunjuk ke bawah --}}
                                <g transform="rotate(180 32 48)">
                                    <path fill="#ffe0b8" stroke="#c47a3a" stroke-width="2.2" stroke-linejoin="round"
                                          d="M28 6c-3.2 0-5.8 2.6-5.8 5.8V42l-7.2-4.2c-2.8-1.6-6.4-.6-7.8 2.1-1.4 2.7-.3 6 2.4 7.4L28 58v30h18V52.5c0-2.2 1.2-4.2 3.1-5.2l6.4-3.4c2.2-1.2 3-3.9 1.8-6.1-1.2-2.2-3.9-3-6.1-1.8l-4.2 2.2V11.8C47 8.6 44.4 6 41.2 6H28z"/>
                                    <path fill="#ffcc80" d="M28 6h13.2c2.4 0 4.4 1.5 5.2 3.6-1.4-.6-3-.9-4.6-.9H30c-1.4 0-2.6.4-3.6 1.1V11.8C26.4 8.4 27 6 28 6z"/>
                                </g>
                            </svg>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('classroom.arena.publish', [$classroom, $quiz]) }}" class="m-0">
                        @csrf
                        <button type="submit" class="arena-rx-tool tone-publish is-spotlight"
                                @click="showPublishCoach = false"
                                onclick="return confirm('Terbitkan experience ini? Siswa akan bisa mulai bermain.')">
                            <span class="arena-rx-tool-ico"><i data-lucide="rocket"></i></span>
                            <span class="arena-rx-tool-copy">
                                <strong>Terbitkan</strong>
                                <small>Buka untuk siswa</small>
                            </span>
                            <span class="arena-rx-publish-glow" aria-hidden="true"></span>
                        </button>
                    </form>
                </div>
                @endif
                <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="arena-rx-tool tone-live">
                    <span class="arena-rx-tool-ico"><i data-lucide="radio"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>{{ $isLive ? 'Lanjut Live' : 'Live Arena' }}</strong>
                        <small>{{ $isLive ? 'Sesi sedang aktif' : 'Host kuis bareng kelas' }}</small>
                    </span>
                    @if($isLive)<span class="arena-rx-tool-pulse" aria-hidden="true"></span>@endif
                </a>
                <a href="{{ route('classroom.arena.results', [$classroom, $quiz]) }}" class="arena-rx-tool tone-hasil">
                    <span class="arena-rx-tool-ico"><i data-lucide="trophy"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>Hasil</strong>
                        <small>Podium &amp; transfer nilai</small>
                    </span>
                </a>
                <a href="{{ route('classroom.arena.edit', [$classroom, $quiz]) }}" class="arena-rx-tool tone-edit">
                    <span class="arena-rx-tool-ico"><i data-lucide="pencil"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>Edit</strong>
                        <small>Ubah soal &amp; pengaturan</small>
                    </span>
                </a>
            </div>
        </div>

        <div class="arena-rx-manage-section">
            <p class="arena-rx-manage-label">Mode &amp; ekspor</p>
            <div class="arena-rx-manage-grid">
                <a href="{{ route('classroom.arena.teams', [$classroom, $quiz]) }}" class="arena-rx-tool tone-tim">
                    <span class="arena-rx-tool-ico"><i data-lucide="users"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>Tim</strong>
                        <small>Bagi kelompok podium</small>
                    </span>
                </a>
                <a href="{{ route('classroom.arena.template.play', [$classroom, $quiz]) }}" class="arena-rx-tool tone-template">
                    <span class="arena-rx-tool-ico"><i data-lucide="layout-grid"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>Template</strong>
                        <small>Pratinjau skin / ular tangga</small>
                    </span>
                </a>
                <a href="{{ route('classroom.arena.pdf', [$classroom, $quiz]) }}" class="arena-rx-tool tone-pdf">
                    <span class="arena-rx-tool-ico"><i data-lucide="file-down"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>PDF</strong>
                        <small>Worksheet tanpa kunci</small>
                    </span>
                </a>
                <a href="{{ route('classroom.arena.pdf', [$classroom, $quiz, 'kunci' => 1]) }}" class="arena-rx-tool tone-key">
                    <span class="arena-rx-tool-ico"><i data-lucide="key-round"></i></span>
                    <span class="arena-rx-tool-copy">
                        <strong>PDF kunci</strong>
                        <small>Lembar jawaban guru</small>
                    </span>
                </a>
            </div>
        </div>

        <div class="arena-rx-skin-bar">
            <div class="min-w-0">
                <p class="m-0 text-[11px] font-black uppercase tracking-wider text-slate-400">Skin template</p>
                <p class="m-0 text-xs font-semibold text-slate-500 truncate">Dipakai saat main mode template</p>
            </div>
            <form method="POST" action="{{ route('classroom.arena.template', [$classroom, $quiz]) }}" class="m-0">
                @csrf
                <select name="template" class="arena-rx-skin-select" onchange="this.form.submit()" aria-label="Skin template">
                    @foreach(['quiz'=>'Quiz','match'=>'Pasangkan','flashcard'=>'Flashcard','crossword'=>'Teka-teki','unjumble'=>'Susun kata','ular_tangga'=>'Ular tangga'] as $tv=>$tl)
                    <option value="{{ $tv }}" @selected(($quiz->template ?? 'quiz')===$tv)>{{ $tl }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <details class="arena-rx-preview">
            <summary>
                <span class="arena-rx-tool-ico tone-preview-ico"><i data-lucide="eye"></i></span>
                <span>
                    <strong>Pratinjau soal</strong>
                    <small>Kunci jawaban terlihat</small>
                </span>
                <i data-lucide="chevron-down" class="arena-rx-preview-chevron"></i>
            </summary>
            <ol class="mt-3 space-y-3 list-decimal pl-5 text-sm">
                @foreach($quiz->questions as $q)
                <li>
                    <p class="font-bold text-slate-800 dark:text-slate-100 m-0">{{ $q->question_text }}</p>
                    <ul class="mt-1 space-y-0.5 text-slate-600 dark:text-slate-300">
                        @foreach($q->options as $o)
                        <li class="{{ $o->is_correct ? 'text-emerald-600 font-bold' : '' }}">{{ $o->option_text }}@if($o->is_correct) ✓@endif</li>
                        @endforeach
                    </ul>
                </li>
                @endforeach
            </ol>
        </details>

        <form method="POST" action="{{ route('classroom.arena.destroy', [$classroom, $quiz]) }}" onsubmit="return confirm('Hapus kuis ini?')" class="arena-rx-danger-row">
            @csrf @method('DELETE')
            <button type="submit" class="arena-rx-danger-btn">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Hapus experience
            </button>
        </form>
    </div>

    @if(($copyTargets ?? collect())->isNotEmpty() && $quiz->questions->isNotEmpty())
    <div class="arena-rx-detail-panel space-y-4" x-data="{ selected: {{ $copyTargets->count() <= 4 ? $copyTargets->pluck('uuid')->values()->toJson() : '[]' }}, all: @js($copyTargets->pluck('uuid')->values()) }">
        <div>
            <p class="arena-lobby-kicker m-0" style="color:var(--arena-teal)">Cross-class</p>
            <h2 class="m-0 text-lg font-black text-slate-800 dark:text-slate-100">Salin soal ke kelas lain</h2>
            <p class="m-0 mt-1 text-xs font-semibold text-slate-500">
                Contoh: dari {{ $classroom->title }} ke 8B / 8C / 8D (mapel yang sama). Salinan jadi kuis baru di kelas tujuan.
            </p>
        </div>
        <form method="POST" action="{{ route('classroom.arena.copy', [$classroom, $quiz]) }}" class="space-y-3"
              @submit="
                if (!selected.length) { $event.preventDefault(); alert('Pilih minimal satu kelas.'); return; }
                if (!confirm('Salin soal ke kelas yang dipilih?')) { $event.preventDefault(); }
              ">
            @csrf
            <div class="flex flex-wrap gap-2">
                <button type="button" class="arena-rx-btn arena-rx-btn-ghost !min-h-[2.2rem] !text-[11px]"
                        @click="selected = [...all]">Pilih semua</button>
                <button type="button" class="arena-rx-btn arena-rx-btn-ghost !min-h-[2.2rem] !text-[11px]"
                        @click="selected = []">Kosongkan</button>
            </div>
            <div class="arena-rx-copy-list">
                @foreach($copyTargets as $target)
                <label class="arena-rx-copy-item">
                    <input type="checkbox" name="classroom_ids[]" value="{{ $target->uuid }}" x-model="selected">
                    <span class="min-w-0">
                        <span class="block truncate">{{ $target->title }}</span>
                        <span class="block text-[11px] font-semibold text-slate-400">
                            {{ $target->rombel?->tingkat }}{{ $target->rombel?->kelas }}
                            @if($target->pelajaran) · {{ $target->pelajaran->nama }}@endif
                        </span>
                    </span>
                </label>
                @endforeach
            </div>
            <label class="flex items-center gap-2 text-sm font-bold text-slate-600 dark:text-slate-300 min-h-[44px]">
                <input type="checkbox" name="publish_copies" value="1" class="rounded accent-teal-600">
                Langsung terbitkan salinan di kelas tujuan
            </label>
            <button type="submit" class="arena-rx-cta-big solo !w-auto">
                <i data-lucide="copy" class="w-5 h-5"></i> Salin ke kelas terpilih
            </button>
        </form>
    </div>
    @elseif($quiz->questions->isNotEmpty())
    <div class="arena-rx-detail-panel">
        <p class="m-0 text-sm font-semibold text-slate-500">
            Belum ada kelas tujuan untuk disalin (mapel yang sama &amp; Anda punya akses kelola).
        </p>
    </div>
    @endif
    @endif
</div>
@endsection
