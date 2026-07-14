@extends('layouts.app')
@section('title', $quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 max-w-3xl mx-auto arena-stage">
    <div class="arena-hero p-5 sm:p-7 relative">
        <div class="relative z-[1]">
            <a href="{{ route('classroom.arena.index', $classroom) }}" class="text-xs text-slate-300 hover:text-white inline-flex items-center gap-1 mb-3">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Arena Belajar
            </a>
            <div class="flex flex-wrap gap-2 mb-3">
                <span class="arena-chip">{{ $quiz->statusLabel() }}</span>
                <span class="arena-chip">{{ $quiz->scoringModeLabel() }}</span>
                <span class="arena-chip">{{ $quiz->questions->count() }} soal</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight leading-tight">{{ $quiz->title }}</h1>
            <p class="text-sm text-slate-300 mt-2">
                Nilai maks {{ $quiz->max_score }}
                @if($quiz->due_at) · Batas {{ $quiz->due_at->locale('id')->translatedFormat('d M Y H:i') }}@endif
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    @if($quiz->instructions)
    <div class="arena-card-game p-4">
        <h2 class="text-sm font-black text-slate-700 dark:text-slate-200 mb-2">Petunjuk</h2>
        @include('classroom.partials.richbody', ['html' => $quiz->instructions])
    </div>
    @endif

    @if(auth()->user()->access === 'siswa')
    <div class="arena-card-game p-5 space-y-3">
        @if($myAttempt && $myAttempt->isSubmitted())
            <p class="text-sm text-slate-600 dark:text-slate-300 font-semibold">Run selesai. Lihat skor dan podiummu.</p>
            <a href="{{ route('classroom.arena.result', [$classroom, $quiz, $myAttempt]) }}" class="arena-cta w-full"
               style="background:linear-gradient(135deg,var(--cp),color-mix(in srgb,var(--cp) 60%,#0c1a24));color:#fff">
                Lihat skor
            </a>
        @elseif($quiz->isPublished() && $quiz->isOpenNow($assignment))
            <form method="POST" action="{{ route('classroom.arena.start', [$classroom, $quiz]) }}">@csrf
                <button class="arena-cta w-full" style="background:linear-gradient(135deg,var(--cp),color-mix(in srgb,var(--cp) 55%,#0c1a24));color:#fff">
                    <i data-lucide="play" class="w-4 h-4"></i>
                    {{ $myAttempt ? 'Lanjutkan tantangan' : 'Mulai tantangan' }}
                </button>
            </form>
            <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold border-2 min-h-[48px]"
               style="border-color:color-mix(in srgb,var(--cp) 40%,transparent);color:var(--cp)">
                <i data-lucide="radio" class="w-4 h-4"></i> Gabung Live Arena
            </a>
        @elseif(!$quiz->isPublished())
            <p class="text-sm text-amber-600 font-semibold">Kuis belum diterbitkan.</p>
        @else
            <p class="text-sm text-amber-600 font-semibold">Kuis belum dibuka atau sudah ditutup.</p>
            <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold border min-h-[48px]"
               style="border-color:color-mix(in srgb,var(--cp) 40%,transparent);color:var(--cp)">
                <i data-lucide="radio" class="w-4 h-4"></i> Gabung Live
            </a>
        @endif
    </div>
    @endif

    @if($canManage)
    <div class="arena-card-game p-4 space-y-4">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Panel guru</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @if($quiz->status !== 'published')
            <form method="POST" action="{{ route('classroom.arena.publish', [$classroom, $quiz]) }}" class="contents">@csrf
                <button class="col-span-2 sm:col-span-1 px-3 py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)">Terbitkan</button>
            </form>
            @endif
            <a href="{{ route('classroom.arena.live', [$classroom, $quiz]) }}" class="px-3 py-3 rounded-xl text-sm font-bold text-white min-h-[48px] inline-flex items-center justify-center gap-1.5"
               style="background:linear-gradient(135deg,#0c1a24,#1e3a4c)">
                <i data-lucide="radio" class="w-4 h-4"></i> Live
            </a>
            <a href="{{ route('classroom.arena.results', [$classroom, $quiz]) }}" class="px-3 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 min-h-[48px] inline-flex items-center justify-center gap-1.5">Hasil</a>
            <a href="{{ route('classroom.arena.edit', [$classroom, $quiz]) }}" class="px-3 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 min-h-[48px] inline-flex items-center justify-center">Edit</a>
            <a href="{{ route('classroom.arena.teams', [$classroom, $quiz]) }}" class="px-3 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 min-h-[48px] inline-flex items-center justify-center">Tim</a>
            <a href="{{ route('classroom.arena.template.play', [$classroom, $quiz]) }}" class="px-3 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 min-h-[48px] inline-flex items-center justify-center">Template</a>
            <a href="{{ route('classroom.arena.pdf', [$classroom, $quiz]) }}" class="px-3 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 min-h-[48px] inline-flex items-center justify-center">PDF</a>
            <a href="{{ route('classroom.arena.pdf', [$classroom, $quiz, 'kunci' => 1]) }}" class="px-3 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 min-h-[48px] inline-flex items-center justify-center">PDF kunci</a>
        </div>
        <form method="POST" action="{{ route('classroom.arena.template', [$classroom, $quiz]) }}" class="flex items-center gap-2 flex-wrap">
            @csrf
            <label class="text-xs font-bold text-slate-500">Template</label>
            <select name="template" class="rounded-lg border border-slate-200 dark:border-slate-600 text-sm px-2 py-2" onchange="this.form.submit()">
                @foreach(['quiz'=>'Quiz','match'=>'Pasangkan','flashcard'=>'Flashcard','crossword'=>'Teka-teki','unjumble'=>'Susun kata'] as $tv=>$tl)
                <option value="{{ $tv }}" @selected(($quiz->template ?? 'quiz')===$tv)>{{ $tl }}</option>
                @endforeach
            </select>
        </form>
        <details>
            <summary class="text-sm font-semibold text-slate-600 cursor-pointer">Pratinjau soal (kunci terlihat)</summary>
            <ol class="mt-3 space-y-3 list-decimal pl-5 text-sm">
                @foreach($quiz->questions as $q)
                <li>
                    <p class="font-medium text-slate-800 dark:text-slate-100">{{ $q->question_text }}</p>
                    <ul class="mt-1 space-y-0.5 text-slate-600 dark:text-slate-300">
                        @foreach($q->options as $o)
                        <li class="{{ $o->is_correct ? 'text-emerald-600 font-semibold' : '' }}">{{ $o->option_text }}@if($o->is_correct) ✓@endif</li>
                        @endforeach
                    </ul>
                </li>
                @endforeach
            </ol>
        </details>
        <form method="POST" action="{{ route('classroom.arena.destroy', [$classroom, $quiz]) }}" onsubmit="return confirm('Hapus kuis ini?')">
            @csrf @method('DELETE')
            <button class="text-sm font-semibold text-rose-600">Hapus kuis</button>
        </form>
    </div>
    @endif
</div>
@endsection
