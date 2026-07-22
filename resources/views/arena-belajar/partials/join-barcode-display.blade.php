{{-- Barcode Code128 untuk scanner USB siswa (guru tayangkan di layar) --}}
@if(!empty($payload))
<div class="arena-rx-join-barcode-wrap">
    <svg class="arena-join-barcode" data-payload="{{ $payload }}" role="img" aria-label="Barcode gabung arena"></svg>
    <p class="arena-rx-join-qr-hint font-mono text-[10px] tracking-wide m-0 mt-1.5">{{ $payload }}</p>
</div>
@endif

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
window.arenaRenderJoinBarcodes = function arenaRenderJoinBarcodes(root) {
    if (typeof JsBarcode === 'undefined') return;
    const scope = root || document;
    scope.querySelectorAll('.arena-join-barcode[data-payload]:not([data-rendered])').forEach((el) => {
        try {
            JsBarcode(el, el.dataset.payload, {
                format: 'CODE128',
                width: 1.6,
                height: 48,
                displayValue: false,
                margin: 4,
            });
            el.dataset.rendered = '1';
        } catch (e) {}
    });
};
document.addEventListener('DOMContentLoaded', () => window.arenaRenderJoinBarcodes());
</script>
@endpush
@endonce
