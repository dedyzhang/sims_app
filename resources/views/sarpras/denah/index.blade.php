@extends('sarpras.layouts.app')
@section('title', 'Denah Sekolah')
@section('sarpras_title', 'Denah Sekolah')
@section('sarpras_subtitle', 'Peta gedung, lantai, zona ruangan, status pemakaian, dan titik aset/maintenance sekolah.')

@section('sarpras_actions')
    @can('sarpras.denah.kelola')
        <a href="{{ route('sarpras.denah.create') }}" class="btn-primary inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
            <i data-lucide="plus" class="w-4 h-4"></i> Denah Baru
        </a>
    @endcan
@endsection

@section('sarpras_body')
@php
    $standarZona = [
        ['label' => 'Akademik', 'contoh' => 'Kelas, lab IPA, lab komputer, perpustakaan', 'warna' => '#2563eb'],
        ['label' => 'Administrasi', 'contoh' => 'TU, kepala sekolah, guru, arsip', 'warna' => '#7c3aed'],
        ['label' => 'Fasilitas Umum', 'contoh' => 'UKS, toilet, kantin, aula, mushola', 'warna' => '#059669'],
        ['label' => 'Operasional', 'contoh' => 'Gudang, listrik, server, parkir, keamanan', 'warna' => '#d97706'],
        ['label' => 'Area Risiko', 'contoh' => 'Tangga, jalur evakuasi, titik APAR, area maintenance', 'warna' => '#dc2626'],
    ];
    $statusCards = [
        ['label' => 'Tersedia', 'value' => $denahStats['tersedia'] ?? 0, 'tone' => 'text-emerald-600', 'bg' => 'bg-emerald-100 dark:bg-emerald-950/40'],
        ['label' => 'Digunakan', 'value' => $denahStats['digunakan'] ?? 0, 'tone' => 'text-blue-600', 'bg' => 'bg-blue-100 dark:bg-blue-950/40'],
        ['label' => 'Maintenance', 'value' => $denahStats['maintenance'] ?? 0, 'tone' => 'text-rose-600', 'bg' => 'bg-rose-100 dark:bg-rose-950/40'],
    ];
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div class="card p-4 min-w-0">
            <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500">Gedung</p>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $denahStats['gedung'] ?? 0 }}</p>
        </div>
        <div class="card p-4 min-w-0">
            <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500">Lantai</p>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $denahStats['lantai'] ?? 0 }}</p>
        </div>
        <div class="card p-4 min-w-0">
            <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500">Ruangan</p>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ $denahStats['ruangan'] ?? 0 }}</p>
        </div>
        <div class="card p-4 min-w-0">
            <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500">Tanpa Gambar</p>
            <p class="text-2xl font-extrabold text-amber-600 mt-1">{{ $denahStats['tanpa_gambar'] ?? 0 }}</p>
        </div>
        <div class="card p-4 min-w-0">
            <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500">Lantai Kosong</p>
            <p class="text-2xl font-extrabold text-rose-600 mt-1">{{ $denahStats['tanpa_ruangan'] ?? 0 }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
        <section class="xl:col-span-8 space-y-4">
            <div class="card p-5">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
                    <div class="min-w-0">
                        <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Peta Gedung & Lantai</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 break-words">Struktur terbaik: 1 denah untuk setiap lantai. Ruangan dikelompokkan berdasarkan zona dan diberi blok warna yang konsisten.</p>
                    </div>
                    @can('sarpras.denah.kelola')
                        <a href="{{ route('sarpras.denah.create') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <i data-lucide="building-2" class="w-4 h-4"></i> Tambah Gedung/Lantai
                        </a>
                    @endcan
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" data-drag-container="denah_buildings">
                    @forelse($gedungGroups as $gedung => $lantaiList)
                        <div class="rounded-2xl border border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-4 min-w-0" data-drag-id="{{ Str::slug($gedung) }}">
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary/10 text-primary shrink-0"><i data-lucide="building" class="w-5 h-5"></i></span>
                                        <div class="min-w-0">
                                            <h3 class="font-extrabold text-slate-800 dark:text-slate-100 break-words">{{ $gedung }}</h3>
                                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $lantaiList->count() }} lantai</p>
                                        </div>
                                    </div>
                                </div>
                                @can('sarpras.denah.kelola')
                                    <a href="{{ route('sarpras.denah.create', ['gedung' => $gedung === 'Tanpa Gedung' ? null : $gedung]) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-300 dark:border-emerald-900/60">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Lantai
                                    </a>
                                @endcan
                            </div>

                            <div class="space-y-3" data-drag-container="denah_floors_{{ Str::slug($gedung) }}">
                                @foreach ($lantaiList as $d)
                                    <article class="rounded-2xl border border-slate-100 dark:border-slate-700 overflow-hidden bg-white dark:bg-slate-800/70 flex min-w-0" data-drag-id="{{ $d->id }}">
                                        <a href="{{ route('sarpras.denah.show', $d) }}" class="relative w-28 sm:w-36 shrink-0 bg-slate-100 dark:bg-slate-900 grid place-items-center">
                                            @if ($d->gambar_path)
                                                <img loading="lazy" src="{{ Storage::url($d->gambar_path) }}" alt="{{ $d->nama }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="text-center text-slate-400 p-2">
                                                    <i data-lucide="map" class="w-7 h-7 mx-auto mb-1"></i>
                                                    <span class="text-[10px] font-bold">Belum ada gambar</span>
                                                </div>
                                            @endif
                                            <span class="absolute top-2 left-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg backdrop-blur-sm">{{ $d->lantai ? 'Lantai ' . $d->lantai : 'Lantai' }}</span>
                                        </a>
                                        <div class="p-3.5 flex-1 min-w-0">
                                            <a href="{{ route('sarpras.denah.show', $d) }}" class="block font-extrabold text-slate-800 dark:text-slate-100 hover:text-primary break-words leading-snug">{{ $d->nama }}</a>
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $d->ruangan_count }} ruangan</p>
                                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-bold">
                                                <a href="{{ route('sarpras.denah.show', $d) }}" class="text-primary hover:underline">Buka</a>
                                                @can('sarpras.denah.kelola')
                                                    <a href="{{ route('sarpras.denah.gambar', $d) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Gambar</a>
                                                    @include('sarpras.denah.partials.import-button', ['denah' => $d, 'gaya' => 'link'])
                                                    <a href="{{ route('sarpras.denah.hotspot', $d) }}" class="text-emerald-600 dark:text-emerald-400 hover:underline">Blok Ruangan</a>
                                                    <a href="{{ route('sarpras.denah.edit', $d) }}" class="text-slate-500 dark:text-slate-400 hover:underline">Edit</a>
                                                @endcan
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="lg:col-span-2 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center">
                            <i data-lucide="map" class="w-10 h-10 mx-auto text-slate-400"></i>
                            <p class="font-bold text-slate-700 dark:text-slate-200 mt-3">Belum ada denah sekolah.</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Mulai dari Gedung A - Lantai 1, lalu tambahkan ruangan.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <aside class="xl:col-span-4 space-y-4">
            <section class="card p-5">
                <div class="flex items-center gap-2 mb-3">
                    <span class="grid place-items-center w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300"><i data-lucide="clipboard-list" class="w-5 h-5"></i></span>
                    <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Standar Denah Sekolah</h2>
                </div>
                <div class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <p class="break-words">Denah yang baik harus menjawab: lokasi ruangan, fungsi ruangan, kapasitas, fasilitas, status pemakaian, dan titik risiko/maintenance.</p>
                    <ol class="space-y-2 list-decimal list-inside text-xs leading-relaxed">
                        <li>1 denah untuk 1 lantai, bukan semua gedung digabung.</li>
                        <li>Kode ruangan konsisten, misalnya A-101, A-102, LAB-IPA.</li>
                        <li>Warna blok mengikuti fungsi/zona, bukan acak per ruangan.</li>
                        <li>Setiap ruang punya kapasitas, fasilitas, dan status.</li>
                        <li>Jalur evakuasi, APAR, toilet, UKS, dan tangga harus mudah dikenali.</li>
                    </ol>
                </div>
            </section>

            <section class="card p-5">
                <h3 class="font-extrabold text-slate-800 dark:text-slate-100 mb-3">Rekomendasi Zona Warna</h3>
                <div class="space-y-2.5">
                    @foreach($standarZona as $zona)
                        <div class="flex gap-3 rounded-2xl border border-slate-100 dark:border-slate-700 p-3">
                            <span class="w-3 h-3 rounded-full mt-1 shrink-0" style="background: {{ $zona['warna'] }}"></span>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $zona['label'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 break-words mt-0.5">{{ $zona['contoh'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-5">
                <h3 class="font-extrabold text-slate-800 dark:text-slate-100 mb-3">Status Ruangan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 xl:grid-cols-1 gap-3">
                    @foreach($statusCards as $item)
                        <div class="rounded-2xl {{ $item['bg'] }} p-4">
                            <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $item['label'] }}</p>
                            <p class="text-2xl font-extrabold {{ $item['tone'] }} mt-1">{{ $item['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
