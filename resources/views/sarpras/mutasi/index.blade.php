@extends('sarpras.layouts.app')
@section('title', 'Mutasi Aset')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Mutasi & Perpindahan Aset</h2>
    <div class="flex gap-2">
        <a href="{{ route('sarpras.laporan.mutasi.excel') }}" 
           class="inline-flex items-center gap-2 bg-[#eafaf1] text-[#065f46] border border-[#a7f3d0] dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30 px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#d1fae5]">
            <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Ekspor Excel
        </a>
        <a href="{{ route('sarpras.mutasi.create') }}" 
           class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Mutasi
        </a>
    </div>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Tanggal</th><th>Aset</th><th>Asal</th><th>Tujuan</th><th>Alasan</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($mutasi as $m)
            <tr class="border-b">
                <td class="py-2 px-4">{{ $m->tgl_mutasi->format('d/m/Y') }}</td>
                <td>{{ $m->aset?->nama }}</td>
                <td>{{ $m->ruanganAsal?->kode ?? '-' }}</td>
                <td>{{ $m->ruanganTujuan?->kode ?? '-' }}</td>
                <td>{{ $m->alasan }}</td>
                <td class="px-4"><a href="{{ route('sarpras.mutasi.berita', $m) }}" target="_blank" class="text-blue-600 hover:underline">Berita Acara</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
