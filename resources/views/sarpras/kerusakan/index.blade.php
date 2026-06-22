@extends('sarpras.layouts.app')
@section('title', 'Pelaporan Kerusakan')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Laporan Kerusakan</h2>
    @can('sarpras.kerusakan.lapor')
        <a href="{{ route('sarpras.kerusakan.create') }}" class="bg-red-600 text-white px-4 py-2 rounded text-sm">+ Lapor Kerusakan</a>
    @endcan
</div>

<form method="GET" class="mb-4 flex gap-2 text-sm">
    <select name="status" class="border rounded px-3 py-2" onchange="this.form.submit()">
        <option value="">Semua status</option>
        @foreach (['dilaporkan','diterima','ditolak','selesai'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Objek</th><th>Pelapor</th><th>Urgensi</th><th>Status</th><th>Waktu</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($laporan as $l)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $l->kode }}</td>
                <td>{{ $l->aset?->nama ?? $l->ruangan?->kode ?? '-' }}</td>
                <td>{{ $l->pelapor?->name }}</td>
                <td>
                    @php $warna = ['darurat'=>'bg-red-100 text-red-700','tinggi'=>'bg-orange-100 text-orange-700','sedang'=>'bg-amber-100 text-amber-700','rendah'=>'bg-gray-100 text-gray-700'][$l->urgensi] ?? ''; @endphp
                    <span class="px-2 py-0.5 rounded text-xs {{ $warna }} capitalize">{{ $l->urgensi }}</span>
                </td>
                <td class="capitalize">{{ $l->status }}</td>
                <td>{{ $l->created_at->format('d/m/Y H:i') }}</td>
                <td class="px-4"><a href="{{ route('sarpras.kerusakan.show', $l) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-4 px-4 text-gray-400">Belum ada laporan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $laporan->links() }}</div>
@endsection
