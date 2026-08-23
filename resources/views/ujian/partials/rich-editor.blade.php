{{-- Editor TinyMCE + rumus (LaTeX→SVG) + upload gambar, dipakai utk teks_soal & tiap opsi
     jawaban. Adaptasi dari classroom/partials/editor.blade.php TAPI tanpa tombol YouTube
     (tak relevan utk soal ujian) dan dgn lifecycle mount/unmount MANUAL (window.UjianEditor)
     krn opsi jawaban ditambah/dihapus dinamis lewat Alpine x-for — tinymce.init({selector})
     yang jalan sekali di page-load tak akan pernah "melihat" textarea yg baru ditambah
     belakangan. --}}
@once
@push('styles')
<style>
    .ujian-rich-editor-wrap math-field {
        width: 100%; min-height: 120px; font-size: 20px; border: 1px solid #cbd5e1;
        padding: 12px; border-radius: 8px; display: block; outline: none;
        background: #ffffff !important; color: #0f172a !important; margin: 8px 0;
    }
    body { --keyboard-zindex: 70000 !important; }
    .ML__keyboard, mathlive-shared-virtual-keyboard, [part=virtual-keyboard] { z-index: 70000 !important; }
</style>
@endpush

@push('scripts')
<script>window.MathJax = { tex:{ inlineMath:[['$','$']] }, svg:{ fontCache:'none' }, startup:{ typeset:false } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" id="MathJax-script"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://unpkg.com/mathlive"></script>
<script>
(function () {
    function openMath(editor) {
        let initialLatex = '';
        let displayMode = false;
        let selectedNode = editor.selection.getNode();
        const isEditing = selectedNode && selectedNode.tagName === 'IMG' && selectedNode.classList.contains('math-svg');
        if (isEditing) {
            initialLatex = selectedNode.getAttribute('data-latex') || '';
            displayMode = selectedNode.style.display === 'block';
        }

        editor.windowManager.open({
            title: isEditing ? 'Edit Rumus Matematika' : 'Sisipkan Rumus Matematika',
            body: { type: 'panel', items: [
                {
                    type: 'htmlpanel',
                    html: '<div style="margin-bottom: 12px; font-weight: 500; font-size: 14px;">Rumus Matematika (Visual):</div>' +
                          '<math-field id="ujian-mathlive-field" virtual-keyboard-mode="onfocus" style="width: 100%; min-height: 120px; font-size: 20px; border: 1px solid #cbd5e1; padding: 12px; border-radius: 8px; display: block; outline: none; background: #ffffff !important; color: #0f172a !important; margin: 8px 0;"></math-field>' +
                          '<div style="font-size: 12px; color: #64748b; margin-top: 6px;">Gunakan virtual keyboard MathLive yang muncul otomatis untuk menulis pecahan, matriks, akar, pangkat, dll.</div>'
                },
                { type: 'checkbox', name: 'display', label: 'Tampilkan sebagai blok (besar, di tengah)' },
            ] },
            initialData: { display: displayMode },
            buttons: [{ type: 'cancel', text: 'Batal' }, { type: 'submit', text: isEditing ? 'Perbarui' : 'Sisipkan', primary: true }],
            onSubmit: function (api) {
                const data = api.getData();
                const mf = document.getElementById('ujian-mathlive-field');
                const latex = mf ? (mf.value || '').trim() : '';
                if (!latex) { api.close(); return; }
                try {
                    const node = MathJax.tex2svg(latex, { display: !!data.display });
                    const svg = node.querySelector('svg');
                    const svgStr = new XMLSerializer().serializeToString(svg);
                    const uri = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgStr)));
                    const esc = latex.replace(/"/g, '&quot;');
                    const style = data.display ? 'display:block;margin:10px auto;height:2.4em' : 'vertical-align:middle;height:1.5em';

                    if (isEditing) {
                        selectedNode.setAttribute('src', uri);
                        selectedNode.setAttribute('data-latex', latex);
                        selectedNode.setAttribute('alt', latex);
                        selectedNode.style.cssText = style;
                    } else {
                        editor.insertContent('<img class="math-svg" src="' + uri + '" data-latex="' + esc + '" alt="' + esc + '" style="' + style + '">');
                    }
                } catch (e) {
                    editor.notificationManager.open({ text: 'Gagal merender rumus. Periksa input.', type: 'error' });
                }
                api.close();
            },
        });

        setTimeout(() => {
            const mf = document.getElementById('ujian-mathlive-field');
            if (mf) { mf.value = initialLatex; setTimeout(() => mf.focus(), 50); }
        }, 100);
    }

    function baseConfig() {
        const dark = document.documentElement.classList.contains('dark');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        return {
            height: 260,
            menubar: false,
            plugins: 'lists link table code autolink charmap image',
            toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | rumus | removeformat code',
            // 'scrolling' (bukan 'wrap' default) — di layar sempit (mis. editor kiri/kanan
            // match yg bertumpuk di HP), toolbar TETAP satu baris & bisa digeser horizontal,
            // bukan patah jadi berbaris-baris yg bikin editor terasa rusak/susah dipakai.
            toolbar_mode: 'scrolling',
            branding: false, promotion: false,
            skin: dark ? 'oxide-dark' : 'oxide',
            content_css: dark ? 'dark' : 'default',
            convert_urls: false,
            extended_valid_elements: 'img[class|src|alt|data-latex|style|width|height]',
            content_style: '.math-svg{max-width:100%}',
            images_upload_handler: function (blobInfo) {
                return new Promise(function (resolve, reject) {
                    const fd = new FormData();
                    fd.append('file', blobInfo.blob(), blobInfo.filename());
                    fetch({{ Js::from(route('ujian.soal.unggah-gambar')) }}, {
                        method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    })
                        .then(function (res) { if (!res.ok) throw new Error('upload gagal'); return res.json(); })
                        .then(function (json) { resolve(json.location); })
                        .catch(function () { reject('Gagal mengunggah gambar.'); });
                });
            },
            setup: function (editor) {
                editor.ui.registry.addButton('rumus', { text: '∑ Rumus', tooltip: 'Sisipkan rumus matematika', onAction: () => openMath(editor) });
            },
        };
    }

    function mountWhenReady(el, attempts) {
        attempts = attempts || 0;
        if (typeof tinymce === 'undefined') {
            if (attempts > 50) return; // ~5 detik, menyerah dgn tenang drpd loop selamanya
            setTimeout(() => mountWhenReady(el, attempts + 1), 100);
            return;
        }
        if (!el || !el.id || tinymce.get(el.id)) return;
        // Jangan mount pada elemen yg sedang disembunyikan lewat x-show (mis. opsi
        // true_false, atau kartu soal yg belum dibuka) — TinyMCE butuh dimensi nyata
        // saat init, kalau tidak toolbarnya bisa rusak saat elemen ditampilkan belakangan.
        if (el.offsetParent === null && getComputedStyle(el).display === 'none') return;
        tinymce.init(Object.assign({ target: el }, baseConfig()));
    }

    window.UjianEditor = {
        /** Mount semua .ujian-rich-editor di DOM yg belum punya instance TinyMCE. */
        mountAll: function () {
            document.querySelectorAll('.ujian-rich-editor').forEach(function (el) { mountWhenReady(el); });
        },
        /** Hapus instance TinyMCE terikat id ini SEBELUM elemennya dibuang dari DOM (mis. saat opsi dihapus). */
        unmount: function (id) {
            if (typeof tinymce === 'undefined' || !id) return;
            const ed = tinymce.get(id);
            if (ed) ed.remove();
        },
    };
})();
</script>
@endpush
@endonce
