@extends('sarpras.layouts.app')
@section('title', 'Pengajuan Pengadaan')

@section('sarpras_body')
<div class="max-w-3xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Pengajuan Kebutuhan Aset</h2>
    <form method="POST" action="{{ route('sarpras.pengadaan.store') }}" class="space-y-4 text-sm">
        @csrf
        <div>
            <label class="block text-gray-700 mb-1">Judul</label>
            <input name="judul" required value="{{ old('judul') }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Deskripsi</label>
            <textarea name="deskripsi" rows="2" class="w-full border rounded px-3 py-2">{{ old('deskripsi') }}</textarea>
        </div>

        <h3 class="font-semibold text-gray-700">Item Barang</h3>
        <div id="items" class="space-y-2">
            <div class="grid grid-cols-12 gap-2 items-end item-row">
                <input name="item[0][nama_barang]" placeholder="Nama barang" required class="col-span-4 border rounded px-2 py-2">
                <input name="item[0][qty]" type="number" min="1" value="1" placeholder="Qty" required class="col-span-2 border rounded px-2 py-2">
                <input name="item[0][satuan]" placeholder="Satuan" value="unit" class="col-span-2 border rounded px-2 py-2">
                <input name="item[0][estimasi_harga]" type="number" min="0" placeholder="Harga (Rp)" required class="col-span-3 border rounded px-2 py-2">
                <select name="item[0][kategori_id]" class="col-span-1 border rounded px-1 py-2">
                    <option value="">Kat</option>
                    @foreach ($kategori as $k)<option value="{{ $k->id }}">{{ $k->nama }}</option>@endforeach
                </select>
            </div>
        </div>
        <button type="button" onclick="tambahItem()" class="text-blue-600 text-xs hover:underline">+ Tambah item</button>

        <div class="flex gap-2 pt-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Ajukan</button>
            <a href="{{ route('sarpras.pengadaan.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
let idx = 1;
const kategoriOpts = `@foreach ($kategori as $k)<option value="{{ $k->id }}">{{ $k->nama }}</option>@endforeach`;
function tambahItem() {
    const wrap = document.getElementById('items');
    const row = document.createElement('div');
    row.className = 'grid grid-cols-12 gap-2 items-end item-row';
    row.innerHTML =
        `<input name="item[${idx}][nama_barang]" placeholder="Nama barang" required class="col-span-4 border rounded px-2 py-2">`+
        `<input name="item[${idx}][qty]" type="number" min="1" value="1" required class="col-span-2 border rounded px-2 py-2">`+
        `<input name="item[${idx}][satuan]" value="unit" class="col-span-2 border rounded px-2 py-2">`+
        `<input name="item[${idx}][estimasi_harga]" type="number" min="0" placeholder="Harga (Rp)" required class="col-span-3 border rounded px-2 py-2">`+
        `<select name="item[${idx}][kategori_id]" class="col-span-1 border rounded px-1 py-2"><option value="">Kat</option>${kategoriOpts}</select>`;
    wrap.appendChild(row);
    idx++;
}
</script>
@endpush
@endsection
