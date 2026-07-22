@extends('layouts.app')
@section('title', 'Panduan Visual')
@section('hide_page_footer', true)

@push('styles')
<style>
    /* Panduan visual: isi penuh kolom konten (antara header app & ticker) */
    .panduan-layout-host main.panduan-main-fallback {
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden !important;
        display: flex;
        flex-direction: column;
        padding: 0 !important;
    }

    .panduan-layout-host main.panduan-main-fallback > .anim-fade {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }

    .panduan-shell {
        flex: 1 1 auto;
        min-height: 0;
        width: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .panduan-shell iframe {
        flex: 1 1 auto;
        min-height: 0;
        width: 100%;
        border: 0;
        display: block;
    }
</style>
@endpush

@section('content')
@php
    $breadcrumbs = [['label' => 'Panduan Visual', 'url' => route('panduan.visual')]];
@endphp

<div class="panduan-shell w-full max-w-full min-w-0 bg-white dark:bg-slate-900"
     x-data
     x-init="
        const main = document.querySelector('main');
        main?.classList.add('panduan-main-fallback');
        const shell = $el;
        const frame = $refs.panduanFrame;
        const fitShell = () => {
            if (!main || !shell) return;
            const top = main.getBoundingClientRect().top;
            const vh = window.visualViewport?.height ?? window.innerHeight;
            const ticker = document.querySelector('[data-sims-ticker]');
            const tickerH = ticker ? ticker.getBoundingClientRect().height : 0;
            const h = Math.round(vh - top - tickerH);
            if (h > 0) shell.style.height = h + 'px';
        };
        fitShell();
        requestAnimationFrame(fitShell);
        if (main) new ResizeObserver(fitShell).observe(main);
        window.addEventListener('resize', fitShell, { passive: true });
        window.visualViewport?.addEventListener('resize', fitShell, { passive: true });
        window.visualViewport?.addEventListener('scroll', fitShell, { passive: true });
        const pushTheme = () => {
            if (!frame?.contentWindow) return;
            const dark = document.documentElement.classList.contains('dark');
            frame.contentWindow.postMessage({ type: 'sims-theme', mode: dark ? 'dark' : 'light' }, window.location.origin);
        };
        frame?.addEventListener('load', () => { pushTheme(); fitShell(); });
        new MutationObserver(pushTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        pushTheme();
     ">
    <iframe
        x-ref="panduanFrame"
        src="{{ route('panduan.content') }}"
        class="bg-white dark:bg-slate-900"
        title="Panduan Visual SIMS"
        loading="eager"
        referrerpolicy="same-origin"
    ></iframe>
</div>
@endsection
