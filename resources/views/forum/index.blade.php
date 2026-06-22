@extends('layouts.app')
@section('title', 'Forum Diskusi')

@php
    $catColor = [
        'akademik' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'kesiswaan' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'sarpras' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'umum' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'pengumuman' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    ];
@endphp

@section('content')
<div class="space-y-5">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">Beranda <span class="mx-1">/</span> Forum Diskusi</nav>
            <h1 class="page-title">Forum Diskusi Kelas</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Diskusi akademik, kesiswaan, sarpras &amp; pengumuman antar guru, siswa, dan orang tua.</p>
        </div>
        <div class="flex gap-2">
            @can('manageAccess', App\Models\ForumTopic::class)
            <a href="{{ route('forum.access.edit') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="shield-check" class="w-4 h-4"></i> Pengaturan Akses</a>
            @endcan
            @can('create', App\Models\ForumTopic::class)
            <a href="{{ route('forum.create') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-white hover:opacity-90 transition" style="background:var(--cp)"><i data-lucide="plus" class="w-4 h-4"></i> Buat Topik</a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- KIRI/TENGAH: daftar topik --}}
        <div class="lg:col-span-2 space-y-4">
            <form method="GET" action="{{ route('forum.index') }}" class="card p-3 flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-40">
                    <label class="form-label text-xs">Kategori</label>
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $k => $label)
                        <option value="{{ $k }}" @selected(($filter['category'] ?? '')===$k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($kelasList->isNotEmpty())
                <div class="flex-1 min-w-40">
                    <label class="form-label text-xs">Kelas</label>
                    <select name="kelas" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        @foreach($kelasList as $k)
                        <option value="{{ $k->uuid }}" @selected(($filter['kelas'] ?? '')===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="flex-[2] min-w-48">
                    <label class="form-label text-xs">Cari</label>
                    <input type="text" name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Cari judul / isi…" class="form-input">
                </div>
                <button class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300">Cari</button>
            </form>

            @forelse($topics as $t)
            @php $isNew = $t->last_activity_at && (!isset($reads[$t->uuid]) || ($reads[$t->uuid] && $t->last_activity_at->gt($reads[$t->uuid]))); @endphp
            <a href="{{ route('forum.show', $t) }}" class="card p-4 block hover:border-primary transition">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0" style="background:var(--cp)">{{ $t->author?->initial() ?? '?' }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($t->is_pinned)<i data-lucide="pin" class="w-3.5 h-3.5 text-rose-500"></i>@endif
                            @if($t->is_locked)<i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400"></i>@endif
                            <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold {{ $catColor[$t->category] ?? '' }}">{{ $categories[$t->category] ?? $t->category }}</span>
                            @if($isNew)<span class="text-[11px] px-2 py-0.5 rounded-full bg-primary/10 text-primary font-semibold" style="color:var(--cp)">balasan baru</span>@endif
                        </div>
                        <h3 class="font-bold text-slate-800 dark:text-slate-100 mt-1 truncate">{{ $t->title }}</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 line-clamp-1">{{ Str::limit($t->body, 120) }}</p>
                        <div class="flex items-center gap-3 text-xs text-slate-400 mt-2 flex-wrap">
                            <span class="font-medium text-slate-500 dark:text-slate-300">{{ $t->author?->displayName() }}</span>
                            @if($t->kelas)<span>· Kelas {{ $t->kelas->tingkat }}{{ $t->kelas->kelas }}</span>@endif
                            @if($t->pelajaran)<span>· {{ $t->pelajaran->nama }}</span>@endif
                            <span>· {{ $t->last_activity_at?->locale('id')->diffForHumans() }}</span>
                            <span class="flex items-center gap-1"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> {{ $t->replies_count }}</span>
                            <span class="flex items-center gap-1"><i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> {{ $t->reactions_count }}</span>
                        </div>
                    </div>
                </div>
            </a>
            @empty
            <div class="card p-12 text-center text-slate-400"><i data-lucide="messages-square" class="w-12 h-12 mx-auto mb-3 opacity-30"></i><p class="font-medium">Belum ada topik diskusi.</p></div>
            @endforelse

            {{ $topics->links() }}
        </div>

        {{-- KANAN: topik populer --}}
        <div class="space-y-4">
            <div class="card p-4">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 mb-3"><i data-lucide="flame" class="w-[18px] h-[18px] text-rose-500"></i> Topik Populer</h3>
                <div class="space-y-3">
                    @forelse($popular as $p)
                    <a href="{{ route('forum.show', $p) }}" class="flex items-start gap-2 group">
                        <i data-lucide="message-square" class="w-4 h-4 text-slate-300 mt-0.5 flex-shrink-0"></i>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200 group-hover:text-primary truncate">{{ $p->title }}</p>
                            <p class="text-xs text-slate-400">{{ $p->replies_count }} balasan</p>
                        </div>
                    </a>
                    @empty
                    <p class="text-sm text-slate-400">Belum ada.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
