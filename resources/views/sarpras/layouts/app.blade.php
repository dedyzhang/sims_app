{{--
    Layout modul Sarpras — terintegrasi ke shell SIMS.
    Memakai layout utama SIMS (sidebar, topbar, tema, font, Tailwind CDN).
    View modul mengisi @section('title') dan @section('sarpras_body');
    keduanya dirender di dalam slot konten SIMS oleh layout ini.
--}}
@extends('layouts.app')

@section('content')
    {{-- Header halaman Sarpras --}}
    <div class="flex items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-2.5">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-primary/10 text-primary">
                <i data-lucide="building-2" class="w-[18px] h-[18px]"></i>
            </span>
            <div>
                <h1 class="text-base sm:text-lg font-bold text-slate-800 dark:text-slate-100 leading-tight">
                    @yield('title', 'Sarpras')
                </h1>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 font-medium">Sarana &amp; Prasarana</p>
            </div>
        </div>
        @hasSection('sarpras_actions')
            <div class="flex items-center gap-2">@yield('sarpras_actions')</div>
        @endif
    </div>

    {{-- Flash & error --}}
    @if (session('sukses'))
        <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm">
            {{ session('sukses') }}
        </div>
    @endif
    @if (session('gagal'))
        <div class="mb-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 text-sm">
            {{ session('gagal') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-200 px-4 py-3">
            <ul class="list-disc list-inside text-sm space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Konten halaman modul --}}
    @yield('sarpras_body')
@endsection
