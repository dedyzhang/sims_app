@extends('sarpras.layouts.app')
@section('title', 'Pengadaan')
@section('sarpras_title', 'Pengadaan Barang')
@section('sarpras_subtitle', 'Pengajuan kebutuhan aset, approval, pencatatan penerimaan, dan dokumen nota pengadaan sekolah.')

@section('sarpras_actions')
    @can('sarpras.pengadaan.ajukan')
        <a href="{{ route('sarpras.pengadaan.create') }}"
           class="btn-primary inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Pengajuan</span>
        </a>
    @endcan
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

<div class="space-y-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach(['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'selesai' => 'Selesai'] as $key => $label)
            <a href="{{ route('sarpras.pengadaan.index', ['status' => $key]) }}" class="card card-hover p-4 min-w-0">
                <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500 truncate">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">{{ (int) ($statusCounts[$key] ?? 0) }}</p>
            </a>
        @endforeach
    </div>

    <div class="card p-0 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700 flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <h2 class="text-base font-extrabold text-slate-800 dark:text-slate-100 break-words">Daftar Pengadaan Barang</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 break-words">Pastikan setiap pengajuan punya estimasi biaya, status approval, dan catatan penerimaan yang jelas.</p>
            </div>
            <a href="{{ route('sarpras.pengadaan.index') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                <i data-lucide="list-filter" class="w-4 h-4"></i> Semua Status
            </a>
        </div>

        <div class="overflow-x-auto max-w-full">
            <table class="no-dt w-full min-w-[760px] table-fixed text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-700">
                        <th class="py-3 px-4 w-[120px]">Kode</th>
                        <th class="py-3 px-4 w-[280px]">Judul</th>
                        <th class="py-3 px-4 w-[160px]">Pengaju</th>
                        <th class="py-3 px-4 w-[150px]">Total Estimasi</th>
                        <th class="py-3 px-4 w-[120px]">Status</th>
                        <th class="py-3 px-4 w-[90px]"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($pengadaan as $p)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                            <td class="py-3 px-4 align-top font-bold text-slate-700 dark:text-slate-200 break-words">{{ $p->kode }}</td>
                            <td class="py-3 px-4 align-top min-w-0">
                                <a href="{{ route('sarpras.pengadaan.show', $p) }}" class="block font-bold text-slate-800 dark:text-slate-100 hover:text-primary break-words whitespace-normal leading-snug">
                                    {{ $p->judul }}
                                </a>
                                @if($p->deskripsi)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 break-words whitespace-normal line-clamp-2">{{ $p->deskripsi }}</p>
                                @endif
                            </td>
                            <td class="py-3 px-4 align-top text-slate-600 dark:text-slate-300 break-words">{{ $p->pengaju?->name ?? '-' }}</td>
                            <td class="py-3 px-4 align-top font-semibold text-slate-700 dark:text-slate-200 break-words">{{ $p->total_estimasi_rp }}</td>
                            <td class="py-3 px-4 align-top">
                                <span class="badge capitalize {{ $statusMeta[$p->status] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">{{ $p->status }}</span>
                            </td>
                            <td class="py-3 px-4 align-top text-right">
                                <a href="{{ route('sarpras.pengadaan.show', $p) }}" class="inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline">
                                    Detail <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 px-4 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada pengajuan pengadaan barang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection