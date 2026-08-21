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
    $condBadgeClasses = [
        'baik' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
        'rusak_ringan' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
        'rusak_berat' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
        'hilang' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    ];
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
<form method="GET" class="flex flex-col sm:flex-row gap-2 text-sm">
    <input name="q" value="{{ request('q') }}" placeholder="Cari kode / nama" class="w-full sm:flex-1 min-w-0 border rounded px-3 py-2">
    <select name="kategori_id" class="w-full sm:w-auto sm:min-w-[180px] border rounded px-3 py-2">
        <option value="">Semua kategori</option>
        @foreach ($kategori as $k)
            <option value="{{ $k->id }}" @selected(request('kategori_id')===$k->id)>{{ $k->nama }}</option>
        @endforeach
    </select>
    <select name="kondisi" class="w-full sm:w-auto sm:min-w-[160px] border rounded px-3 py-2">
        <option value="">Semua kondisi</option>
        @foreach (['baik','rusak_ringan','rusak_berat','hilang'] as $kd)
            <option value="{{ $kd }}" @selected(request('kondisi')===$kd)>{{ ucfirst(str_replace('_',' ',$kd)) }}</option>
        @endforeach
    </select>
    <button class="w-full sm:w-auto bg-gray-200 rounded px-4 py-2">Filter</button>
</form>

@if($aset->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 dark:bg-slate-900 dark:border-slate-700 px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
        Belum ada aset yang sesuai dengan filter.
    </div>
@else
    {{-- Desktop/tablet: kolom diberi lebar minimum agar nominal dan aksi tidak pecah per karakter. --}}
    <div class="hidden md:block bg-white rounded-lg shadow overflow-x-auto max-w-full" tabindex="0" aria-label="Tabel inventaris aset, geser ke samping untuk melihat kolom lain">
        <table class="w-full min-w-[980px] text-sm" style="width:max-content; min-width:980px; max-width:none;">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th scope="col" class="py-3 px-4 whitespace-nowrap">Kode</th>
                    <th scope="col" class="py-3 px-4 min-w-[200px]">Nama</th>
                    <th scope="col" class="py-3 px-4 min-w-[180px]">Kategori</th>
                    <th scope="col" class="py-3 px-4 min-w-[140px]">Ruangan</th>
                    <th scope="col" class="py-3 px-4 min-w-[120px]">Kondisi</th>
                    <th scope="col" class="py-3 px-4 whitespace-nowrap">Nilai Perolehan</th>
                    <th scope="col" class="py-3 px-4 whitespace-nowrap">Nilai Buku</th>
                    <th scope="col" class="py-3 px-4 whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($aset as $a)
                <tr class="border-b align-top last:border-b-0">
                    <td class="py-3 px-4 font-medium whitespace-nowrap">{{ $a->kode }}</td>
                    <td class="py-3 px-4 break-words whitespace-normal">{{ $a->nama }}</td>
                    <td class="py-3 px-4 break-words whitespace-normal">{{ $a->kategori?->nama ?? 'Tanpa kategori' }}</td>
                    <td class="py-3 px-4 break-words whitespace-normal">{{ $a->ruangan?->kode ?? 'Tanpa ruangan' }}</td>
                    <td class="py-3 px-4 whitespace-normal">
                        <span class="inline-flex max-w-full rounded-full border px-2.5 py-1 text-xs font-semibold leading-tight {{ $condBadgeClasses[$a->kondisi] ?? $condBadgeClasses['hilang'] }}">
                            {{ $condLabels[$a->kondisi] ?? ucfirst(str_replace('_', ' ', (string) $a->kondisi)) }}
                        </span>
                    </td>
                    <td class="py-3 px-4 whitespace-nowrap sarpras-keep-nowrap">{{ $a->nilai_perolehan_rp }}</td>
                    <td class="py-3 px-4 whitespace-nowrap sarpras-keep-nowrap">{{ $a->nilai_buku_rp }}</td>
                    <td class="py-3 px-4 whitespace-nowrap sarpras-keep-nowrap">
                        <a href="{{ route('sarpras.aset.show', $a) }}" class="inline-flex min-h-9 items-center rounded-lg px-2 text-blue-600 hover:bg-blue-50 hover:underline dark:hover:bg-blue-900/30 sarpras-keep-nowrap" style="max-width:none; flex-wrap:nowrap; white-space:nowrap;">Detail</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile: kartu menjaga seluruh detail terbaca tanpa mengecilkan kolom tabel. --}}
    <div class="md:hidden space-y-3" aria-label="Daftar inventaris aset">
        @foreach($aset as $a)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wide text-slate-500 dark:text-slate-400 break-words">{{ $a->kode }}</p>
                        <h3 class="mt-1 text-base font-bold leading-snug text-slate-800 dark:text-slate-100 break-words">{{ $a->nama }}</h3>
                    </div>
                    <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold leading-tight {{ $condBadgeClasses[$a->kondisi] ?? $condBadgeClasses['hilang'] }}">
                        {{ $condLabels[$a->kondisi] ?? ucfirst(str_replace('_', ' ', (string) $a->kondisi)) }}
                    </span>
                </div>

                <dl class="mt-4 grid grid-cols-1 min-[420px]:grid-cols-2 gap-2.5 text-sm">
                    <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/70">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Kategori</dt>
                        <dd class="mt-1 break-words text-slate-700 dark:text-slate-200">{{ $a->kategori?->nama ?? 'Tanpa kategori' }}</dd>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/70">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ruangan</dt>
                        <dd class="mt-1 break-words text-slate-700 dark:text-slate-200">{{ $a->ruangan?->kode ?? 'Tanpa ruangan' }}</dd>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/70">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nilai Perolehan</dt>
                        <dd class="mt-1 whitespace-nowrap sarpras-keep-nowrap text-slate-700 dark:text-slate-200">{{ $a->nilai_perolehan_rp }}</dd>
                    </div>
                    <div class="min-w-0 rounded-xl bg-slate-50 px-3 py-2.5 dark:bg-slate-800/70">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nilai Buku</dt>
                        <dd class="mt-1 whitespace-nowrap sarpras-keep-nowrap text-slate-700 dark:text-slate-200">{{ $a->nilai_buku_rp }}</dd>
                    </div>
                </dl>

                <a href="{{ route('sarpras.aset.show', $a) }}" class="mt-3 inline-flex min-h-10 w-full items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-bold text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50">Lihat detail aset</a>
            </article>
        @endforeach
    </div>
@endif


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
