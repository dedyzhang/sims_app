@extends('sarpras.layouts.app')
@section('title', 'Peminjaman')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Peminjaman &amp; Booking Ruangan</h2>
    @can('sarpras.peminjaman.ajukan')
        <a href="{{ route('sarpras.peminjaman.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Ajukan Peminjaman</a>
    @endcan
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Peminjam</th><th>Ruangan</th><th>Aset</th><th>Periode</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($peminjaman as $p)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $p->kode }}</td>
                <td>{{ $p->peminjam?->name }}</td>
                <td>{{ $p->ruangan ? $p->ruangan->kode . ' — ' . $p->ruangan->nama : '—' }}</td>
                <td>{{ $p->items_count ?? $p->items->count() }} item</td>
                <td class="whitespace-nowrap">
                    {{ optional($p->mulai)->format('d/m/Y H:i') ?? $p->tgl_pinjam?->format('d/m/Y') }}
                    @if ($p->selesai)<span class="text-gray-400">→ {{ $p->selesai->format('d/m/Y H:i') }}</span>@endif
                </td>
                <td class="capitalize">{{ $p->status }}</td>
                <td class="px-4"><a href="{{ route('sarpras.peminjaman.show', $p) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-4 px-4 text-gray-400">Belum ada peminjaman.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $peminjaman->links() }}</div>
@endsection
