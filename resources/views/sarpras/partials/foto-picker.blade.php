{{--
| Pemilih foto reusable untuk modul Sarpras.
| - Kamera LIVE di dalam halaman (getUserMedia) -> preview realtime + "Jepret".
| - Galeri (multi).
| Semua hasil digabung ke SATU input file via DataTransfer, dibatasi jumlahnya,
| dengan preview + tombol hapus.
|
| Props:
|   $name  : nama input yang DIKIRIM ke server (mis. 'foto[]').
|   $label : (opsional) teks label di atas tombol.
|   $hint  : (opsional) teks bantuan kecil.
|   $max   : (opsional) batas jumlah foto, default 4.
|   $live  : (opsional) aktifkan kamera live di halaman, default false.
--}}
@php($max = $max ?? 4)
@php($live = $live ?? false)
<div x-data="sarprasFotoPicker({{ $max }}, {{ $live ? 'true' : 'false' }})" class="space-y-3">
    <div class="flex items-end justify-between gap-3 flex-wrap">
        <div>
            @isset($label)
                <label class="block text-[11px] font-extrabold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $label }}</label>
            @endisset
            @isset($hint)
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $hint }}</p>
            @endisset
        </div>
        <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400" x-text="`${items.length}/{{ $max }} foto`"></p>
    </div>

    {{-- Pemicu --}}
    <div class="flex flex-wrap gap-2" x-show="!streaming">
        @if ($live)
        <button type="button" @click="bukaKamera()"
                class="inline-flex items-center gap-2 cursor-pointer rounded-xl px-4 py-2.5 text-xs font-extrabold text-white bg-[#ea4335] hover:bg-[#d93025] shadow-[0_8px_16px_rgba(234,67,53,.22)] transition"
                :class="penuh && 'opacity-50 pointer-events-none'">
            <i data-lucide="video" class="w-4 h-4"></i> Kamera Langsung
        </button>
        @endif
        <label class="inline-flex items-center gap-2 cursor-pointer rounded-xl px-4 py-2.5 text-xs font-extrabold text-[#1a73e8] bg-white dark:bg-slate-800 border border-[#dadce0] dark:border-slate-600 hover:bg-[#f8fbff] dark:hover:bg-slate-700 shadow-sm transition"
               :class="penuh && 'opacity-50 pointer-events-none'">
            <i data-lucide="image" class="w-4 h-4"></i> Galeri
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="tambah($event)" :disabled="penuh">
        </label>
    </div>

    @if ($live)
    {{-- Kamera LIVE: preview realtime + jepret. --}}
    <div x-show="streaming" x-cloak class="space-y-3">
        <div class="relative bg-slate-900 rounded-2xl overflow-hidden ring-1 ring-slate-800 shadow-lg">
            <video x-ref="video" autoplay playsinline muted class="w-full max-h-80 object-contain"></video>
            <div class="absolute top-3 left-3 inline-flex items-center gap-1.5 bg-black/55 text-white text-[10px] font-bold px-2.5 py-1 rounded-full backdrop-blur-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-[#ea4335] animate-pulse"></span> LIVE
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="jepret()" :disabled="penuh"
                    class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-xs font-extrabold text-white bg-[#1a73e8] hover:bg-[#1557b0] shadow-[0_8px_16px_rgba(26,115,232,.25)] transition disabled:opacity-50">
                <i data-lucide="camera" class="w-4 h-4"></i> Jepret
            </button>
            <button type="button" @click="gantiKamera()"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-[#dadce0] dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Putar Kamera
            </button>
            <button type="button" @click="tutupKamera()"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-800 border border-[#dadce0] dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="x" class="w-4 h-4"></i> Tutup
            </button>
        </div>
        <p class="text-[11px] text-slate-400"
           x-text="penuh ? 'Batas foto tercapai.' : 'Arahkan kamera ke bagian yang rusak, lalu tekan Jepret.'"></p>
    </div>
    <canvas x-ref="canvas" class="hidden"></canvas>
    <p x-show="kameraError" x-cloak class="text-xs text-[#ea4335] font-medium" x-text="kameraError"></p>
    @endif

    <input name="{{ $name }}" type="file" x-ref="finalInput" multiple class="hidden">

    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5" x-show="items.length" x-cloak>
        <template x-for="(it, i) in items" :key="it.key">
            <div class="relative group">
                <img :src="it.url" class="w-full h-24 object-cover rounded-xl border border-[#dadce0] dark:border-slate-600 shadow-sm">
                <button type="button" @click="hapus(i)"
                        class="absolute -top-2 -right-2 w-7 h-7 grid place-items-center rounded-full bg-white dark:bg-slate-800 text-[#ea4335] border border-[#dadce0] dark:border-slate-600 shadow-md text-sm font-bold hover:bg-rose-50 transition"
                        title="Hapus foto">&times;</button>
            </div>
        </template>
    </div>
</div>

@once
@push('scripts')
<script>
function sarprasFotoPicker(max = 4, live = false) {
    return {
        items: [],
        max: max,
        live: live,
        streaming: false,
        stream: null,
        facing: 'environment',
        kameraError: '',

        get penuh() { return this.items.length >= this.max; },

        tambah(e) {
            for (const f of Array.from(e.target.files || [])) this.tambahFile(f);
            e.target.value = '';
        },
        tambahFile(f) {
            if (this.items.length >= this.max) return;
            if (!f.type.startsWith('image/')) return;
            this.items.push({
                key: `${f.name}-${f.size}-${f.lastModified}-${this.items.length}`,
                file: f,
                url: URL.createObjectURL(f),
            });
            this.sync();
        },
        hapus(i) {
            URL.revokeObjectURL(this.items[i].url);
            this.items.splice(i, 1);
            this.sync();
        },
        sync() {
            const dt = new DataTransfer();
            this.items.forEach((it) => dt.items.add(it.file));
            this.$refs.finalInput.files = dt.files;
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        async bukaKamera() {
            this.kameraError = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.kameraError = 'Kamera live tidak didukung browser ini. Gunakan tombol Galeri.';
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: this.facing } },
                    audio: false,
                });
                this.streaming = true;
                this.$nextTick(() => {
                    const v = this.$refs.video;
                    v.srcObject = this.stream;
                    v.play().catch(() => {});
                    window.lucide && window.lucide.createIcons();
                });
            } catch (e) {
                const nama = (e && e.name) ? e.name : 'error';
                this.kameraError = 'Tidak bisa mengakses kamera (' + nama + '). Pastikan situs diakses via HTTPS dan izin kamera diberikan.';
            }
        },
        async gantiKamera() {
            this.facing = this.facing === 'environment' ? 'user' : 'environment';
            this.hentikanStream();
            await this.bukaKamera();
        },
        jepret() {
            if (this.penuh) return;
            const v = this.$refs.video, c = this.$refs.canvas;
            const w = v.videoWidth, h = v.videoHeight;
            if (!w || !h) return;
            c.width = w;
            c.height = h;
            c.getContext('2d').drawImage(v, 0, 0, w, h);
            c.toBlob((blob) => {
                if (!blob) return;
                const ts = (window.performance ? Math.round(performance.now()) : this.items.length);
                const file = new File([blob], `kamera-${ts}.jpg`, { type: 'image/jpeg' });
                this.tambahFile(file);
                if (this.penuh) this.tutupKamera();
            }, 'image/jpeg', 0.92);
        },
        hentikanStream() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
            }
        },
        tutupKamera() {
            this.hentikanStream();
            this.streaming = false;
        },
        destroy() {
            this.hentikanStream();
        },
    };
}
</script>
@endpush
@endonce
