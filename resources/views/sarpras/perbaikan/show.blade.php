@extends('sarpras.layouts.app')
@section('title', 'Perbaikan ' . $perbaikan->kode)

@section('sarpras_body')
<div class="max-w-2xl bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-start mb-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ $perbaikan->kode }}</h2>
            <p class="text-sm text-gray-500">{{ $perbaikan->aset?->nama ?? 'Tanpa aset' }}</p>
        </div>
        <span class="px-3 py-1 rounded text-xs capitalize bg-gray-100">{{ $perbaikan->status }}</span>
    </div>
    <p class="text-sm text-gray-700 mb-3">{{ $perbaikan->deskripsi }}</p>
    <dl class="text-sm grid grid-cols-2 gap-2 mb-4">
        <dt class="text-gray-500">Teknisi</dt><dd>{{ $perbaikan->teknisi?->nama ?? '-' }}</dd>
        <dt class="text-gray-500">Biaya</dt><dd>{{ $perbaikan->biaya_rp }}</dd>
        <dt class="text-gray-500">Mulai</dt><dd>{{ optional($perbaikan->tgl_mulai)->format('d/m/Y') ?? '-' }}</dd>
        <dt class="text-gray-500">Selesai</dt><dd>{{ optional($perbaikan->tgl_selesai)->format('d/m/Y') ?? '-' }}</dd>
    </dl>

    @can('sarpras.perbaikan.kelola')
        <h3 class="font-semibold text-gray-700 mb-2 text-sm">Perbarui</h3>
        <form method="POST" action="{{ route('sarpras.perbaikan.update', $perbaikan) }}" class="space-y-3 text-sm">
            @csrf @method('PUT')
            <input type="hidden" name="deskripsi" value="{{ $perbaikan->deskripsi }}">
            <input type="hidden" name="aset_id" value="{{ $perbaikan->aset_id }}">
            <input type="hidden" name="teknisi_id" value="{{ $perbaikan->teknisi_id }}">
            <div class="grid grid-cols-2 gap-3">
                <select name="status" class="border rounded px-3 py-2">
                    @foreach (['antri','dikerjakan','selesai','batal'] as $s)
                        <option value="{{ $s }}" @selected($perbaikan->status===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <input name="biaya" type="number" min="0" value="{{ $perbaikan->biaya }}" class="border rounded px-3 py-2">
                <input name="tgl_mulai" type="date" value="{{ optional($perbaikan->tgl_mulai)->format('Y-m-d') }}" class="border rounded px-3 py-2">
                <input name="tgl_selesai" type="date" value="{{ optional($perbaikan->tgl_selesai)->format('Y-m-d') }}" class="border rounded px-3 py-2">
            </div>
            <textarea name="catatan" rows="2" placeholder="Catatan" class="w-full border rounded px-3 py-2">{{ $perbaikan->catatan }}</textarea>
            <button class="bg-slate-900 text-white rounded px-4 py-2">Simpan</button>
        </form>
        <p class="text-xs text-gray-400 mt-2">Status "selesai" otomatis mengembalikan aset ke kondisi baik & status aktif.</p>
    @endcan
</div>
@endsection
