@extends('layouts.app')
@section('title', 'Panduan Visual')
@section('hide_page_footer', true)

@section('content')
@php
    $breadcrumbs = [['label' => 'Panduan Visual', 'url' => route('panduan.visual')]];
@endphp

<div class="panduan-shell -mx-5 md:-mx-7 -my-4 flex flex-col bg-white dark:bg-slate-900 overflow-hidden"
     style="height: calc(100dvh - 4rem - 2rem - 1.75rem); min-height: 420px;">
    <iframe
        src="{{ route('panduan.content') }}"
        class="w-full h-full flex-1 border-0 min-h-0 bg-white"
        title="Panduan Visual SIMS"
        loading="eager"
        referrerpolicy="same-origin"
    ></iframe>
</div>
@endsection
