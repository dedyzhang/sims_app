@extends('sarpras.layouts.app')
@section('title', $teknisi->exists ? 'Edit Teknisi' : 'Teknisi Baru')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $teknisi->exists ? 'Edit' : 'Tambah' }} Teknisi</h2>
    <form method="POST" class="space-y-4 text-sm"
          action="{{ $teknisi->exists ? route('sarpras.teknisi.update', $teknisi) : route('sarpras.teknisi.store') }}">
        @csrf @if ($teknisi->exists) @method('PUT') @endif
        <div>
            <label class="block text-gray-700 mb-1">Nama</label>
            <input name="nama" required value="{{ old('nama', $teknisi->nama) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Tipe</label>
            <select name="tipe" class="w-full border rounded px-3 py-2">
                @foreach (['internal','eksternal'] as $tp)
                    <option value="{{ $tp }}" @selected(old('tipe', $teknisi->tipe)===$tp)>{{ ucfirst($tp) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Spesialisasi</label>
            <input name="spesialisasi" value="{{ old('spesialisasi', $teknisi->spesialisasi) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Telepon</label>
            <input name="telepon" value="{{ old('telepon', $teknisi->telepon) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Alamat</label>
            <textarea name="alamat" rows="2" class="w-full border rounded px-3 py-2">{{ old('alamat', $teknisi->alamat) }}</textarea>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.teknisi.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
