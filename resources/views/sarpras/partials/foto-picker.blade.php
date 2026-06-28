{{--
| Pemilih foto reusable untuk modul Sarpras.
| - Kamera LIVE di dalam halaman (getUserMedia) -> preview realtime + "Jepret".
| - Ambil Foto (kamera bawaan HP via capture) & Galeri.
| Semua hasil digabung ke SATU input file via DataTransfer, dibatasi jumlahnya,
| dengan preview + tombol hapus.
|
| Props:
|   $name  : nama input yang DIKIRIM ke server (mis. 'foto[]').
|   $label : (opsional) teks label di atas tombol.
|   $max   : (opsional) batas jumlah foto, default 4.
|   $live  : (opsional) aktifkan kamera live di halaman, default false.
--}}
@php($max = $max ?? 4)
@php($live = $live ?? false)
<div x-data="sarprasFotoPicker({{ $max }}, {{ $live ? 'true' : 'false' }})">
    @isset($label)<label class="block text-gray-700 mb-1">{{ $label }}</label>@endisset

    {{-- Pemicu --}}
    <div class="flex flex-wrap gap-2 mb-2">
        @if ($live)
        <button type="button" @click="bukaKamera()" x-show="!streaming"
                class="inline-flex items-center gap-1 cursor-pointer bg-red-600 text-white rounded px-3 py-2 text-xs hover:bg-red-700"
                :class="penuh && 'opacity-50 pointer-events-none'">
            <i data-lucide="video" class="w-4 h-4"></i> Kamera Langsung
        </button>
        @endif
        <label class="inline-flex items-center gap-1 cursor-pointer bg-gray-50 text-gray-700 border rounded px-3 py-2 text-xs hover:bg-gray-100"
               :class="penuh && 'opacity-50 pointer-events-none'">
            <i data-lucide="image" class="w-4 h-4"></i> Galeri
            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="tambah($event)" :disabled="penuh">
        </label>
    </div>

    @if ($live)
    {{-- Kamera LIVE: preview realtime + jepret. --}}
    <div x-show="streaming" x-cloak class="mb-2">
        <div class="relative bg-black rounded overflow-hidden">
            <video x-ref="video" autoplay playsinline muted class="w-full max-h-72 object-contain"></video>
        </div>
        <div class="flex flex-wrap gap-2 mt-2">
            <button type="button" @click="jepret()" :disabled="penuh"
                    class="inline-flex items-center gap-1 bg-red-600 text-white rounded px-4 py-2 text-xs disabled:opacity-50">
                <i data-lucide="camera" class="w-4 h-4"></i> Jepret
            </button>
            <button type="button" @click="gantiKamera()"
                    class="inline-flex items-center gap-1 border rounded px-3 py-2 text-xs hover:bg-gray-100">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Putar Kamera
            </button>
            <button type="button" @click="tutupKamera()"
                    class="inline-flex items-center gap-1 border rounded px-3 py-2 text-xs hover:bg-gray-100">
                <i data-lucide="x" class="w-4 h-4"></i> Tutup
            </button>
        </div>
        <p class="text-xs text-gray-400 mt-1"
           x-text="penuh ? 'Batas foto tercapai.' : 'Arahkan kamera ke bagian yang rusak, lalu tekan Jepret.'"></p>
    </div>
    {{-- Kanvas tersembunyi untuk menangkap frame video. --}}
    <canvas x-ref="canvas" class="hidden"></canvas>
    <p x-show="kameraError" x-cloak class="text-xs text-red-600 mb-1" x-text="kameraError"></p>
    @endif

    {{-- Input asli yang DIKIRIM ke server — diisi via JS (DataTransfer). --}}
    <input name="{{ $name }}" type="file" x-ref="finalInput" multiple class="hidden">

    {{-- Preview foto terpilih + tombol hapus. --}}
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2" x-show="items.length" x-cloak>
        <template x-for="(it, i) in items" :key="it.key">
            <div class="relative">
                <img :src="it.url" class="w-full h-20 object-cover rounded border">
                <button type="button" @click="hapus(i)"
                        class="absolute -top-1.5 -right-1.5 bg-red-600 text-white rounded-full w-5 h-5 grid place-items-center text-xs leading-none shadow">&times;</button>
            </div>
        </template>
    </div>
    <p class="text-xs text-gray-400 mt-1" x-text="`${items.length}/{{ $max }} foto dipilih`"></p>
</div>

@once
@push('scripts')
<script>
// Komponen Alpine reusable: kamera live (getUserMedia) + capture/galeri,
// digabung ke satu input file via DataTransfer, dibatasi jumlahnya, dengan preview.
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

        // --- Daftar foto ---
        tambah(e) {
            for (const f of Array.from(e.target.files || [])) this.tambahFile(f);
            e.target.value = ''; // reset agar foto sama bisa diambil ulang
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

        // --- Kamera live ---
        async bukaKamera() {
            this.kameraError = '';
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.kameraError = 'Kamera live tidak didukung browser ini. Gunakan tombol "Ambil Foto".';
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
            if (!w || !h) return; // video belum siap
            c.width = w;
            c.height = h;
            c.getContext('2d').drawImage(v, 0, 0, w, h);
            c.toBlob((blob) => {
                if (!blob) return;
                const ts = (window.performance ? Math.round(performance.now()) : this.items.length);
                const file = new File([blob], `kamera-${ts}.jpg`, { type: 'image/jpeg' });
                this.tambahFile(file);
                if (this.penuh) this.tutupKamera(); // sudah penuh -> matikan kamera
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
        // Dipanggil Alpine saat elemen dilepas — pastikan kamera dimatikan.
        destroy() {
            this.hentikanStream();
        },
    };
}
</script>
@endpush
@endonce
