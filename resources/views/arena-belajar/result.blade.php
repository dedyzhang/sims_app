@extends('layouts.app')
@section('title', 'Hasil — '.$quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 max-w-lg mx-auto arena-stage">
    <div class="arena-hero p-6 sm:p-8 text-center relative">
        <div class="relative z-[1] space-y-4">
            <p class="arena-chip mx-auto">Hasil tantangan</p>
            <h1 class="text-lg font-bold text-slate-200">{{ $quiz->title }}</h1>

            @if($showScore)
                <div class="arena-score-orb">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest font-bold opacity-80">Skor</p>
                        <p class="text-4xl font-black tabular-nums leading-none">{{ $attempt->total_score }}</p>
                    </div>
                </div>
                <p class="text-sm text-slate-300">
                    {{ $attempt->correct_count }}/{{ $quiz->questions->count() }} benar · dari {{ $quiz->max_score }}
                </p>
            @else
                <p class="text-xl font-black">Jawaban terkumpul</p>
                <p class="text-sm text-slate-300">Skor disembunyikan guru.</p>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    @if($showScore && $attempt->answers->isNotEmpty())
    <div class="arena-card-game divide-y divide-slate-100 dark:divide-slate-700 overflow-hidden">
        @foreach($quiz->questions as $i => $q)
            @php $ans = $attempt->answers->firstWhere('question_id', $q->uuid); @endphp
            <div class="p-4 flex gap-3">
                <div class="w-9 h-9 rounded-xl grid place-items-center font-black text-sm shrink-0
                    {{ ($ans && $ans->is_correct) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                    {{ $i+1 }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $q->question_text }}</p>
                    <p class="text-xs mt-1 font-semibold {{ ($ans && $ans->is_correct) ? 'text-emerald-600' : 'text-rose-600' }}">
                        @if(!$ans) Belum dijawab
                        @elseif($ans->is_correct) Benar · +{{ $ans->points_awarded }}
                        @else Belum tepat
                        @endif
                    </p>
                    @if($q->explanation)
                    <p class="text-xs text-slate-500 mt-1">{{ $q->explanation }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif

    @if($leaderboard->isNotEmpty())
    <div class="arena-card-game p-4">
        <h2 class="font-black text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
            <i data-lucide="trophy" class="w-4 h-4" style="color:var(--cp)"></i> Podium kelas
        </h2>
        <ol class="space-y-2">
            @foreach($leaderboard as $i => $row)
            <li class="flex items-center gap-3 text-sm rounded-xl px-2 py-2 {{ $row->uuid === $attempt->uuid ? 'bg-primary/10 font-bold' : '' }}">
                <span class="arena-rank {{ $i===0?'arena-rank-1':($i===1?'arena-rank-2':($i===2?'arena-rank-3':'')) }}">{{ $i+1 }}</span>
                <span class="flex-1 truncate text-slate-700 dark:text-slate-200">{{ $row->student?->displayName() ?? 'Siswa' }}</span>
                @if($showScore || auth()->user()->can('manage', $quiz))
                <span class="font-black tabular-nums" style="color:var(--cp)">{{ $row->total_score }}</span>
                @endif
            </li>
            @endforeach
        </ol>
    </div>
    @endif

    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="block text-center text-sm font-bold text-slate-500 hover:text-slate-700">Kembali ke kuis</a>
</div>
@endsection
