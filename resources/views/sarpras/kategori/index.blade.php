@extends('sarpras.layouts.app')
@section('title', 'Kategori & Lokasi')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Pengaturan Kategori Aset</h2>
    <a href="{{ route('sarpras.kategori.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Kategori</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Nama</th><th>Induk</th><th>Jml Aset</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($kategori as $k)
            <tr class="border-b">
                <td class="py-2 px-4">{{ $k->kode }}</td>
                <td class="font-medium">{{ $k->nama }}</td>
                <td>{{ $k->parent?->nama ?? '-' }}</td>
                <td>{{ $k->aset_count }}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.kategori.edit', $k) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.kategori.destroy', $k) }}" onsubmit="return confirm('Hapus kategori?')">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-4 px-4 text-gray-400">Belum ada kategori.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $kategori->links() }}</div>
@endsection
