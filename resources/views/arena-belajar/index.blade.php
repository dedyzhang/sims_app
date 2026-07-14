@extends('layouts.app')
@section('title', 'Arena Belajar')

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 arena-stage">
    <div class="arena-hero p-5 sm:p-7 relative">
        <div class="relative z-[1] flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <a href="{{ route('classroom.show', $classroom) }}" class="text-xs text-slate-300/90 hover:text-white inline-flex items-center gap-1 mb-3">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> {{ $classroom->title }}
                </a>
                <p class="arena-chip mb-2"><i data-lucide="zap" class="w-3 h-3"></i> Mode game</p>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Arena Belajar</h1>
                <p class="text-sm text-slate-300 mt-1.5 max-w-md">Tantang kelasmu. Jawab cepat, lihat podium, kumpulkan skor.</p>
            </div>
            @if($canManage)
            <a href="{{ route('classroom.arena.create', $classroom) }}" class="arena-cta shrink-0">
                <i data-lucide="plus" class="w-4 h-4"></i> Buat kuis baru
            </a>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @forelse($quizzes as $q)
        <a href="{{ route('classroom.arena.show', [$classroom, $q]) }}" class="arena-card-game p-4 flex gap-3 arena-anim-in" style="animation-delay: {{ $loop->index * 40 }}ms">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 text-white font-black text-lg"
                 style="background:linear-gradient(145deg,var(--cp),color-mix(in srgb,var(--cp) 55%,#0c1a24))">
                {{ $loop->iteration }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 flex-wrap mb-1">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md"
                          style="background:color-mix(in srgb,var(--cp) 14%,transparent);color:var(--cp)">{{ $q->statusLabel() }}</span>
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500">{{ $q->scoringModeLabel() }}</span>
                </div>
                <h3 class="font-black text-slate-800 dark:text-slate-100 truncate text-base">{{ $q->title }}</h3>
                <p class="text-xs text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                    <span class="inline-flex items-center gap-1"><i data-lucide="help-circle" class="w-3 h-3"></i> {{ $q->questions_count }} soal</span>
                    <span>·</span>
                    <span>{{ $q->max_score }} poin</span>
                    @if($q->due_at)<span>· Batas {{ $q->due_at->locale('id')->translatedFormat('d M') }}</span>@endif
                </p>
            </div>
            <div class="self-center text-slate-300"><i data-lucide="chevron-right" class="w-5 h-5"></i></div>
        </a>
        @empty
        <div class="sm:col-span-2 arena-hero p-10 text-center relative">
            <div class="relative z-[1]">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl grid place-items-center bg-white/10">
                    <i data-lucide="gamepad-2" class="w-8 h-8 text-white"></i>
                </div>
                <p class="font-black text-xl">Arena masih kosong</p>
                <p class="text-sm text-slate-300 mt-1">Buat kuis pertama dan undang kelas masuk.</p>
                @if($canManage)
                <a href="{{ route('classroom.arena.create', $classroom) }}" class="arena-cta mt-5 inline-flex">Mulai buat kuis</a>
                @endif
            </div>
        </div>
        @endforelse
    </div>
</div>
@endsection
