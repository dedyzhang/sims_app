{{--
    Layout modul Sarpras — terintegrasi ke shell SIMS.
    Memakai layout utama SIMS (sidebar, topbar, tema, font, Tailwind CDN).

    Slot yang bisa diisi view:
      @section('title')            → judul tab browser (dipakai layouts.app)
      @section('sarpras_title')    → judul besar header (default "Sarana & Prasarana")
      @section('sarpras_subtitle') → subjudul header
      @section('sarpras_actions')  → tombol aksi kanan header
      @section('sarpras_body')     → konten halaman
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

/* Tab nav sarpras */
.sarpras-tabs::-webkit-scrollbar { height:4px; }
.sarpras-tabs::-webkit-scrollbar-thumb { background:rgb(203 213 225 / .6); border-radius:9999px; }
.dark .sarpras-tabs::-webkit-scrollbar-thumb { background:rgb(71 85 105 / .6); }
</style>
@endpush

@section('content')
<div class="sarpras-scope space-y-5">

    {{-- Header --}}
    <div class="flex items-end justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">@yield('sarpras_title', 'Sarana & Prasarana')</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">@yield('sarpras_subtitle', 'Manajemen aset, gedung interaktif, pengadaan barang, peminjaman, perbaikan, dan mutasi barang.')</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @can('sarpras.denah.kelola')
                @if (request()->routeIs('sarpras.dashboard') || request()->routeIs('sarpras.denah.index') || request()->routeIs('sarpras.denah.show'))
                    <button type="button" id="btn-toggle-tata-letak" onclick="toggleTataLetakMode()"
                       class="btn-accent inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-sm">
                        <i data-lucide="layout-dashboard" class="w-4 h-4"></i> <span>Tata Letak</span>
                    </button>
                @endif
            @endcan
            @hasSection('sarpras_actions')
                @yield('sarpras_actions')
            @endif
        </div>
    </div>



    {{-- Konten halaman modul --}}
    @yield('sarpras_body')
</div>

@push('scripts')
<script>
// === KONFIRMASI HAPUS (jQuery-confirm wrapper) ===
// confirmAction(form, pesan, type) — dipakai oleh onsubmit form hapus di modul Sarpras.
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

// confirmDelete(form) — versi sederhana tanpa parameter type.
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
            btn.className = "btn-accent inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-sm";
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
            btn.className = "btn-accent inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition shadow-sm";
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
