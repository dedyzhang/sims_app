{{-- Isi materi (dipakai di tampilan normal & mode terkunci). Var: $material, $canManage --}}
@php $fmt = fn ($b) => ($b = (int) $b) >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B'); @endphp
<div x-data="materialPreview()">
<div class="flex items-start gap-3">
    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style="background:color-mix(in srgb, var(--cp) 14%, transparent)"><i data-lucide="book-open" class="w-6 h-6" style="color:var(--cp)"></i></div>
    <div class="min-w-0 flex-1">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">{{ $material->title }} @if($material->is_locked)<i data-lucide="lock" class="w-4 h-4 text-amber-500"></i>@endif</h1>
        <p class="text-xs text-slate-400 mt-0.5">{{ $material->uploader?->displayName() }} · {{ $material->created_at?->locale('id')->diffForHumans() }}</p>
    </div>
    @if($canManage)
    <div class="flex items-center gap-1 flex-shrink-0">
        <a href="{{ route('classroom.material.edit', $material) }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-400 hover:text-primary"><i data-lucide="pencil" class="w-4 h-4"></i></a>
        <form method="POST" action="{{ route('classroom.material.destroy', $material) }}" onsubmit="return confirmDelete(this)">@csrf @method('DELETE')<button class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-400 hover:text-rose-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
    </div>
    @endif
</div>

@if($material->classrooms->count() > 1 && $canManage)
<div class="flex flex-wrap items-center gap-1.5 mt-3 text-xs text-slate-400">
    <i data-lucide="link" class="w-3.5 h-3.5"></i> Tampil di:
    @foreach($material->classrooms as $rc)<span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $rc->rombel?->tingkat }}{{ $rc->rombel?->kelas }}</span>@endforeach
</div>
@endif

@if($material->meet_url)
<div class="flex flex-wrap items-center gap-2 mt-4">
    <a href="{{ $material->meet_url }}" target="_blank" rel="noopener" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-bold text-white shadow-sm hover:opacity-90 transition" style="background:#16a34a">
        <i data-lucide="video" class="w-4 h-4"></i> Gabung Google Meet
        <span class="text-[11px] font-normal opacity-80 hidden sm:inline">· {{ \Illuminate\Support\Str::after($material->meet_url, 'meet.google.com/') }}</span>
    </a>
    @if($canManage)
    <form method="POST" action="{{ route('classroom.material.closemeet', $material) }}" onsubmit="return confirmAction(this, 'Tutup kelas online? Link Google Meet akan dihapus dari materi ini.', 'orange')">@csrf
        <button class="inline-flex items-center gap-1.5 px-4 py-3 rounded-xl text-sm font-semibold border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-700 dark:hover:bg-rose-900/30"><i data-lucide="video-off" class="w-4 h-4"></i> Tutup Google Meet</button>
    </form>
    @endif
</div>
@endif

@if($material->description)<p class="text-slate-600 dark:text-slate-300 mt-3">{{ $material->description }}</p>@endif
@if($material->body)<div class="mt-3">@include('classroom.partials.richbody', ['html' => $material->body])</div>@endif
@include('components.teacher-audio-player', ['audioLinks' => $material->audioLinks])

@if($material->link_url)<a href="{{ $material->link_url }}" target="_blank" class="text-sm inline-flex items-center gap-1 mt-3" style="color:var(--cp)"><i data-lucide="link" class="w-4 h-4"></i> {{ \Illuminate\Support\Str::limit($material->link_url, 60) }}</a>@endif

@if($material->files->isNotEmpty())
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-4">
    @foreach($material->files as $f)
    @php $canPreview = $f->isImage() || $f->mime === 'application/pdf'; @endphp
    @if($canPreview)
    <button type="button" @click="open('{{ route('classroom.material.file.preview', $f) }}', '{{ route('classroom.material.file', $f) }}', '{{ addslashes($f->original_name) }}', {{ $f->isImage() ? 'true' : 'false' }})"
        class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary text-sm text-left w-full">
        <i data-lucide="{{ $f->isImage() ? 'image' : 'file-text' }}" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
        <span class="truncate flex-1">{{ $f->original_name }}</span>
        <span class="text-[11px] text-slate-400">{{ $fmt($f->size_compressed ?? $f->size_original) }}</span>
        <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-400"></i>
    </button>
    @else
    <a href="{{ route('classroom.material.file', $f) }}" class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary text-sm">
        <i data-lucide="file-text" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
        <span class="truncate flex-1">{{ $f->original_name }}</span>
        <span class="text-[11px] text-slate-400">{{ $fmt($f->size_compressed ?? $f->size_original) }}</span>
        <i data-lucide="download" class="w-3.5 h-3.5 text-slate-400"></i>
    </a>
    @endif
    @endforeach
</div>
@endif

{{-- Modal preview file (gambar/PDF) — sengaja TIDAK di-teleport ke <body>: kalau materi sedang
     dalam mode terkunci (layar penuh via Fullscreen API), elemen di luar kontainer fullscreen
     tidak akan tampil di atasnya. Modal harus tetap jadi keturunan DOM materi ini.
     PDF dirender pakai PDF.js ke <canvas> (bukan <iframe>) karena Chrome/browser Android
     tidak punya plugin PDF bawaan utk iframe — kalau iframe, malah muncul kartu "Open" yang
     buka app/tab lain (bisa memicu deteksi pindah-tab di mode terkunci). --}}
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
                        <i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i> Memuat PDF…
                    </div>
                    <div x-show="pvErr" x-cloak class="py-16 text-center text-rose-500 text-sm">Gagal memuat PDF. Coba unduh langsung.</div>
                    <div x-ref="pdfBox" class="py-2 min-w-min"></div>
                </div>
            </template>
        </div>
    </div>
</div>
</div>

@once
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    function materialPreview() {
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
                    // Render resolusi tinggi sekali (dpr + headroom) supaya tetap tajam saat di-zoom;
                    // zoom in/out sesudahnya cuma ubah lebar CSS canvas, tidak render ulang.
                    const dpr = Math.min(window.devicePixelRatio || 1, 2);
                    const headroom = 2; // render ±2x lebih tajam dari lebar tampil agar teks kecil tetap terbaca saat di-zoom
                    for (let n = 1; n <= pdf.numPages; n++) {
                        if (this.pvUrl !== url) return; // modal ditutup/ganti file di tengah render
                        const page = await pdf.getPage(n);
                        const unscaled = page.getViewport({ scale: 1 });
                        const fitScale = Math.max(0.4, (box.clientWidth - 8) / unscaled.width);
                        const renderScale = fitScale * dpr * headroom;
                        const viewport = page.getViewport({ scale: renderScale });
                        const canvas = document.createElement('canvas');
                        canvas.width = viewport.width; canvas.height = viewport.height;
                        const baseWidth = viewport.width / dpr / headroom; // lebar tampil di zoom 100%
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
