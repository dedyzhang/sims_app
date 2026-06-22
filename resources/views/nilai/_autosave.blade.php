@once
@push('styles')
<style>
    /* Sel angka contenteditable — tampil seperti teks tabel, bukan kotak form */
    /* kolom angka dibuat ramping (padding sel default tabel terlalu lebar) */
    .data-table th.col-nilai, .data-table td.col-nilai { padding: 7px 8px; width: 1%; }
    /* garis grid tabel diperjelas */
    .data-table.grid-bordered th,
    .data-table.grid-bordered td { border: 1px solid color-mix(in srgb, var(--cp) 16%, #d8dee9); }
    .dark .data-table.grid-bordered th,
    .dark .data-table.grid-bordered td { border-color: #334155; }
    .nilai-cell {
        display: block;
        min-width: 48px;
        min-height: 38px;
        line-height: 22px;
        padding: 8px 6px;
        text-align: center;
        border-radius: 7px;
        font-weight: 600;
        color: inherit;
        cursor: text;
        outline: none;
        -webkit-user-select: text;
        user-select: text;
        transition: background .15s, box-shadow .15s;
    }
    .nilai-cell:hover { background: color-mix(in srgb, var(--cp) 8%, transparent); }
    .nilai-cell:focus { background: #fff; box-shadow: 0 0 0 2px color-mix(in srgb, var(--cp) 50%, transparent); }
    .dark .nilai-cell:focus { background: #0f172a; }
    .nilai-cell.saving { box-shadow: 0 0 0 2px #fcd34d; }
    .nilai-cell.saved  { box-shadow: 0 0 0 2px #34d399; }
    .nilai-cell.err    { box-shadow: 0 0 0 2px #f87171; }
    /* nilai di bawah KKTP → latar merah */
    .nilai-cell.below-kkm:not(:focus) { background: rgba(239,68,68,.14); color: #dc2626; }
    .dark .nilai-cell.below-kkm:not(:focus) { background: rgba(239,68,68,.24); color: #fca5a5; }
    .data-table td.avg-below { background: rgba(239,68,68,.14) !important; color: #dc2626; }
    .dark .data-table td.avg-below { background: rgba(239,68,68,.24) !important; color: #fca5a5; }
    .nilai-cell:empty:not(:focus)::before { content: '–'; color: #cbd5e1; font-weight: 400; }
    .dark .nilai-cell:empty:not(:focus)::before { color: #475569; }
</style>
@endpush

@push('scripts')
<script>
    // Kirim satu nilai sel ke server (auto-save) + indikator status.
    window.postCell = function (url, body, el) {
        el.classList.remove('saved', 'err');
        el.classList.add('saving');
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify(body),
        })
        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(() => {
            el.classList.remove('saving');
            el.classList.add('saved');
            setTimeout(() => el.classList.remove('saved'), 1100);
        })
        .catch(() => {
            el.classList.remove('saving');
            el.classList.add('err');
            if (window.showToast) showToast('Gagal menyimpan nilai. Coba lagi.', 'error');
        });
    };

    (function () {
        const isCell = (el) => el && el.classList && el.classList.contains('nilai-cell');

        // tandai merah bila nilai di bawah KKTP (data-kkm)
        function markKkm(el) {
            const kkm = parseInt(el.dataset.kkm || '', 10);
            const v = (el.textContent || '').replace(/[^0-9]/g, '');
            el.classList.toggle('below-kkm', !isNaN(kkm) && v !== '' && parseInt(v, 10) < kkm);
        }

        // Hanya boleh angka, maksimal 3 digit (0–100 dijaga saat simpan)
        document.addEventListener('beforeinput', function (e) {
            const el = e.target;
            if (!isCell(el)) return;
            if (e.inputType && e.inputType.startsWith('insert')) {
                const teks = (el.textContent || '').replace(/\n/g, '');
                const tambahan = e.data || '';
                if (e.inputType === 'insertText' && !/^[0-9]+$/.test(tambahan)) { e.preventDefault(); return; }
                if (teks.length >= 3 && e.inputType === 'insertText') { e.preventDefault(); }
            }
        });

        // Simpan saat keluar dari sel (blur)
        document.addEventListener('focusout', function (e) {
            const el = e.target;
            if (!isCell(el)) return;
            let v = (el.textContent || '').replace(/[^0-9]/g, '');
            let num = v === '' ? null : Math.max(0, Math.min(100, parseInt(v, 10)));
            el.textContent = (num === null ? '' : num);   // normalisasi tampilan
            markKkm(el);
            const body = Object.assign(JSON.parse(el.dataset.body || '{}'), { nilai: num });
            postCell(el.dataset.url, body, el);
        });

        // warnai merah saat mengetik & saat halaman dimuat
        document.addEventListener('input', function (e) { if (isCell(e.target)) markKkm(e.target); });
        document.querySelectorAll('.nilai-cell[data-kkm]').forEach(markKkm);

        // Fokus ke sel + taruh kursor di akhir teks
        function focusCell(el) {
            if (!el) return false;
            el.focus();
            const range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            const sel = window.getSelection();
            sel.removeAllRanges(); sel.addRange(range);
            return true;
        }

        // Navigasi keyboard: Enter / ↑ ↓ pindah baris (kolom sama), ← → pindah antar sel
        document.addEventListener('keydown', function (e) {
            const el = e.target;
            if (!isCell(el)) return;
            const cells = Array.from(document.querySelectorAll('.nilai-cell'));
            const i = cells.indexOf(el);

            if (e.key === 'Enter' || e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const col = el.dataset.col || '0';
                const sameCol = cells.filter(c => (c.dataset.col || '0') === col);
                const idx = sameCol.indexOf(el);
                const next = (e.key === 'ArrowUp') ? sameCol[idx - 1] : sameCol[idx + 1];
                if (!focusCell(next)) el.blur();
                return;
            }

            // ← → hanya lompat sel bila kursor di ujung teks (biar tetap bisa edit di tengah)
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                const sel = window.getSelection();
                const len = (el.textContent || '').length;
                const off = sel ? sel.anchorOffset : 0;
                if (e.key === 'ArrowRight' && off >= len) { if (focusCell(cells[i + 1])) e.preventDefault(); }
                else if (e.key === 'ArrowLeft' && off <= 0) { if (focusCell(cells[i - 1])) e.preventDefault(); }
            }
        });
    })();
</script>
@endpush
@endonce
