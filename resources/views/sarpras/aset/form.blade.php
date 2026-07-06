@extends('sarpras.layouts.app')
@section('title', $aset->exists ? 'Edit Aset' : 'Aset Baru')

@section('sarpras_body')
<div class="max-w-2xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $aset->exists ? 'Edit' : 'Tambah' }} Aset</h2>
    <form method="POST" enctype="multipart/form-data" class="space-y-4 text-sm"
          action="{{ $aset->exists ? route('sarpras.aset.update', $aset) : route('sarpras.aset.store') }}">
        @csrf @if ($aset->exists) @method('PUT') @endif
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-gray-700 mb-1">Kode</label>
                <input name="kode" required value="{{ old('kode', $aset->kode) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Nama</label>
                <input name="nama" required value="{{ old('nama', $aset->nama) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Kategori</label>
                <select name="kategori_id" class="w-full border rounded px-3 py-2">
                    <option value="">—</option>
                    @foreach ($kategori as $k)
                        <option value="{{ $k->id }}" @selected(old('kategori_id', $aset->kategori_id)===$k->id)>{{ $k->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Ruangan</label>
                <select name="ruangan_id" class="w-full border rounded px-3 py-2">
                    <option value="">—</option>
                    @foreach ($ruangan as $r)
                        <option value="{{ $r->id }}" @selected(old('ruangan_id', $aset->ruangan_id)===$r->id)>{{ $r->kode }} — {{ $r->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Merk</label>
                <input name="merk" value="{{ old('merk', $aset->merk) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Sumber Dana</label>
                <input name="sumber_dana" value="{{ old('sumber_dana', $aset->sumber_dana) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Kondisi</label>
                <select name="kondisi" class="w-full border rounded px-3 py-2">
                    @foreach (['baik','rusak_ringan','rusak_berat','hilang'] as $kd)
                        <option value="{{ $kd }}" @selected(old('kondisi', $aset->kondisi)===$kd)>{{ ucfirst(str_replace('_',' ',$kd)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border rounded px-3 py-2">
                    @foreach (['aktif','dipinjam','perbaikan','dihapus','dimutasi'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $aset->status)===$st)>{{ ucfirst($st) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Tgl Perolehan</label>
                <input name="tgl_perolehan" type="date" value="{{ old('tgl_perolehan', optional($aset->tgl_perolehan)->format('Y-m-d')) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Nilai Perolehan (Rp, angka bulat)</label>
                <input name="nilai_perolehan" type="number" min="0" step="1" required value="{{ old('nilai_perolehan', $aset->nilai_perolehan) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Masa Manfaat (tahun)</label>
                <input name="masa_manfaat_tahun" type="number" min="1" max="50" step="1" value="{{ old('masa_manfaat_tahun', $aset->masa_manfaat_tahun ?? 4) }}" class="w-full border rounded px-3 py-2">
                <p class="text-xs text-gray-400 mt-1">Untuk hitung penyusutan &amp; Nilai Buku (garis lurus).</p>
            </div>
        </div>

        {{-- Spesifikasi key-value dinamis --}}
        <div>
            <label class="block text-gray-700 mb-1">Spesifikasi (key — value)</label>
            <div id="spek-list" class="space-y-2">
                @php $spek = old('spek_key') ? array_combine(old('spek_key', []), old('spek_val', [])) : ($aset->spesifikasi ?? []); @endphp
                @foreach($spek as $k => $v)
                    <div class="flex gap-2">
                        <input name="spek_key[]" value="{{ $k }}" placeholder="key" class="w-1/2 border rounded px-3 py-2">
                        <input name="spek_val[]" value="{{ $v }}" placeholder="value" class="w-1/2 border rounded px-3 py-2">
                    </div>
                @endforeach
            </div>
            <button type="button" onclick="tambahSpek()" class="mt-2 text-blue-600 text-xs hover:underline">+ Tambah baris</button>
        </div>

        <div>
            <label class="block text-gray-700 mb-1">Foto Aset (maks 10MB — dikompres ≤2MB)</label>
            <input name="foto" type="file" accept="image/*" class="w-full text-xs">
            @if ($aset->foto_path)
                <img src="{{ Storage::url($aset->foto_path) }}" class="mt-2 h-24 rounded border object-cover">
            @endif
        </div>

        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.aset.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function tambahSpek() {
    const wrap = document.getElementById('spek-list');
    const row = document.createElement('div');
    row.className = 'flex gap-2';
    row.innerHTML = '<input name="spek_key[]" placeholder="key" class="w-1/2 border rounded px-3 py-2">' +
                    '<input name="spek_val[]" placeholder="value" class="w-1/2 border rounded px-3 py-2">';
    wrap.appendChild(row);
}
</script>
@endpush
@endsection
