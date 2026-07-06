@extends('sarpras.layouts.app')
@section('title', 'Denah Gedung')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Denah Gedung &amp; Lantai</h2>
    @can('sarpras.denah.kelola')
        <a href="{{ route('sarpras.denah.create') }}" class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Gedung / Denah Baru
        </a>
    @endcan
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start" data-drag-container="denah_buildings">
@foreach($gedungGroups as $gedung => $lantaiList)
    <div class="mb-6 bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-slate-100 dark:border-slate-700/50 transition-all duration-200" data-drag-id="{{ Str::slug($gedung) }}">
        {{-- Header gedung --}}
        <div class="flex items-center justify-between gap-3 mb-2">
            <div class="flex items-center gap-2">
                <i data-lucide="building" class="w-[18px] h-[18px] text-primary"></i>
                <h3 class="font-bold text-gray-800">{{ $gedung }}</h3>
                <span class="text-xs text-gray-400">{{ $lantaiList->count() }} lantai</span>
            </div>
            @can('sarpras.denah.kelola')
                <a href="{{ route('sarpras.denah.create', ['gedung' => $gedung === 'Tanpa Gedung' ? null : $gedung]) }}"
                   class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30 hover:bg-emerald-100 hover:text-emerald-800 px-3 py-1.5 rounded-full text-xs font-bold transition-all duration-150 whitespace-nowrap">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah Lantai
                </a>
            @endcan
        </div>

        {{-- Kartu lantai dalam gedung --}}
        <div class="grid grid-cols-1 gap-4" data-drag-container="denah_floors_{{ Str::slug($gedung) }}">
            @foreach ($lantaiList as $d)
                <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-700/50 shadow-sm overflow-hidden transition-all duration-200 flex flex-row items-stretch" data-drag-id="{{ $d->id }}">
                    <a href="{{ route('sarpras.denah.show', $d) }}" class="relative w-32 sm:w-40 flex-shrink-0 bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                        @if ($d->gambar_path)
                            <img loading="lazy" src="{{ Storage::url($d->gambar_path) }}" alt="{{ $d->nama }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="text-slate-400 dark:text-slate-500 text-xs text-center p-2">
                                🗺️ Belum ada denah
                            </div>
                        @endif
                        @if ($d->lantai)
                            <span class="absolute top-2 left-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded backdrop-blur-sm">
                                Lantai {{ $d->lantai }}
                            </span>
                        @endif
                    </a>
                    <div class="p-4 flex-1 flex flex-col justify-between min-w-0">
                        <div>
                            <h4 class="font-bold text-slate-800 dark:text-slate-100 text-base truncate">{{ $d->nama }}</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $d->ruangan_count }} ruangan</p>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold">
                            <a href="{{ route('sarpras.denah.show', $d) }}" class="text-blue-600 dark:text-blue-400 hover:underline">Lihat</a>
                            @can('sarpras.denah.kelola')
                                <span class="text-slate-200 dark:text-slate-700">|</span>
                                <a href="{{ route('sarpras.denah.gambar', $d) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Gambar</a>
                                <span class="text-slate-200 dark:text-slate-700">|</span>
                                @include('sarpras.denah.partials.import-button', ['denah' => $d, 'gaya' => 'link'])
                                <span class="text-slate-200 dark:text-slate-700">|</span>
                                <a href="{{ route('sarpras.denah.hotspot', $d) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">Blok Ruangan</a>
                                <span class="text-slate-200 dark:text-slate-700">|</span>
                                <a href="{{ route('sarpras.denah.edit', $d) }}" class="text-slate-500 dark:text-slate-400 hover:underline">Edit</a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
</div>
@endsection
