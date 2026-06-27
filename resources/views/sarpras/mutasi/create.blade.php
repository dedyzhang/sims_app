@extends('sarpras.layouts.app')
@section('title', 'Mutasi Aset')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Mutasi / Perpindahan Aset</h2>
    <form method="POST" action="{{ route('sarpras.mutasi.store') }}" class="space-y-4 text-sm">
        @csrf
        <div>
            <label class="block text-gray-700 mb-1">Aset</label>
            <select name="aset_id" required class="w-full border rounded px-3 py-2">
                <option value="">— pilih —</option>
                @foreach ($aset as $a)<option value="{{ $a->id }}" @selected(old('aset_id')===$a->id)>{{ $a->kode }} — {{ $a->nama }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Ruangan Asal (opsional, default posisi saat ini)</label>
            <select name="ruangan_asal_id" class="w-full border rounded px-3 py-2">
                <option value="">— otomatis —</option>
                @foreach ($ruangan as $r)<option value="{{ $r->id }}">{{ $r->kode }} — {{ $r->nama }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Ruangan Tujuan</label>
            <select name="ruangan_tujuan_id" required class="w-full border rounded px-3 py-2">
                <option value="">— pilih —</option>
                @foreach ($ruangan as $r)<option value="{{ $r->id }}">{{ $r->kode }} — {{ $r->nama }}</option>@endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-gray-700 mb-1">Tanggal Mutasi</label>
                <input name="tgl_mutasi" type="date" required value="{{ old('tgl_mutasi', now()->format('Y-m-d')) }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Alasan</label>
            <textarea name="alasan" rows="2" class="w-full border rounded px-3 py-2">{{ old('alasan') }}</textarea>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan Mutasi</button>
            <a href="{{ route('sarpras.mutasi.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
