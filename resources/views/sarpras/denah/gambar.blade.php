@extends('sarpras.layouts.app')
@section('title', 'Gambar Denah: ' . $denah->nama)

@section('sarpras_body')
<div class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Menggambar Denah — {{ $denah->nama }}</h2>
    <div class="flex items-center gap-3 text-sm">
        @include('sarpras.denah.partials.import-button', ['denah' => $denah])
        <a href="{{ route('sarpras.denah.hotspot', $denah) }}" class="text-blue-600 hover:underline">Lewati → Atur Blok Ruangan</a>
    </div>
</div>
<p class="text-xs text-gray-400 mb-3">Punya gambar denah jadi? Klik <b>Import Denah</b> untuk mengunggah file (jpg/png/webp/gif/bmp) — atau gambar manual di bawah.</p>

<div class="bg-white rounded-lg shadow p-3" x-data>
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-2 mb-3 text-sm" id="toolbar">
        <div class="flex items-center gap-1 rounded-lg border p-1" role="group">
            <button type="button" data-tool="pen"   class="tool px-2 py-1 rounded" title="Pensil">✏️</button>
            <button type="button" data-tool="line"  class="tool px-2 py-1 rounded" title="Garis dinding">／</button>
            <button type="button" data-tool="rect"  class="tool px-2 py-1 rounded" title="Kotak ruangan">▭</button>
            <button type="button" data-tool="text"  class="tool px-2 py-1 rounded" title="Teks">T</button>
            <button type="button" data-tool="eraser" class="tool px-2 py-1 rounded" title="Penghapus">⌫</button>
        </div>

        <label class="flex items-center gap-1">Warna
            <input type="color" id="warna" value="#1f2937" class="w-8 h-8 border rounded">
        </label>
        <label class="flex items-center gap-1">Tebal
            <input type="range" id="tebal" min="1" max="24" value="4">
        </label>

        <button type="button" id="btn-undo" class="px-3 py-1.5 border rounded">↶ Undo</button>
        <button type="button" id="btn-clear" class="px-3 py-1.5 border rounded text-red-600">Bersihkan</button>

        <form method="POST" action="{{ route('sarpras.denah.gambar.simpan', $denah) }}" id="form-simpan" class="ml-auto">
            @csrf
            <input type="hidden" name="gambar_data" id="gambar_data">
            <button class="bg-slate-900 text-white px-4 py-1.5 rounded">💾 Simpan Denah</button>
        </form>
    </div>

    <p class="text-xs text-gray-400 mb-2">
        Pilih alat lalu gambar di kanvas. <b>Kotak</b> untuk ruangan, <b>Garis</b> untuk dinding, <b>Teks</b> untuk label.
        Setelah disimpan, lanjut <b>Atur Blok Ruangan</b> untuk menempatkan ruangan interaktif di atas gambar.
    </p>

    {{-- Kanvas (buffer 1600x1000 = rasio 16:10) --}}
    <div class="w-full max-w-4xl mx-auto border rounded overflow-hidden bg-white" style="aspect-ratio:16/10;">
        <canvas id="papan" width="1600" height="1000" class="w-full h-full touch-none block"></canvas>
    </div>
</div>

@push('styles')
<style>
    .tool.active { background: var(--cp); color:#fff; }
    .tool:hover:not(.active){ background:#f1f5f9; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const canvas = document.getElementById('papan');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;

    // Latar putih (denah berlatar putih).
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);

    // Muat gambar denah lama (bila ada) sebagai latar agar bisa diedit ulang.
    @if ($denah->gambar_path)
    (function () {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            // Contain: pertahankan rasio, tengah.
            const r = Math.min(W / img.width, H / img.height);
            const w = img.width * r, h = img.height * r;
            ctx.drawImage(img, (W - w) / 2, (H - h) / 2, w, h);
            pushUndo();
        };
        img.src = "{{ Storage::url($denah->gambar_path) }}";
    })();
    @endif

    let tool = 'pen';
    let warna = document.getElementById('warna');
    let tebal = document.getElementById('tebal');
    let drawing = false, startX = 0, startY = 0, snapshot = null;

    // Undo stack (ImageData; dibatasi 25 langkah).
    const undoStack = [];
    function pushUndo() {
        try {
            undoStack.push(ctx.getImageData(0, 0, W, H));
            if (undoStack.length > 25) undoStack.shift();
        } catch (e) { /* abaikan */ }
    }
    pushUndo(); // kondisi awal (putih)

    // Pilih alat.
    document.querySelectorAll('.tool').forEach(function (b) {
        b.addEventListener('click', function () {
            tool = b.dataset.tool;
            document.querySelectorAll('.tool').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
        });
    });
    document.querySelector('.tool[data-tool="pen"]').classList.add('active');

    // Konversi koordinat pointer -> buffer kanvas.
    function pos(e) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - rect.left) / rect.width * W,
            y: (e.clientY - rect.top) / rect.height * H,
        };
    }

    function strokeStyle() {
        ctx.lineWidth = +tebal.value * 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = (tool === 'eraser') ? '#ffffff' : warna.value;
        ctx.fillStyle = warna.value;
    }

    canvas.addEventListener('pointerdown', function (e) {
        const p = pos(e);

        if (tool === 'text') {
            const t = prompt('Teks label:');
            if (t) {
                strokeStyle();
                ctx.font = 'bold ' + (+tebal.value * 8) + 'px Plus Jakarta Sans, sans-serif';
                ctx.fillText(t, p.x, p.y);
                pushUndo();
            }
            return;
        }

        drawing = true;
        startX = p.x; startY = p.y;
        strokeStyle();
        if (tool === 'pen' || tool === 'eraser') {
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        } else {
            // simpan snapshot utk preview garis/kotak
            snapshot = ctx.getImageData(0, 0, W, H);
        }
        canvas.setPointerCapture(e.pointerId);
    });

    canvas.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        const p = pos(e);
        strokeStyle();

        if (tool === 'pen' || tool === 'eraser') {
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        } else if (snapshot) {
            ctx.putImageData(snapshot, 0, 0);
            if (tool === 'line') {
                ctx.beginPath();
                ctx.moveTo(startX, startY);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
            } else if (tool === 'rect') {
                ctx.strokeRect(startX, startY, p.x - startX, p.y - startY);
            }
        }
    });

    function finish(e) {
        if (!drawing) return;
        drawing = false;
        snapshot = null;
        pushUndo();
    }
    canvas.addEventListener('pointerup', finish);
    canvas.addEventListener('pointercancel', finish);

    // Undo.
    document.getElementById('btn-undo').addEventListener('click', function () {
        if (undoStack.length <= 1) return;
        undoStack.pop();                       // buang state sekarang
        const prev = undoStack[undoStack.length - 1];
        ctx.putImageData(prev, 0, 0);
    });

    // Bersihkan.
    document.getElementById('btn-clear').addEventListener('click', function () {
        $.confirm({
            title: 'Bersihkan seluruh gambar?',
            content: 'Semua coretan pada kanvas akan dihapus dan tidak dapat dikembalikan.',
            type: 'orange',
            buttons: {
                ya: { text: 'Ya, Bersihkan', btnClass: 'btn-orange', keys: ['enter'], action: function () {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, W, H);
                    pushUndo();
                } },
                batal: { text: 'Batal' }
            }
        });
    });

    // Simpan -> isi hidden field dgn PNG data URL.
    document.getElementById('form-simpan').addEventListener('submit', function () {
        document.getElementById('gambar_data').value = canvas.toDataURL('image/png');
    });
})();
</script>
@endpush
@endsection
