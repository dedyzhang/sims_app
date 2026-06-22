{{-- Popup nama materi + daftar TP (muncul saat hover elemen ber-atribut data-tps) --}}
<div id="mtip"></div>

@push('styles')
<style>
    #mtip { position:fixed; z-index:9999; pointer-events:none; max-width:300px; background:#0f172a; color:#fff;
            border-radius:10px; padding:9px 12px; font-size:12px; line-height:1.45; text-align:left;
            box-shadow:0 14px 34px -8px rgba(0,0,0,.5); opacity:0; transition:opacity .12s; transform:translate(-50%,-100%); }
    #mtip.show { opacity:1; }
    #mtip .mt-nama { font-weight:700; margin-bottom:3px; }
    #mtip .mt-tp { color:#cbd5e1; font-size:11px; }
    #mtip::after { content:''; position:absolute; left:50%; bottom:-5px; transform:translateX(-50%);
                   border:5px solid transparent; border-top-color:#0f172a; border-bottom:0; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const tip = document.getElementById('mtip');
    if (!tip) return;
    const esc = (s) => (s || '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));
    function show(el) {
        let tps = [];
        try { tps = JSON.parse(el.dataset.tps || '[]'); } catch (e) {}
        let html = '<div class="mt-nama">' + esc(el.dataset.nama) + '</div>';
        html += tps.length
            ? '<div class="mt-tp">' + tps.map((t, i) => (i + 1) + '. ' + esc(t)).join('<br>') + '</div>'
            : '<div class="mt-tp">(belum ada TP)</div>';
        tip.innerHTML = html;
        const r = el.getBoundingClientRect();
        tip.style.left = (r.left + r.width / 2) + 'px';
        tip.style.top = (r.top - 8) + 'px';
        tip.classList.add('show');
    }
    function hide() { tip.classList.remove('show'); }
    document.querySelectorAll('[data-tps]').forEach(el => {
        el.addEventListener('mouseenter', () => show(el));
        el.addEventListener('mouseleave', hide);
    });
    window.addEventListener('scroll', hide, true);
})();
</script>
@endpush
