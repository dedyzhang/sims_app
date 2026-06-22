@extends('sarpras.layouts.app')
@section('title', 'Katalog Aset')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Katalog Aset Sekolah</h2>
    @can('sarpras.aset.kelola')
        <a href="{{ route('sarpras.aset.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Aset</a>
    @endcan
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
    <input name="q" value="{{ request('q') }}" placeholder="Cari kode / nama" class="border rounded px-3 py-2">
    <select name="kategori_id" class="border rounded px-3 py-2">
        <option value="">Semua kategori</option>
        @foreach ($kategori as $k)
            <option value="{{ $k->id }}" @selected(request('kategori_id')===$k->id)>{{ $k->nama }}</option>
        @endforeach
    </select>
    <select name="kondisi" class="border rounded px-3 py-2">
        <option value="">Semua kondisi</option>
        @foreach (['baik','rusak_ringan','rusak_berat','hilang'] as $kd)
            <option value="{{ $kd }}" @selected(request('kondisi')===$kd)>{{ ucfirst(str_replace('_',' ',$kd)) }}</option>
        @endforeach
    </select>
    <button class="bg-gray-200 rounded px-4 py-2">Filter</button>
</form>

<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Kode</th><th>Nama</th><th>Kategori</th><th>Ruangan</th><th>Kondisi</th><th>Nilai</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($aset as $a)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $a->kode }}</td>
                <td>{{ $a->nama }}</td>
                <td>{{ $a->kategori?->nama }}</td>
                <td>{{ $a->ruangan?->kode }}</td>
                <td class="capitalize">{{ str_replace('_',' ',$a->kondisi) }}</td>
                <td>{{ $a->nilai_perolehan_rp }}</td>
                <td class="px-4"><a href="{{ route('sarpras.aset.show', $a) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-4 px-4 text-gray-400">Belum ada aset.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $aset->links() }}</div>
@endsection
