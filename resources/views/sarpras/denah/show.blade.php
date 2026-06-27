@extends('sarpras.layouts.app')
@section('title', 'Denah: ' . $denah->nama)

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ $denah->nama }}</h2>
        <p class="text-sm text-gray-500">{{ $denah->gedung }} {{ $denah->lantai ? '· Lantai '.$denah->lantai : '' }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('sarpras.denah.index') }}" class="bg-slate-600 text-white px-3 py-1.5 rounded text-xs hover:bg-slate-700">← Daftar</a>
        @can('sarpras.denah.kelola')
            @include('sarpras.denah.partials.import-button', ['denah' => $denah])
            @if ($denah->gambar_path)
                <form method="POST" action="{{ route('sarpras.denah.gambar.hapus', $denah) }}"
                      onsubmit="return confirm('Hapus gambar denah ini? Blok ruangan tetap tersimpan dan Anda bisa import / menggambar ulang.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1 border border-red-300 text-red-600 px-3 py-1.5 rounded text-xs hover:bg-red-50">🗑️ Hapus Gambar</button>
                </form>
            @endif
            <a href="{{ route('sarpras.denah.gambar', $denah) }}" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-xs hover:bg-indigo-700">✏️ Gambar Denah</a>
            <a href="{{ route('sarpras.denah.hotspot', $denah) }}" class="bg-emerald-600 text-white px-3 py-1.5 rounded text-xs hover:bg-emerald-700">Atur Blok Ruangan</a>
        @endcan
    </div>
</div>

{{-- Pemilih lantai pada gedung yang sama --}}
@if ($denah->gedung)
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs text-gray-400 mr-1">{{ $denah->gedung }} —</span>
        @foreach ($lantaiSegedung as $l)
            <a href="{{ route('sarpras.denah.show', $l) }}"
               class="px-3 py-1 rounded-full text-sm {{ $l->id === $denah->id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ $l->lantai ? 'Lantai ' . $l->lantai : $l->nama }}
            </a>
        @endforeach
        @can('sarpras.denah.kelola')
            <a href="{{ route('sarpras.denah.create', ['gedung' => $denah->gedung]) }}"
               class="px-3 py-1 rounded-full text-sm border border-dashed border-gray-300 text-gray-500 hover:bg-gray-50">+ Lantai</a>
        @endcan
    </div>
@endif

<p class="text-sm text-gray-500 mb-2">Klik ruangan (mis. <b>7A</b>) untuk melihat detail.</p>

{{--
    DENAH INTERAKTIF.
    Container position:relative & responsif (lebar mengikuti layar, TANPA pixel hardcoded).
    Hotspot position:absolute pakai KOORDINAT PERSEN (pos_x/pos_y) + translate(-50%,-50%)
    sehingga presisi & tidak bergeser di ukuran layar berbeda.
--}}
<div class="bg-white rounded-lg shadow p-3">
    {{-- Toolbar: zoom live + export --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2 text-sm">
            <span class="text-gray-500 hidden sm:inline">Perbesar</span>
            <button type="button" id="zoom-out" class="w-7 h-7 grid place-items-center border rounded hover:bg-gray-50">−</button>
            <input id="zoom-range" type="range" min="50" max="200" step="10" value="100" class="w-28 sm:w-44">
            <button type="button" id="zoom-in" class="w-7 h-7 grid place-items-center border rounded hover:bg-gray-50">+</button>
            <span id="zoom-label" class="text-gray-600 w-11 tabular-nums">100%</span>
            <button type="button" id="zoom-reset" class="text-xs text-blue-600 hover:underline">Reset</button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 hidden sm:inline">Kertas:</span>
            <select id="paper-size" class="text-xs border rounded px-2 py-1.5 text-gray-600 bg-white focus:outline-none focus:ring-1 focus:ring-blue-300">
                <option value="297/210">A4 Landscape</option>
                <option value="210/297">A4 Portrait</option>
                <option value="330/215">F4 Landscape</option>
                <option value="215/330">F4 Portrait</option>
                <option value="16/10">Bebas (16:10)</option>
            </select>
            <div class="w-px h-5 bg-gray-200 mx-1"></div>
            <button type="button" id="export-jpeg" class="inline-flex items-center gap-1 bg-amber-600 text-white rounded px-3 py-1.5 text-xs hover:bg-amber-700 disabled:opacity-50">
                <i data-lucide="image" class="w-3.5 h-3.5"></i> JPEG
            </button>
            <button type="button" id="export-pdf" class="inline-flex items-center gap-1 bg-red-600 text-white rounded px-3 py-1.5 text-xs hover:bg-red-700 disabled:opacity-50">
                <i data-lucide="file-down" class="w-3.5 h-3.5"></i> PDF
            </button>
        </div>
    </div>

    {{-- Mode warna + legenda (interaktif) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3 border-t pt-3">
        <div class="inline-flex rounded-lg border bg-gray-50 p-0.5 text-sm" id="mode-warna">
            <button type="button" data-mode="ruangan"
                    class="px-3 py-1 rounded-md font-medium bg-white shadow text-gray-800">Warna Ruangan</button>
            <button type="button" data-mode="status"
                    class="px-3 py-1 rounded-md font-medium text-gray-500 hover:text-gray-700">Status</button>
        </div>
        {{-- Legenda status (muncul saat mode "Status") --}}
        <div id="legenda-status" class="hidden flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background:#059669"></span> Aman</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background:#d97706"></span> Sedang dipinjam</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm" style="background:#dc2626"></span> Ada kerusakan</span>
        </div>
    </div>

    <div id="denah-zoom-wrap" class="overflow-auto">
        <div id="denah-zoom" class="origin-top-left transition-transform" style="transform: scale(1);">
            <div id="denah-capture" class="relative mx-auto select-none" style="aspect-ratio: 297/210; height: min(68vh, 560px); width: auto;">
                @if ($denah->gambar_path)
                    <img loading="lazy" src="{{ Storage::url($denah->gambar_path) }}" alt="{{ $denah->nama }}"
                         class="absolute inset-0 w-full h-full object-contain bg-gray-50 rounded">
                @else
                    <div class="absolute inset-0 flex items-center justify-center bg-gray-100 text-gray-400 rounded">
                        🗺️ Gambar denah belum diunggah
                    </div>
                @endif

                @foreach ($denah->ruangan as $r)
                    @php
                        $kr = (int) ($jmlKerusakan[$r->id] ?? 0);
                        $dipinjam = $sedangDipinjam->has($r->id);
                        $status = $kr > 0 ? 'kerusakan' : ($dipinjam ? 'dipinjam' : 'aman');
                        $statusWarna = ['aman' => '#059669', 'dipinjam' => '#d97706', 'kerusakan' => '#dc2626'][$status];
                        // Hitung left/top tanpa transform agar html2canvas tidak salah hitung clip rect.
                        $lebar  = $r->lebar  ?? 14;
                        $tinggi = $r->tinggi ?? 9;
                        $blokL  = $r->pos_x - $lebar  / 2;
                        $blokT  = $r->pos_y - $tinggi / 2;
                    @endphp
                    {{-- Blok ruangan berlabel (kotak persen). Warna bisa ganti via toggle Mode Warna. --}}
                    <a href="{{ route('sarpras.ruangan.show', $r) }}"
                       title="{{ $r->kode }} — {{ $r->nama }}{{ $kr > 0 ? ' · '.$kr.' kerusakan' : ($dipinjam ? ' · sedang dipinjam' : '') }}"
                       data-blok
                       data-warna="{{ $r->warna_hex }}"
                       data-warna-teks="{{ $r->warna_teks }}"
                       data-status="{{ $status }}"
                       data-status-warna="{{ $statusWarna }}"
                       class="denah-blok absolute z-10 group flex flex-col items-center justify-center text-center rounded-md border-2 border-white shadow transition duration-150 hover:z-20 hover:scale-105 hover:shadow-lg hover:ring-2 hover:ring-white/80 hover:brightness-110"
                       style="left: {{ $blokL }}%; top: {{ $blokT }}%; width: {{ $lebar }}%; height: {{ $tinggi }}%; background-color: {{ $r->warna_hex }}; color: {{ $r->warna_teks }};">
                        @if ($kr > 0)
                            {{-- Titik berkedip: penanda ruangan ada kerusakan terbuka --}}
                            <span class="absolute top-1 right-1 flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 ring-1 ring-white"></span>
                            </span>
                        @endif
                        <span class="font-bold text-xs leading-tight px-1">{{ $r->kode }}</span>
                        @if ($r->nama)
                            <span class="text-[10px] leading-tight px-1 opacity-90 truncate max-w-full">{{ $r->nama }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="mt-6 bg-white rounded-lg shadow p-5">
    <div class="flex items-center justify-between gap-3 mb-3">
        <h3 class="font-semibold text-gray-800">Daftar Ruangan</h3>
        @can('sarpras.denah.kelola')
            <button type="button" id="toggle-import-ruangan"
                    class="inline-flex items-center gap-1.5 border border-emerald-600 text-emerald-700 px-3 py-1.5 rounded text-sm hover:bg-emerald-50">
                <i data-lucide="upload" class="w-4 h-4"></i> Import Excel
            </button>
        @endcan
    </div>

    @can('sarpras.denah.kelola')
        {{-- Panel import ruangan dari Excel/CSV (tersembunyi by default) --}}
        <div id="panel-import-ruangan" class="hidden mb-4 rounded-lg border border-emerald-100 bg-emerald-50/40 p-4">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h4 class="font-semibold text-gray-800 text-sm">Import Ruangan ke Denah Ini</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Unggah file Excel/CSV. Ruangan dengan <b>kode</b> yang sudah ada di denah ini akan diperbarui, sisanya ditambahkan & ditata otomatis di denah.</p>
                </div>
                <a href="{{ route('sarpras.ruangan.import.template') }}"
                   class="shrink-0 inline-flex items-center gap-1.5 text-sm text-emerald-700 font-medium hover:underline">
                    <i data-lucide="download" class="w-4 h-4"></i> Unduh template
                </a>
            </div>

            <form method="POST" action="{{ route('sarpras.ruangan.import', $denah) }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-center gap-2 text-sm">
                @csrf
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                       class="border rounded px-3 py-2 bg-white file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:rounded file:text-sm">
                <button class="bg-emerald-600 text-white rounded px-4 py-2 hover:bg-emerald-700">Proses Import</button>
            </form>

            <p class="text-xs text-gray-400 mt-2">
                Kolom: <code>kode, nama, kapasitas, warna, deskripsi</code>. Warna format hex (mis. <code>#059669</code>); posisi blok bisa dirapikan via <b>Atur Blok Ruangan</b>.
            </p>
        </div>

        {{-- Catatan hasil import (baris dilewati / data diabaikan) --}}
        @if (session('import_catatan') && count(session('import_catatan')))
            <details class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm" open>
                <summary class="cursor-pointer font-medium">{{ count(session('import_catatan')) }} catatan saat import (klik untuk lihat)</summary>
                <ul class="list-disc list-inside mt-2 space-y-0.5 max-h-48 overflow-y-auto">
                    @foreach (session('import_catatan') as $c)
                        <li>{{ $c }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endcan

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
        @foreach ($denah->ruangan as $r)
            @php
                $kr = (int) ($jmlKerusakan[$r->id] ?? 0);
                $dipinjam = $sedangDipinjam->has($r->id);
                $statusWarna = $kr > 0 ? '#dc2626' : ($dipinjam ? '#d97706' : '#059669');
                $ja = (int) ($jmlAset[$r->id] ?? 0);
            @endphp
            <a href="{{ route('sarpras.ruangan.show', $r) }}"
               class="flex items-center gap-2 border rounded px-3 py-2 hover:bg-gray-50 hover:border-gray-300 transition">
                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $statusWarna }}" title="Status"></span>
                <span class="flex-1 min-w-0 truncate"><span class="font-semibold">{{ $r->kode }}</span> — {{ $r->nama }}</span>
                @if ($kr > 0)
                    <span class="shrink-0 text-[10px] font-bold text-red-700 bg-red-100 rounded px-1.5 py-0.5">{{ $kr }}⚠</span>
                @elseif ($dipinjam)
                    <span class="shrink-0 text-[10px] font-semibold text-amber-700 bg-amber-100 rounded px-1.5 py-0.5">dipinjam</span>
                @elseif ($ja > 0)
                    <span class="shrink-0 text-[10px] text-gray-400">{{ $ja }} aset</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// === Zoom live + export denah (JPEG/PDF) — semua di sisi klien ===
(function () {
    const zoom = document.getElementById('denah-zoom');
    const range = document.getElementById('zoom-range');
    const label = document.getElementById('zoom-label');
    const capture = document.getElementById('denah-capture');
    if (!zoom || !capture) return;

    // --- Zoom ---
    function setZoom(v) {
        v = Math.min(200, Math.max(50, v));
        range.value = v;
        zoom.style.transform = 'scale(' + (v / 100) + ')';
        label.textContent = v + '%';
    }
    range.addEventListener('input', () => setZoom(parseInt(range.value, 10)));
    document.getElementById('zoom-in').addEventListener('click', () => setZoom(parseInt(range.value, 10) + 10));
    document.getElementById('zoom-out').addEventListener('click', () => setZoom(parseInt(range.value, 10) - 10));
    document.getElementById('zoom-reset').addEventListener('click', () => setZoom(100));

    // --- Ukuran kertas ---
    const paperSelect = document.getElementById('paper-size');
    if (paperSelect) {
        paperSelect.addEventListener('change', function () {
            capture.style.aspectRatio = this.value;
            setZoom(100);
        });
    }

    // --- Export ---
    const namaFile = @json(\Illuminate\Support\Str::slug($denah->nama ?: 'denah'));

    // Render node denah ke canvas resolusi 2x.
    // offsetWidth/Height mengabaikan transform zoom; width & height di-pin eksplisit
    // sementara karena html2canvas tidak mengenal CSS aspect-ratio maupun width:auto.
    function render() {
        const w = capture.offsetWidth;
        const h = capture.offsetHeight;
        const prevW = capture.style.width;
        const prevH = capture.style.height;
        capture.style.width  = w + 'px';
        capture.style.height = h + 'px';
        return html2canvas(capture, {
            backgroundColor: '#ffffff',
            useCORS: true,
            scale: 2,
            width: w,
            height: h,
            logging: false,
        }).finally(() => {
            capture.style.width  = prevW;
            capture.style.height = prevH;
        });
    }
    function unduh(href, ext) {
        const a = document.createElement('a');
        a.href = href;
        a.download = namaFile + '.' + ext;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    document.getElementById('export-jpeg').addEventListener('click', async function () {
        this.disabled = true;
        try {
            const cv = await render();
            unduh(cv.toDataURL('image/jpeg', 0.92), 'jpg');
        } catch (e) {
            alert('Gagal export JPEG: ' + (e && e.message ? e.message : e));
        }
        this.disabled = false;
    });

    document.getElementById('export-pdf').addEventListener('click', async function () {
        this.disabled = true;
        try {
            const cv = await render();
            const img = cv.toDataURL('image/jpeg', 0.92);
            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
                orientation: cv.width >= cv.height ? 'l' : 'p',
                unit: 'px',
                format: [cv.width, cv.height],
            });
            pdf.addImage(img, 'JPEG', 0, 0, cv.width, cv.height);
            pdf.save(namaFile + '.pdf');
        } catch (e) {
            alert('Gagal export PDF: ' + (e && e.message ? e.message : e));
        }
        this.disabled = false;
    });
})();

// === Toggle Mode Warna: "Warna Ruangan" <-> "Status" ===
(function () {
    const grup = document.getElementById('mode-warna');
    const legenda = document.getElementById('legenda-status');
    const blok = document.querySelectorAll('[data-blok]');
    if (!grup || !blok.length) return;

    function terapkan(mode) {
        blok.forEach((el) => {
            const warna = mode === 'status' ? el.dataset.statusWarna : el.dataset.warna;
            el.style.backgroundColor = warna;
            // Mode status memakai teks putih (warna status selalu gelap).
            el.style.color = mode === 'status' ? '#ffffff' : el.dataset.warnaTeks;
        });
        legenda.classList.toggle('hidden', mode !== 'status');
        legenda.classList.toggle('flex', mode === 'status');
        grup.querySelectorAll('button').forEach((b) => {
            const aktif = b.dataset.mode === mode;
            b.classList.toggle('bg-white', aktif);
            b.classList.toggle('shadow', aktif);
            b.classList.toggle('text-gray-800', aktif);
            b.classList.toggle('text-gray-500', !aktif);
        });
    }

    grup.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-mode]');
        if (btn) terapkan(btn.dataset.mode);
    });
})();

// === Toggle panel Import Ruangan ===
(function () {
    const btn = document.getElementById('toggle-import-ruangan');
    const panel = document.getElementById('panel-import-ruangan');
    if (btn && panel) {
        btn.addEventListener('click', () => panel.classList.toggle('hidden'));
    }
    // Buka panel otomatis bila ada catatan hasil import.
    @if (session('import_catatan') && count(session('import_catatan')))
        panel?.classList.remove('hidden');
    @endif
})();
</script>
@endpush
@endsection
