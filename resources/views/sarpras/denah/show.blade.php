@extends('sarpras.layouts.app')
@section('title', 'Denah: ' . $denah->nama)

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ $denah->nama }}</h2>
        <p class="text-sm text-gray-500">{{ $denah->gedung }} {{ $denah->lantai ? '· Lantai '.$denah->lantai : '' }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('sarpras.denah.index') }}" class="px-3 py-2 border rounded text-sm text-gray-600">← Daftar</a>
        @can('sarpras.denah.kelola')
            @include('sarpras.denah.partials.import-button', ['denah' => $denah])
            <a href="{{ route('sarpras.denah.gambar', $denah) }}" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm">✏️ Gambar Denah</a>
            <a href="{{ route('sarpras.denah.hotspot', $denah) }}" class="bg-emerald-600 text-white px-4 py-2 rounded text-sm">Atur Blok Ruangan</a>
        @endcan
    </div>
</div>

{{-- Pemilih lantai pada gedung yang sama --}}
@if ($denah->gedung)
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs text-gray-400 mr-1">{{ $denah->gedung }} —</span>
        @foreach ($lantaiSegedung as $l)
            <a href="{{ route('sarpras.denah.show', $l) }}"
               class="px-3 py-1 rounded-full text-sm {{ $l->id === $denah->id ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ $l->lantai ? 'Lantai ' . $l->lantai : $l->nama }}
            </a>
        @endforeach
        @can('sarpras.denah.kelola')
            <a href="{{ route('sarpras.denah.create', ['gedung' => $denah->gedung]) }}"
               class="px-3 py-1 rounded-full text-sm border border-dashed border-gray-300 text-gray-500 hover:bg-gray-50">+ Lantai</a>
        @endcan
    </div>
@endif

<p class="text-sm text-gray-500 mb-2">Klik ruangan (mis. <b>7A</b>) untuk melihat detail.</p>

{{--
    DENAH INTERAKTIF.
    Container position:relative & responsif (lebar mengikuti layar, TANPA pixel hardcoded).
    Hotspot position:absolute pakai KOORDINAT PERSEN (pos_x/pos_y) + translate(-50%,-50%)
    sehingga presisi & tidak bergeser di ukuran layar berbeda.
--}}
<div class="bg-white rounded-lg shadow p-3">
    <div class="relative w-full max-w-4xl mx-auto select-none" style="aspect-ratio: 16/10;">
        @if ($denah->gambar_path)
            <img loading="lazy" src="{{ Storage::url($denah->gambar_path) }}" alt="{{ $denah->nama }}"
                 class="absolute inset-0 w-full h-full object-contain bg-gray-50 rounded">
        @else
            <div class="absolute inset-0 flex items-center justify-center bg-gray-100 text-gray-400 rounded">
                🗺️ Gambar denah belum diunggah
            </div>
        @endif

        @foreach ($denah->ruangan as $r)
            {{-- Blok ruangan berlabel (kotak persen) --}}
            <a href="{{ route('sarpras.ruangan.show', $r) }}"
               title="{{ $r->kode }} — {{ $r->nama }}"
               class="absolute z-10 -translate-x-1/2 -translate-y-1/2 group flex flex-col items-center justify-center text-center overflow-hidden rounded-md border-2 border-white bg-emerald-600/85 text-white shadow hover:bg-emerald-700 transition"
               style="left: {{ $r->pos_x }}%; top: {{ $r->pos_y }}%; width: {{ $r->lebar ?? 14 }}%; height: {{ $r->tinggi ?? 9 }}%;">
                <span class="font-bold text-xs leading-tight px-1">{{ $r->kode }}</span>
                @if ($r->nama)
                    <span class="text-[10px] leading-tight px-1 opacity-90 truncate max-w-full">{{ $r->nama }}</span>
                @endif
            </a>
        @endforeach
    </div>
</div>

<div class="mt-6 bg-white rounded-lg shadow p-5">
    <h3 class="font-semibold text-gray-800 mb-3">Daftar Ruangan</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 text-sm">
        @foreach ($denah->ruangan as $r)
            <a href="{{ route('sarpras.ruangan.show', $r) }}"
               class="border rounded px-3 py-2 hover:bg-gray-50">
                <span class="font-semibold">{{ $r->kode }}</span> — {{ $r->nama }}
            </a>
        @endforeach
    </div>
</div>
@endsection
