@extends('sarpras.layouts.app')
@section('title', 'Penghapusan Aset')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Penghapusan Aset</h2>
    @can('sarpras.penghapusan.ajukan')
        <a href="{{ route('sarpras.penghapusan.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Ajukan Penghapusan</a>
    @endcan
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Aset</th><th>Metode</th><th>Pengaju</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($penghapusan as $p)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $p->kode }}</td>
                <td>{{ $p->aset?->nama }}</td>
                <td class="capitalize">{{ $p->metode }}</td>
                <td>{{ $p->pengaju?->name }}</td>
                <td class="capitalize">{{ $p->status }}</td>
                <td class="px-4"><a href="{{ route('sarpras.penghapusan.show', $p) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-4 px-4 text-gray-400">Belum ada pengajuan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $penghapusan->links() }}</div>
@endsection
