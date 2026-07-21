@extends('layouts.app')
@section('title', $mission->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 max-w-3xl mx-auto arena-stage">
    <div>
        <a href="{{ route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'misi']) }}" class="arena-hud-back mb-3">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span>Lobby Arena</span>
        </a>
        <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100">{{ $mission->title }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $mission->summary }}</p>
    </div>

    <div class="card p-4 sm:p-5 space-y-3">
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="px-2 py-1 rounded-lg bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 font-semibold">{{ $mission->subject }}</span>
            <span class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500">{{ $mission->mechanicLabel() }}</span>
            <span class="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500">{{ $mission->duration_minutes }} menit</span>
            <span class="px-2 py-1 rounded-lg {{ $assignment->isOpen() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                {{ $assignment->isOpen() ? 'Misi aktif' : 'Misi tertutup' }}
            </span>
        </div>
        @if($assignment->due_at)
        <p class="text-sm text-slate-500">Batas waktu: {{ $assignment->due_at->locale('id')->translatedFormat('d M Y H:i') }}</p>
        @endif
    </div>

    @if(auth()->user()->access === 'siswa')
    <div class="card p-4 sm:p-5">
        @if($myAttempt && $myAttempt->status === 'completed')
        <p class="text-sm text-slate-600 dark:text-slate-300">Kamu sudah menyelesaikan misi ini dengan skor <strong>{{ $myAttempt->score }}%</strong>.</p>
        @elseif($myAttempt && $myAttempt->status === 'awaiting_reflection')
        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">Skor tersimpan. Lanjutkan refleksi debrief.</p>
        <a href="{{ route('jagat-misi.debrief', $myAttempt) }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)">
            Lanjut debrief
        </a>
        @elseif($assignment->isOpen())
        <a href="{{ route('classroom.jagat.play', [$classroom, $mission]) }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)">
            <i data-lucide="play" class="w-4 h-4"></i> Mulai misi
        </a>
        @else
        <p class="text-sm text-slate-500">Misi belum dibuka atau sudah melewati batas waktu.</p>
        @endif
    </div>
    @endif

    @if($canManage)
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('classroom.jagat.results', [$classroom, $mission]) }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-bold border border-slate-200 dark:border-slate-600 min-h-[48px]">
            <i data-lucide="bar-chart-2" class="w-4 h-4"></i> Monitor hasil
        </a>
        @can('manage', $mission)
        <a href="{{ route('jagat-misi.builder.edit', $mission) }}" class="inline-flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-bold border border-slate-200 dark:border-slate-600 min-h-[48px]">
            <i data-lucide="pencil" class="w-4 h-4"></i> Edit misi
        </a>
        @endcan
    </div>
    @endif
</div>
@endsection
