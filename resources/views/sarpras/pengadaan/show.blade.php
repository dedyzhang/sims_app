@extends('sarpras.layouts.app')
@section('title', 'Pengadaan ' . $pengadaan->kode)
@section('sarpras_title', 'Detail Pengadaan Barang')
@section('sarpras_subtitle', 'Rincian pengajuan, item barang, approval, penerimaan, dan dokumen pendukung.')

@section('sarpras_actions')
    <a href="{{ route('sarpras.pengadaan.index') }}" class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-800">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
@endsection

@section('sarpras_body')
@php
    $statusMeta = [
        'diajukan' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        'disetujui' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        'ditolak' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
        'selesai' => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="lg:col-span-2 card p-0 overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-extrabold text-slate-800 dark:text-slate-100 break-words leading-snug">{{ $pengadaan->judul }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 break-words">{{ $pengadaan->kode }} - {{ $pengadaan->pengaju?->name ?? '-' }}</p>
            </div>
            <span class="badge capitalize shrink-0 {{ $statusMeta[$pengadaan->status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">{{ $pengadaan->status }}</span>
        </div>

        <div class="p-5 space-y-5">
            @if($pengadaan->deskripsi)
                <p class="text-sm text-slate-700 dark:text-slate-300 break-words whitespace-pre-line">{{ $pengadaan->deskripsi }}</p>
            @endif

            <div class="overflow-x-auto max-w-full border border-slate-100 dark:border-slate-700 rounded-2xl">
                <table class="no-dt w-full min-w-[760px] table-fixed text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                            <th class="py-3 px-4 w-[280px]">Barang</th>
                            <th class="py-3 px-4 w-[100px]">Qty</th>
                            <th class="py-3 px-4 w-[150px]">Harga</th>
                            <th class="py-3 px-4 w-[150px]">Subtotal</th>
                            <th class="py-3 px-4 w-[160px]">Diterima</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach ($pengadaan->items as $it)
                            <tr>
                                <td class="py-3 px-4 align-top">
                                    <p class="font-bold text-slate-800 dark:text-slate-100 break-words whitespace-normal leading-snug">{{ $it->nama_barang }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 break-words">{{ $it->kategori?->nama ?? 'Tanpa kategori' }}</p>
                                </td>
                                <td class="py-3 px-4 align-top text-slate-700 dark:text-slate-300 break-words">{{ $it->qty }} {{ $it->satuan }}</td>
                                <td class="py-3 px-4 align-top text-slate-700 dark:text-slate-300 break-words">{{ \App\Sarpras\Support\Rupiah::format($it->estimasi_harga) }}</td>
                                <td class="py-3 px-4 align-top font-semibold text-slate-800 dark:text-slate-100 break-words">{{ $it->subtotal_rp }}</td>
                                <td class="py-3 px-4 align-top text-slate-700 dark:text-slate-300 break-words">{{ $it->qty_diterima }} {{ $it->kondisi_terima ? '(' . $it->kondisi_terima . ')' : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-extrabold bg-slate-50 dark:bg-slate-900/40">
                            <td colspan="3" class="py-3 px-4 text-right text-slate-700 dark:text-slate-200">Total Estimasi</td>
                            <td colspan="2" class="py-3 px-4 text-slate-900 dark:text-slate-100 break-words">{{ $pengadaan->total_estimasi_rp }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($pengadaan->alasan_tolak)
                <div class="bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 text-rose-700 dark:text-rose-300 text-sm rounded-2xl p-4 break-words">
                    <b>Ditolak:</b> {{ $pengadaan->alasan_tolak }}
                </div>
            @endif

            @can('sarpras.pengadaan.kelola')
                @if ($pengadaan->status === 'disetujui')
                    <div class="border border-slate-100 dark:border-slate-700 rounded-2xl p-4">
                        <h3 class="font-extrabold text-slate-800 dark:text-slate-100 mb-3 text-sm">Catat Penerimaan</h3>
                        <form method="POST" action="{{ route('sarpras.pengadaan.terima', $pengadaan) }}" class="space-y-3 text-sm">
                            @csrf
                            <input type="date" name="tgl_terima" value="{{ now()->format('Y-m-d') }}" class="form-input max-w-xs">
                            @foreach ($pengadaan->items as $it)
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-center">
                                    <span class="md:col-span-5 font-semibold text-slate-700 dark:text-slate-200 break-words">{{ $it->nama_barang }}</span>
                                    <input type="number" min="0" name="qty_diterima[{{ $it->id }}]" placeholder="Qty diterima" class="form-input md:col-span-3">
                                    <input name="kondisi_terima[{{ $it->id }}]" placeholder="Kondisi" class="form-input md:col-span-4">
                                </div>
                            @endforeach
                            <button class="btn-primary rounded-xl px-4 py-2 text-sm font-bold">Simpan Penerimaan</button>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </section>

    <aside class="space-y-4 min-w-0">
        @can('sarpras.pengadaan.setujui')
            @if ($pengadaan->status === 'diajukan')
                <section class="card p-5">
                    <h3 class="font-extrabold text-slate-800 dark:text-slate-100 mb-3">Approval</h3>
                    <form method="POST" action="{{ route('sarpras.pengadaan.setujui', $pengadaan) }}" class="mb-2">@csrf
                        <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl py-2 text-sm font-bold">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route('sarpras.pengadaan.tolak', $pengadaan) }}" class="space-y-2">@csrf
                        <textarea name="alasan_tolak" rows="3" placeholder="Alasan tolak" class="form-input text-sm"></textarea>
                        <button class="w-full bg-rose-600 hover:bg-rose-700 text-white rounded-xl py-2 text-sm font-bold">Tolak</button>
                    </form>
                </section>
            @endif
        @endcan

        <section class="card p-5 min-w-0">
            <h3 class="font-extrabold text-slate-800 dark:text-slate-100 mb-3">Dokumen / Nota</h3>
            <div class="space-y-2">
                @forelse($pengadaan->dokumen as $d)
                    <a href="{{ $d->url }}" target="_blank" class="block text-primary hover:underline text-sm font-semibold break-words">{{ $d->nama }}</a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada dokumen.</p>
                @endforelse
            </div>
            @can('sarpras.pengadaan.kelola')
                <form method="POST" action="{{ route('sarpras.pengadaan.dokumen', $pengadaan) }}" enctype="multipart/form-data" class="mt-4 space-y-3 text-sm">
                    @csrf
                    <input name="nama" placeholder="Nama dokumen" required class="form-input">
                    <input name="file" type="file" accept="image/*" required class="w-full text-xs text-slate-600 dark:text-slate-300">
                    <button class="w-full bg-slate-900 dark:bg-primary text-white rounded-xl py-2 font-bold">Upload (dikompres <= 2MB)</button>
                </form>
            @endcan
        </section>
    </aside>
</div>
@endsection