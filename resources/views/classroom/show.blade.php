@extends('layouts.app')
@section('title', $classroom->title)

@push('styles')<style>[x-cloak]{display:none!important}</style>@endpush

@php
    $fmt = function ($b) { $u=['B','KB','MB','GB']; $i=0; $b=(int)$b; while($b>=1024 && $i<3){ $b/=1024; $i++; } return round($b,1).' '.$u[$i]; };
    $me = auth()->user();
@endphp

@section('content')
<div class="space-y-5" x-data="{ tab: '{{ request('tab','materi') }}' }">
    {{-- Header --}}
    <div class="card overflow-hidden">
        <div class="min-h-[7.5rem] sm:min-h-[8.5rem] p-5 sm:p-6 relative flex flex-col justify-end" style="background:{{ $classroom->cover_color }}">
            {{-- Class Code --}}
            <span class="absolute top-4 right-5 text-white/80 text-[10px] sm:text-xs font-mono tracking-widest">{{ $classroom->class_code }}</span>
            
            {{-- Title & Status --}}
            <div class="text-white mt-3">
                <span class="text-[9px] px-2.5 py-1 rounded-full bg-white/20 backdrop-blur-md text-white border border-white/20 font-bold uppercase tracking-wider shadow-sm">{{ $classroom->statusLabel() }}</span>
                <h1 class="text-xl sm:text-2xl font-black mt-2.5 drop-shadow-sm leading-tight">{{ $classroom->title }}</h1>
            </div>
        </div>
        <div class="p-4 flex items-center justify-between flex-wrap gap-3">
            <div class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-3 flex-wrap">
                <span>{{ $classroom->pelajaran?->nama ?? 'Tanpa mapel' }}</span>
                @if($classroom->semester)<span>· Semester {{ $classroom->semester->semester }} {{ $classroom->semester->tahun }}</span>@endif
                <span>· @foreach($classroom->kelas as $k)<span class="font-medium">{{ $k->tingkat }}{{ $k->kelas }}</span>@if(!$loop->last), @endif @endforeach</span>
                <span>· {{ $classroom->members->count() }} siswa</span>
            </div>
            @if($classroom->rombel)
            <a href="{{ route('classroom.kelas', $classroom->rombel) }}" class="text-sm font-medium flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300"><i data-lucide="arrow-left" class="w-4 h-4"></i> Mapel lain</a>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-slate-200 dark:border-slate-700">
        @foreach(['materi'=>'Materi','tugas'=>'Latihan & Tugas','anggota'=>'Anggota'] as $k=>$label)
        <button @click="tab='{{ $k }}'" :class="tab==='{{ $k }}' ? 'border-primary text-primary' : 'border-transparent text-slate-500'" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition" style="--tw-text-opacity:1" :style="tab==='{{ $k }}' ? 'color:var(--cp);border-color:var(--cp)' : ''">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ===== TAB MATERI ===== --}}
    <div x-show="tab==='materi'" class="space-y-4">
        @if($canManage)
        <a href="{{ route('classroom.material.create', $classroom) }}" class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white" style="background:var(--cp)"><i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah Materi</a>
        @endif

        @forelse($classroom->materials as $m)
        <a href="{{ route('classroom.material.show', [$m, 'class' => $classroom->uuid]) }}" class="card p-4 flex items-center gap-3 hover:border-primary transition">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:color-mix(in srgb, var(--cp) 14%, transparent)"><i data-lucide="book-open" class="w-5 h-5" style="color:var(--cp)"></i></div>
            <div class="min-w-0 flex-1">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate flex items-center gap-1.5">{{ $m->title }}@if($m->meet_url)<span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-semibold flex-shrink-0"><i data-lucide="video" class="w-3 h-3"></i> Meet</span>@endif</h3>
                @if($m->description)<p class="text-sm text-slate-500 dark:text-slate-400 truncate">{{ $m->description }}</p>@endif
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $m->uploader?->displayName() }} · {{ $m->created_at?->locale('id')->diffForHumans() }}</p>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-400 flex-shrink-0">
                <span class="flex items-center gap-1"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> {{ $m->comments_count }}</span>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </div>
        </a>
        @empty
        <div class="card p-10 text-center text-slate-400"><i data-lucide="book-open" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Belum ada materi.</p></div>
        @endforelse
    </div>

    {{-- ===== TAB TUGAS ===== --}}
    <div x-show="tab==='tugas'" x-cloak class="space-y-4">
        @if($canManage)
        <a href="{{ route('classroom.assignment.create', $classroom) }}" class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold text-white" style="background:var(--cp)"><i data-lucide="plus-circle" class="w-4 h-4"></i> Buat Latihan / Tugas</a>
        @endif

        @forelse($classroom->assignments as $a)
        <a href="{{ route('classroom.assignment.show', [$a, 'class' => $classroom->uuid]) }}" class="card p-4 flex items-center gap-3 hover:border-primary transition">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:color-mix(in srgb, var(--cp) 14%, transparent)"><i data-lucide="clipboard-list" class="w-5 h-5" style="color:var(--cp)"></i></div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 capitalize">{{ $a->type }}</span>
                    @if($a->status==='draft')<span class="text-[11px] px-2 py-0.5 rounded bg-amber-100 text-amber-700">Draf</span>@endif
                    <h3 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $a->title }}</h3>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">Nilai maks {{ $a->max_score }}@if($a->due_at) · Batas {{ $a->due_at->locale('id')->translatedFormat('d M Y H:i') }}@endif
                    @if($me->access==='siswa') @php $sub=$mySubmissions[$a->uuid]??null; @endphp · @if($sub && $sub->status==='graded')<span class="text-emerald-600 font-semibold">@if($a->hide_scores)Tugas sudah dikoreksi@elseNilai {{ $sub->score }}@endif</span>@elseif($sub)<span class="text-sky-600">Sudah dikumpulkan</span>@else<span class="text-amber-600">Belum dikumpulkan</span>@endif @endif
                </p>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-400 flex-shrink-0">
                @if($canManage)<span class="font-semibold px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-600">{{ $a->submissions_count }} kumpul</span>@endif
                <span class="flex items-center gap-1"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> {{ $a->comments_count }}</span>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </div>
        </a>
        @empty
        <div class="card p-10 text-center text-slate-400"><i data-lucide="clipboard-list" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Belum ada tugas/latihan.</p></div>
        @endforelse
    </div>

    {{-- ===== TAB ANGGOTA ===== --}}
    <div x-show="tab==='anggota'" x-cloak>
        <div class="card divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($classroom->members as $mem)
            <div class="flex items-center gap-3 p-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background:var(--cp)">{{ $mem->user?->initial() ?? '?' }}</div>
                <div class="min-w-0 flex-1"><p class="font-medium text-slate-700 dark:text-slate-200 truncate">{{ $mem->user?->displayName() }}</p><p class="text-xs text-slate-400">{{ $mem->user?->roleLabel() }}</p></div>
                <span class="text-[11px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 capitalize">{{ $mem->role_in_class }}</span>
            </div>
            @empty
            <p class="p-8 text-center text-slate-400 text-sm">Belum ada anggota.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
