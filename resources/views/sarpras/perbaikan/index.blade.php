@extends('sarpras.layouts.app')
@section('title', 'Perbaikan / Service')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Manajemen Perbaikan</h2>
    @can('sarpras.perbaikan.kelola')
        <a href="{{ route('sarpras.perbaikan.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Order Perbaikan</a>
    @endcan
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Aset</th><th>Teknisi</th><th>Status</th><th>Biaya</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($perbaikan as $p)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $p->kode }}</td>
                <td>{{ $p->aset?->nama ?? '-' }}</td>
                <td>{{ $p->teknisi?->nama ?? '-' }}</td>
                <td class="capitalize">{{ $p->status }}</td>
                <td>{{ $p->biaya_rp }}</td>
                <td class="px-4"><a href="{{ route('sarpras.perbaikan.show', $p) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-4 px-4 text-gray-400">Belum ada order perbaikan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $perbaikan->links() }}</div>
@endsection
