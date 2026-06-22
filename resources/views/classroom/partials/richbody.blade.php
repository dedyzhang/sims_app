{{-- Render konten kaya (materi/instruksi). Var: $html --}}
<div class="rich-body">{!! \App\Support\RichText::clean($html) !!}</div>

@once
@push('styles')
<style>
    .rich-body { line-height: 1.7; color: inherit; }
    .rich-body p { margin: 0 0 .7em; }
    .rich-body ul, .rich-body ol { margin: 0 0 .7em 1.4em; }
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
