@extends('sarpras.layouts.app')
@section('title', $denah->exists ? 'Edit Denah' : 'Denah Baru')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $denah->exists ? 'Edit Denah' : 'Denah Baru' }}</h2>
    <form method="POST" enctype="multipart/form-data" class="space-y-4 text-sm"
          action="{{ $denah->exists ? route('sarpras.denah.update', $denah) : route('sarpras.denah.store') }}">
        @csrf
        @if ($denah->exists) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-gray-700 mb-1">Gedung</label>
                <input name="gedung" value="{{ old('gedung', $denah->gedung) }}" placeholder="mis. Gedung A" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Lantai</label>
                <input name="lantai" value="{{ old('lantai', $denah->lantai) }}" placeholder="mis. 1, 2, Dasar" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Nama Denah <span class="text-gray-400 font-normal">(opsional)</span></label>
            <input name="nama" value="{{ old('nama', $denah->nama) }}" placeholder="Otomatis dari Gedung + Lantai bila dikosongkan" class="w-full border rounded px-3 py-2">
            <p class="text-xs text-gray-400 mt-1">Kosongkan untuk nama otomatis, mis. “Gedung A - Lantai 2”.</p>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Deskripsi</label>
            <textarea name="deskripsi" rows="2" class="w-full border rounded px-3 py-2">{{ old('deskripsi', $denah->deskripsi) }}</textarea>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Gambar Denah (jpg/png/webp, maks 10MB — otomatis dikompres ≤2MB)</label>
            <input name="gambar" type="file" accept="image/*" class="w-full text-xs">
            @if ($denah->gambar_path)
                <img src="{{ Storage::url($denah->gambar_path) }}" class="mt-2 h-24 rounded border object-contain">
            @endif
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.denah.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
