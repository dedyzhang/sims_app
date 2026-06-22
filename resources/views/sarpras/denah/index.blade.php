@extends('sarpras.layouts.app')
@section('title', 'Denah Gedung')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Denah Gedung &amp; Lantai</h2>
    @can('sarpras.denah.kelola')
        <a href="{{ route('sarpras.denah.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Gedung / Denah Baru</a>
    @endcan
</div>

@forelse ($gedungGroups as $gedung => $lantaiList)
    <div class="mb-6">
        {{-- Header gedung --}}
        <div class="flex items-center justify-between gap-3 mb-2">
            <div class="flex items-center gap-2">
                <i data-lucide="building" class="w-[18px] h-[18px] text-primary"></i>
                <h3 class="font-bold text-gray-800">{{ $gedung }}</h3>
                <span class="text-xs text-gray-400">{{ $lantaiList->count() }} lantai</span>
            </div>
            @can('sarpras.denah.kelola')
                <a href="{{ route('sarpras.denah.create', ['gedung' => $gedung === 'Tanpa Gedung' ? null : $gedung]) }}"
                   class="text-sm text-emerald-600 hover:underline whitespace-nowrap">+ Tambah Lantai</a>
            @endcan
        </div>

        {{-- Kartu lantai dalam gedung --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($lantaiList as $d)
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <a href="{{ route('sarpras.denah.show', $d) }}" class="block relative">
                        @if ($d->gambar_path)
                            <img loading="lazy" src="{{ Storage::url($d->gambar_path) }}" alt="{{ $d->nama }}"
                                 class="w-full h-40 object-cover bg-gray-100">
                        @else
                            <div class="w-full h-40 flex items-center justify-center bg-gray-100 text-gray-400 text-sm">
                                🗺️ Belum ada gambar denah
                            </div>
                        @endif
                        @if ($d->lantai)
                            <span class="absolute top-2 left-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded shadow">
                                Lantai {{ $d->lantai }}
                            </span>
                        @endif
                    </a>
                    <div class="p-4">
                        <h4 class="font-semibold text-gray-800">{{ $d->nama }}</h4>
                        <p class="text-sm text-gray-500">{{ $d->ruangan_count }} ruangan</p>
                        <div class="mt-3 flex gap-3 text-sm">
                            <a href="{{ route('sarpras.denah.show', $d) }}" class="text-blue-600 hover:underline">Lihat</a>
                            @can('sarpras.denah.kelola')
                                <a href="{{ route('sarpras.denah.gambar', $d) }}" class="text-indigo-600 hover:underline">Gambar</a>
                                @include('sarpras.denah.partials.import-button', ['denah' => $d, 'gaya' => 'link'])
                                <a href="{{ route('sarpras.denah.hotspot', $d) }}" class="text-emerald-600 hover:underline">Blok Ruangan</a>
                                <a href="{{ route('sarpras.denah.edit', $d) }}" class="text-gray-600 hover:underline">Edit</a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@empty
    <p class="text-gray-400">Belum ada denah. Tambahkan gedung/denah baru.</p>
@endforelse
@endsection
