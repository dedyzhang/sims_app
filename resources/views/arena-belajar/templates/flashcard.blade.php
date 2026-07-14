@extends('layouts.app')
@section('title', 'Flashcard — '.$quiz->title)

@section('content')
<div class="max-w-md mx-auto space-y-4"
     x-data="{
        cards: @js($quiz->questions->map(fn($q) => [
            'q' => $q->question_text,
            'a' => $q->type === 'short_answer'
                ? implode(' / ', $q->meta['answers'] ?? [])
                : ($q->options->firstWhere('is_correct', true)?->option_text ?? ($q->meta['pairs'][0]['right'] ?? '—')),
        ])->values()),
        i: 0, flip: false
     }">
    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="text-sm text-slate-500 inline-flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
    <h1 class="text-xl font-black">Flashcard</h1>
    <p class="text-xs text-slate-500" x-text="(i+1) + ' / ' + cards.length"></p>

    <button type="button" @click="flip = !flip"
            class="card w-full min-h-[14rem] p-6 flex items-center justify-center text-center text-lg font-bold text-slate-800 dark:text-slate-100">
        <span x-show="!flip" x-text="cards[i]?.q"></span>
        <span x-show="flip" x-cloak class="text-emerald-600" x-text="cards[i]?.a"></span>
    </button>

    <div class="flex gap-2">
        <button type="button" class="flex-1 px-4 py-3 rounded-xl border font-semibold min-h-[48px]" @click="i = Math.max(0, i-1); flip=false">Sebelumnya</button>
        <button type="button" class="flex-1 px-4 py-3 rounded-xl text-white font-bold min-h-[48px]" style="background:var(--cp)" @click="i = Math.min(cards.length-1, i+1); flip=false">Berikutnya</button>
    </div>
</div>
@endsection
