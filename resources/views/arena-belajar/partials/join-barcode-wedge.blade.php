{{-- Input scanner USB barcode (keyboard wedge) — siswa --}}
<div class="pt-1 border-t border-slate-100 dark:border-slate-700">
    <p class="m-0 mb-1.5 text-[11px] font-semibold text-slate-500">Scanner USB / barcode guru</p>
    <input type="text"
           x-model="barcodeWedge"
           @keydown.enter.prevent="submitBarcodeWedge($event)"
           autocomplete="off"
           class="form-input text-sm font-mono"
           placeholder="Arahkan scanner ke barcode layar guru…">
    <p class="text-xs text-rose-500 m-0 mt-1" x-show="barcodeError" x-text="barcodeError"></p>
</div>
