@extends('layouts.app')
@section('title', 'Ruang Kelas (Tugas Kelas)')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="page-title">Pantau Tugas Ruang Kelas</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftar Ruang Kelas yang diajarkan ke kelas {{ $kelas->tingkat }}{{ $kelas->kelas }}</p>
    </div>

    @if($classrooms->isEmpty())
        <div class="card p-8 text-center">
            <i data-lucide="folder-open" class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3"></i>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-200">Belum Ada Ruang Kelas</h3>
            <p class="text-slate-500 mt-1">Belum ada kelas atau tugas yang ditugaskan ke kelas Anda.</p>
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($classrooms as $room)
                <a href="{{ route('classroom.show', $room) }}" class="card p-0 hover:-translate-y-1 hover:shadow-lg transition overflow-hidden border border-slate-200 dark:border-slate-700 flex flex-col">
                    <div class="h-24 p-5 flex flex-col justify-between" style="background-color: {{ $room->cover_color ?? '#3b82f6' }};">
                        <div class="flex items-start justify-between">
                            <h2 class="font-bold text-white text-lg leading-tight line-clamp-1" title="{{ $room->title }}">{{ $room->title }}</h2>
                        </div>
                        <p class="text-white/80 text-sm line-clamp-1">{{ $room->pelajaran?->nama ?? 'Umum' }}</p>
                    </div>
                    <div class="p-4 flex-1 flex flex-col justify-between gap-3 bg-white dark:bg-slate-800">
                        <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center shrink-0">
                                <i data-lucide="user" class="w-4 h-4 text-slate-500"></i>
                            </div>
                            <span class="truncate">{{ $room->author?->name ?? '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>Status: {{ $room->statusLabel() }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $classrooms->links() }}
    @endif
</div>
@endsection
