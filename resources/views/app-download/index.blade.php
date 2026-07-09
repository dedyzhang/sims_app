@extends('layouts.app')
@section('title', 'Unduh Aplikasi')

@section('content')
@php
    $fmt = fn ($b) => $b >= 1048576 ? round($b/1048576, 1).' MB' : round($b/1024).' KB';
    $meta = [
        'apk'     => ['label' => 'Aplikasi Android', 'desc' => 'Pasang di HP Android (.apk)', 'icon' => 'smartphone', 'color' => 'emerald'],
        'windows' => ['label' => 'Aplikasi Windows', 'desc' => 'Installer untuk PC/Laptop (.exe/.msi)', 'icon' => 'monitor', 'color' => 'sky'],
    ];
@endphp
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="page-title">Unduh Aplikasi</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pasang aplikasi sekolah di perangkatmu.</p>
    </div>

    @if(empty($apps))
        <div class="card p-10 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 grid place-items-center mb-3">
                <i data-lucide="package-x" class="w-7 h-7 text-slate-400"></i>
            </div>
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Belum ada aplikasi tersedia</p>
            <p class="text-xs text-slate-400 mt-1">Silakan cek kembali nanti.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($apps as $key => $app)
            @php $m = $meta[$key]; @endphp
            <div class="card p-6 flex flex-col">
                <div class="flex items-center gap-3 mb-4">
                    <span class="grid place-items-center w-12 h-12 rounded-2xl bg-{{ $m['color'] }}-500/10 text-{{ $m['color'] }}-600 flex-shrink-0">
                        <i data-lucide="{{ $m['icon'] }}" class="w-6 h-6"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-bold text-slate-800 dark:text-slate-100">{{ $m['label'] }}</p>
                        <p class="text-xs text-slate-400">{{ $m['desc'] }}</p>
                    </div>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1 mb-4">
                    <p class="truncate"><i data-lucide="file" class="w-3.5 h-3.5 inline -mt-0.5"></i> {{ $app['name'] }}</p>
                    <p>
                        <i data-lucide="hard-drive" class="w-3.5 h-3.5 inline -mt-0.5"></i> {{ $fmt($app['size']) }}
                        @if($app['version'])
                            <span class="ml-2 inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-700 px-2 py-0.5 font-semibold text-slate-600 dark:text-slate-300">{{ $app['version'] }}</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('app.download.file', $key) }}"
                   class="btn-primary mt-auto px-5 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Unduh
                </a>
            </div>
            @endforeach
        </div>

        <p class="text-xs text-slate-400 mt-5 leading-relaxed">
            <i data-lucide="info" class="w-3.5 h-3.5 inline -mt-0.5"></i>
            Untuk Android, aktifkan “Instal dari sumber tidak dikenal” bila diminta saat memasang .apk.
        </p>
    @endif
</div>
@endsection
