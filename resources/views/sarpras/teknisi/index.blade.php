@extends('sarpras.layouts.app')
@section('title', 'Teknisi')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Manajemen Teknisi</h2>
    <a href="{{ route('sarpras.teknisi.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Teknisi</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Nama</th><th>Tipe</th><th>Spesialisasi</th><th>Telepon</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($teknisi as $t)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $t->nama }}</td>
                <td class="capitalize">{{ $t->tipe }}</td>
                <td>{{ $t->spesialisasi }}</td><td>{{ $t->telepon }}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.teknisi.edit', $t) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.teknisi.destroy', $t) }}" onsubmit="return confirm('Hapus teknisi?')">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-4 px-4 text-gray-400">Belum ada teknisi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $teknisi->links() }}</div>
@endsection
