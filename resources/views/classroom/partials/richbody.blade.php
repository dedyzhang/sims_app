{{-- Render konten kaya (materi/instruksi). Var: $html --}}
<div class="rich-body">{!! \App\Support\RichText::clean($html) !!}</div>

@once
@push('styles')
<style>
    .rich-body { line-height: 1.7; color: inherit; }
    .rich-body p { margin: 0 0 .7em; }
    .rich-body ul {
        list-style-type: disc !important;
        margin: 0.5em 0 0.7em 1.5em !important;
        padding-left: 0.5em !important;
    }
    .rich-body ol {
        list-style-type: decimal !important;
        margin: 0.5em 0 0.7em 1.5em !important;
        padding-left: 0.5em !important;
    }
    .rich-body ol[type="A"], .rich-body ol.list-alpha-upper { list-style-type: upper-alpha !important; }
    .rich-body ol[type="a"], .rich-body ol.list-alpha-lower { list-style-type: lower-alpha !important; }
    .rich-body ol[type="I"], .rich-body ol.list-roman-upper { list-style-type: upper-roman !important; }
    .rich-body ol[type="i"], .rich-body ol.list-roman-lower { list-style-type: lower-roman !important; }
    .rich-body ol[type="1"], .rich-body ol.list-decimal { list-style-type: decimal !important; }
    .rich-body ul ul {
        list-style-type: circle !important;
        margin: 0.25em 0 0.25em 1.25em !important;
    }
    .rich-body ul ul ul {
        list-style-type: square !important;
    }
    .rich-body ol ol {
        list-style-type: lower-alpha !important;
        margin: 0.25em 0 0.25em 1.25em !important;
    }
    .rich-body ol ol ol {
        list-style-type: lower-roman !important;
    }
    .rich-body li {
        display: list-item !important;
        margin-bottom: 0.25em;
    }
    .rich-body li:last-child {
        margin-bottom: 0;
    }
    .rich-body li > p {
        margin: 0 0 0.25em;
    }
    .rich-body li > p:last-child {
        margin-bottom: 0;
    }
    .rich-body svg { display: inline-block; vertical-align: middle; }
    .rich-body .lucide,
    .rich-body svg.lucide {
        display: inline-block;
        vertical-align: -0.15em;
        width: 1.15em;
        height: 1.15em;
    }
    .rich-body i[data-lucide] {
        display: inline-block;
        vertical-align: middle;
        min-width: 1em;
        min-height: 1em;
    }
    .rich-body img.math-svg { display: inline-block; }
    .rich-body table { border-collapse: collapse; margin: .5em 0; }
    .rich-body table td, .rich-body table th { border: 1px solid #cbd5e1; padding: 4px 8px; }
    .rich-body a { color: var(--cp); text-decoration: underline; }
    .yt-frame { position: relative; padding-bottom: 56.25%; height: 0; margin: 12px 0; border-radius: 12px; overflow: hidden; background:#000; }
    .yt-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
</style>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rich-body .yt-embed[data-yt]').forEach(function (el) {
        var id = el.getAttribute('data-yt');
        if (!/^[A-Za-z0-9_-]{11}$/.test(id || '')) return;
        var wrap = document.createElement('div');
        wrap.className = 'yt-frame';
        var f = document.createElement('iframe');
        f.src = 'https://www.youtube-nocookie.com/embed/' + id;
        f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
        f.allowFullscreen = true;
        wrap.appendChild(f);
        el.replaceWith(wrap);
    });
});
</script>
@endpush
@endonce
