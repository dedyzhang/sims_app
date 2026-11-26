{{-- Editor TinyMCE + rumus (LaTeX→SVG) + YouTube. Var: $name, $value (HTML) --}}
<textarea name="{{ $name }}" class="rich-editor">{{ $value ?? '' }}</textarea>

@once
@push('styles')
<style>
    math-field {
        width: 100%;
        min-height: 120px;
        font-size: 20px;
        border: 1px solid #cbd5e1;
        padding: 12px;
        border-radius: 8px;
        display: block;
        outline: none;
        background: #ffffff !important;
        color: #0f172a !important;
        margin: 8px 0;
    }
    
    /* Ensure virtual keyboard is displayed above TinyMCE modals */
    body {
        --keyboard-zindex: 70000 !important;
    }
    .ML__keyboard, mathlive-shared-virtual-keyboard, [part=virtual-keyboard] {
        z-index: 70000 !important;
    }
</style>
@endpush

@push('scripts')
<script>window.MathJax = { tex:{ inlineMath:[['$','$']] }, svg:{ fontCache:'none' }, startup:{ typeset:false } };</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" id="MathJax-script"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script src="https://unpkg.com/mathlive@0.111.4/mathlive.min.js"></script>
<script>
(function () {
    if (typeof tinymce === 'undefined') return;
    const dark = document.documentElement.classList.contains('dark');

    // Configure MathLive keyboard to explicitly include matrices and fractions in a default Visual tab
    if (window.mathVirtualKeyboard) {
        const customKeyboard = {
            label: 'Visual',
            tooltip: 'Rumus Visual (Matriks, Pecahan, dll)',
            layers: [
                {
                    rows: [
                        [
                            { latex: '\\frac{#?}{#?}' },
                            { latex: '\\sqrt{#?}' },
                            { latex: '\\sqrt[#?]{#?}' },
                            { latex: '#?^{#?}' },
                            { latex: '#?_{#?}' },
                            { latex: '\\left(#?\\right)' },
                            { latex: '\\left[#?\\right]' }
                        ],
                        [
                            { latex: '\\begin{pmatrix} #? & #? \\\\ #? & #? \\end{pmatrix}' },
                            { latex: '\\begin{pmatrix} #? & #? & #? \\\\ #? & #? & #? \\\\ #? & #? & #? \\end{pmatrix}' },
                            { latex: '\\begin{pmatrix} #? \\\\ #? \\end{pmatrix}' },
                            { latex: '\\begin{vmatrix} #? & #? \\\\ #? & #? \\end{vmatrix}' },
                            { latex: '\\pi' },
                            { latex: '\\infty' },
                            { latex: '\\theta' }
                        ],
                        [
                            { latex: '\\times' },
                            { latex: '\\div' },
                            { latex: '\\pm' },
                            { latex: '\\sum_{#?}^{#?}' },
                            { latex: '\\int_{#?}^{#?}' },
                            { latex: '\\lim_{#?\\to#?}' },
                            { latex: '\\log_{#?}(#?)' }
                        ]
                    ]
                }
            ]
        };
        window.mathVirtualKeyboard.layouts = [
            customKeyboard,
            'numeric',
            'symbols',
            'greek'
        ];
    }

    function ytId(u) {
        u = (u || '').trim();
        const m = u.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
        if (m) return m[1];
        return /^[A-Za-z0-9_-]{11}$/.test(u) ? u : null;
    }

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
                          '<math-field id="mathlive-field" virtual-keyboard-mode="onfocus" style="width: 100%; min-height: 120px; font-size: 20px; border: 1px solid #cbd5e1; padding: 12px; border-radius: 8px; display: block; outline: none; background: #ffffff !important; color: #0f172a !important; margin: 8px 0;"></math-field>' +
                          '<div style="font-size: 12px; color: #64748b; margin-top: 6px;">Gunakan virtual keyboard MathLive yang muncul otomatis untuk menulis pecahan, matriks, akar, pangkat, dll.</div>'
                },
                { type: 'checkbox', name: 'display', label: 'Tampilkan sebagai blok (besar, di tengah)' },
            ] },
            initialData: {
                display: displayMode
            },
            buttons: [{ type: 'cancel', text: 'Batal' }, { type: 'submit', text: isEditing ? 'Perbarui' : 'Sisipkan', primary: true }],
            onSubmit: function (api) {
                const data = api.getData();
                const mf = document.getElementById('mathlive-field');
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

        // Set initial value to the math-field
        setTimeout(() => {
            const mf = document.getElementById('mathlive-field');
            if (mf) {
                mf.value = initialLatex;
                setTimeout(() => {
                    mf.focus();
                }, 50);
            }
        }, 100);
    }

    function openYt(editor) {
        editor.windowManager.open({
            title: 'Sisipkan Video YouTube',
            body: { type: 'panel', items: [{ type: 'input', name: 'url', label: 'Tempel URL atau ID video YouTube' }] },
            buttons: [{ type: 'cancel', text: 'Batal' }, { type: 'submit', text: 'Sisipkan', primary: true }],
            onSubmit: function (api) {
                const id = ytId(api.getData().url);
                if (!id) { editor.notificationManager.open({ text: 'URL YouTube tidak valid.', type: 'error' }); return; }
                editor.insertContent('<div class="yt-embed" data-yt="' + id + '" contenteditable="false" style="margin:10px 0;padding:16px;border:1px dashed #94a3b8;border-radius:10px;background:#f1f5f9;color:#475569;font-weight:600">▶ Video YouTube (' + id + ')</div><p></p>');
                api.close();
            },
        });
    }

    function cleanPastedHtml(rawHtml) {
        if (!rawHtml || typeof rawHtml !== 'string') return rawHtml;
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(rawHtml, 'text/html');
            const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_ALL, null, false);
            const toRemove = [];
            let node;
            while ((node = walker.nextNode())) {
                if (node.nodeType === Node.COMMENT_NODE) {
                    toRemove.push(node);
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    if (node.tagName.includes(':')) {
                        toRemove.push(node);
                        continue;
                    }
                    if (node.tagName === 'FONT') {
                        const parent = node.parentNode;
                        if (parent) {
                            while (node.firstChild) parent.insertBefore(node.firstChild, node);
                            toRemove.push(node);
                        }
                        continue;
                    }
                    if (node.tagName === 'SPAN' && /mso-tab-count/i.test(node.getAttribute('style') || '')) {
                        const space = doc.createTextNode(' ');
                        node.parentNode?.replaceChild(space, node);
                        continue;
                    }
                    if (node.className && /mso|apple/i.test(node.className)) {
                        node.removeAttribute('class');
                    }
                    if (node.hasAttribute('style')) {
                        node.style.fontFamily = '';
                        node.style.fontSize = '';
                        node.style.lineHeight = '';
                        node.style.letterSpacing = '';
                        node.style.textIndent = '';
                        node.style.marginLeft = '';
                        node.style.marginRight = '';
                        let css = node.style.cssText || '';
                        css = css.replace(/mso-[^;]+;?/gi, '')
                                 .replace(/tab-stops?:[^;]+;?/gi, '')
                                 .replace(/font-family:[^;]+;?/gi, '')
                                 .replace(/font-size:[^;]+;?/gi, '')
                                 .trim();
                        if (css) {
                            node.style.cssText = css;
                        } else {
                            node.removeAttribute('style');
                        }
                    }
                    if (node.tagName === 'SPAN' && node.attributes.length === 0) {
                        const parent = node.parentNode;
                        if (parent) {
                            while (node.firstChild) parent.insertBefore(node.firstChild, node);
                            toRemove.push(node);
                        }
                    }
                }
            }
            toRemove.forEach(n => { if (n.parentNode) n.parentNode.removeChild(n); });
            let html = doc.body.innerHTML;
            html = html.replace(/\t+/g, ' ');
            html = html.replace(/(?:&nbsp;|\u00A0){2,}/gi, ' ');
            html = html.replace(/(<br\s*\/?>)\s*(?:&nbsp;|\u00A0|\s)+/gi, '$1');
            html = html.replace(/(<p[^>]*>)\s*(?:&nbsp;|\u00A0|\s)+/gi, '$1');
            return html;
        } catch (e) {
            return rawHtml
                .replace(/<span[^>]*mso-tab-count[^>]*>.*?<\/span>/gi, ' ')
                .replace(/(font-family|font-size|line-height|text-indent|mso-[a-z0-9\-]+)\s*:[^;"]+;?/gi, '')
                .replace(/\t+/g, ' ')
                .replace(/(?:&nbsp;|\u00A0){2,}/gi, ' ');
        }
    }

    tinymce.init({
        selector: '.rich-editor',
        height: 360,
        menubar: false,
        plugins: 'lists link table code autolink charmap image',
        toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | rumus youtube | removeformat code',
        toolbar_mode: 'wrap',
        branding: false, promotion: false,
        skin: dark ? 'oxide-dark' : 'oxide',
        content_css: dark ? 'dark' : 'default',
        convert_urls: false,
        extended_valid_elements: 'img[class|src|alt|data-latex|style|width|height],div[class|data-yt|contenteditable|style]',
        content_style: 'body { font-family: "Plus Jakarta Sans", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; font-size: 14px; line-height: 1.6; color: ' + (dark ? '#f1f5f9' : '#0f172a') + '; } .math-svg{max-width:100%}.yt-embed{font-family:system-ui} p, span, div { font-family: inherit !important; }',
        paste_preprocess: function (plugin, args) {
            args.content = cleanPastedHtml(args.content);
        },
        setup: function (editor) {
            editor.on('PastePreProcess', function (e) {
                e.content = cleanPastedHtml(e.content);
            });
            editor.ui.registry.addButton('rumus', { text: '∑ Rumus', tooltip: 'Sisipkan rumus matematika', onAction: () => openMath(editor) });
            editor.ui.registry.addButton('youtube', { text: '▶ YouTube', tooltip: 'Sisipkan video YouTube', onAction: () => openYt(editor) });
        },
    });
})();
</script>
@endpush
@endonce
