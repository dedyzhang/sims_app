{{-- Render teks_soal/opsi (HTML dari TinyMCE) dgn aman lewat RichText::clean(). Var: $html --}}
<div class="ujian-rich-body">{!! \App\Support\RichText::clean($html) !!}</div>

@once
@push('styles')
<style>
    .ujian-rich-body { line-height: 1.6; overflow-wrap: break-word; word-wrap: break-word; overflow-x: auto; }
    .ujian-rich-body pre { white-space: pre-wrap; word-wrap: break-word; }
    .ujian-rich-body p, .ujian-rich-body span { max-width: 100%; white-space: pre-wrap !important; }
    .ujian-rich-body p { margin: 0 0 .5em; }
    .ujian-rich-body p:last-child { margin-bottom: 0; }
    .ujian-rich-body ul {
        list-style-type: disc !important;
        margin: 0.5em 0 0.5em 1.5em !important;
        padding-left: 0.5em !important;
    }
    .ujian-rich-body ol {
        list-style-type: decimal !important;
        margin: 0.5em 0 0.5em 1.5em !important;
        padding-left: 0.5em !important;
    }
    .ujian-rich-body ol[type="A"], .ujian-rich-body ol.list-alpha-upper { list-style-type: upper-alpha !important; }
    .ujian-rich-body ol[type="a"], .ujian-rich-body ol.list-alpha-lower { list-style-type: lower-alpha !important; }
    .ujian-rich-body ol[type="I"], .ujian-rich-body ol.list-roman-upper { list-style-type: upper-roman !important; }
    .ujian-rich-body ol[type="i"], .ujian-rich-body ol.list-roman-lower { list-style-type: lower-roman !important; }
    .ujian-rich-body ol[type="1"], .ujian-rich-body ol.list-decimal { list-style-type: decimal !important; }
    .ujian-rich-body ul ul {
        list-style-type: circle !important;
        margin: 0.25em 0 0.25em 1.25em !important;
    }
    .ujian-rich-body ul ul ul {
        list-style-type: square !important;
    }
    .ujian-rich-body ol ol {
        list-style-type: lower-alpha !important;
        margin: 0.25em 0 0.25em 1.25em !important;
    }
    .ujian-rich-body ol ol ol {
        list-style-type: lower-roman !important;
    }
    .ujian-rich-body li {
        display: list-item !important;
        margin-bottom: 0.25em;
    }
    .ujian-rich-body li:last-child {
        margin-bottom: 0;
    }
    .ujian-rich-body li > p {
        margin: 0 0 0.25em;
    }
    .ujian-rich-body li > p:last-child {
        margin-bottom: 0;
    }
    .ujian-rich-body img.math-svg { display: inline-block; vertical-align: middle; }
    .ujian-rich-body img:not(.math-svg) { max-width: 100%; border-radius: 8px; margin: 6px 0; }
    .ujian-rich-body svg { display: inline-block; vertical-align: middle; }
    .ujian-rich-body .lucide,
    .ujian-rich-body svg.lucide {
        display: inline-block;
        vertical-align: -0.15em;
        width: 1.15em;
        height: 1.15em;
    }
    .ujian-rich-body i[data-lucide] {
        display: inline-block;
        vertical-align: middle;
        min-width: 1em;
        min-height: 1em;
    }
    .ujian-rich-body table { border-collapse: collapse; margin: .5em 0; }
    .ujian-rich-body table td, .ujian-rich-body table th { border: 1px solid #cbd5e1; padding: 4px 8px; }
</style>
@endpush
@endonce

