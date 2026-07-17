@extends('layouts.app')
@section('title', 'Riwayat Pembaruan')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">Riwayat Pembaruan Aplikasi</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Catatan pembaruan & fitur baru dari waktu ke waktu</p>
    </div>

    @if($updates->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="sparkles" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada info pembaruan yang diterbitkan.</p>
    </div>
    @else
    <div class="space-y-3" x-data="{ open: '{{ $updates->first()->uuid }}' }">
        @foreach($updates as $u)
        <div class="card overflow-hidden">
            <button type="button" @click="open = open === '{{ $u->uuid }}' ? null : '{{ $u->uuid }}'" class="w-full p-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                <div class="min-w-0 flex items-center gap-3">
                    <span class="grid place-items-center w-9 h-9 rounded-full bg-primary/10 text-primary flex-shrink-0"><i data-lucide="sparkles" class="w-4 h-4"></i></span>
                    <div class="min-w-0">
                        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">v{{ $u->version }} &mdash; {{ $u->title }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $u->released_at->isoFormat('D MMMM Y') }}</p>
                    </div>
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition flex-shrink-0" :class="open === '{{ $u->uuid }}' ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open === '{{ $u->uuid }}'" x-collapse x-cloak class="px-4 pb-4 border-t border-slate-100 dark:border-slate-700 pt-4">
                <div class="whats-new-content text-sm text-slate-600 dark:text-slate-300">
                    {!! \App\Support\RichText::clean($u->content) !!}
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

@push('styles')
<style>
    .whats-new-content p { margin: 0 0 0.6em; }
    .whats-new-content p:last-child { margin-bottom: 0; }
    .whats-new-content ul, .whats-new-content ol { margin: 0 0 0.6em; padding-left: 1.25em; }
    .whats-new-content ul { list-style: disc; }
    .whats-new-content ol { list-style: decimal; }
    .whats-new-content li { margin: 0.25em 0; }
    .whats-new-content strong { font-weight: 700; color: inherit; }
    .whats-new-content a { color: var(--cp); text-decoration: underline; }
</style>
@endpush
@endsection
