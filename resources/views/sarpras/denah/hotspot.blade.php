@extends('sarpras.layouts.app')
@section('title', 'Atur Blok Ruangan: ' . $denah->nama)

@section('sarpras_body')
<div class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Atur Blok Ruangan — {{ $denah->nama }}</h2>
    <div class="flex items-center gap-3 text-sm">
        <a href="{{ route('sarpras.denah.gambar', $denah) }}" class="text-indigo-600 hover:underline">✏️ Gambar Denah</a>
        @if ($denah->gambar_path)
            <form method="POST" action="{{ route('sarpras.denah.gambar.hapus', $denah) }}"
                  onsubmit="return confirm('Hapus gambar denah ini? Blok ruangan tetap tersimpan dan Anda bisa import / menggambar ulang.')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 hover:underline">🗑️ Hapus Gambar</button>
            </form>
        @endif
        <a href="{{ route('sarpras.denah.show', $denah) }}" class="text-blue-600 hover:underline">← Lihat Denah</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- KANVAS DENAH --}}
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-3">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <p class="text-sm text-gray-500">
                Klik pada denah untuk posisi ruangan baru. Seret blok untuk memindah; seret sisi/sudut untuk mengubah ukuran (otomatis tersimpan).
            </p>
            <div class="flex items-center gap-2 text-sm">
                <label class="inline-flex items-center gap-1.5 cursor-pointer select-none">
                    <input type="checkbox" id="grid-toggle" class="rounded border-gray-300">
                    <span>Grid</span>
                </label>
                <select id="grid-size" class="border rounded px-2 py-1 text-xs">
                    <option value="1">1%</option>
                    <option value="2">2%</option>
                    <option value="2.5">2.5%</option>
                    <option value="5" selected>5%</option>
                    <option value="10">10%</option>
                </select>
            </div>
        </div>
        <div id="kanvas" class="relative w-full mx-auto select-none border rounded bg-gray-50" style="aspect-ratio: 16/10;">
            @if ($denah->gambar_path)
                <img id="denah-img" src="{{ Storage::url($denah->gambar_path) }}"
                     class="absolute inset-0 w-full h-full object-contain pointer-events-none" alt="denah">
            @else
                <div class="absolute inset-0 flex items-center justify-center text-gray-400 text-sm text-center px-4">
                    Belum ada gambar denah. Klik “✏️ Gambar Denah” untuk menggambar, atau unggah di Edit Denah.
                </div>
            @endif

            {{-- Overlay grid (toggle) --}}
            <div id="grid-overlay" class="absolute inset-0 z-0 pointer-events-none hidden"></div>

            @php
                // Gagang resize: 4 sisi + 4 sudut. [posisi css, cursor]
                $gagang = [
                    'n'  => ['top:-5px;left:50%;transform:translate(-50%,-50%);',  'ns-resize'],
                    's'  => ['bottom:-5px;left:50%;transform:translate(-50%,50%);', 'ns-resize'],
                    'w'  => ['left:-5px;top:50%;transform:translate(-50%,-50%);',   'ew-resize'],
                    'e'  => ['right:-5px;top:50%;transform:translate(50%,-50%);',   'ew-resize'],
                    'nw' => ['top:-5px;left:-5px;transform:translate(-50%,-50%);',  'nwse-resize'],
                    'ne' => ['top:-5px;right:-5px;transform:translate(50%,-50%);',  'nesw-resize'],
                    'sw' => ['bottom:-5px;left:-5px;transform:translate(-50%,50%);','nesw-resize'],
                    'se' => ['bottom:-5px;right:-5px;transform:translate(50%,50%);','nwse-resize'],
                ];
            @endphp
            @foreach ($denah->ruangan as $r)
                <div data-id="{{ $r->id }}"
                     class="hotspot absolute z-10 -translate-x-1/2 -translate-y-1/2 rounded-md border-2 border-white shadow cursor-move"
                     style="left: {{ $r->pos_x }}%; top: {{ $r->pos_y }}%; width: {{ $r->lebar ?? 14 }}%; height: {{ $r->tinggi ?? 9 }}%; background-color: {{ $r->warna_hex }}; color: {{ $r->warna_teks }};">
                    <span class="absolute inset-0 flex items-center justify-center font-bold text-xs px-1 truncate pointer-events-none">{{ $r->kode }}</span>
                    @foreach ($gagang as $dir => [$css, $cursor])
                        <span class="rz absolute w-2.5 h-2.5 bg-white border border-emerald-700 rounded-sm"
                              data-dir="{{ $dir }}" style="{{ $css }}cursor:{{ $cursor }};"></span>
                    @endforeach
                </div>
            @endforeach

            {{-- Marker sementara untuk posisi baru --}}
            <div id="marker-baru" class="hidden absolute z-20 -translate-x-1/2 -translate-y-1/2">
                <span class="flex items-center justify-center w-9 h-9 rounded-full bg-amber-500 text-white text-xs font-bold shadow ring-2 ring-white">+</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-2">Posisi & ukuran disimpan dalam PERSEN (0–100) agar responsif.</p>
    </div>

    {{-- FORM TAMBAH RUANGAN --}}
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-800 mb-3">Tambah Ruangan</h3>
        <form method="POST" action="{{ route('sarpras.ruangan.store', $denah) }}" enctype="multipart/form-data" class="space-y-3 text-sm">
            @csrf
            <div>
                <label class="block text-gray-700 mb-1">Kode (mis. 7A)</label>
                <input name="kode" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Nama Ruangan</label>
                <input name="nama" class="w-full border rounded px-3 py-2">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-gray-700 mb-1">Pos X (%)</label>
                    <input id="pos_x" name="pos_x" value="50" required readonly class="w-full border rounded px-3 py-2 bg-gray-50">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Pos Y (%)</label>
                    <input id="pos_y" name="pos_y" value="50" required readonly class="w-full border rounded px-3 py-2 bg-gray-50">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Lebar (%)</label>
                    <input name="lebar" type="number" min="1" max="100" step="0.5" value="14" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Tinggi (%)</label>
                    <input name="tinggi" type="number" min="1" max="100" step="0.5" value="9" class="w-full border rounded px-3 py-2">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-gray-700 mb-1">Kapasitas</label>
                    <input name="kapasitas" type="number" min="0" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Warna Blok</label>
                    <input name="warna" type="color" value="{{ \App\Sarpras\Models\DenahRuangan::WARNA_DEFAULT }}"
                           class="w-full h-[42px] border rounded px-1 py-1 cursor-pointer">
                </div>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Foto Ruangan (opsional)</label>
                <input name="foto" type="file" accept="image/*" class="w-full text-xs">
            </div>
            <button class="w-full bg-slate-900 text-white rounded py-2">Simpan Ruangan</button>
        </form>

        <h3 class="font-semibold text-gray-800 mt-6 mb-2">Ruangan ({{ $denah->ruangan->count() }})</h3>
        <ul class="text-sm divide-y">
            @foreach ($denah->ruangan as $r)
                <li class="py-2 flex justify-between items-center gap-2">
                    <span class="flex items-center gap-2">
                        <input type="color" value="{{ $r->warna_hex }}" data-id="{{ $r->id }}"
                               class="warna-input w-6 h-6 p-0 border rounded cursor-pointer" title="Ubah warna {{ $r->kode }}">
                        <span><b>{{ $r->kode }}</b> · {{ number_format($r->pos_x,1) }}%, {{ number_format($r->pos_y,1) }}%</span>
                    </span>
                    <form method="POST" action="{{ route('sarpras.ruangan.destroy', $r) }}"
                          onsubmit="return confirm('Hapus ruangan {{ $r->kode }}?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600 hover:underline text-xs">Hapus</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</div>

@push('scripts')
<script>
// === Editor blok ruangan: posisi & ukuran dalam PERSEN relatif kanvas ===
(function () {
    const kanvas = document.getElementById('kanvas');
    const markerBaru = document.getElementById('marker-baru');
    const inX = document.getElementById('pos_x');
    const inY = document.getElementById('pos_y');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    if (!kanvas) return;

    // === GRID + SNAP ===
    const gridToggle = document.getElementById('grid-toggle');
    const gridSizeSel = document.getElementById('grid-size');
    const gridOverlay = document.getElementById('grid-overlay');

    function gridStep() { return parseFloat(gridSizeSel.value) || 5; }
    function gridOn() { return gridToggle.checked; }
    function snap(v) {
        if (!gridOn()) return v;
        const s = gridStep();
        return Math.min(100, Math.max(0, +(Math.round(v / s) * s).toFixed(2)));
    }
    function renderGrid() {
        if (gridOn()) {
            const s = gridStep();
            gridOverlay.style.backgroundImage =
                'linear-gradient(to right, rgba(15,23,42,.18) 1px, transparent 1px),' +
                'linear-gradient(to bottom, rgba(15,23,42,.18) 1px, transparent 1px)';
            gridOverlay.style.backgroundSize = s + '% ' + s + '%';
            gridOverlay.classList.remove('hidden');
        } else {
            gridOverlay.classList.add('hidden');
        }
    }
    // Pulihkan preferensi dari localStorage.
    try {
        gridToggle.checked = localStorage.getItem('sarpras_grid') === '1';
        const gs = localStorage.getItem('sarpras_grid_size');
        if (gs) gridSizeSel.value = gs;
    } catch (e) {}
    gridToggle.addEventListener('change', function () {
        try { localStorage.setItem('sarpras_grid', gridToggle.checked ? '1' : '0'); } catch (e) {}
        renderGrid();
    });
    gridSizeSel.addEventListener('change', function () {
        try { localStorage.setItem('sarpras_grid_size', gridSizeSel.value); } catch (e) {}
        renderGrid();
    });
    renderGrid();

    function persen(evt) {
        const rect = kanvas.getBoundingClientRect();
        let x = (evt.clientX - rect.left) / rect.width * 100;
        let y = (evt.clientY - rect.top) / rect.height * 100;
        return { x: Math.min(100, Math.max(0, +x.toFixed(2))), y: Math.min(100, Math.max(0, +y.toFixed(2))) };
    }

    // Klik kanvas kosong -> set posisi ruangan baru.
    kanvas.addEventListener('click', function (e) {
        if (e.target.closest('.hotspot')) return;
        const p = persen(e);
        const x = snap(p.x), y = snap(p.y);
        inX.value = x; inY.value = y;
        markerBaru.style.left = x + '%';
        markerBaru.style.top = y + '%';
        markerBaru.classList.remove('hidden');
    });

    function simpan(el, body) {
        fetch('{{ url('sarpras/ruangan') }}/' + el.dataset.id + '/posisi', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        }).then(r => r.json()).catch(() => alert('Gagal menyimpan.'));
    }

    const MIN = 2; // ukuran minimum blok (%)

    let mode = null, target = null, dir = null, edges = null;

    // Sisi-sisi blok (persen) dari posisi pusat + ukuran saat ini.
    function sisiDari(el) {
        const cx = parseFloat(el.style.left), cy = parseFloat(el.style.top);
        const w = parseFloat(el.style.width), h = parseFloat(el.style.height);
        return { kiri: cx - w / 2, kanan: cx + w / 2, atas: cy - h / 2, bawah: cy + h / 2 };
    }

    // Terapkan sisi -> hitung ulang pusat + ukuran lalu set ke elemen.
    function terapkanSisi(el, s) {
        const w = Math.max(MIN, s.kanan - s.kiri);
        const h = Math.max(MIN, s.bawah - s.atas);
        el.style.left = ((s.kiri + s.kanan) / 2).toFixed(2) + '%';
        el.style.top = ((s.atas + s.bawah) / 2).toFixed(2) + '%';
        el.style.width = w.toFixed(2) + '%';
        el.style.height = h.toFixed(2) + '%';
    }

    kanvas.querySelectorAll('.hotspot').forEach(function (h) {
        h.addEventListener('pointerdown', function (e) {
            target = h;
            if (e.target.classList.contains('rz')) {
                mode = 'resize';
                dir = e.target.dataset.dir;     // n/s/e/w/ne/nw/se/sw
                edges = sisiDari(h);            // sisi yang berlawanan jadi jangkar (tetap)
            } else {
                mode = 'move';
            }
            e.preventDefault();
            e.stopPropagation();
            try { h.setPointerCapture(e.pointerId); } catch (err) {}
        });
    });

    document.addEventListener('pointermove', function (e) {
        if (!target) return;
        const p = persen(e);

        if (mode === 'move') {
            target.style.left = snap(p.x) + '%';
            target.style.top = snap(p.y) + '%';
        } else if (mode === 'resize') {
            const px = snap(p.x), py = snap(p.y);
            const s = Object.assign({}, edges); // mulai dari sisi awal (jangkar tetap)
            if (dir.includes('e')) s.kanan = Math.max(edges.kiri + MIN, px);
            if (dir.includes('w')) s.kiri = Math.min(edges.kanan - MIN, px);
            if (dir.includes('s')) s.bawah = Math.max(edges.atas + MIN, py);
            if (dir.includes('n')) s.atas = Math.min(edges.bawah - MIN, py);
            terapkanSisi(target, s);
        }
    });

    document.addEventListener('pointerup', function () {
        if (!target) return;
        const el = target; const m = mode;
        target = null; mode = null; dir = null; edges = null;
        const body = {
            pos_x: parseFloat(el.style.left),
            pos_y: parseFloat(el.style.top),
        };
        if (m === 'resize') {
            body.lebar = parseFloat(el.style.width);
            body.tinggi = parseFloat(el.style.height);
        }
        simpan(el, body);
    });

    // === Ubah warna blok dari daftar ruangan ===
    function teksKontras(hex) {
        const h = hex.replace('#', '');
        const r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#111827' : '#ffffff';
    }
    document.querySelectorAll('.warna-input').forEach(function (inp) {
        const hs = kanvas.querySelector('.hotspot[data-id="' + inp.dataset.id + '"]');
        function terapkan() {
            if (!hs) return;
            hs.style.backgroundColor = inp.value;
            hs.style.color = teksKontras(inp.value);
        }
        inp.addEventListener('input', terapkan);   // preview langsung saat menggeser
        inp.addEventListener('change', function () { // simpan saat selesai memilih
            terapkan();
            const body = { warna: inp.value };
            if (hs) { body.pos_x = parseFloat(hs.style.left); body.pos_y = parseFloat(hs.style.top); }
            fetch('{{ url('sarpras/ruangan') }}/' + inp.dataset.id + '/posisi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            }).then(r => r.json()).catch(() => alert('Gagal menyimpan warna.'));
        });
    });
})();
</script>
@endpush
@endsection
