@extends('sarpras.layouts.app')
@section('title', 'Lapor Kerusakan')
@section('sarpras_title', 'Lapor Kerusakan')

@section('sarpras_subtitle')
@php $hanyaSayaSub = $hanyaMilikSaya ?? false; @endphp
{{ $hanyaSayaSub
    ? 'Laporkan kerusakan fasilitas atau inventaris, lalu pantau status laporan Anda.'
    : 'Terima dan tindaklanjuti laporan kerusakan dari warga sekolah.' }}
@endsection

@section('sarpras_actions')
    @can('sarpras.kerusakan.lapor')
        <a href="{{ route('sarpras.kerusakan.create') }}"
           class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Lapor Kerusakan
        </a>
    @endcan
@endsection

@section('sarpras_body')
@php $hanyaSaya = $hanyaMilikSaya ?? false; @endphp

<form method="GET" class="mb-4 flex flex-wrap gap-2 items-center">
    <select name="status" class="sarpras-field !w-auto min-w-[160px]" onchange="this.form.submit()">
        <option value="">Semua status</option>
        @foreach (['dilaporkan','diterima','ditolak','selesai'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
</form>

<div class="card !rounded-[24px]">
    <div class="overflow-x-auto">
        <table class="w-full text-sm data-table" style="width: 100%;">
            <thead>
                <tr class="text-left text-slate-400 bg-[#f8fafd] dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-700">
                    <th class="py-3 px-4 font-bold">Kode</th>
                    <th class="py-3 px-4 font-bold">Objek</th>
                    @unless($hanyaSaya)<th class="py-3 px-4 font-bold">Pelapor</th>@endunless
                    <th class="py-3 px-4 font-bold">Urgensi</th>
                    <th class="py-3 px-4 font-bold">Status</th>
                    <th class="py-3 px-4 font-bold">Waktu</th>
                    <th class="py-3 px-4 font-bold">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($laporan as $l)
                @php
                    $warna = ['darurat'=>'bg-rose-100 text-rose-700','tinggi'=>'bg-orange-100 text-orange-700','sedang'=>'bg-amber-100 text-amber-700','rendah'=>'bg-slate-100 text-slate-700'][$l->urgensi] ?? 'bg-slate-100 text-slate-700';
                @endphp
                <tr class="border-b border-slate-50 dark:border-slate-700/50 hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                    <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-100">{{ $l->kode }}</td>
                    <td class="py-3 px-4 text-slate-600 dark:text-slate-300">{{ $l->aset?->nama ?? $l->ruangan?->kode ?? '-' }}</td>
                    @unless($hanyaSaya)
                        <td class="py-3 px-4 text-slate-600 dark:text-slate-300">{{ $l->pelapor?->name }}</td>
                    @endunless
                    <td class="py-3 px-4">
                        <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold capitalize {{ $warna }}">{{ $l->urgensi }}</span>
                    </td>
                    <td class="py-3 px-4 capitalize text-slate-600 dark:text-slate-300">{{ $l->status }}</td>
                    <td class="py-3 px-4 text-slate-500">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                    <td class="py-3 px-4">
                        <a href="{{ route('sarpras.kerusakan.show', $l) }}" class="inline-flex items-center gap-1 text-xs font-extrabold text-[#1a73e8] hover:underline">
                            Detail <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $hanyaSaya ? 6 : 7 }}" class="py-12 text-center text-sm text-slate-500">
                        Belum ada laporan. Klik <span class="font-semibold text-[#1a73e8]">Lapor Kerusakan</span> jika menemukan fasilitas bermasalah.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
