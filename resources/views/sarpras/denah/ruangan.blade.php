@extends('sarpras.layouts.app')
@section('title', 'Ruangan ' . $ruangan->kode)

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ $ruangan->kode }} — {{ $ruangan->nama }}</h2>
        <p class="text-sm text-gray-500">
            Denah: <a href="{{ route('sarpras.denah.show', $ruangan->denah_id) }}" class="text-blue-600 hover:underline">{{ $ruangan->denah?->nama }}</a>
            @if ($ruangan->kapasitas) · Kapasitas {{ $ruangan->kapasitas }} @endif
        </p>
    </div>
    @can('sarpras.kerusakan.lapor')
        <a href="{{ route('sarpras.kerusakan.create', ['ruangan_id' => $ruangan->id]) }}"
           class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Lapor Kerusakan
        </a>
    @endcan
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-700 mb-2 text-sm">Denah Ruangan</h3>
        @if ($ruangan->gambar_denah_path)
            <img loading="lazy" src="{{ Storage::url($ruangan->gambar_denah_path) }}"
                 class="w-full rounded bg-gray-50 object-contain max-h-72">
        @else
            <div class="h-40 flex items-center justify-center bg-gray-100 text-gray-400 rounded text-sm">Belum ada denah ruangan</div>
        @endif
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <h3 class="font-semibold text-gray-700 mb-2 text-sm">Foto Ruangan</h3>
        @if ($ruangan->foto_path)
            <img loading="lazy" src="{{ Storage::url($ruangan->foto_path) }}"
                 class="w-full rounded bg-gray-50 object-cover max-h-72">
        @else
            <div class="h-40 flex items-center justify-center bg-gray-100 text-gray-400 rounded text-sm">Belum ada foto ruangan</div>
        @endif
    </div>
</div>

<div class="bg-white rounded-lg shadow p-5 mt-4">
    <h3 class="font-semibold text-gray-800 mb-3">Aset di Ruangan Ini ({{ $ruangan->aset->count() }})</h3>
    <table class="w-full text-sm no-dt">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2">Kode</th><th>Nama</th><th>Kategori</th><th>Kondisi</th>
        </tr></thead>
        <tbody>
        @foreach($ruangan->aset as $a)
            <tr class="border-b">
                <td class="py-2"><a class="text-blue-600 hover:underline" href="{{ route('sarpras.aset.show', $a) }}">{{ $a->kode }}</a></td>
                <td>{{ $a->nama }}</td>
                <td>{{ $a->kategori?->nama }}</td>
                <td class="capitalize">{{ str_replace('_', ' ', $a->kondisi) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
