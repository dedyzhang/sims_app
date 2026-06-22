@extends('layouts.app')
@section('title', 'Kelas ' . $kelas->tingkat . $kelas->kelas)

@php
    $gradients = [
        'linear-gradient(135deg, #4f46e5, #7c3aed)', // Indigo-Purple
        'linear-gradient(135deg, #0d9488, #10b981)', // Teal-Emerald
        'linear-gradient(135deg, #f97316, #ec4899)', // Orange-Pink
        'linear-gradient(135deg, #3b82f6, #06b6d4)', // Blue-Cyan
        'linear-gradient(135deg, #ec4899, #8b5cf6)', // Pink-Purple
        'linear-gradient(135deg, #f59e0b, #d97706)', // Amber-Orange
        'linear-gradient(135deg, #ef4444, #f43f5e)', // Red-Rose
        'linear-gradient(135deg, #06b6d4, #0d9488)', // Cyan-Teal
        'linear-gradient(135deg, #8b5cf6, #d946ef)', // Violet-Magenta
        'linear-gradient(135deg, #64748b, #475569)', // Slate-Gray
    ];

    $getIcon = function($name) {
        $name = strtolower($name);
        if (str_contains($name, 'agama')) return 'bookmark';
        if (str_contains($name, 'pancasila') || str_contains($name, 'kewarganegaraan') || str_contains($name, 'pkn')) return 'flag';
        if (str_contains($name, 'matematika')) return 'calculator';
        if (str_contains($name, 'alam') || str_contains($name, 'ipa') || str_contains($name, 'fisika') || str_contains($name, 'biologi') || str_contains($name, 'sains')) return 'beaker';
        if (str_contains($name, 'sosial') || str_contains($name, 'ips') || str_contains($name, 'sejarah') || str_contains($name, 'geografi') || str_contains($name, 'ekonomi')) return 'globe';
        if (str_contains($name, 'inggris') || str_contains($name, 'indonesia') || str_contains($name, 'mandarin') || str_contains($name, 'melayu') || str_contains($name, 'bahasa')) return 'languages';
        if (str_contains($name, 'seni') || str_contains($name, 'budaya') || str_contains($name, 'musik') || str_contains($name, 'lukis') || str_contains($name, 'prakarya')) return 'palette';
        if (str_contains($name, 'jasmani') || str_contains($name, 'olahraga') || str_contains($name, 'kesehatan') || str_contains($name, 'penjas')) return 'dumbbell';
        if (str_contains($name, 'informatika') || str_contains($name, 'komputer') || str_contains($name, 'teknologi') || str_contains($name, 'tik')) return 'cpu';
        return 'book-open';
    };
@endphp

@section('content')
<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/10">
        <div class="flex items-center gap-3.5">
            <a href="{{ route('classroom.index') }}" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-primary hover:border-primary shadow-sm hover:shadow-md transition-all duration-300"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
            <div>
                <nav class="text-[10px] font-bold text-primary uppercase tracking-wider flex items-center gap-1.5">
                    <span>Ruang Kelas</span>
                    <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                    <span class="text-slate-400">Kelas {{ $kelas->tingkat }}{{ $kelas->kelas }}</span>
                </nav>
                <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 tracking-tight mt-0.5">Kelas {{ $kelas->tingkat }}{{ $kelas->kelas }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Silakan pilih salah satu mata pelajaran di bawah untuk memasuki ruang belajar.</p>
            </div>
        </div>
    </div>

    @if($ngajars->isEmpty())
    <div class="card p-16 text-center text-slate-400 max-w-xl mx-auto rounded-2xl shadow-inner border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20">
        <div class="w-16 h-16 mx-auto mb-4 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-400 shadow-sm">
            <i data-lucide="book-x" class="w-8 h-8 opacity-60"></i>
        </div>
        <h3 class="font-bold text-slate-700 dark:text-slate-300 text-base">Belum Ada Mata Pelajaran</h3>
        <p class="text-xs text-slate-400 max-w-xs mx-auto mt-1">Belum ada penugasan jam mengajar (guru pengampu) yang terdaftar untuk kelas ini.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($ngajars as $i => $ng)
        @php $mine = in_array($ng->id_pelajaran, $myPelajaran, true); @endphp
        <a href="{{ route('classroom.subject', [$kelas, $ng->pelajaran]) }}" class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700/50 shadow-sm hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 flex flex-col justify-between">
            {{-- Card Header with Gradient --}}
            <div class="min-h-[8.5rem] flex-grow p-5 flex flex-col justify-between relative overflow-hidden" style="background:{{ $gradients[$i % count($gradients)] }}">
                {{-- Floating Glassy Pattern Effect --}}
                <div class="absolute right-0 top-0 w-32 h-32 bg-white/10 rounded-full blur-2xl -mr-8 -mt-8 pointer-events-none transition-transform duration-500 group-hover:scale-125"></div>
                <div class="absolute left-0 bottom-0 w-24 h-24 bg-black/10 rounded-full blur-xl -ml-8 -mb-8 pointer-events-none"></div>

                <div class="flex items-center justify-between gap-2 z-10">
                    {{-- Subject Icon --}}
                    <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/20 shadow-sm transition-all duration-300 group-hover:bg-white group-hover:text-slate-800">
                        <i data-lucide="{{ $getIcon($ng->pelajaran?->nama) }}" class="w-5 h-5"></i>
                    </div>
                    
                    {{-- Status Badge --}}
                    @if($mine)
                    <span class="text-[9px] px-2.5 py-1 rounded-full bg-white/20 backdrop-blur-md text-white border border-white/30 font-bold tracking-wide uppercase whitespace-nowrap shadow-sm">
                        Anda pengampu
                    </span>
                    @endif
                </div>

                {{-- Subject Name --}}
                <h3 class="font-extrabold text-white text-base leading-snug tracking-wide line-clamp-3 mt-3 z-10 drop-shadow-sm">
                    {{ $ng->pelajaran?->nama }}
                </h3>
            </div>

            {{-- Card Body --}}
            <div class="p-5 flex items-center justify-between gap-3 bg-white dark:bg-slate-800/40 relative z-10 border-t border-slate-50 dark:border-slate-700/20">
                <div class="flex items-center gap-2.5 min-w-0">
                    {{-- Avatar/Initial Badge --}}
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-slate-500 bg-slate-100 dark:bg-slate-700 dark:text-slate-300 shadow-inner flex-shrink-0 group-hover:scale-105 transition-transform">
                        {{ strtoupper(substr($ng->guru?->nama ?? 'B', 0, 1)) }}
                    </div>
                    
                    {{-- Teacher Name --}}
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider">Guru Pengampu</p>
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 truncate mt-0.5" title="{{ $ng->guru?->nama ?? 'Belum ada guru' }}">
                            {{ $ng->guru?->nama ?? 'Belum ada guru' }}
                        </p>
                    </div>
                </div>

                {{-- Action Button Indicator --}}
                <div class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-slate-400 flex items-center justify-center border border-slate-100 dark:border-slate-700/40 transition-all duration-300 group-hover:bg-primary group-hover:text-white group-hover:border-transparent shadow-sm flex-shrink-0">
                    <i data-lucide="arrow-right" class="w-4 h-4 transition-transform duration-300 group-hover:translate-x-0.5"></i>
                </div>
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>
@endsection
