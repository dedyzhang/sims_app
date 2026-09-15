{{-- Modal preview file (gambar/PDF) sengaja TIDAK di-teleport ke <body> agar tampil saat Fullscreen API aktif. --}}
<div x-show="pvUrl" x-cloak class="modal-backdrop" x-transition style="z-index:70" @click.self="close()" @keydown.escape.window="close()">
    <div class="modal-box max-w-4xl w-full h-[88vh] flex flex-col" @click.stop>
        <div class="p-3 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between gap-2 flex-shrink-0 flex-wrap">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate flex-1 min-w-0" x-text="pvName"></p>
            <div class="flex items-center gap-1 flex-shrink-0">
                <template x-if="!pvImg && !pvLoading && !pvErr">
                    <div class="flex items-center gap-0.5 mr-1 rounded-lg border border-slate-200 dark:border-slate-600 overflow-hidden">
                        <button @click="zoomOut()" class="p-2 text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700" title="Perkecil"><i data-lucide="zoom-out" class="w-4 h-4"></i></button>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-300 px-1.5 tabular-nums select-none" x-text="Math.round(pvZoom*100)+'%'"></span>
                        <button @click="zoomIn()" class="p-2 text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700" title="Perbesar"><i data-lucide="zoom-in" class="w-4 h-4"></i></button>
                    </div>
                </template>
                <a :href="pvDl" class="p-2 rounded-lg text-slate-400 hover:text-primary" title="Unduh"><i data-lucide="download" class="w-4 h-4"></i></a>
                <button @click="close()" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400" title="Tutup"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        </div>
        <div class="flex-1 min-h-0 bg-slate-100 dark:bg-slate-900 overflow-auto">
            <img x-show="pvImg" :src="pvUrl" class="w-full h-full object-contain">
            <template x-if="!pvImg">
                <div class="relative">
                    <div x-show="pvLoading" class="py-16 text-center text-slate-400 text-sm flex flex-col items-center gap-2">
                        <i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i> Memuat PDF...
                    </div>
                    <div x-show="pvErr" x-cloak class="py-16 text-center text-rose-500 text-sm">Gagal memuat PDF. Coba unduh langsung.</div>
                    <div x-ref="pdfBox" class="py-2 min-w-min"></div>
                </div>
            </template>
        </div>
    </div>
</div>

@once
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    function filePreviewModal() {
        return {
            pvUrl: null, pvDl: null, pvName: '', pvImg: false, pvLoading: false, pvErr: false,
            pvZoom: 1, pvCanvases: [],
            open(url, dl, name, isImg) {
                this.pvUrl = url; this.pvDl = dl; this.pvName = name; this.pvImg = isImg; this.pvErr = false;
                this.pvZoom = 1; this.pvCanvases = [];
                if (isImg) return;
                this.pvLoading = true;
                this.$nextTick(() => this.renderPdf(url));
            },
            close() { this.pvUrl = null; this.pvCanvases = []; if (this.$refs.pdfBox) this.$refs.pdfBox.innerHTML = ''; },
            zoomIn() { this.pvZoom = Math.min(3, +(this.pvZoom + 0.25).toFixed(2)); this.applyZoom(); },
            zoomOut() { this.pvZoom = Math.max(0.5, +(this.pvZoom - 0.25).toFixed(2)); this.applyZoom(); },
            applyZoom() {
                this.pvCanvases.forEach((c) => { c.style.width = (c.dataset.baseWidth * this.pvZoom) + 'px'; });
            },
            async renderPdf(url) {
                const box = this.$refs.pdfBox;
                box.innerHTML = '';
                this.pvCanvases = [];
                try {
                    if (window.pdfjsLib && !window.pdfjsLib.GlobalWorkerOptions.workerSrc) {
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    }
                    const pdf = await window.pdfjsLib.getDocument(url).promise;
                    const dpr = Math.min(window.devicePixelRatio || 1, 2);
                    const headroom = 2; 
                    for (let n = 1; n <= pdf.numPages; n++) {
                        if (this.pvUrl !== url) return; 
                        const page = await pdf.getPage(n);
                        const unscaled = page.getViewport({ scale: 1 });
                        const fitScale = Math.max(0.4, (box.clientWidth - 8) / unscaled.width);
                        const renderScale = fitScale * dpr * headroom;
                        const viewport = page.getViewport({ scale: renderScale });
                        const canvas = document.createElement('canvas');
                        canvas.width = viewport.width; canvas.height = viewport.height;
                        const baseWidth = viewport.width / dpr / headroom; 
                        canvas.dataset.baseWidth = baseWidth;
                        canvas.style.width = baseWidth + 'px';
                        canvas.className = 'mx-auto block mb-2 rounded shadow bg-white';
                        box.appendChild(canvas);
                        this.pvCanvases.push(canvas);
                        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                    }
                    this.pvLoading = false;
                } catch (e) {
                    this.pvLoading = false; this.pvErr = true;
                }
            },
        };
    }
</script>
@endpush
@endonce
