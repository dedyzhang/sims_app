@extends('layouts.app')
@section('title', 'Hasil — '.$quiz->title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="arena-stage arena-rx arena-rx-detail space-y-5 max-w-lg mx-auto">
    <header class="arena-lobby-hud !mt-0">
        <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span class="truncate">Experience</span>
        </a>
    </header>

    <div class="arena-rx-detail-hero p-6 sm:p-8 text-center relative">
        <div class="arena-rx-detail-hero-grid" aria-hidden="true"></div>
        <div class="relative z-[1] space-y-4">
            <span class="arena-rx-flag arena-rx-flag-ok inline-flex">Run selesai</span>
            <h1 class="m-0 text-lg font-bold text-slate-200">{{ $quiz->title }}</h1>

            @if($showScore)
                <div class="arena-rx-result-orb">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest font-black opacity-90 m-0">Skor</p>
                        <p class="text-4xl font-black tabular-nums leading-none m-0" style="font-family:'Fredoka',sans-serif">{{ $attempt->total_score }}</p>
                    </div>
                </div>
                <p class="text-sm text-slate-300 font-semibold m-0">
                    {{ $attempt->correct_count }}/{{ $quiz->questions->count() }} benar · dari {{ $quiz->max_score }} XP
                </p>
            @else
                <p class="text-2xl font-black m-0" style="font-family:'Fredoka',sans-serif">Jawaban terkumpul</p>
                <p class="text-sm text-slate-300 font-semibold m-0">Skor disembunyikan guru.</p>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 border-2 border-emerald-300 text-emerald-800 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif

    @if($showScore && $attempt->answers->isNotEmpty())
    <div class="arena-rx-detail-panel !p-0 overflow-hidden divide-y-2 divide-slate-100 dark:divide-slate-700">
        @foreach($quiz->questions as $i => $q)
            @php $ans = $attempt->answers->firstWhere('question_id', $q->uuid); @endphp
            <div class="p-4 flex gap-3">
                <div class="w-10 h-10 rounded-xl grid place-items-center font-black text-sm shrink-0 border-2
                    {{ ($ans && $ans->is_correct) ? 'bg-emerald-100 text-emerald-700 border-emerald-300' : 'bg-rose-100 text-rose-700 border-rose-300' }}">
                    {{ $i+1 }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100 m-0">{{ $q->question_text }}</p>
                    <p class="text-xs mt-1 font-bold {{ ($ans && $ans->is_correct) ? 'text-emerald-600' : 'text-rose-600' }}">
                        @if(!$ans) Belum dijawab
                        @elseif($ans->is_correct) Benar · +{{ $ans->points_awarded }}
                        @else Belum tepat
                        @endif
                    </p>
                    @if($q->explanation)
                    <p class="text-xs text-slate-500 mt-1 font-medium">{{ $q->explanation }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    @if($leaderboard->isNotEmpty())
    <div class="arena-rx-detail-panel">
        <h2 class="font-black text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2 m-0">
            <i data-lucide="trophy" class="w-4 h-4 text-amber-500"></i> Podium kelas
        </h2>
        <ol class="space-y-2 m-0 p-0 list-none">
            @foreach($leaderboard as $i => $row)
            <li class="flex items-center gap-3 text-sm rounded-xl px-2 py-2.5 border-2 {{ $row->uuid === $attempt->uuid ? 'border-teal-300 bg-teal-50 dark:bg-teal-900/20 font-bold' : 'border-transparent' }}">
                <span class="arena-rank {{ $i===0?'arena-rank-1':($i===1?'arena-rank-2':($i===2?'arena-rank-3':'')) }}">{{ $i+1 }}</span>
                <span class="flex-1 truncate text-slate-700 dark:text-slate-200 font-semibold">{{ $row->student?->displayName() ?? 'Siswa' }}</span>
                @if($showScore || auth()->user()->can('manage', $quiz))
                <span class="font-black tabular-nums text-teal-600">{{ $row->total_score }}</span>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
    @endif

    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-rx-btn arena-rx-btn-ghost w-full !min-h-[3rem]">Kembali ke experience</a>
</div>
@endsection
