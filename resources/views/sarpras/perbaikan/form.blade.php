@extends('sarpras.layouts.app')
@section('title', 'Order Perbaikan Baru')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Order Perbaikan</h2>
    <form method="POST" action="{{ route('sarpras.perbaikan.store') }}" class="space-y-4 text-sm">
        @csrf
        <div>
            <label class="block text-gray-700 mb-1">Aset</label>
            <select name="aset_id" class="w-full border rounded px-3 py-2">
                <option value="">—</option>
                @foreach ($aset as $a)<option value="{{ $a->id }}" @selected(old('aset_id')===$a->id)>{{ $a->kode }} — {{ $a->nama }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Dari Laporan (opsional)</label>
            <select name="laporan_id" class="w-full border rounded px-3 py-2">
                <option value="">—</option>
                @foreach ($laporan as $l)<option value="{{ $l->id }}">{{ $l->kode }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Teknisi</label>
            <select name="teknisi_id" class="w-full border rounded px-3 py-2">
                <option value="">—</option>
                @foreach ($teknisi as $t)<option value="{{ $t->id }}" @selected(old('teknisi_id')===$t->id)>{{ $t->nama }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Deskripsi</label>
            <textarea name="deskripsi" rows="3" required class="w-full border rounded px-3 py-2">{{ old('deskripsi') }}</textarea>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div>
                <label class="block text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full border rounded px-3 py-2">
                    @foreach (['antri','dikerjakan','selesai','batal'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Biaya (Rp)</label>
                <input name="biaya" type="number" min="0" value="0" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Tgl Mulai</label>
                <input name="tgl_mulai" type="date" class="w-full border rounded px-3 py-2">
            </div>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
            <a href="{{ route('sarpras.perbaikan.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
