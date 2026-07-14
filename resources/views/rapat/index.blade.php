@extends('layouts.app')
@section('title', 'Agenda Rapat')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Agenda Rapat</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Notulen &amp; dokumentasi rapat sekolah</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if($canManage)
            <a href="{{ route('rapat.sekretaris') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="user-cog" class="w-4 h-4"></i> Kelola Sekretaris
            </a>
            <a href="{{ route('rapat.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Catat Rapat
            </a>
            @endif
        </div>
    </div>

    @if($rapats->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="notebook-pen" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada rapat tercatat.</p>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @foreach($rapats as $r)
            <a href="{{ route('rapat.show', $r) }}" class="p-4 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                    <i data-lucide="notebook-pen" class="w-[18px] h-[18px]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $r->judul }}</p>
                    <p class="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3 h-3"></i> {{ $r->tanggal->isoFormat('dddd, D MMM Y') }}</span>
                        <span class="flex items-center gap-1"><i data-lucide="users" class="w-3 h-3"></i> {{ $r->guru_hadir_count }} guru hadir</span>
                        <span class="flex items-center gap-1"><i data-lucide="image" class="w-3 h-3"></i> {{ $r->dokumentasi_count }} dokumentasi</span>
                    </p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-300 flex-shrink-0"></i>
            </a>
            @endforeach
        </div>
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $rapats->links() }}
        </div>
    </div>
    @endif
</div>
@endsection
