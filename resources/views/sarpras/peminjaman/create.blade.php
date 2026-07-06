@extends('sarpras.layouts.app')
@section('title', 'Ajukan Peminjaman')

@section('sarpras_body')
<div class="max-w-2xl bg-white rounded-lg shadow p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-1">Form Peminjaman</h2>
    <p class="text-xs text-gray-400 mb-4">Satu pengajuan dapat memuat <b>ruangan</b> dan/atau <b>aset</b> sekaligus, melalui satu alur persetujuan.</p>

    <form method="POST" action="{{ route('sarpras.peminjaman.store') }}" class="space-y-4 text-sm">
        @csrf
        <div>
            <label class="block text-gray-700 mb-1">Keperluan</label>
            <textarea name="keperluan" rows="2" required class="w-full border rounded px-3 py-2">{{ old('keperluan') }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-gray-700 mb-1">Mulai</label>
                <input name="mulai" type="datetime-local" required value="{{ old('mulai') }}" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Selesai</label>
                <input name="selesai" type="datetime-local" required value="{{ old('selesai') }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        {{-- Ruangan (opsional) --}}
        <div class="rounded-lg border border-gray-200 p-3">
            <label class="block text-gray-700 mb-1 font-medium">Ruangan <span class="text-gray-400 font-normal">(opsional)</span></label>
            <select name="ruangan_id" class="w-full border rounded px-3 py-2">
                <option value="">— tidak booking ruangan —</option>
                @foreach ($ruangan as $r)
                    <option value="{{ $r->id }}" @selected(old('ruangan_id') === $r->id)>{{ $r->kode }} — {{ $r->nama }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Bila dipilih, sistem menolak jika bentrok dengan pemesanan lain pada rentang waktu yang sama.</p>
        </div>

        {{-- Aset (opsional) --}}
        <div class="rounded-lg border border-gray-200 p-3">
            <label class="block text-gray-700 mb-1 font-medium">Aset <span class="text-gray-400 font-normal">(opsional)</span></label>
            <div class="border rounded p-3 max-h-60 overflow-y-auto space-y-2">
                @foreach($aset as $a)
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="aset_id[]" value="{{ $a->id }}"
                               @checked(collect(old('aset_id', []))->contains($a->id))>
                        <span class="flex-1">{{ $a->kode }} — {{ $a->nama }}</span>
                        <input type="number" name="qty[{{ $a->id }}]" value="{{ old('qty.' . $a->id, 1) }}" min="1" class="w-20 border rounded px-2 py-1">
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-1">Centang aset yang dipinjam beserta jumlahnya.</p>
        </div>

        <p class="text-xs text-gray-500">Minimal pilih <b>ruangan</b> atau <b>satu aset</b>.</p>

        <div class="flex gap-2">
            <button class="bg-slate-900 text-white rounded px-4 py-2">Ajukan</button>
            <a href="{{ route('sarpras.peminjaman.index') }}" class="px-4 py-2 border rounded">Batal</a>
        </div>
    </form>
</div>
@endsection
