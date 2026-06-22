@extends('layouts.app')
@section('title', 'Ruang Kelas')

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
@endphp

@section('content')
<div class="space-y-6">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-primary/10 via-primary/5 to-transparent border border-primary/10">
        <div class="flex items-center gap-3.5">
            <div class="p-2.5 rounded-xl border border-primary/10 bg-primary/5 text-primary shadow-sm"><i data-lucide="graduation-cap" class="w-5 h-5"></i></div>
            <div>
                <nav class="text-[10px] font-bold text-primary uppercase tracking-wider">Navigasi Utama</nav>
                <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100 tracking-tight mt-0.5">Ruang Kelas</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pilih kelas untuk masuk ke ruang digitalnya — materi, tugas, dan diskusi tiap mata pelajaran.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm shadow-sm">{{ session('success') }}</div>
    @endif

    @if($kelasList->isEmpty())
    <div class="card p-16 text-center text-slate-400 max-w-xl mx-auto rounded-2xl shadow-inner border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/20">
        <div class="w-16 h-16 mx-auto mb-4 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center text-slate-400 shadow-sm">
            <i data-lucide="layout-grid" class="w-8 h-8 opacity-60"></i>
        </div>
        <h3 class="font-bold text-slate-700 dark:text-slate-300 text-base">Belum Ada Kelas</h3>
        <p class="text-xs text-slate-400 max-w-xs mx-auto mt-1">Belum ada kelas yang didaftarkan atau dapat Anda akses saat ini.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($kelasList as $i => $k)
        <a href="{{ route('classroom.kelas', $k) }}" class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-100 dark:border-slate-700/50 shadow-sm hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 flex flex-col justify-between">
            {{-- Card Header with Gradient --}}
            <div class="h-28 p-5 flex flex-col justify-between relative overflow-hidden" style="background:{{ $gradients[$i % count($gradients)] }}">
                {{-- Floating Glassy Pattern Effect --}}
                <div class="absolute right-0 top-0 w-32 h-32 bg-white/10 rounded-full blur-2xl -mr-8 -mt-8 pointer-events-none transition-transform duration-500 group-hover:scale-125"></div>
                <div class="absolute left-0 bottom-0 w-24 h-24 bg-black/10 rounded-full blur-xl -ml-8 -mb-8 pointer-events-none"></div>

                <div class="flex items-center justify-between gap-2 z-10">
                    {{-- Door/School Icon --}}
                    <div class="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/20 shadow-sm transition-all duration-300 group-hover:bg-white group-hover:text-slate-800">
                        <i data-lucide="door-open" class="w-4 h-4"></i>
                    </div>
                </div>

                {{-- Class Name --}}
                <h3 class="font-black text-white text-xl leading-none tracking-tight mb-1 z-10 drop-shadow-sm">
                    Kelas {{ $k->tingkat }}{{ $k->kelas }}
                </h3>
            </div>

            {{-- Card Body --}}
            <div class="p-4 flex items-center justify-between gap-3 bg-white dark:bg-slate-800/40 relative z-10 border-t border-slate-50 dark:border-slate-700/20">
                <div class="flex items-center gap-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><i data-lucide="book-open" class="w-4 h-4 text-slate-400"></i> {{ $mapelCounts[$k->uuid] ?? 0 }} mapel</span>
                    <span class="flex items-center gap-1.5"><i data-lucide="users" class="w-4 h-4 text-slate-400"></i> {{ $siswaCounts[$k->uuid] ?? 0 }} siswa</span>
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
