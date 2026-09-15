@extends('layouts.app')
@section('title', 'Tugas - ' . $classroom->title)

@section('content')
<div class="space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('walikelas.ruang_kelas.index') }}" class="btn-secondary px-3 py-2"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">{{ $classroom->title }}</h1>
            <p class="text-sm text-slate-500 mt-0.5">Guru: {{ $classroom->author?->name ?? '-' }} &bull; Kelas: {{ $kelas->tingkat }}{{ $kelas->kelas }}</p>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="card p-8 text-center">
            <i data-lucide="file-check" class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3"></i>
            <h3 class="text-lg font-semibold text-slate-700 dark:text-slate-200">Belum Ada Tugas</h3>
            <p class="text-slate-500 mt-1">Belum ada tugas yang diberikan di ruang kelas ini.</p>
        </div>
    @else
        <div class="card">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700">
                <h3 class="font-bold text-slate-700 dark:text-slate-200">Daftar Tugas</h3>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700">
                @foreach($assignments as $a)
                    <a href="{{ route('walikelas.ruang_kelas.assignment', ['classroom' => $classroom, 'assignmentUuid' => $a->uuid]) }}" class="block p-4 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                    <i data-lucide="file-text" class="w-5 h-5 text-primary"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-800 dark:text-slate-100">{{ $a->title }}</h4>
                                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                                        <span>Dibuat: {{ $a->created_at->format('d M Y') }}</span>
                                        @if($a->due_date)
                                            <span class="text-rose-500 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> Tenggat: {{ $a->due_date->format('d M Y, H:i') }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="shrink-0 flex gap-2">
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $a->submitted_count }} / {{ $a->total_students }}</div>
                                    <div class="text-xs text-slate-500">Siswa Mengerjakan</div>
                                </div>
                                <div class="w-10 flex items-center justify-center text-slate-400">
                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
