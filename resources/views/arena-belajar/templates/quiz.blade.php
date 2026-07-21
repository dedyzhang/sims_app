@extends('layouts.app')
@section('title', 'Template — '.$quiz->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-4 max-w-xl mx-auto arena-stage">
    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
        <span>Experience</span>
    </a>
    <h1 class="text-xl font-black">Mode Quiz</h1>
    <p class="text-sm text-slate-500">Template standar — kerjakan lewat attempt async atau live.</p>
    <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="inline-flex px-4 py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)">Buka kuis</a>
</div>
@endsection
