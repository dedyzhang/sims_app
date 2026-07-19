@extends('layouts.app')
@section('title', 'Panduan Visual')
@section('hide_page_footer', true)

@section('content')
@php
    $breadcrumbs = [['label' => 'Panduan Visual', 'url' => route('panduan.visual')]];
@endphp

<div class="panduan-shell -mx-5 md:-mx-7 -my-4 flex flex-col w-full max-w-full min-w-0 bg-white dark:bg-slate-900 overflow-hidden"
     style="height: calc(100dvh - 4rem - 2rem - 1.75rem); min-height: 320px;"
     x-data
     x-init="
        const frame = $refs.panduanFrame;
        const pushTheme = () => {
            if (!frame?.contentWindow) return;
            const dark = document.documentElement.classList.contains('dark');
            frame.contentWindow.postMessage({ type: 'sims-theme', mode: dark ? 'dark' : 'light' }, window.location.origin);
        };
        frame?.addEventListener('load', pushTheme);
        new MutationObserver(pushTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        pushTheme();
     ">
    <iframe
        x-ref="panduanFrame"
        src="{{ route('panduan.content') }}"
        class="block w-full max-w-full h-full flex-1 border-0 min-h-0 min-w-0 bg-white dark:bg-slate-900"
        title="Panduan Visual SIMS"
        loading="eager"
        referrerpolicy="same-origin"
    ></iframe>
</div>
@endsection
