@extends('layouts.app')
@section('title', 'Panduan SIMS')

@section('content')
@php
    $breadcrumbs = [['label' => 'Panduan SIMS', 'url' => route('panduan.index')]];
@endphp

<div class="max-w-7xl mx-auto" x-data="panduanPage()" @click="if ($event.target.matches('.panduan-prose img')) zoomSrc = $event.target.src">
    <div class="flex items-start justify-between flex-wrap gap-3 mb-6">
        <div>
            <nav class="text-xs text-slate-400 mb-1">Beranda <span class="mx-1">/</span> Panduan SIMS</nav>
            <h1 class="page-title">Panduan SIMS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tutorial penggunaan aplikasi sekolah dari dokumen panduan resmi.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-5 items-start">
        <aside class="lg:sticky lg:top-6 space-y-4">
            <div class="card p-4">
                <label for="panduanSearch" class="form-label">Cari di panduan</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input id="panduanSearch" x-model.debounce.150ms="q" type="search" class="form-input pl-9" placeholder="Contoh: absensi, rapor, SPP">
                </div>
                <div class="mt-3 flex items-center justify-between text-[11px] text-slate-400 gap-3">
                    <span>Terakhir diperbarui</span>
                    <span class="font-semibold text-slate-500 dark:text-slate-300 text-right">{{ $lastUpdated }}</span>
                </div>
            </div>

            <div class="card p-4 max-h-[calc(100vh-220px)] overflow-y-auto">
                <div class="flex items-center gap-2 mb-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                    <i data-lucide="list-tree" class="w-4 h-4" style="color:var(--cp)"></i>
                    Daftar Isi
                </div>
                <nav class="space-y-1.5 text-sm">
                    @foreach($sections as $section)
                    <div data-toc-item data-search="{{ e($section['search']) }}" x-show="matches($el)" x-cloak>
                        <a href="#{{ $section['id'] }}" class="block px-3 py-2 rounded-xl font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/60">
                            {{ $section['title'] }}
                        </a>
                        @if(!empty($section['subsections']))
                        <div class="ml-3 pl-3 border-l border-slate-200 dark:border-slate-700 space-y-0.5">
                            @foreach($section['subsections'] as $subsection)
                            <a href="#{{ $subsection['id'] }}" class="block px-3 py-1.5 rounded-lg text-xs text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/60">
                                {{ $subsection['title'] }}
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </nav>
            </div>
        </aside>

        <main class="space-y-4 min-w-0">
            @if($introHtml)
            <section class="card p-5 panduan-prose text-slate-600 dark:text-slate-300">
                {!! $introHtml !!}
            </section>
            @endif

            @foreach($sections as $section)
            <article id="card-{{ $section['id'] }}" data-panduan-section data-search="{{ e($section['search']) }}" x-show="matches($el)" x-cloak class="card p-5 sm:p-7 panduan-prose text-slate-700 dark:text-slate-300 scroll-mt-24">
                {!! $section['html'] !!}
            </article>
            @endforeach

            <div x-show="q && !hasMatch()" x-cloak class="card p-10 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 grid place-items-center mb-3">
                    <i data-lucide="search-x" class="w-7 h-7 text-slate-400"></i>
                </div>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Tidak ada bagian yang cocok</p>
                <p class="text-xs text-slate-400 mt-1">Coba kata kunci lain atau kosongkan pencarian.</p>
            </div>
        </main>
    </div>

    {{-- Lightbox: klik screenshot di panduan utk lihat ukuran penuh --}}
    <div x-show="zoomSrc" x-cloak @click="zoomSrc=null" @keydown.escape.window="zoomSrc=null" class="fixed inset-0 z-[10000] flex items-center justify-center p-6" style="background:rgba(15,12,10,.82); backdrop-filter:blur(6px)">
        <img :src="zoomSrc" class="max-h-[90vh] max-w-[94vw] rounded-2xl shadow-2xl ring-4 ring-white/10" @click.stop>
        <button @click="zoomSrc=null" class="absolute top-5 right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
</div>

<style>
    html { scroll-behavior: smooth; }
    .panduan-prose { line-height: 1.75; }
    .panduan-prose h2 { scroll-margin-top: 96px; font-size: 1.25rem; line-height: 1.35; font-weight: 800; color: color-mix(in srgb, var(--cp) 72%, #1e293b); margin: 0 0 1rem; padding-bottom: .7rem; border-bottom: 1px solid color-mix(in srgb, var(--cp) 14%, #e2e8f0); }
    .dark .panduan-prose h2 { color: #f1f5f9; border-bottom-color: #334155; }
    .panduan-prose h3 { scroll-margin-top: 96px; font-size: 1rem; line-height: 1.45; font-weight: 800; color: #334155; margin: 1.6rem 0 .65rem; }
    .dark .panduan-prose h3 { color: #e2e8f0; }
    .panduan-prose p { margin: .55rem 0; }
    .panduan-prose ul, .panduan-prose ol { margin: .65rem 0 1rem; padding-left: 1.35rem; }
    .panduan-prose ul { list-style: disc; }
    .panduan-prose ol { list-style: decimal; }
    .panduan-prose li { margin: .25rem 0; }
    .panduan-prose code { padding: .12rem .35rem; border-radius: .45rem; background: color-mix(in srgb, var(--cp) 9%, #f8fafc); color: color-mix(in srgb, var(--cp) 70%, #0f172a); font-size: .86em; font-weight: 700; }
    .dark .panduan-prose code { background: #0f172a; color: #cbd5e1; }
    .panduan-prose a { color: var(--cp); font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
    .panduan-prose blockquote { margin: 1rem 0; padding: .8rem 1rem; border-left: 4px solid var(--cp); background: color-mix(in srgb, var(--cp) 7%, #fff); border-radius: .8rem; }
    .dark .panduan-prose blockquote { background: #0f172a; }
    .panduan-prose table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: .88rem; }
    .panduan-prose th, .panduan-prose td { border: 1px solid #e2e8f0; padding: .55rem .7rem; text-align: left; }
    .dark .panduan-prose th, .dark .panduan-prose td { border-color: #334155; }
    .panduan-prose th { background: #f8fafc; font-weight: 800; }
    .dark .panduan-prose th { background: #0f172a; }
    .panduan-prose img { display: block; max-width: 100%; height: auto; margin: 1rem auto; border-radius: 1rem; border: 1px solid color-mix(in srgb, var(--cp) 14%, #e2e8f0); box-shadow: 0 10px 30px -12px rgba(15,23,42,.18); cursor: zoom-in; }
    .dark .panduan-prose img { border-color: #334155; box-shadow: 0 10px 30px -12px rgba(0,0,0,.4); }
</style>

<script>
    function panduanPage() {
        return {
            q: '',
            zoomSrc: null,
            matches(el) {
                const query = this.q.trim().toLowerCase();
                if (!query) return true;
                return (el.dataset.search || '').toLowerCase().includes(query);
            },
            hasMatch() {
                const query = this.q.trim().toLowerCase();
                if (!query) return true;
                return Array.from(document.querySelectorAll('[data-panduan-section]')).some((el) => (el.dataset.search || '').toLowerCase().includes(query));
            }
        }
    }
</script>
@endsection