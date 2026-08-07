{{-- Render teks_soal/opsi (HTML dari TinyMCE) dgn aman lewat RichText::clean(). Var: $html --}}
<div class="ujian-rich-body">{!! \App\Support\RichText::clean($html) !!}</div>

@once
@push('styles')
<style>
    .ujian-rich-body { line-height: 1.6; }
    .ujian-rich-body p { margin: 0 0 .5em; }
    .ujian-rich-body p:last-child { margin-bottom: 0; }
    .ujian-rich-body ul, .ujian-rich-body ol { margin: 0 0 .5em 1.4em; }
    .ujian-rich-body img.math-svg { display: inline-block; vertical-align: middle; }
    .ujian-rich-body img:not(.math-svg) { max-width: 100%; border-radius: 8px; margin: 6px 0; }
    .ujian-rich-body table { border-collapse: collapse; margin: .5em 0; }
    .ujian-rich-body table td, .ujian-rich-body table th { border: 1px solid #cbd5e1; padding: 4px 8px; }
</style>
@endpush
@endonce
