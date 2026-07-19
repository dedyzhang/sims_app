{{--
    Layout modul Sarpras - terintegrasi ke shell SIMS.
    Memakai layout utama SIMS (sidebar, topbar, tema, font, Tailwind CDN).

    Slot yang bisa diisi view:
      @section('title')            -> judul tab browser (dipakai layouts.app)
      @section('sarpras_title')    -> judul besar header (default "Sarana & Prasarana")
      @section('sarpras_subtitle') -> subjudul header
      @section('sarpras_actions')  -> tombol aksi kanan header
      @section('sarpras_body')     -> konten halaman
--}}
@extends('layouts.app')

@push('styles')
<style>
/* ============================================================
   Dark-mode modul Sarpras (override terscope di .sarpras-scope).
   ============================================================ */
.dark .sarpras-scope { color:#cbd5e1; }
.dark .sarpras-scope .bg-white { background-color:#1e293b !important; }
.dark .sarpras-scope .text-gray-900,
.dark .sarpras-scope .text-gray-800,
.dark .sarpras-scope .text-gray-700,
.dark .sarpras-scope .text-slate-900,
.dark .sarpras-scope .text-slate-800,
.dark .sarpras-scope .text-slate-700 { color:#e2e8f0 !important; }
.dark .sarpras-scope .text-gray-600,
.dark .sarpras-scope .text-gray-500 { color:#94a3b8 !important; }
.dark .sarpras-scope .text-gray-400,
.dark .sarpras-scope .text-gray-300 { color:#64748b !important; }
.dark .sarpras-scope .bg-gray-50,
.dark .sarpras-scope .bg-gray-100 { background-color:#334155 !important; }
.dark .sarpras-scope .border,
.dark .sarpras-scope .border-t,
.dark .sarpras-scope .border-b,
.dark .sarpras-scope .border-l,
.dark .sarpras-scope .border-r,
.dark .sarpras-scope .border-gray-100,
.dark .sarpras-scope .border-gray-200,
.dark .sarpras-scope .border-gray-300,
.dark .sarpras-scope .divide-y > :not([hidden]) ~ :not([hidden]) { border-color:#334155 !important; }
.dark .sarpras-scope input:not([type=color]):not([type=file]),
.dark .sarpras-scope select,
.dark .sarpras-scope textarea { background-color:#0f172a !important; color:#e2e8f0 !important; border-color:#334155 !important; }
.dark .sarpras-scope input::placeholder,
.dark .sarpras-scope textarea::placeholder { color:#64748b !important; }
.dark .sarpras-scope .hover\:bg-gray-50:hover,
.dark .sarpras-scope .hover\:bg-gray-100:hover { background-color:#334155 !important; }

/* Tabel Sarpras: teks panjang harus membungkus, bukan keluar dari kartu. */
.sarpras-scope { max-width:100%; overflow-wrap:anywhere; }
.sarpras-scope .card,
.sarpras-scope .table-responsive,
.sarpras-scope .dataTables_wrapper,
.sarpras-scope .dataTables_scroll,
.sarpras-scope .dataTables_scrollHead,
.sarpras-scope .dataTables_scrollBody { max-width:100%; }
.sarpras-scope .dataTables_wrapper {
    overflow:visible;
    margin-top:0 !important;
    width:100% !important;
}
/* Toolbar DataTables: length + filter tetap di dalam kartu, tidak float keluar. */
.sarpras-scope .sarpras-dt-toolbar {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    gap:.75rem 1rem;
    padding:.85rem 1rem .35rem;
    box-sizing:border-box;
    width:100%;
}
.sarpras-scope .sarpras-dt-footer {
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    gap:.75rem 1rem;
    padding:.5rem 1rem 1rem;
    box-sizing:border-box;
    width:100%;
}
.sarpras-scope .dataTables_length,
.sarpras-scope .dataTables_filter,
.sarpras-scope .dataTables_info,
.sarpras-scope .dataTables_paginate {
    float:none !important;
    width:auto !important;
    margin:0 !important;
    padding:0 !important;
    text-align:left !important;
}
.sarpras-scope .dataTables_filter {
    margin-left:auto !important;
}
.sarpras-scope .dataTables_length label,
.sarpras-scope .dataTables_filter label {
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    flex-wrap:wrap;
    margin:0;
    font-size:.75rem;
    font-weight:700;
    color:#5f6368;
}
.dark .sarpras-scope .dataTables_length label,
.dark .sarpras-scope .dataTables_filter label { color:#94a3b8; }
.sarpras-scope .dataTables_length select,
.sarpras-scope .dataTables_filter input {
    border:1px solid #dadce0 !important;
    border-radius:.75rem !important;
    padding:.4rem .65rem !important;
    min-height:2.1rem;
    background:#fff;
    color:#202124;
    font-size:.8rem;
    max-width:100%;
}
.dark .sarpras-scope .dataTables_length select,
.dark .sarpras-scope .dataTables_filter input {
    background:#0f172a !important;
    border-color:#334155 !important;
    color:#e2e8f0 !important;
}
.sarpras-scope .dataTables_filter input {
    width:min(220px, 55vw) !important;
}
.sarpras-scope .dataTables_scroll,
.sarpras-scope .dataTables_scrollHead,
.sarpras-scope .dataTables_scrollBody {
    overflow-x:auto;
}
.sarpras-scope table:not(.ttd) { max-width:100%; }
.sarpras-scope table:not(.ttd) th,
.sarpras-scope table:not(.ttd) td,
.sarpras-scope table.dataTable.nowrap th,
.sarpras-scope table.dataTable.nowrap td,
.sarpras-scope .data-table th,
.sarpras-scope .data-table td {
    white-space:normal !important;
    overflow-wrap:anywhere;
    word-break:break-word;
    vertical-align:top;
}
.sarpras-scope td .badge,
.sarpras-scope th .badge,
.sarpras-scope td a,
.sarpras-scope th a { max-width:100%; }
.sarpras-scope td .badge,
.sarpras-scope th .badge { white-space:normal; text-align:center; }
.sarpras-scope td form,
.sarpras-scope td .inline-flex,
.sarpras-scope td .flex { flex-wrap:wrap; }
.sarpras-scope .dt-nowrap,
.sarpras-scope .whitespace-nowrap:not(.sarpras-keep-nowrap) { white-space:normal !important; }

/* Google Education visual system for Sarpras */
.sarpras-google-shell {
    --google-blue:#1a73e8;
    --google-red:#ea4335;
    --google-yellow:#fbbc04;
    --google-green:#34a853;
    --google-ink:#202124;
    --google-muted:#5f6368;
    --google-line:#dadce0;
    --google-soft:#f8fafd;
}
.sarpras-google-shell .card {
    border-color:rgba(218,220,224,.86);
    box-shadow:0 10px 28px rgba(60,64,67,.08);
}
.sarpras-google-hero {
    position:relative;
    overflow:hidden;
    background:
        linear-gradient(90deg, var(--google-blue) 0 24%, var(--google-red) 24% 49%, var(--google-yellow) 49% 74%, var(--google-green) 74% 100%) top/100% 5px no-repeat,
        linear-gradient(135deg, #fff 0%, #f8fbff 62%, #eef5ff 100%);
}
.sarpras-google-hero::after {
    content:"";
    position:absolute;
    right:-56px;
    bottom:-72px;
    width:220px;
    height:220px;
    border-radius:9999px;
    border:34px solid rgba(26,115,232,.08);
    pointer-events:none;
}
.sarpras-google-kicker {
    display:inline-flex;
    align-items:center;
    gap:.45rem;
    border:1px solid rgba(218,220,224,.9);
    background:#fff;
    color:var(--google-muted);
    border-radius:9999px;
    padding:.32rem .62rem;
    font-size:.68rem;
    font-weight:800;
    letter-spacing:0;
}
.sarpras-google-dot {
    width:.48rem;
    height:.48rem;
    border-radius:9999px;
    background:var(--google-blue);
    box-shadow:10px 0 0 var(--google-red),20px 0 0 var(--google-yellow),30px 0 0 var(--google-green);
    margin-right:1.85rem;
}
.sarpras-google-dot.green {
    background:var(--google-green);
    box-shadow:none;
    margin-right:0;
}
.sarpras-google-icon {
    display:inline-flex;
    width:3rem;
    height:3rem;
    align-items:center;
    justify-content:center;
    border-radius:1rem;
    color:#fff;
    background:linear-gradient(135deg, var(--google-blue), #4285f4);
    box-shadow:0 12px 26px rgba(26,115,232,.24);
}
.sarpras-google-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    border:1px solid rgba(218,220,224,.95);
    background:#fff;
    color:#1a73e8;
    box-shadow:0 8px 18px rgba(60,64,67,.08);
}
.sarpras-google-btn:hover {
    background:#f8fbff;
    border-color:rgba(26,115,232,.35);
}
.sarpras-google-btn-primary {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    border:none;
    border-radius:.75rem;
    background:#1a73e8;
    color:#fff;
    font-weight:800;
    box-shadow:0 8px 18px rgba(26,115,232,.28);
    transition:background .15s ease, box-shadow .15s ease;
}
.sarpras-google-btn-primary:hover { background:#1557b0; }
.sarpras-google-btn-success {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    border:none;
    border-radius:.75rem;
    background:#34a853;
    color:#fff;
    font-weight:800;
    box-shadow:0 8px 18px rgba(52,168,83,.25);
}
.sarpras-google-btn-success:hover { background:#2d8e47; }
.sarpras-google-btn-danger {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    border:none;
    border-radius:.75rem;
    background:#ea4335;
    color:#fff;
    font-weight:800;
    box-shadow:0 8px 18px rgba(234,67,53,.22);
}
.sarpras-google-btn-danger:hover { background:#d93025; }
.sarpras-google-btn-ghost {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.45rem;
    border:1px solid #dadce0;
    border-radius:.75rem;
    background:#fff;
    color:#5f6368;
    font-weight:700;
}
.sarpras-google-btn-ghost:hover { background:#f8fafd; }
.sarpras-field-label {
    display:block;
    margin-bottom:.4rem;
    font-size:.68rem;
    font-weight:800;
    letter-spacing:.04em;
    text-transform:uppercase;
    color:#94a3b8;
}
.sarpras-field {
    width:100%;
    border:1px solid #dadce0;
    border-radius:.75rem;
    padding:.65rem .9rem;
    font-size:.875rem;
    background:#fff;
    color:#202124;
}
.sarpras-field:focus {
    outline:none;
    border-color:#1a73e8 !important;
    box-shadow:0 0 0 3px rgba(26,115,232,.14);
}
.dark .sarpras-google-btn-ghost,
.dark .sarpras-field {
    background:#0f172a;
    border-color:#334155;
    color:#e2e8f0;
}
.dark .sarpras-field-label { color:#64748b; }
.dark .sarpras-google-btn-ghost { color:#cbd5e1; }
.dark .sarpras-google-btn-ghost:hover { background:#1e293b; }
.sarpras-google-tabs {
    background:#fff;
    border:1px solid rgba(218,220,224,.9);
    box-shadow:0 8px 24px rgba(60,64,67,.07);
}
.sarpras-tab-link {
    color:#5f6368;
    border:1px solid transparent;
    background:transparent;
}
.sarpras-tab-link:hover {
    background:#f1f5ff;
    color:#1a73e8;
}
.sarpras-tab-link.is-active {
    color:#fff;
    background:#1a73e8;
    border-color:#1a73e8;
    box-shadow:0 8px 18px rgba(26,115,232,.22);
}
.dark .sarpras-google-shell .card {
    border-color:rgba(71,85,105,.82);
    box-shadow:0 12px 28px rgba(0,0,0,.28);
}
.dark .sarpras-google-hero {
    background:
        linear-gradient(90deg, var(--google-blue) 0 24%, var(--google-red) 24% 49%, var(--google-yellow) 49% 74%, var(--google-green) 74% 100%) top/100% 5px no-repeat,
        linear-gradient(135deg, #0f172a 0%, #111827 62%, #172554 100%);
}
.dark .sarpras-google-kicker,
.dark .sarpras-google-btn,
.dark .sarpras-google-tabs {
    background:#111827;
    border-color:#334155;
}
.dark .sarpras-google-kicker { color:#cbd5e1; }
.dark .sarpras-google-btn { color:#93c5fd; }
.dark .sarpras-tab-link { color:#cbd5e1; }
.dark .sarpras-tab-link:hover { background:#1e293b; color:#93c5fd; }
.sarpras-google-shell .bg-white.rounded-lg.shadow,
.sarpras-google-shell .bg-white.rounded-lg.shadow-sm,
.sarpras-google-shell .bg-white.rounded-lg {
    border:1px solid rgba(218,220,224,.86);
    border-radius:1rem !important;
    box-shadow:0 10px 28px rgba(60,64,67,.08) !important;
}
.sarpras-google-shell input:not([type=color]):not([type=file]),
.sarpras-google-shell select,
.sarpras-google-shell textarea {
    border-radius:.75rem !important;
    border-color:#dadce0;
    background:#fff;
    transition:border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
}
.sarpras-google-shell input:not([type=color]):not([type=file]):focus,
.sarpras-google-shell select:focus,
.sarpras-google-shell textarea:focus {
    outline:none;
    border-color:#1a73e8 !important;
    box-shadow:0 0 0 3px rgba(26,115,232,.14);
}
.sarpras-google-shell [class*="bg-slate-900"],
.sarpras-google-shell [class*="hover:bg-slate-800"]:hover {
    background-color:#1a73e8 !important;
}
.sarpras-google-shell [class*="bg-primary"] {
    background-color:#1a73e8 !important;
}
.sarpras-google-shell .text-primary,
.sarpras-google-shell [class*="text-primary"] {
    color:#1a73e8 !important;
}
.sarpras-google-shell .border-primary,
.sarpras-google-shell [class*="border-primary"] {
    border-color:#1a73e8 !important;
}
.dark .sarpras-google-shell .bg-white.rounded-lg.shadow,
.dark .sarpras-google-shell .bg-white.rounded-lg.shadow-sm,
.dark .sarpras-google-shell .bg-white.rounded-lg {
    background:#111827 !important;
    border-color:#334155;
    box-shadow:0 12px 28px rgba(0,0,0,.28) !important;
}
.dark .sarpras-google-shell input:not([type=color]):not([type=file]),
.dark .sarpras-google-shell select,
.dark .sarpras-google-shell textarea {
    background:#0f172a !important;
    border-color:#334155 !important;
    color:#e2e8f0 !important;
}
/* Tab nav sarpras */
.sarpras-tabs::-webkit-scrollbar { height:4px; }
.sarpras-tabs::-webkit-scrollbar-thumb { background:rgb(203 213 225 / .6); border-radius:9999px; }
.dark .sarpras-tabs::-webkit-scrollbar-thumb { background:rgb(71 85 105 / .6); }
</style>
@endpush

@section('content')
<div class="sarpras-scope sarpras-google-shell space-y-5">

    {{-- Header --}}
    <div class="card sarpras-google-hero !rounded-[24px] p-5 sm:p-6">
        <div class="relative z-10 flex items-start justify-between gap-4 flex-wrap">
            <div class="flex items-start gap-3 min-w-0">
                <div class="sarpras-google-icon flex-shrink-0">
                    <i data-lucide="school" class="w-6 h-6"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="sarpras-google-kicker"><span class="sarpras-google-dot"></span> Google Ops Theme</span>
                        <span class="sarpras-google-kicker"><span class="sarpras-google-dot green"></span> Sarpras Sekolah</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 leading-tight">@yield('sarpras_title', 'Sarana & Prasarana')</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 max-w-3xl">@yield('sarpras_subtitle', 'Manajemen aset, gedung interaktif, pengadaan barang, peminjaman, perbaikan, dan mutasi barang.')</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap justify-end">
                @can('sarpras.denah.kelola')
                    @if (request()->routeIs('sarpras.dashboard') || request()->routeIs('sarpras.denah.index') || request()->routeIs('sarpras.denah.show'))
                        <button type="button" id="btn-toggle-tata-letak" onclick="toggleTataLetakMode()"
                           class="sarpras-google-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition">
                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> <span>Tata Letak</span>
                        </button>
                    @endif
                @endcan
                @hasSection('sarpras_actions')
                    @yield('sarpras_actions')
                @endif
            </div>
        </div>
    </div>

    {{-- Navigasi struktur modul Sarpras: operator vs staf (guru) --}}
    @php
        $bolehKelolaSarpras = auth()->user()?->isAdmin() || auth()->user()?->canAccess('manage_sarpras');
        if ($bolehKelolaSarpras) {
            $sarprasNav = [
                ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'sarpras.dashboard', 'active' => ['sarpras.dashboard'], 'can' => 'sarpras.dashboard.lihat'],
                ['label' => 'Kerusakan', 'icon' => 'triangle-alert', 'route' => 'sarpras.kerusakan.index', 'active' => ['sarpras.kerusakan.*'], 'can' => 'sarpras.kerusakan.lihat'],
                ['label' => 'Inventaris', 'icon' => 'package', 'route' => 'sarpras.aset.index', 'active' => ['sarpras.aset.*'], 'can' => 'sarpras.aset.lihat'],
                ['label' => 'Denah Sekolah', 'icon' => 'map', 'route' => 'sarpras.denah.index', 'active' => ['sarpras.denah.*', 'sarpras.ruangan.*'], 'can' => 'sarpras.denah.lihat'],
                ['label' => 'Booking', 'icon' => 'calendar-check', 'route' => 'sarpras.booking.index', 'active' => ['sarpras.booking.*'], 'can' => 'sarpras.peminjaman.lihat'],
                ['label' => 'Peminjaman', 'icon' => 'hand-helping', 'route' => 'sarpras.peminjaman.index', 'active' => ['sarpras.peminjaman.*'], 'can' => 'sarpras.peminjaman.lihat'],
                ['label' => 'Pengadaan', 'icon' => 'shopping-cart', 'route' => 'sarpras.pengadaan.index', 'active' => ['sarpras.pengadaan.*'], 'can' => 'sarpras.pengadaan.lihat'],
                ['label' => 'Perawatan', 'icon' => 'wrench', 'route' => 'sarpras.perbaikan.index', 'active' => ['sarpras.perbaikan.*', 'sarpras.teknisi.*', 'sarpras.jadwal.*'], 'can' => 'sarpras.perbaikan.lihat'],
                ['label' => 'Mutasi/Hapus', 'icon' => 'replace', 'route' => 'sarpras.mutasi.index', 'active' => ['sarpras.mutasi.*', 'sarpras.penghapusan.*'], 'can' => 'sarpras.mutasi.kelola'],
                ['label' => 'Laporan', 'icon' => 'file-bar-chart', 'route' => 'sarpras.laporan.index', 'active' => ['sarpras.laporan.*'], 'can' => 'sarpras.laporan.lihat'],
                ['label' => 'Master Data', 'icon' => 'settings-2', 'route' => 'sarpras.kategori.index', 'active' => ['sarpras.kategori.*', 'sarpras.supplier.*'], 'can' => 'sarpras.pengaturan.kelola'],
            ];
        } else {
            // Staf/guru: aksi harian saja.
            $sarprasNav = [
                ['label' => 'Pinjam Barang', 'icon' => 'hand-helping', 'route' => 'sarpras.peminjaman.index', 'active' => ['sarpras.peminjaman.*'], 'can' => 'sarpras.peminjaman.lihat'],
                ['label' => 'Booking Ruangan', 'icon' => 'calendar-check', 'route' => 'sarpras.booking.index', 'active' => ['sarpras.booking.*'], 'can' => 'sarpras.peminjaman.lihat'],
                ['label' => 'Lapor Kerusakan', 'icon' => 'triangle-alert', 'route' => 'sarpras.kerusakan.index', 'active' => ['sarpras.kerusakan.*'], 'can' => 'sarpras.kerusakan.lihat'],
                ['label' => 'Denah Sekolah', 'icon' => 'map', 'route' => 'sarpras.denah.index', 'active' => ['sarpras.denah.*', 'sarpras.ruangan.*'], 'can' => 'sarpras.denah.lihat'],
            ];
        }
    @endphp
    <div class="card sarpras-google-tabs !rounded-2xl p-2 overflow-x-auto sarpras-tabs">
        <div class="flex items-center gap-1 min-w-max">
            @foreach($sarprasNav as $item)
                @can($item['can'])
                    @php $active = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="sarpras-tab-link inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition {{ $active ? 'is-active' : '' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endcan
            @endforeach
        </div>
    </div>
    {{-- Konten halaman modul --}}
    @yield('sarpras_body')
</div>

@push('scripts')
<script>
// === KONFIRMASI HAPUS (jQuery-confirm wrapper) ===
// confirmAction(form, pesan, type) - dipakai oleh onsubmit form hapus di modul Sarpras.
// Mengembalikan false untuk mencegah submit langsung; form disubmit lewat dialog.
window.confirmAction = function (form, pesan, type) {
    type = type || 'red';
    $.confirm({
        title: 'Konfirmasi',
        content: pesan,
        type: type,
        buttons: {
            ya: {
                text: 'Ya, Hapus',
                btnClass: 'btn-' + (type === 'red' ? 'red' : 'warning'),
                keys: ['enter'],
                action: function () { form.submit(); }
            },
            batal: { text: 'Batal' }
        }
    });
    return false; // cegah submit langsung
};

// confirmDelete(form) - versi sederhana tanpa parameter type.
window.confirmDelete = function (form) {
    return window.confirmAction(form, 'Hapus item ini? Tindakan tidak dapat dibatalkan.', 'red');
};
</script>
@endpush

<script>
// === DRAG & DROP LAYOUT ARRANGEMENT (LocalStorage-backed) ===
let isLayoutEditMode = false;

function applySavedLayouts() {
    document.querySelectorAll('[data-drag-container]').forEach(container => {
        const key = 'sarpras_layout_' + container.getAttribute('data-drag-container');
        const savedOrder = localStorage.getItem(key);
        if (savedOrder) {
            const orderIds = JSON.parse(savedOrder);
            const elements = Array.from(container.children);
            
            // Map elements by a unique identifier (data-drag-id)
            const elementsMap = {};
            elements.forEach(el => {
                const id = el.getAttribute('data-drag-id') || el.innerText.trim();
                elementsMap[id] = el;
            });
            
            // Re-append elements in the saved order
            orderIds.forEach(id => {
                if (elementsMap[id]) {
                    container.appendChild(elementsMap[id]);
                    delete elementsMap[id];
                }
            });
            
            // Append any remaining elements that weren't in the saved order
            Object.values(elementsMap).forEach(el => {
                container.appendChild(el);
            });
        }
    });
}

function toggleTataLetakMode() {
    isLayoutEditMode = !isLayoutEditMode;
    const btn = document.getElementById('btn-toggle-tata-letak');
    const containers = document.querySelectorAll('[data-drag-container]');
    
    if (isLayoutEditMode) {
        if (btn) {
            btn.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> <span>Selesai</span>';
            btn.className = "sarpras-google-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition";
            if (window.lucide) window.lucide.createIcons();
        }
        
        containers.forEach(container => {
            container.classList.add('ring-4', 'ring-emerald-300/40', 'p-2', 'rounded-xl', 'bg-emerald-50/10', 'transition-all');
            Array.from(container.children).forEach(child => {
                child.setAttribute('draggable', 'true');
                child.classList.add('cursor-move', 'opacity-90', 'hover:border-emerald-400');
                
                // Add drag events
                child.addEventListener('dragstart', handleDragStart);
                child.addEventListener('dragover', handleDragOver);
                child.addEventListener('drop', handleDrop);
                child.addEventListener('dragend', handleDragEnd);
            });
        });
    } else {
        if (btn) {
            btn.innerHTML = '<i data-lucide="layout-dashboard" class="w-4 h-4"></i> <span>Tata Letak</span>';
            btn.className = "sarpras-google-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition";
            if (window.lucide) window.lucide.createIcons();
        }
        
        containers.forEach(container => {
            container.classList.remove('ring-4', 'ring-emerald-300/40', 'p-2', 'rounded-xl', 'bg-emerald-50/10');
            
            // Save new order to localStorage
            const key = 'sarpras_layout_' + container.getAttribute('data-drag-container');
            const orderIds = Array.from(container.children).map(child => {
                return child.getAttribute('data-drag-id') || child.innerText.trim();
            });
            localStorage.setItem(key, JSON.stringify(orderIds));
            
            Array.from(container.children).forEach(child => {
                child.removeAttribute('draggable');
                child.classList.remove('cursor-move', 'opacity-90', 'hover:border-emerald-400');
                
                // Remove drag events
                child.removeEventListener('dragstart', handleDragStart);
                child.removeEventListener('dragover', handleDragOver);
                child.removeEventListener('drop', handleDrop);
                child.removeEventListener('dragend', handleDragEnd);
            });
        });
    }
}

let dragSrcEl = null;

function handleDragStart(e) {
    this.style.opacity = '0.4';
    dragSrcEl = this;
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation();
    }
    
    if (dragSrcEl !== this) {
        const container = this.parentNode;
        const children = Array.from(container.children);
        const fromIndex = children.indexOf(dragSrcEl);
        const toIndex = children.indexOf(this);
        
        if (fromIndex < toIndex) {
            container.insertBefore(dragSrcEl, this.nextSibling);
        } else {
            container.insertBefore(dragSrcEl, this);
        }
    }
    return false;
}

function handleDragEnd(e) {
    this.style.opacity = '1';
}

// Run applySavedLayouts on DOMContentLoaded or immediately if DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applySavedLayouts);
} else {
    applySavedLayouts();
}
</script>
@endsection
