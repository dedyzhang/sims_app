@extends('layouts.app')
@section('title', 'Arena Belajar — Katalog Misi')

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@php
    $artPairs = [
        ['#00a99d', '#12345b'],
        ['#f5a524', '#e85d75'],
        ['#5ba85b', '#12345b'],
        ['#e85d75', '#00a99d'],
        ['#12345b', '#00a99d'],
        ['#f5a524', '#12345b'],
    ];
@endphp

@section('content')
<div class="space-y-5 arena-stage">
    <div class="arena-hero p-5 sm:p-8 relative">
        <div class="arena-hero-stars" aria-hidden="true"></div>
        <a href="{{ route('dashboard') }}" class="arena-hero-back">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
            <span>Dashboard</span>
        </a>
        <div class="relative z-[1] arena-intro arena-intro-katalog">
            <div class="arena-planet" aria-hidden="true">
                <div class="arena-orbit arena-orbit-a"><span class="arena-orbit-dot"></span></div>
                <div class="arena-orbit arena-orbit-b"></div>
                <div class="arena-planet-core">AB</div>
            </div>
            <div>
                <p class="arena-eyebrow">Arena Belajar</p>
                <h1 class="arena-title">Katalog Misi</h1>
                <p class="text-sm sm:text-base text-white/75 mt-2.5 max-w-lg leading-relaxed">
                    Pilih misi cerita, keputusan, puzzle, atau kuis dalam misi — main solo atau lewat penugasan kelas.
                </p>
                <div class="arena-cta-row mt-5">
                    <a href="{{ route('jagat-misi.progress') }}" class="arena-cta arena-cta-amber">
                        <i data-lucide="trophy" class="w-4 h-4"></i> Progres Saya
                    </a>
                    @can('create', \App\Models\Mission::class)
                    <a href="{{ route('jagat-misi.builder.index') }}" class="arena-cta">
                        <i data-lucide="folder-cog" class="w-4 h-4"></i> Kelola katalog
                    </a>
                    @endcan
                    @can('viewAnalytics', \App\Models\Mission::class)
                    <a href="{{ route('jagat-misi.analytics') }}" class="arena-cta arena-cta-ghost">Analitik</a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @if($classrooms->isNotEmpty())
    @php
        $groupedClassrooms = $classrooms->groupBy(function($c) {
            return $c->pelajaran ? $c->pelajaran->nama : 'Lainnya';
        })->sortKeys();
    @endphp
    <div class="space-y-4" x-data="{ search: '', activeTab: 'Semua' }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-black text-slate-800 dark:text-slate-100">Kuis &amp; Live per Kelas</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih Ruang Kelas untuk buka kuis interaktif &amp; sesi live-nya.</p>
            </div>
            @if(auth()->user()->isAdmin() || $classrooms->count() > 6)
            <div class="relative w-full sm:w-72 shrink-0">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400"></i>
                </div>
                <input type="text" x-model="search" placeholder="Cari pelajaran atau kelas..." class="block w-full pl-9 pr-3 py-2 border border-slate-300 rounded-xl bg-white text-sm placeholder-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">
            </div>
            @endif
        </div>
        
        <!-- Tabs Pelajaran -->
        @if(auth()->user()->isAdmin() || $groupedClassrooms->count() > 1)
        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-hide">
            <button type="button" 
                    @click="activeTab = 'Semua'"
                    :class="activeTab === 'Semua' ? 'bg-primary text-white border-primary' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                    class="px-4 py-2 text-sm font-bold border rounded-full transition-colors flex-shrink-0">
                Semua
            </button>
            @foreach($groupedClassrooms->keys() as $subjectName)
                <button type="button" 
                        title="{{ $subjectName }}"
                        @click="activeTab = '{{ addslashes($subjectName) }}'"
                        :class="activeTab === '{{ addslashes($subjectName) }}' ? 'bg-primary text-white border-primary' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                        class="px-4 py-2 text-sm font-bold border rounded-full transition-colors truncate max-w-[200px] flex-shrink-0">
                    {{ $subjectName }}
                </button>
            @endforeach
        </div>
        @endif

        <div class="arena-catalog-grid">
            @foreach($classrooms as $classroom)
            <a href="{{ route('classroom.arena.index', $classroom) }}" class="arena-mission-card arena-catalog-card arena-anim-in"
               x-show="(activeTab === 'Semua' || activeTab === '{{ addslashes($classroom->pelajaran?->nama ?? 'Lainnya') }}') && (search === '' || $el.innerText.toLowerCase().includes(search.toLowerCase()))"
               x-transition.opacity.duration.200ms
            >
                <div class="arena-card-art" style="background:{{ $classroom->cover_color }}">
                    <span class="arena-card-shine" aria-hidden="true"></span>
                    <i data-lucide="gamepad-2" class="w-9 h-9"></i>
                    <span class="arena-card-play" aria-hidden="true"><i data-lucide="play" class="w-4 h-4"></i></span>
                </div>
                <div class="arena-mission-card-body arena-catalog-body">
                    <div class="arena-catalog-pills">
                        <span class="arena-pill arena-pill-teal">{{ $classroom->pelajaran?->nama ?? 'Ruang Kelas' }}</span>
                        @if($classroom->rombel)
                        <span class="arena-pill">{{ $classroom->rombel->tingkat }}{{ $classroom->rombel->kelas }}</span>
                        @endif
                    </div>
                    <h3 class="arena-catalog-title">{{ $classroom->title }}</h3>
                    <div class="arena-catalog-cta">
                        <span>Buka Arena</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <div>
        <h2 class="text-lg font-black text-slate-800 dark:text-slate-100">Katalog Misi</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Misi cerita, keputusan, puzzle, atau kuis dalam misi — main solo atau lewat penugasan kelas.</p>
    </div>
    <div class="arena-catalog-grid">
        @forelse($missions as $mission)
        @php
            $playRoute = str_contains($mission->mechanic_type, 'recall') || str_contains($mission->mechanic_type, 'quiz')
                ? route('jagat-misi.player', $mission)
                : route('jagat-misi.play', $mission);
            [$artA, $artB] = $artPairs[$loop->index % count($artPairs)];
            $jenjangKey = $mission->jenjangKey();
            $jenjangLabel = $mission->jenjangLabel();
            $mechanicLabel = $mission->mechanicLabel();
            $displayTitle = trim(preg_replace('/^\[(?:DEMO|Tren)\]\s*/u', '', $mission->title) ?? $mission->title);
            $displaySummary = trim(preg_replace('/^\[(?:Tren\s*2025[–-]2026\s*·\s*)?(?:SD|SMP|SMA\/?SMK)\]\s*/u', '', (string) $mission->summary));
            $isTren = $mission->isTren();
        @endphp
        <a href="{{ $playRoute }}"
           class="arena-mission-card arena-catalog-card arena-anim-in"
           style="animation-delay: {{ $loop->index * 35 }}ms">
            <div class="arena-card-art" style="--art-a:{{ $artA }};--art-b:{{ $artB }}">
                <span class="arena-card-shine" aria-hidden="true"></span>
                <i data-lucide="compass" class="w-9 h-9"></i>
                <span class="arena-card-play" aria-hidden="true"><i data-lucide="play" class="w-4 h-4"></i></span>
            </div>
            <div class="arena-mission-card-body arena-catalog-body">
                <div class="arena-catalog-pills">
                    <span class="arena-pill arena-pill-jenjang arena-pill-{{ $jenjangKey }}">{{ $jenjangLabel }}</span>
                    @if($isTren)
                    <span class="arena-pill arena-pill-tren">Tren 25–26</span>
                    @endif
                    <span class="arena-pill arena-pill-teal">{{ $mission->subject }}</span>
                    <span class="arena-pill">{{ $mechanicLabel }}</span>
                </div>
                <h3 class="arena-catalog-title">{{ $displayTitle }}</h3>
                <p class="arena-catalog-summary">{{ $displaySummary }}</p>
                <div class="arena-catalog-meta">
                    <span>{{ $mission->duration_minutes }} menit</span>
                    <span aria-hidden="true">·</span>
                    <span>Skor maks {{ $mission->max_score }}</span>
                </div>
                <div class="arena-catalog-cta">
                    <span>Mainkan</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </div>
            </div>
        </a>
        @empty
        <div class="arena-panel text-center py-12 arena-catalog-empty">
            <p class="font-black text-lg text-slate-700 dark:text-slate-200">Belum ada misi terbit</p>
            <p class="text-sm text-slate-500 mt-1">Jalankan seeder misi Arena Belajar untuk mengisi katalog yang siap dimainkan.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
