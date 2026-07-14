@extends('layouts.app')
@section('title', 'Pasangkan — '.$quiz->title)

@section('content')
@php
    $matchQs = $quiz->questions->where('type', 'match');
@endphp
<div class="space-y-5 max-w-xl mx-auto" x-data="{ done: {} }">
    <div>
        <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="text-sm text-slate-500 inline-flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
        </a>
        <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">Mode Pasangkan</h1>
        <p class="text-sm text-slate-500">Latihan visual dari bank soal yang sama (tanpa menyimpan skor).</p>
    </div>

    @forelse($matchQs as $q)
    @php $pairs = $q->meta['pairs'] ?? []; $rights = collect($pairs)->pluck('right')->shuffle(); @endphp
    <div class="card p-4 space-y-3">
        <p class="font-bold text-slate-800 dark:text-slate-100">{{ $q->question_text }}</p>
        @foreach($pairs as $i => $pair)
        <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
            <span class="sm:w-1/2 text-sm font-semibold">{{ $pair['left'] }}</span>
            <select class="sm:w-1/2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[44px]">
                <option value="">— pilih —</option>
                @foreach($rights as $r)
                <option value="{{ $r }}" @selected($r === $pair['right'] && false)>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        @endforeach
    </div>
    @empty
    <div class="card p-8 text-center text-slate-400">Belum ada soal tipe Pasangkan. Ubah soal di builder.</div>
    @endforelse
</div>
@endsection
