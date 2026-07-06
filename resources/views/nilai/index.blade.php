@extends('layouts.app')
@php $canViewAll = $isAdmin || auth()->user()?->access === 'kepala'; @endphp
@section('title', $canViewAll ? 'Penilaian' : 'Buku Guru')

@section('content')
@php
    // Curated color list
    $colors = [
        ['light_bg' => '#f5f3ff', 'light_border' => '#ddd6fe', 'light_text' => '#6d28d9', 'dark_bg' => '#2e1065', 'dark_border' => '#4c1d95', 'dark_text' => '#ddd6fe'], // Violet
        ['light_bg' => '#f0fdf4', 'light_border' => '#bbf7d0', 'light_text' => '#16a34a', 'dark_bg' => '#14532d', 'dark_border' => '#166534', 'dark_text' => '#bbf7d0'], // Green/Emerald
        ['light_bg' => '#f0f9ff', 'light_border' => '#bae6fd', 'light_text' => '#0284c7', 'dark_bg' => '#0c4a6e', 'dark_border' => '#075985', 'dark_text' => '#bae6fd'], // Sky
        ['light_bg' => '#fff1f2', 'light_border' => '#fecdd3', 'light_text' => '#e11d48', 'dark_bg' => '#881337', 'dark_border' => '#9f1239', 'dark_text' => '#fecdd3'], // Rose
        ['light_bg' => '#fdfbeb', 'light_border' => '#fef3c7', 'light_text' => '#d97706', 'dark_bg' => '#78350f', 'dark_border' => '#92400e', 'dark_text' => '#fef3c7'], // Amber
        ['light_bg' => '#fef2f2', 'light_border' => '#fee2e2', 'light_text' => '#dc2626', 'dark_bg' => '#7f1d1d', 'dark_border' => '#991b1b', 'dark_text' => '#fee2e2'], // Red
        ['light_bg' => '#fdf4ff', 'light_border' => '#f5d0fe', 'light_text' => '#c026d3', 'dark_bg' => '#701a75', 'dark_border' => '#86198f', 'dark_text' => '#f5d0fe'], // Fuchsia
        ['light_bg' => '#f0fdfa', 'light_border' => '#99f6e4', 'light_text' => '#0d9488', 'dark_bg' => '#115e59', 'dark_border' => '#134e4a', 'dark_text' => '#99f6e4'], // Teal
        ['light_bg' => '#fff7ed', 'light_border' => '#ffedd5', 'light_text' => '#ea580c', 'dark_bg' => '#7c2d12', 'dark_border' => '#9a3412', 'dark_text' => '#ffedd5'], // Orange
        ['light_bg' => '#ecfeff', 'light_border' => '#c5f2f7', 'light_text' => '#0891b2', 'dark_bg' => '#164e63', 'dark_border' => '#155e75', 'dark_text' => '#c5f2f7'], // Cyan
        ['light_bg' => '#e0f2fe', 'light_border' => '#bae6fd', 'light_text' => '#0369a1', 'dark_bg' => '#075985', 'dark_border' => '#0369a1', 'dark_text' => '#e0f2fe'], // Blue
        ['light_bg' => '#f0fdf4', 'light_border' => '#dcfce7', 'light_text' => '#15803d', 'dark_bg' => '#166534', 'dark_border' => '#14532d', 'dark_text' => '#dcfce7'], // Lime/Green
    ];

    // Build map of tingkat to color
    $tingkatColors = [];
    $idx = 0;
    foreach ($ngajars as $n) {
        if ($n->kelas && !isset($tingkatColors[$n->kelas->tingkat])) {
            $tingkatColors[$n->kelas->tingkat] = $colors[$idx % count($colors)];
            $idx++;
        }
    }

    $uniqueKelas = $ngajars->pluck('kelas')->unique('uuid')->filter()->sortBy(fn($k) => [$k->tingkat, $k->kelas]);
    $uniquePelajaran = $ngajars->pluck('pelajaran')->unique('uuid')->filter()->sortBy('urutan');
    $uniqueGuru = $ngajars->pluck('guru')->unique('uuid')->filter()->sortBy('nama');
@endphp

<style>
    @foreach($tingkatColors as $tingkat => $c)
        .card-tingkat-{{ $tingkat }} {
            background-color: {{ $c['light_bg'] }} !important;
            border-color: {{ $c['light_border'] }} !important;
        }
        .dark .card-tingkat-{{ $tingkat }} {
            background-color: {{ $c['dark_bg'] }} !important;
            border-color: {{ $c['dark_border'] }} !important;
        }
        .text-tingkat-{{ $tingkat }} {
            color: {{ $c['light_text'] }} !important;
            background-color: color-mix(in srgb, {{ $c['light_text'] }} 12%, transparent) !important;
        }
        .dark .text-tingkat-{{ $tingkat }} {
            color: {{ $c['dark_text'] }} !important;
            background-color: color-mix(in srgb, {{ $c['dark_text'] }} 22%, transparent) !important;
        }
        .border-tingkat-{{ $tingkat }} {
            border-color: color-mix(in srgb, {{ $c['light_text'] }} 30%, transparent) !important;
        }
        .dark .border-tingkat-{{ $tingkat }} {
            border-color: color-mix(in srgb, {{ $c['dark_text'] }} 40%, transparent) !important;
        }
    @endforeach
</style>

<div class="space-y-5" x-data="{
    search: '',
    filterKelas: '',
    filterPelajaran: '',
    filterGuru: '',
    items: [
        @foreach($ngajars as $n)
        {
            uuid: '{{ $n->uuid }}',
            pelajaranNama: '{{ addslashes($n->pelajaran?->nama ?? '') }}',
            pelajaranKode: '{{ addslashes($n->pelajaran?->kode ?? '') }}',
            kelasNama: '{{ $n->kelas?->tingkat }}{{ $n->kelas?->kelas }}',
            kelasUuid: '{{ $n->kelas?->uuid ?? '' }}',
            guruNama: '{{ addslashes($n->guru?->nama ?? '') }}',
            guruUuid: '{{ $n->guru?->uuid ?? '' }}',
            kkm: {{ $n->kkm ?? $n->pelajaran?->kkm ?? 75 }},
            materiUrl: '{{ route('nilai.materi', $n->uuid) }}',
            formatifUrl: '{{ route('nilai.formatif', $n->uuid) }}',
            sumatifUrl: '{{ route('nilai.sumatif', $n->uuid) }}',
            ptsUrl: '{{ route('nilai.pts', $n->uuid) }}',
            pasUrl: '{{ route('nilai.pas', $n->uuid) }}',
            raporUrl: '{{ route('nilai.rapor', $n->uuid) }}',
            colorClass: 'card-tingkat-{{ $n->kelas?->tingkat ?? '' }}',
            textClass: 'text-tingkat-{{ $n->kelas?->tingkat ?? '' }}'
        },
        @endforeach
    ],
    get filteredItems() {
        return this.items.filter(item => {
            const matchSearch = this.search === '' || 
                item.pelajaranNama.toLowerCase().includes(this.search.toLowerCase()) ||
                item.pelajaranKode.toLowerCase().includes(this.search.toLowerCase()) ||
                item.guruNama.toLowerCase().includes(this.search.toLowerCase()) ||
                item.kelasNama.toLowerCase().includes(this.search.toLowerCase());

            const matchKelas = this.filterKelas === '' || item.kelasUuid === this.filterKelas;
            const matchPelajaran = this.filterPelajaran === '' || item.pelajaranNama === this.filterPelajaran;
            const matchGuru = this.filterGuru === '' || item.guruUuid === this.filterGuru;

            return matchSearch && matchKelas && matchPelajaran && matchGuru;
        });
    }
}" x-init="$nextTick(() => { lucide.createIcons(); }); $watch('filteredItems', () => { $nextTick(() => { lucide.createIcons(); }); })">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">{{ $canViewAll ? 'Penilaian' : 'Buku Guru' }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $canViewAll ? 'Semua penugasan mengajar' : 'Penugasan mengajar Anda' }}
                @if($semester) &bull; <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $semester->nama_lengkap }}</span> @endif
            </p>
        </div>
        <a href="{{ route('nilai.kktp') }}" class="btn-accent flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold">
            <i data-lucide="target" class="w-4 h-4"></i> Atur KKTP
        </a>
    </div>

    @if($ngajars->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="book-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada penugasan mengajar.</p>
        <p class="text-sm mt-1">{{ $isAdmin ? 'Atur "Pelajaran Diajar" tiap guru dulu.' : 'Hubungi admin untuk mengatur pelajaran yang Anda ajar.' }}</p>
    </div>
    @else
    {{-- Search & Filters --}}
    <div class="card p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 bg-white/70 dark:bg-slate-800/70 backdrop-blur-md">
        <div>
            <label class="form-label text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500">Cari Kelas / Pelajaran / Guru</label>
            <div class="relative">
                <input type="text" x-model="search" placeholder="Cari..." class="form-input pl-9">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
            </div>
        </div>
        <div>
            <label class="form-label text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500">Filter Kelas</label>
            <select x-model="filterKelas" class="form-select">
                <option value="">Semua Kelas</option>
                @foreach($uniqueKelas as $k)
                    <option value="{{ $k->uuid }}">{{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500">Filter Mata Pelajaran</label>
            <select x-model="filterPelajaran" class="form-select">
                <option value="">Semua Pelajaran</option>
                @foreach($uniquePelajaran as $p)
                    <option value="{{ $p->nama }}">{{ $p->nama }}</option>
                @endforeach
            </select>
        </div>
        @if($isAdmin)
        <div>
            <label class="form-label text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500">Filter Guru</label>
            <select x-model="filterGuru" class="form-select">
                <option value="">Semua Guru</option>
                @foreach($uniqueGuru as $g)
                    <option value="{{ $g->uuid }}">{{ $g->nama }}</option>
                @endforeach
            </select>
        </div>
        @else
        <div class="hidden lg:block"></div>
        @endif
    </div>

    {{-- Cards Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <template x-for="item in filteredItems" :key="item.uuid">
            <div :class="'card p-4 flex flex-col gap-3 transition-all duration-300 ' + item.colorClass">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-bold text-slate-800 dark:text-slate-100 truncate text-base" x-text="item.pelajaranNama"></p>
                        <p class="text-xs text-slate-400 font-medium mt-0.5"><span x-text="item.pelajaranKode"></span> &bull; KKM <span x-text="item.kkm"></span></p>
                    </div>
                    <span :class="'badge flex-shrink-0 font-bold px-3 py-1 rounded-full ' + item.textClass" x-text="item.kelasNama"></span>
                </div>
                
                @if($isAdmin)
                <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 font-medium mt-1">
                    <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i> <span x-text="item.guruNama"></span>
                </p>
                @endif

                <div class="grid grid-cols-2 gap-2 mt-2">
                    <a :href="item.materiUrl" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white/40 dark:hover:bg-slate-700/50 transition">
                        <i data-lucide="book-open" class="w-3.5 h-3.5"></i> Materi
                    </a>
                    <a :href="item.formatifUrl" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white/40 dark:hover:bg-slate-700/50 transition">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Formatif
                    </a>
                    <a :href="item.sumatifUrl" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white/40 dark:hover:bg-slate-700/50 transition">
                        <i data-lucide="clipboard-check" class="w-3.5 h-3.5"></i> Sumatif
                    </a>
                    <a :href="item.ptsUrl" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white/40 dark:hover:bg-slate-700/50 transition">
                        <i data-lucide="file-clock" class="w-3.5 h-3.5"></i> PTS
                    </a>
                    <a :href="item.pasUrl" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white/40 dark:hover:bg-slate-700/50 transition">
                        <i data-lucide="file-check-2" class="w-3.5 h-3.5"></i> PAS
                    </a>
                    <a :href="item.raporUrl" class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-bold bg-primary/10 text-primary hover:bg-primary/20 transition">
                        <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Rapor
                    </a>
                </div>
            </div>
        </template>
    </div>

    {{-- Empty State for Filters --}}
    <div x-show="filteredItems.length === 0" class="card p-12 text-center text-slate-400 bg-white/70 dark:bg-slate-800/70 backdrop-blur-md" x-transition>
        <i data-lucide="search-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Tidak ada penugasan mengajar yang cocok.</p>
        <p class="text-sm mt-1 text-slate-400">Silakan sesuaikan kata kunci pencarian atau filter Anda.</p>
    </div>
    @endif
</div>
@endsection
