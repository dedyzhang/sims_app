@extends('sarpras.layouts.app')
@section('title', 'Denah Sekolah: ' . $denah->nama)
@section('sarpras_title', 'Denah Sekolah: ' . $denah->nama)
@section('sarpras_subtitle', 'Peta interaktif ruangan, status pemakaian, aset, booking, dan titik maintenance per lantai.')

@section('sarpras_body')
<div class="flex justify-between items-start gap-3 flex-wrap mb-4">
    <div class="min-w-0">
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-100 break-words">{{ $denah->nama }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 break-words">{{ $denah->gedung }} {{ $denah->lantai ? '- Lantai '.$denah->lantai : '' }}</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('sarpras.denah.index') }}" class="inline-flex items-center gap-1.5 bg-slate-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-slate-700">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Daftar
        </a>
        @can('sarpras.denah.kelola')
            @include('sarpras.denah.partials.import-button', ['denah' => $denah])
            @if ($denah->gambar_path)
                <form method="POST" action="{{ route('sarpras.denah.gambar.hapus', $denah) }}"
                      onsubmit="return confirmAction(this, 'Hapus gambar denah ini? Blok ruangan tetap tersimpan dan Anda bisa import / menggambar ulang.', 'red')">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 border border-red-300 text-red-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-red-50">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Gambar
                    </button>
                </form>
            @endif
            <a href="{{ route('sarpras.denah.gambar', $denah) }}" class="inline-flex items-center gap-1.5 bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-indigo-700">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Gambar Denah
            </a>
            <a href="{{ route('sarpras.denah.hotspot', $denah) }}" class="inline-flex items-center gap-1.5 bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-emerald-700">
                <i data-lucide="grid-2x2" class="w-3.5 h-3.5"></i> Atur Blok Ruangan
            </a>
        @endcan
    </div>
</div>
{{-- Pemilih lantai pada gedung yang sama --}}
@if ($denah->gedung)
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs text-slate-400 mr-1">{{ $denah->gedung }} —</span>
        @foreach ($lantaiSegedung as $l)
            <a href="{{ route('sarpras.denah.show', $l) }}"
               class="px-3 py-1 rounded-full text-sm {{ $l->id === $denah->id ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $l->lantai ? 'Lantai ' . $l->lantai : $l->nama }}
            </a>
        @endforeach
        @can('sarpras.denah.kelola')
            <a href="{{ route('sarpras.denah.create', ['gedung' => $denah->gedung]) }}"
               class="px-3 py-1 rounded-full text-sm border border-dashed border-slate-300 text-slate-500 hover:bg-slate-50">+ Lantai</a>
        @endcan
    </div>
@endif

<p class="text-sm text-slate-500 mb-2">Klik ruangan (mis. <b>7A</b>) untuk melihat detail.</p>

{{--
    DENAH SEKOLAH INTERAKTIF.
    Container position:relative & responsif (lebar mengikuti layar, TANPA pixel hardcoded).
    Hotspot position:absolute pakai KOORDINAT PERSEN (pos_x/pos_y) + translate(-50%,-50%)
    sehingga presisi & tidak bergeser di ukuran layar berbeda.
--}}
<div class="bg-white rounded-lg shadow p-3">
    {{-- Toolbar: zoom live + export --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2 text-sm">
            <span class="text-slate-500 hidden sm:inline">Perbesar</span>
            <button type="button" id="zoom-out" class="w-8 h-8 grid place-items-center border rounded-lg hover:bg-slate-50 text-lg leading-none">−</button>
            <input id="zoom-range" type="range" min="50" max="200" step="10" value="100" class="w-28 sm:w-44 touch-none">
            <button type="button" id="zoom-in" class="w-8 h-8 grid place-items-center border rounded-lg hover:bg-slate-50 text-lg leading-none">+</button>
            <span id="zoom-label" class="text-slate-600 w-11 tabular-nums">100%</span>
            <button type="button" id="zoom-reset" class="text-xs text-blue-600 hover:underline">Reset</button>
            <button type="button" id="fullscreen-toggle" class="ml-1 inline-flex items-center gap-1 text-xs text-slate-600 border rounded-lg px-2 py-1.5 hover:bg-slate-50" title="Layar penuh">
                <i data-lucide="maximize" class="w-3.5 h-3.5"></i>
                <span class="hidden sm:inline">Layar Penuh</span>
            </button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500 hidden sm:inline">Kertas:</span>
            <select id="paper-size" class="text-xs border rounded px-2 py-1.5 text-slate-600 bg-white focus:outline-none focus:ring-1 focus:ring-blue-300">
                <option value="297/210">A4 Landscape</option>
                <option value="210/297">A4 Portrait</option>
                <option value="330/215">F4 Landscape</option>
                <option value="215/330">F4 Portrait</option>
                <option value="16/10">Bebas (16:10)</option>
            </select>
            <div class="w-px h-5 bg-slate-200 mx-1"></div>
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
        <div class="inline-flex rounded-lg border bg-slate-50 p-0.5 text-sm" id="mode-warna">
            <button type="button" data-mode="ruangan"
                    class="px-3 py-1 rounded-md font-medium bg-white shadow text-slate-800">Warna Ruangan</button>
            <button type="button" data-mode="status"
                    class="px-3 py-1 rounded-md font-medium text-slate-500 hover:text-slate-700">Status</button>
        </div>
        {{-- Legenda status (muncul saat mode "Status") --}}
        <div id="legenda-status" class="hidden flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
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
                         class="absolute inset-0 w-full h-full object-contain bg-slate-50 rounded">
                @else
                    <div class="absolute inset-0 flex items-center justify-center bg-slate-100 text-slate-400 rounded">
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
                       title="{{ $r->kode }} - {{ $r->nama }}{{ $kr > 0 ? ' - '.$kr.' kerusakan' : ($dipinjam ? ' - sedang dipinjam' : '') }}"
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

<div class="mt-6 bg-white rounded-lg shadow p-5" x-data="{ openEdit: false, editForm: { action: '', kode: '', nama: '', kapasitas: '', warna: '#3b82f6', pos_x: '', pos_y: '', lebar: '', tinggi: '' } }">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-4">
        <h3 class="font-semibold text-slate-800">Daftar Ruangan</h3>
        @can('sarpras.denah.kelola')
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" id="toggle-tambah-ruangan"
                        class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
                    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Ruangan
                </button>
                <button type="button" id="toggle-import-ruangan"
                        class="inline-flex items-center gap-2 bg-[#eafaf1] text-[#065f46] border border-[#a7f3d0] px-4 py-2 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#d1fae5]">
                    <i data-lucide="upload" class="w-4 h-4"></i> Import Excel
                </button>
            </div>
        @endcan
    </div>

    @can('sarpras.denah.kelola')
        {{-- Panel tambah ruangan secara manual --}}
        <div id="panel-tambah-ruangan" class="hidden mb-4 rounded-lg border border-slate-200 bg-slate-50/50 p-4">
            <h4 class="font-semibold text-slate-800 text-sm mb-3">Tambah Ruangan Baru</h4>
            <form method="POST" action="{{ route('sarpras.ruangan.store', $denah) }}" enctype="multipart/form-data"
                  class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                @csrf
                <input type="hidden" name="pos_x" value="50">
                <input type="hidden" name="pos_y" value="50">
                <input type="hidden" name="lebar" value="14">
                <input type="hidden" name="tinggi" value="9">
                <input type="hidden" name="status" value="tersedia">
                <input type="hidden" name="gedung" value="{{ $denah->gedung }}">
                <input type="hidden" name="lantai" value="{{ $denah->lantai }}">

                <div>
                    <label class="block text-slate-600 text-xs font-semibold mb-1">Kode Ruangan (mis. 7A) <span class="text-red-500">*</span></label>
                    <input name="kode" required placeholder="mis. 7A" class="w-full border rounded px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-slate-600 text-xs font-semibold mb-1">Nama Ruangan <span class="text-red-500">*</span></label>
                    <input name="nama" required placeholder="mis. Kelas 7A" class="w-full border rounded px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-slate-600 text-xs font-semibold mb-1">Kapasitas Orang (opsional)</label>
                    <input name="kapasitas" type="number" min="0" placeholder="mis. 36" class="w-full border rounded px-3 py-2 bg-white">
                </div>
                <div>
                    <label class="block text-slate-600 text-xs font-semibold mb-1">Warna Blok</label>
                    <div class="flex gap-1.5">
                        <input name="warna" type="color" value="#3b82f6" class="h-[38px] w-12 border rounded px-1 py-1 cursor-pointer bg-white">
                        <button type="submit" class="flex-1 bg-slate-900 hover:bg-slate-800 text-white rounded font-bold transition">Simpan</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Panel import ruangan dari Excel/CSV (tersembunyi by default) --}}
        <div id="panel-import-ruangan" class="hidden mb-4 rounded-lg border border-emerald-100 bg-emerald-50/40 p-4">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <h4 class="font-semibold text-slate-800 text-sm">Import Ruangan ke Denah Ini</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Unggah file Excel/CSV. Ruangan dengan <b>kode</b> yang sudah ada di denah ini akan diperbarui, sisanya ditambahkan & ditata otomatis di denah.</p>
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
                <button class="inline-flex items-center gap-1.5 bg-[#eafaf1] text-[#065f46] border border-[#a7f3d0] px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#d1fae5]">
                    <i data-lucide="upload" class="w-4 h-4"></i> Proses Import
                </button>
            </form>

            <p class="text-xs text-slate-400 mt-2">
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

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
        @foreach ($denah->ruangan as $r)
            @php
                $kr = (int) ($jmlKerusakan[$r->id] ?? 0);
                $dipinjam = $sedangDipinjam->has($r->id);
                $statusWarna = $kr > 0 ? '#dc2626' : ($dipinjam ? '#d97706' : '#059669');
                $ja = (int) ($jmlAset[$r->id] ?? 0);
            @endphp
            <div class="group flex items-center justify-between border rounded-xl bg-white hover:bg-slate-50 hover:border-slate-300 transition-all duration-200">
                <a href="{{ route('sarpras.ruangan.show', $r) }}"
                   class="flex-1 flex items-center gap-2 px-3.5 py-2.5 min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $statusWarna }}" title="Status"></span>
                    <span class="flex-1 min-w-0 truncate text-slate-700 dark:text-slate-200">
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $r->kode }}</span> - {{ $r->nama }}
                    </span>
                    @if ($kr > 0)
                        <span class="shrink-0 text-[10px] font-bold text-red-700 bg-red-100 rounded px-1.5 py-0.5">{{ $kr }}⚠</span>
                    @elseif ($dipinjam)
                        <span class="shrink-0 text-[10px] font-semibold text-amber-700 bg-amber-100 rounded px-1.5 py-0.5">dipinjam</span>
                    @elseif ($ja > 0)
                        <span class="shrink-0 text-[10px] text-slate-400 dark:text-slate-500">{{ $ja }} aset</span>
                    @endif
                </a>
                @can('sarpras.denah.kelola')
                    <div class="flex items-center gap-1 shrink-0 pr-2">
                        <a href="{{ route('sarpras.denah.hotspot', $denah) }}"
                           class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition"
                           title="Atur Tata Letak / Posisi (Drag & Drop)">
                            <i data-lucide="move" class="w-4 h-4"></i>
                        </a>
                        <button type="button" @click="editForm.action='{{ route('sarpras.ruangan.update', $r) }}'; editForm.kode='{{ $r->kode }}'; editForm.nama='{{ $r->nama }}'; editForm.kapasitas='{{ $r->kapasitas }}'; editForm.warna='{{ $r->warna_hex }}'; editForm.pos_x='{{ $r->pos_x }}'; editForm.pos_y='{{ $r->pos_y }}'; editForm.lebar='{{ $r->lebar }}'; editForm.tinggi='{{ $r->tinggi }}'; openEdit=true"
                                class="p-1.5 text-slate-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition" title="Edit Ruangan">
                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                        </button>
                        <form method="POST" action="{{ route('sarpras.ruangan.destroy', $r) }}"
                              onsubmit="return confirmAction(this, 'Hapus ruangan &ldquo;{{ $r->kode }}&rdquo;? Semua aset di dalamnya akan terputus dari ruangan ini.', 'red')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-1.5 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition" title="Hapus Ruangan">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                    </div>
                @endcan
            </div>
        @endforeach
    </div>

    @can('sarpras.denah.kelola')
    {{-- Modal edit ruangan --}}
    <div x-show="openEdit" x-cloak x-transition.opacity class="fixed inset-0 z-[9990] grid place-items-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="openEdit=false">
        <div class="card !rounded-2xl w-full max-w-md p-5 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Edit Ruangan</h3>
                <button @click="openEdit=false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form method="POST" :action="editForm.action" class="space-y-3">
                @csrf @method('PUT')
                <input type="hidden" name="pos_x" x-model="editForm.pos_x">
                <input type="hidden" name="pos_y" x-model="editForm.pos_y">
                <input type="hidden" name="lebar" x-model="editForm.lebar">
                <input type="hidden" name="tinggi" x-model="editForm.tinggi">
                <div>
                    <label class="form-label">Kode Ruangan</label>
                    <input type="text" name="kode" x-model="editForm.kode" required class="form-input text-sm">
                </div>
                <div>
                    <label class="form-label">Nama Ruangan</label>
                    <input type="text" name="nama" x-model="editForm.nama" required class="form-input text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Kapasitas</label>
                        <input type="number" name="kapasitas" x-model="editForm.kapasitas" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Warna Blok</label>
                        <input type="color" name="warna" x-model="editForm.warna" class="h-[38px] w-full border rounded px-1 py-1 cursor-pointer bg-white dark:bg-slate-800">
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" @click="openEdit=false" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
                    <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-sm font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
// === Zoom live + export denah (JPEG/PDF) - semua di sisi klien ===
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

    // --- Layar penuh (fullscreen) pada container denah ---
    const fsBtn = document.getElementById('fullscreen-toggle');
    const wrap = document.getElementById('denah-zoom-wrap');
    if (fsBtn && wrap) {
        fsBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                wrap.requestFullscreen?.() || wrap.webkitRequestFullscreen?.();
            } else {
                document.exitFullscreen?.() || document.webkitExitFullscreen?.();
            }
        });
        document.addEventListener('fullscreenchange', () => {
            const icon = fsBtn.querySelector('[data-lucide]');
            if (icon) {
                icon.setAttribute('data-lucide', document.fullscreenElement ? 'minimize' : 'maximize');
                if (window.lucide) window.lucide.createIcons();
            }
        });
    }

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
            $.alert({ title: 'Gagal Export JPEG', content: (e && e.message ? e.message : String(e)), type: 'red' });
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
            $.alert({ title: 'Gagal Export PDF', content: (e && e.message ? e.message : String(e)), type: 'red' });
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
            b.classList.toggle('text-slate-800', aktif);
            b.classList.toggle('text-slate-500', !aktif);
        });
    }

    grup.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-mode]');
        if (btn) terapkan(btn.dataset.mode);
    });
})();

// === Toggle panel Tambah Ruangan ===
(function () {
    const btn = document.getElementById('toggle-tambah-ruangan');
    const panel = document.getElementById('panel-tambah-ruangan');
    if (btn && panel) {
        btn.addEventListener('click', () => panel.classList.toggle('hidden'));
    }
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
