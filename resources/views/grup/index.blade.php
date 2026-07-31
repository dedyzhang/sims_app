@extends('layouts.app')
@section('title', 'Grup Chat')

@section('content')
<div class="space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">Beranda <span class="mx-1">/</span> Grup Chat</nav>
        <h1 class="page-title">Grup Chat</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            Grup percakapan kelas dan paguyuban orang tua. Anggota mengikuti data sekolah &mdash; tidak perlu diundang manual.
        </p>
    </div>

    @if($grupAktif->isEmpty() && $grupArsip->isEmpty())
        <div class="card p-8 text-center">
            <i data-lucide="users-round" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600"></i>
            <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada grup untuk Anda</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Grup dibuat otomatis per kelas. Hubungi admin bila Anda merasa seharusnya tergabung di sebuah grup.
            </p>
        </div>
    @endif

    @if($grupAktif->isNotEmpty())
    <div class="card divide-y divide-slate-100 dark:divide-slate-700">
        @foreach($grupAktif as $g)
            @include('grup.partials._kartu', ['g' => $g])
        @endforeach
    </div>
    @endif

    @if($grupArsip->isNotEmpty())
    <div x-data="{ buka: false }">
        <button type="button" @click="buka = !buka"
                class="flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition">
            <i data-lucide="archive" class="w-4 h-4"></i>
            Arsip ({{ $grupArsip->count() }})
            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" ::class="buka && 'rotate-180'"></i>
        </button>
        <div x-show="buka" x-cloak x-collapse class="card divide-y divide-slate-100 dark:divide-slate-700 mt-3">
            @foreach($grupArsip as $g)
                @include('grup.partials._kartu', ['g' => $g])
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
