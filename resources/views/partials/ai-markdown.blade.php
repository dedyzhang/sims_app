@once
<style>
    .ai-answer { line-height: 1.65; }
    .ai-answer > * + * { margin-top: .65rem; }
    .ai-answer p { margin: 0; }
    .ai-answer h3 { margin: .85rem 0 .35rem; font-size: .95rem; line-height: 1.35; font-weight: 800; color: inherit; }
    .ai-answer ul, .ai-answer ol { margin: .45rem 0 .7rem; padding-left: 1.25rem; }
    .ai-answer ul { list-style: disc; }
    .ai-answer ol { list-style: decimal; }
    .ai-answer li { margin: .22rem 0; padding-left: .1rem; }
    .ai-answer strong { font-weight: 800; }
    .ai-answer code { border-radius: .45rem; background: rgba(15, 23, 42, .07); padding: .08rem .3rem; font-size: .9em; }
    .dark .ai-answer code { background: rgba(255, 255, 255, .1); }
    .ai-answer pre { overflow-x: auto; border-radius: .75rem; background: #0f172a; color: #e2e8f0; padding: .85rem 1rem; font-size: .78rem; line-height: 1.55; }
    .ai-answer pre code { background: transparent; padding: 0; color: inherit; }
    .ai-answer a { color: var(--cp); font-weight: 700; text-decoration: underline; text-underline-offset: 3px; }
    .ai-answer blockquote { margin: .75rem 0; border-left: 3px solid var(--cp); padding: .35rem .75rem; color: #64748b; background: rgba(15, 23, 42, .04); border-radius: .55rem; }
    .dark .ai-answer blockquote { color: #cbd5e1; background: rgba(255, 255, 255, .05); }
</style>
<script>
    window.renderAiMarkdown = window.renderAiMarkdown || function (value) {
        const text = String(value || '').replace(/\r\n?/g, '\n').trim();
        if (!text) return '';

        const escapeHtml = (raw) => String(raw).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
        }[char]));

        const safeHref = (raw) => {
            const url = String(raw || '').trim();
            return /^(https?:\/\/|mailto:)/i.test(url) ? escapeHtml(url) : '#';
        };

        const inline = (raw) => {
            const tokens = [];
            const hold = (html) => {
                tokens.push(html);
                return '%%AI_TOKEN_' + (tokens.length - 1) + '%%';
            };

            let html = escapeHtml(raw);
            html = html.replace(/`([^`]+)`/g, (_m, code) => hold('<code>' + code + '</code>'));
            html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/g, (_m, label, url) => {
                return hold('<a href="' + safeHref(url) + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
            });
            html = html.replace(/(^|\s)(https?:\/\/[^\s<]+)/g, (_m, prefix, url) => {
                const clean = url.replace(/[.,;:!?)]$/, '');
                const suffix = url.slice(clean.length);
                return prefix + hold('<a href="' + safeHref(clean) + '" target="_blank" rel="noopener noreferrer">' + clean + '</a>') + suffix;
            });
            html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/%%AI_TOKEN_(\d+)%%/g, (_m, i) => tokens[Number(i)] || '');
            return html;
        };

        const lines = text.split('\n');
        const blocks = [];
        let paragraph = [];
        let listType = null;
        let listItems = [];
        let quote = [];
        let inCode = false;
        let code = [];

        const closeParagraph = () => {
            if (!paragraph.length) return;
            blocks.push('<p>' + inline(paragraph.join(' ')) + '</p>');
            paragraph = [];
        };
        const closeList = () => {
            if (!listType) return;
            blocks.push('<' + listType + '>' + listItems.map((item) => '<li>' + inline(item) + '</li>').join('') + '</' + listType + '>');
            listType = null;
            listItems = [];
        };
        const closeQuote = () => {
            if (!quote.length) return;
            blocks.push('<blockquote>' + quote.map(inline).join('<br>') + '</blockquote>');
            quote = [];
        };
        const closeCode = () => {
            blocks.push('<pre><code>' + escapeHtml(code.join('\n')) + '</code></pre>');
            code = [];
        };
        const closeFlow = () => { closeParagraph(); closeList(); closeQuote(); };

        for (const line of lines) {
            const trimmed = line.trim();

            if (/^```/.test(trimmed)) {
                if (inCode) {
                    closeCode();
                    inCode = false;
                } else {
                    closeFlow();
                    inCode = true;
                    code = [];
                }
                continue;
            }
            if (inCode) { code.push(line); continue; }

            if (trimmed === '') { closeFlow(); continue; }

            const heading = trimmed.match(/^#{1,4}\s+(.+)$/);
            if (heading) {
                closeFlow();
                blocks.push('<h3>' + inline(heading[1]) + '</h3>');
                continue;
            }

            const unordered = trimmed.match(/^[-*]\s+(.+)$/);
            if (unordered) {
                closeParagraph(); closeQuote();
                if (listType && listType !== 'ul') closeList();
                listType = 'ul';
                listItems.push(unordered[1]);
                continue;
            }

            const ordered = trimmed.match(/^\d+[.)]\s+(.+)$/);
            if (ordered) {
                closeParagraph(); closeQuote();
                if (listType && listType !== 'ol') closeList();
                listType = 'ol';
                listItems.push(ordered[1]);
                continue;
            }

            const quoted = trimmed.match(/^>\s?(.+)$/);
            if (quoted) {
                closeParagraph(); closeList();
                quote.push(quoted[1]);
                continue;
            }

            closeList(); closeQuote();
            paragraph.push(trimmed);
        }

        if (inCode) closeCode();
        closeFlow();

        return blocks.join('');
    };
</script>
@endonce