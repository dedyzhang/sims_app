@extends('sarpras.layouts.app')
@section('title', 'Inventaris Barang')
@section('sarpras_title', 'Inventaris Barang')
@section('sarpras_subtitle', 'Katalog aset sekolah, kondisi fisik, nilai buku, dan lokasi ruangan.')

@section('sarpras_actions')
    @can('sarpras.aset.kelola')
        <button type="button" id="toggle-import" class="sarpras-google-btn-ghost px-4 py-2 text-xs sm:text-sm">
            <i data-lucide="upload" class="w-4 h-4"></i> Import
        </button>
    @endcan
    @if(Route::has('sarpras.laporan.aset.excel'))
        <a href="{{ route('sarpras.laporan.aset.excel') }}" class="sarpras-google-btn-ghost px-4 py-2 text-xs sm:text-sm">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Export Excel
        </a>
    @endif
    @can('sarpras.aset.kelola')
        <a href="{{ route('sarpras.aset.create') }}" class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Aset
        </a>
    @endcan
@endsection

@use('App\Sarpras\Support\Rupiah')
@section('sarpras_body')
@php
    $condColors = ['baik' => '#10b981', 'rusak_ringan' => '#f59e0b', 'rusak_berat' => '#ef4444', 'hilang' => '#94a3b8'];
    $condLabels = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat', 'hilang' => 'Hilang'];
    $totalKondisi = (int) $kondisiCount->sum();

    // Segmen donut (conic-gradient) + legenda.
    $stops = [];
    $acc = 0.0;
    foreach ($kondisiUrut as $k) {
        $val = (int) ($kondisiCount[$k] ?? 0);
        if ($val > 0 && $totalKondisi > 0) {
            $pct = $val / $totalKondisi * 100;
            $stops[] = $condColors[$k] . ' ' . round($acc, 2) . '% ' . round($acc + $pct, 2) . '%';
            $acc += $pct;
        }
    }
    $donut = count($stops) ? 'conic-gradient(' . implode(',', $stops) . ')' : '#e2e8f0';
@endphp

@can('sarpras.aset.kelola')
    {{-- Panel import Excel/CSV (tersembunyi by default) --}}
    <div id="panel-import" class="hidden card !rounded-[24px] border border-emerald-100 dark:border-emerald-900/40 p-5 mb-4">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="font-extrabold text-slate-800 dark:text-slate-100">Import Katalog Aset</h3>
                <p class="text-xs text-slate-500 mt-0.5">Unggah file Excel/CSV. Aset dengan <b>kode</b> yang sudah ada akan diperbarui, sisanya ditambahkan.</p>
            </div>
            <a href="{{ route('sarpras.aset.import.template') }}" class="shrink-0 inline-flex items-center gap-1.5 text-sm text-emerald-700 font-bold hover:underline">
                <i data-lucide="download" class="w-4 h-4"></i> Unduh template
            </a>
        </div>
        <form method="POST" action="{{ route('sarpras.aset.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 text-sm">
            @csrf
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="sarpras-field !w-auto">
            <button class="sarpras-google-btn-success px-5 py-2.5 text-xs sm:text-sm">
                <i data-lucide="upload" class="w-4 h-4"></i> Proses Import
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-2">Kolom: <code>kode, nama, kategori, ruangan, merk, kondisi, status, tgl_perolehan, nilai_perolehan, sumber_dana</code>.</p>
    </div>

    @if (session('import_catatan') && count(session('import_catatan')))
        <details class="rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-4" open>
            <summary class="cursor-pointer font-medium">{{ count(session('import_catatan')) }} catatan saat import (klik untuk lihat)</summary>
            <ul class="list-disc list-inside mt-2 space-y-0.5 max-h-48 overflow-y-auto">
                @foreach (session('import_catatan') as $c)<li>{{ $c }}</li>@endforeach
            </ul>
        </details>
    @endif
@endcan

{{-- Kartu neraca aset --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="card p-5 flex items-center gap-4">
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-900/40 text-amber-500 flex-shrink-0"><i data-lucide="archive" class="w-6 h-6"></i></span>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Aset</p>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ number_format($totalAset, 0, ',', '.') }} unit</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500 flex-shrink-0"><i data-lucide="banknote" class="w-6 h-6"></i></span>
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Nilai Perolehan</p>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 truncate">{{ Rupiah::format($totalNilai) }}</p>
        </div>
    </div>
    <div class="card p-5 flex items-center gap-4">
        <span class="grid place-items-center w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-900/40 text-sky-500 flex-shrink-0"><i data-lucide="trending-down" class="w-6 h-6"></i></span>
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Nilai Buku (setelah susut)</p>
            <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 truncate">{{ Rupiah::format($totalNilaiBuku) }}</p>
        </div>
    </div>
</div>

{{-- Kondisi (donut) + Neraca per kategori --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    {{-- Donut kondisi aset --}}
    <div class="card p-5">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4">Kondisi Aset</h3>
        @if($totalKondisi === 0)
            <p class="text-sm text-slate-400 py-10 text-center">Belum ada data aset.</p>
        @else
        <div class="flex items-center gap-6 flex-wrap">
            <div class="relative flex-shrink-0" style="width:170px;height:170px">
                <div class="w-full h-full rounded-full" style="background:{{ $donut }}"></div>
                <div class="absolute inset-0 m-auto rounded-full bg-white dark:bg-slate-800 grid place-items-center" style="width:96px;height:96px">
                    <div class="text-center">
                        <p class="text-xl font-extrabold text-slate-800 dark:text-slate-100">{{ number_format($totalKondisi) }}</p>
                        <p class="text-[11px] text-slate-400">unit</p>
                    </div>
                </div>
            </div>
            <div class="space-y-2.5 flex-1 min-w-[140px]">
                @foreach($kondisiUrut as $k)
                    @php $val = (int) ($kondisiCount[$k] ?? 0); @endphp
                    @if($val > 0)
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-medium" style="color:{{ $condColors[$k] }}">
                            <span class="w-3 h-3 rounded-full" style="background:{{ $condColors[$k] }}"></span>{{ $condLabels[$k] }}
                        </span>
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $val }} unit</span>
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Neraca aset per kategori --}}
    <div class="card p-5">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-4">Neraca Aset per Kategori</h3>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="pb-2 font-semibold">Kategori</th>
                    <th class="pb-2 font-semibold text-center">Jumlah</th>
                    <th class="pb-2 font-semibold text-right">Nilai Perolehan</th>
                </tr>
            </thead>
            <tbody>
                @foreach($perKategori as $row)
                <tr class="border-b border-slate-50 dark:border-slate-700/50">
                    <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ $row->kategori?->nama ?? 'Tanpa Kategori' }}</td>
                    <td class="py-2.5 text-center text-slate-600 dark:text-slate-300">{{ $row->jml }}</td>
                    <td class="py-2.5 text-right text-slate-600 dark:text-slate-300">{{ Rupiah::format((int) $row->nilai) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Filter + tabel katalog --}}
<form method="GET" class="flex flex-wrap gap-2 text-sm">
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
            <th class="py-2 px-4">Kode</th><th>Nama</th><th>Kategori</th><th>Ruangan</th><th>Kondisi</th><th>Nilai Perolehan</th><th>Nilai Buku</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($aset as $a)
            <tr class="border-b">
                <td class="py-2 px-4 font-medium">{{ $a->kode }}</td>
                <td>{{ $a->nama }}</td>
                <td>{{ $a->kategori?->nama }}</td>
                <td>{{ $a->ruangan?->kode }}</td>
                <td class="capitalize">{{ str_replace('_',' ',$a->kondisi) }}</td>
                <td>{{ $a->nilai_perolehan_rp }}</td>
                <td>{{ $a->nilai_buku_rp }}</td>
                <td class="px-4"><a href="{{ route('sarpras.aset.show', $a) }}" class="text-blue-600 hover:underline">Detail</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>


@push('scripts')
<script>
(function () {
    const btn = document.getElementById('toggle-import');
    const panel = document.getElementById('panel-import');
    if (btn && panel) btn.addEventListener('click', () => panel.classList.toggle('hidden'));
    @if (session('import_catatan') && count(session('import_catatan')))
        panel?.classList.remove('hidden');
    @endif
})();
</script>
@endpush
@endsection
