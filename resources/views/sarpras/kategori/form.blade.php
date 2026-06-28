@extends('sarpras.layouts.app')
@section('title', $kategori->exists ? 'Edit Kategori' : 'Kategori Baru')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $kategori->exists ? 'Edit' : 'Tambah' }} Kategori</h2>
    <form method="POST" class="space-y-4 text-sm"
          action="{{ $kategori->exists ? route('sarpras.kategori.update', $kategori) : route('sarpras.kategori.store') }}">
        @csrf @if ($kategori->exists) @method('PUT') @endif
        <div>
            <label class="block text-gray-700 mb-1">Nama</label>
            <input name="nama" required value="{{ old('nama', $kategori->nama) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Kode</label>
            <input name="kode" value="{{ old('kode', $kategori->kode) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Kategori Induk (opsional)</label>
            <select name="parent_id" class="w-full border rounded px-3 py-2">
                <option value="">— tidak ada —</option>
                @foreach ($semua as $s)
                    <option value="{{ $s->id }}" @selected(old('parent_id', $kategori->parent_id)===$s->id)>{{ $s->nama }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Deskripsi</label>
            <textarea name="deskripsi" rows="2" class="w-full border rounded px-3 py-2">{{ old('deskripsi', $kategori->deskripsi) }}</textarea>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.kategori.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
