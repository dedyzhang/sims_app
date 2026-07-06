@extends('sarpras.layouts.app')
@section('title', 'Penghapusan Aset')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Penghapusan Aset</h2>
    @can('sarpras.penghapusan.ajukan')
        <a href="{{ route('sarpras.penghapusan.create') }}" 
           class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Ajukan Penghapusan
        </a>
    @endcan
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Aset</th><th>Metode</th><th>Pengaju</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($penghapusan as $p)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $p->kode }}</td>
                <td>{{ $p->aset?->nama }}</td>
                <td class="capitalize">{{ $p->metode }}</td>
                <td>{{ $p->pengaju?->name }}</td>
                <td class="capitalize">{{ $p->status }}</td>
                <td class="px-4"><a href="{{ route('sarpras.penghapusan.show', $p) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
