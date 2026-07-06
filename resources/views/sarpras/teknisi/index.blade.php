@extends('sarpras.layouts.app')
@section('title', 'Teknisi')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Manajemen Teknisi</h2>
    <a href="{{ route('sarpras.teknisi.create') }}" 
       class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
        <i data-lucide="plus" class="w-4 h-4"></i> Teknisi
    </a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Nama</th><th>Tipe</th><th>Spesialisasi</th><th>Telepon</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($teknisi as $t)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $t->nama }}</td>
                <td class="capitalize">{{ $t->tipe }}</td>
                <td>{{ $t->spesialisasi }}</td><td>{{ $t->telepon }}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.teknisi.edit', $t) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.teknisi.destroy', $t) }}" onsubmit="return confirmDelete(this)">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
