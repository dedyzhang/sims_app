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

    {{--
        Flash & error: ditangani terpusat oleh toast di layout utama SIMS
        (resources/views/layouts/app.blade.php) yang sudah mendukung key
        'sukses'/'gagal' modul Sarpras. Banner inline dihapus agar tidak dobel.
    --}}

    {{-- Konten halaman modul --}}
    @yield('sarpras_body')
@endsection
