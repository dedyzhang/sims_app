@extends('layouts.app')
@section('title', 'Susun Kata — '.$quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
@php
    $items = $quiz->questions->where('type', 'short_answer')
        ->map(function ($q) {
            $ans = $q->meta['answers'][0] ?? null;
            if (!$ans) return null;
            $letters = preg_split('//u', mb_strtoupper(preg_replace('/\s+/', '', $ans) ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            shuffle($letters);
            return ['clue' => $q->question_text, 'scrambled' => implode(' ', $letters), 'answer' => mb_strtoupper(preg_replace('/\s+/', '', $ans) ?? '')];
        })->filter()->values();
@endphp
<div class="space-y-4 max-w-xl mx-auto arena-stage" x-data="{ show: {} }">
    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
        <span>Experience</span>
    </a>
    <h1 class="text-xl font-black">Susun Kata</h1>
    @forelse($items as $i => $item)
    <div class="card p-4 space-y-2">
        <p class="text-sm font-semibold">{{ $item['clue'] }}</p>
        <p class="font-mono text-lg tracking-widest" style="color:var(--cp)">{{ $item['scrambled'] }}</p>
        <button type="button" class="text-sm font-semibold text-emerald-600" @click="show[{{ $i }}]=!show[{{ $i }}]">Tampilkan jawaban</button>
        <p x-show="show[{{ $i }}]" x-cloak class="font-bold">{{ $item['answer'] }}</p>
    </div>
    @empty
    <div class="card p-8 text-center text-slate-400">Butuh soal isian singkat untuk mode ini.</div>
    @endforelse
</div>
@endsection
