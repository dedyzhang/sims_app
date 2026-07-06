@extends('sarpras.layouts.app')
@section('title', 'Pengadaan')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Pengadaan Barang</h2>
    @can('sarpras.pengadaan.ajukan')
        <a href="{{ route('sarpras.pengadaan.create') }}" 
           class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Pengajuan
        </a>
    @endcan
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Judul</th><th>Pengaju</th><th>Total Estimasi</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($pengadaan as $p)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $p->kode }}</td>
                <td>{{ $p->judul }}</td>
                <td>{{ $p->pengaju?->name }}</td>
                <td>{{ $p->total_estimasi_rp }}</td>
                <td class="capitalize">{{ $p->status }}</td>
                <td class="px-4"><a href="{{ route('sarpras.pengadaan.show', $p) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
