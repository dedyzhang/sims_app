@extends('sarpras.layouts.app')
@section('title', 'Lapor Kerusakan')

@section('sarpras_body')
<div class="max-w-xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Form Lapor Kerusakan</h2>
    <form method="POST" action="{{ route('sarpras.kerusakan.store') }}" enctype="multipart/form-data" class="space-y-4 text-sm">
        @csrf
        <div>
            <label class="block text-gray-700 mb-1">Aset (opsional)</label>
            <select name="aset_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih aset —</option>
                @foreach ($aset as $a)
                    <option value="{{ $a->id }}" @selected(old('aset_id')===$a->id)>{{ $a->kode }} — {{ $a->nama }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Ruangan (opsional)</label>
            <select name="ruangan_id" class="w-full border rounded px-3 py-2">
                <option value="">— pilih ruangan —</option>
                @foreach ($ruangan as $r)
                    <option value="{{ $r->id }}" @selected(old('ruangan_id', $ruanganTerpilih)===$r->id)>{{ $r->kode }} — {{ $r->nama }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Pilih minimal salah satu: aset atau ruangan.</p>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Tingkat Urgensi</label>
            <select name="urgensi" required class="w-full border rounded px-3 py-2">
                @foreach (['rendah','sedang','tinggi','darurat'] as $u)
                    <option value="{{ $u }}" @selected(old('urgensi','sedang')===$u)>{{ ucfirst($u) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Deskripsi Kerusakan</label>
            <textarea name="deskripsi" rows="3" required class="w-full border rounded px-3 py-2">{{ old('deskripsi') }}</textarea>
        </div>
        <div>
            @include('sarpras.partials.foto-picker', [
                'name' => 'foto[]',
                'label' => 'Foto bagian yang rusak (1–4, otomatis dikompres ≤2MB)',
                'max' => 4,
                'live' => true,
            ])
        </div>
        <div class="flex gap-2">
            <button class="bg-red-600 text-white rounded px-4 py-2">Kirim ke Waka Sarpras</button>
            <a href="{{ route('sarpras.kerusakan.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
