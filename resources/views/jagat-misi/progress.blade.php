@extends('layouts.app')
@section('title', 'Progres Arena Belajar')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/jagat-misi/styles.css') }}">
<link rel="stylesheet" href="{{ asset('assets/jagat-misi/progress.css') }}">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
@php
    $summary = $profile['summary'] ?? [];
    $badges = $profile['badges'] ?? [];
    $entries = $leaderboard['entries'] ?? [];
@endphp
<div class="space-y-5 arena-stage">
    <div class="flex items-center justify-between gap-3">
        <div>
            <a href="{{ route('jagat-misi.index') }}" class="arena-hud-back mb-3">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span>Katalog Misi</span>
            </a>
            <h1 class="text-2xl font-black">Progres & Leaderboard</h1>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-900">
            <p class="text-xs text-slate-500">Level</p>
            <p class="text-2xl font-black">{{ $summary['level'] ?? 1 }}</p>
        </div>
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-900">
            <p class="text-xs text-slate-500">XP</p>
            <p class="text-2xl font-black">{{ $summary['xp'] ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-900">
            <p class="text-xs text-slate-500">Streak</p>
            <p class="text-2xl font-black">{{ $summary['streak_days'] ?? 0 }} hari</p>
        </div>
        <div class="rounded-2xl border p-4 bg-white dark:bg-slate-900">
            <p class="text-xs text-slate-500">Misi Selesai</p>
            <p class="text-2xl font-black">{{ $summary['missions_completed'] ?? 0 }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border p-5 bg-white dark:bg-slate-900">
            <h2 class="font-black mb-3">Lencana</h2>
            <div class="space-y-2">
                @forelse($badges as $badge)
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary">{{ strtoupper(substr($badge['name'] ?? '?', 0, 1)) }}</span>
                    <div>
                        <p class="font-bold">{{ $badge['name'] }}</p>
                        <p class="text-xs text-slate-500">{{ $badge['description'] }}</p>
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-500">Belum ada lencana. Selesaikan misi untuk membuka reward.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl border p-5 bg-white dark:bg-slate-900">
            <h2 class="font-black mb-3">Leaderboard</h2>
            <div class="space-y-2">
                @forelse($entries as $entry)
                <div class="flex items-center justify-between text-sm py-1 border-b border-slate-100 dark:border-slate-800 last:border-0">
                    <span><strong>#{{ $entry['rank'] }}</strong> {{ $entry['name'] }}</span>
                    <span class="text-slate-500">{{ $entry['xp'] }} XP</span>
                </div>
                @empty
                <p class="text-sm text-slate-500">Belum ada data leaderboard.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
