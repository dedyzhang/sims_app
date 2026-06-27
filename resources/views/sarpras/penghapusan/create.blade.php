@extends('sarpras.layouts.app')
@section('title', 'Ajukan Penghapusan')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Pengajuan Penghapusan Aset</h2>
    <form method="POST" action="{{ route('sarpras.penghapusan.store') }}" class="space-y-4 text-sm">
        @csrf
        <div>
            <label class="block text-gray-700 mb-1">Aset</label>
            <select name="aset_id" required class="w-full border rounded px-3 py-2">
                <option value="">— pilih —</option>
                @foreach ($aset as $a)<option value="{{ $a->id }}" @selected(old('aset_id')===$a->id)>{{ $a->kode }} — {{ $a->nama }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Metode</label>
            <select name="metode" class="w-full border rounded px-3 py-2">
                @foreach (['musnah','jual','hibah','lainnya'] as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Alasan</label>
            <textarea name="alasan" rows="3" required class="w-full border rounded px-3 py-2">{{ old('alasan') }}</textarea>
        </div>
        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Ajukan</button>
            <a href="{{ route('sarpras.penghapusan.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
