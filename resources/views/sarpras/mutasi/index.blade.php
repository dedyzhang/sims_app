@extends('sarpras.layouts.app')
@section('title', 'Mutasi Aset')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Mutasi & Perpindahan Aset</h2>
    <div class="flex gap-2">
        <a href="{{ route('sarpras.laporan.mutasi.excel') }}" class="border px-4 py-2 rounded text-sm">⬇ Excel</a>
        <a href="{{ route('sarpras.mutasi.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Mutasi</a>
    </div>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Tanggal</th><th>Aset</th><th>Asal</th><th>Tujuan</th><th>Alasan</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($mutasi as $m)
            <tr class="border-b">
                <td class="py-2 px-4">{{ $m->tgl_mutasi->format('d/m/Y') }}</td>
                <td>{{ $m->aset?->nama }}</td>
                <td>{{ $m->ruanganAsal?->kode ?? '-' }}</td>
                <td>{{ $m->ruanganTujuan?->kode ?? '-' }}</td>
                <td>{{ $m->alasan }}</td>
                <td class="px-4"><a href="{{ route('sarpras.mutasi.berita', $m) }}" target="_blank" class="text-blue-600 hover:underline">Berita Acara</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-4 px-4 text-gray-400">Belum ada mutasi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $mutasi->links() }}</div>
@endsection
