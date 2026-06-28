@extends('sarpras.layouts.app')
@section('title', 'Pengadaan')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Pengadaan Barang</h2>
    @can('sarpras.pengadaan.ajukan')
        <a href="{{ route('sarpras.pengadaan.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Pengajuan</a>
    @endcan
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Judul</th><th>Pengaju</th><th>Total Estimasi</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($pengadaan as $p)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $p->kode }}</td>
                <td>{{ $p->judul }}</td>
                <td>{{ $p->pengaju?->name }}</td>
                <td>{{ $p->total_estimasi_rp }}</td>
                <td class="capitalize">{{ $p->status }}</td>
                <td class="px-4"><a href="{{ route('sarpras.pengadaan.show', $p) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-4 px-4 text-gray-400">Belum ada pengajuan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $pengadaan->links() }}</div>
@endsection
