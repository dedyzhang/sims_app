@extends('sarpras.layouts.app')
@section('title', 'Supplier')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Manajemen Supplier</h2>
    <a href="{{ route('sarpras.supplier.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Supplier</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Nama</th><th>Kontak</th><th>Telepon</th><th>NPWP</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($supplier as $s)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $s->nama }}</td>
                <td>{{ $s->kontak }}</td><td>{{ $s->telepon }}</td><td>{{ $s->npwp }}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.supplier.edit', $s) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.supplier.destroy', $s) }}" onsubmit="return confirm('Hapus supplier?')">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-4 px-4 text-gray-400">Belum ada supplier.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $supplier->links() }}</div>
@endsection
