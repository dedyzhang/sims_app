@extends('sarpras.layouts.app')
@section('title', $jadwal->exists ? 'Edit Jadwal' : 'Jadwal Baru')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $jadwal->exists ? 'Edit' : 'Tambah' }} Jadwal Pemeliharaan</h2>
    <form method="POST" class="space-y-4 text-sm"
          action="{{ $jadwal->exists ? route('sarpras.jadwal.update', $jadwal) : route('sarpras.jadwal.store') }}">
        @csrf @if ($jadwal->exists) @method('PUT') @endif
        <div>
            <label class="block text-gray-700 mb-1">Nama Jadwal</label>
            <input name="nama" required value="{{ old('nama', $jadwal->nama) }}" class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Aset (opsional)</label>
            <select name="aset_id" class="w-full border rounded px-3 py-2">
                <option value="">— umum —</option>
                @foreach ($aset as $a)
                    <option value="{{ $a->id }}" @selected(old('aset_id', $jadwal->aset_id)===$a->id)>{{ $a->kode }} — {{ $a->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-gray-700 mb-1">Interval (hari)</label>
                <input name="interval_hari" type="number" min="1" required value="{{ old('interval_hari', $jadwal->interval_hari) }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Tanggal Berikutnya</label>
                <input name="tgl_berikutnya" type="date" required value="{{ old('tgl_berikutnya', optional($jadwal->tgl_berikutnya)->format('Y-m-d')) }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="aktif" value="1" @checked(old('aktif', $jadwal->aktif ?? true))> Aktif
        </label>
        <div>
            <label class="block text-gray-700 mb-1">Catatan</label>
            <textarea name="catatan" rows="2" class="w-full border rounded px-3 py-2">{{ old('catatan', $jadwal->catatan) }}</textarea>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.jadwal.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
