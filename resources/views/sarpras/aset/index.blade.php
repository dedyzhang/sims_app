@extends('sarpras.layouts.app')
@section('title', 'Katalog Aset')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Katalog Aset Sekolah</h2>
    @can('sarpras.aset.kelola')
        <div class="flex items-center gap-2">
            <button type="button" id="toggle-import"
                    class="inline-flex items-center gap-1.5 border border-emerald-600 text-emerald-700 px-4 py-2 rounded text-sm hover:bg-emerald-50">
                <i data-lucide="upload" class="w-4 h-4"></i> Import Excel
            </button>
            <a href="{{ route('sarpras.aset.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Aset</a>
        </div>
    @endcan
</div>

@can('sarpras.aset.kelola')
    {{-- Panel import Excel/CSV (tersembunyi by default) --}}
    <div id="panel-import" class="hidden mb-4 bg-white rounded-lg shadow border border-emerald-100 p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="font-semibold text-gray-800">Import Katalog Aset</h3>
                <p class="text-xs text-gray-500 mt-0.5">Unggah file Excel/CSV. Aset dengan <b>kode</b> yang sudah ada akan diperbarui, sisanya ditambahkan.</p>
            </div>
            <a href="{{ route('sarpras.aset.import.template') }}"
               class="shrink-0 inline-flex items-center gap-1.5 text-sm text-emerald-700 font-medium hover:underline">
                <i data-lucide="download" class="w-4 h-4"></i> Unduh template
            </a>
        </div>

        <form method="POST" action="{{ route('sarpras.aset.import') }}" enctype="multipart/form-data"
              class="flex flex-wrap items-center gap-2 text-sm">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="border rounded px-3 py-2 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:rounded file:text-sm">
            <button class="bg-emerald-600 text-white rounded px-4 py-2 hover:bg-emerald-700">Proses Import</button>
        </form>

        <p class="text-xs text-gray-400 mt-2">
            Kolom: <code>kode, nama, kategori, ruangan, merk, kondisi, status, tgl_perolehan, nilai_perolehan, sumber_dana</code>.
            Kategori dicocokkan via nama/kode, ruangan via kode.
        </p>
    </div>

    {{-- Catatan hasil import (baris dilewati / data tak ditemukan) --}}
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

<form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
    <input name="q" value="{{ request('q') }}" placeholder="Cari kode / nama" class="border rounded px-3 py-2">
    <select name="kategori_id" class="border rounded px-3 py-2">
        <option value="">Semua kategori</option>
        @foreach ($kategori as $k)
            <option value="{{ $k->id }}" @selected(request('kategori_id')===$k->id)>{{ $k->nama }}</option>
        @endforeach
    </select>
    <select name="kondisi" class="border rounded px-3 py-2">
        <option value="">Semua kondisi</option>
        @foreach (['baik','rusak_ringan','rusak_berat','hilang'] as $kd)
            <option value="{{ $kd }}" @selected(request('kondisi')===$kd)>{{ ucfirst(str_replace('_',' ',$kd)) }}</option>
        @endforeach
    </select>
    <button class="bg-gray-200 rounded px-4 py-2">Filter</button>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Nama</th><th>Kategori</th><th>Ruangan</th><th>Kondisi</th><th>Nilai</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($aset as $a)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $a->kode }}</td>
                <td>{{ $a->nama }}</td>
                <td>{{ $a->kategori?->nama }}</td>
                <td>{{ $a->ruangan?->kode }}</td>
                <td class="capitalize">{{ str_replace('_',' ',$a->kondisi) }}</td>
                <td>{{ $a->nilai_perolehan_rp }}</td>
                <td class="px-4"><a href="{{ route('sarpras.aset.show', $a) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-4 px-4 text-gray-400">Belum ada aset.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $aset->links() }}</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('toggle-import');
    const panel = document.getElementById('panel-import');
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
