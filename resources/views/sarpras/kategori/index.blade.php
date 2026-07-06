@extends('sarpras.layouts.app')
@section('title', 'Kategori & Lokasi')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Pengaturan Kategori Aset</h2>
    <div class="flex items-center gap-2">
        <button type="button" id="toggle-import-kategori"
                class="inline-flex items-center gap-2 bg-[#eafaf1] text-[#065f46] border border-[#a7f3d0] px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#d1fae5]">
            <i data-lucide="upload" class="w-4 h-4"></i> Import Excel
        </button>
        <a href="{{ route('sarpras.kategori.create') }}" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Kategori
        </a>
    </div>
</div>

{{-- Panel import kategori dari Excel/CSV (tersembunyi by default) --}}
<div id="panel-import-kategori" class="hidden mb-4 bg-white rounded-lg shadow border border-emerald-100 p-5">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="font-semibold text-gray-800">Import Kategori Aset</h3>
            <p class="text-xs text-gray-500 mt-0.5">Unggah file Excel/CSV. Kategori dengan <b>kode</b> (atau <b>nama</b> bila kode kosong) yang sudah ada akan diperbarui, sisanya ditambahkan.</p>
        </div>
        <a href="{{ route('sarpras.kategori.import.template') }}"
           class="shrink-0 inline-flex items-center gap-1.5 text-sm text-emerald-700 font-medium hover:underline">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh template
        </a>
    </div>

    <form method="POST" action="{{ route('sarpras.kategori.import') }}" enctype="multipart/form-data"
          class="flex flex-wrap items-center gap-2 text-sm">
        @csrf
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required
               class="border rounded px-3 py-2 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:rounded file:text-sm">
        <button class="inline-flex items-center gap-1.5 bg-[#eafaf1] text-[#065f46] border border-[#a7f3d0] px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#d1fae5]">
            <i data-lucide="upload" class="w-4 h-4"></i> Proses Import
        </button>
    </form>

    <p class="text-xs text-gray-400 mt-2">
        Kolom: <code>kode, nama, induk, deskripsi</code>. Kolom <b>induk</b> diisi nama/kode kategori induk (boleh merujuk baris lain di file yang sama).
    </p>
</div>

{{-- Catatan hasil import (baris dilewati / induk tak ditemukan) --}}
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
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Nama</th><th>Induk</th><th>Jml Aset</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($kategori as $k)
            <tr class="border-b">
                <td class="py-2 px-4">{{ $k->kode }}</td>
                <td class="font-medium">{{ $k->nama }}</td>
                <td>{{ $k->parent?->nama ?? '-' }}</td>
                <td>{{ $k->aset_count }}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.kategori.edit', $k) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.kategori.destroy', $k) }}" onsubmit="return confirmDelete(this)">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>


@push('scripts')
<script>
(function () {
    const btn = document.getElementById('toggle-import-kategori');
    const panel = document.getElementById('panel-import-kategori');
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
