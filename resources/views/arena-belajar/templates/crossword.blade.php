@extends('layouts.app')
@section('title', 'Crossword — '.$quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
@php
    $words = $quiz->questions->where('type', 'short_answer')
        ->flatMap(fn ($q) => collect($q->meta['answers'] ?? [])->map(fn ($a) => [
            'word' => mb_strtoupper(preg_replace('/\s+/', '', $a) ?? ''),
            'clue' => $q->question_text,
        ]))
        ->filter(fn ($w) => mb_strlen($w['word']) >= 3)
        ->unique('word')
        ->take(8)
        ->values();
@endphp
<div class="space-y-4 max-w-xl mx-auto arena-stage">
    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
        <span>Experience</span>
    </a>
    <h1 class="text-xl font-black">Teka-teki kata</h1>
    <p class="text-sm text-slate-500">Versi sederhana dari bank isian singkat (bukan grid silang penuh).</p>
    <ol class="card divide-y divide-slate-100 dark:divide-slate-700">
        @foreach($words as $i => $w)
        <li class="p-4 space-y-2">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $i+1 }}. {{ $w['clue'] }}</p>
            <p class="font-mono tracking-[0.3em] text-slate-400">{{ str_repeat('_ ', mb_strlen($w['word'])) }}</p>
            <details class="text-xs text-emerald-600"><summary>Lihat jawaban</summary>{{ $w['word'] }}</details>
        </li>
        @endforeach
    </ol>
</div>
@endsection
