@extends('layouts.app')
@section('title', $pengumuman->judul)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('pengumuman.index') }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <h1 class="page-title flex-1 truncate">Pengumuman</h1>
        @if($bolehKelola)
        <a href="{{ route('pengumuman.edit', $pengumuman) }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-500" title="Sunting"><i data-lucide="pencil" class="w-4 h-4"></i></a>
        <form method="POST" action="{{ route('pengumuman.destroy', $pengumuman) }}" onsubmit="return confirmDelete(this)">
            @csrf @method('DELETE')
            <button type="submit" class="p-2 rounded-lg border border-rose-200 dark:border-rose-700 hover:bg-rose-50 dark:hover:bg-rose-900/30 text-rose-500" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        </form>
        @endif
    </div>

    <article class="card p-6 sm:p-8 space-y-4">
        <header class="space-y-3 border-b border-slate-100 dark:border-slate-700 pb-4">
            <div class="w-12 h-12 rounded-2xl grid place-items-center text-white" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                <i data-lucide="megaphone" class="w-6 h-6"></i>
            </div>
            <h2 class="text-xl font-black text-slate-800 dark:text-slate-100 leading-snug">{{ $pengumuman->judul }}</h2>
            <div class="flex items-center flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400">
                <span class="inline-flex items-center gap-1"><i data-lucide="user" class="w-3.5 h-3.5"></i>{{ $pengumuman->pembuat?->displayName() ?? 'Sistem' }}</span>
                <span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i>{{ $pengumuman->created_at->locale('id')->isoFormat('D MMMM Y, HH:mm') }}</span>
            </div>
            <div class="flex items-center flex-wrap gap-1.5">
                <span class="text-[11px] text-slate-400">Untuk:</span>
                @if($pengumuman->untukSemua())
                    <span class="badge bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">Semua peran</span>
                @else
                    @foreach($pengumuman->target_roles as $r)
                        <span class="badge bg-primary/10" style="color:var(--cp)">{{ \App\Models\Pengumuman::TARGET_ROLES[$r] ?? $r }}</span>
                    @endforeach
                @endif
            </div>
        </header>

        <div class="prose-sm text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-wrap">{{ $pengumuman->isi }}</div>
    </article>
</div>
@endsection
