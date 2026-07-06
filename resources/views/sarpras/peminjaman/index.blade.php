@extends('sarpras.layouts.app')
@section('title', 'Peminjaman Aset')
@section('sarpras_title', 'Peminjaman Aset & Booking Ruangan')
@section('sarpras_subtitle', 'Peminjaman barang inventaris dan pemakaian ruangan kelas/laboratorium untuk KBM & rapat.')

@section('sarpras_actions')
    @can('sarpras.peminjaman.ajukan')
        <a href="{{ route('sarpras.peminjaman.create') }}" class="inline-flex items-center gap-2 bg-slate-900 dark:bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold">
            <i data-lucide="plus" class="w-4 h-4"></i> Pinjam Aset
        </a>
    @endcan
    <a href="{{ route('sarpras.booking.index') }}" class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700">
        <i data-lucide="building-2" class="w-4 h-4"></i> Layanan Booking Ruang
    </a>
@endsection

@section('sarpras_body')
@php
    $bStatus = [
        'diajukan'  => ['Menunggu', 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300'],
        'disetujui' => ['Disetujui','bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'],
        'ditolak'   => ['Ditolak',  'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
        'dipinjam'  => ['Dipinjam', 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300'],
        'selesai'   => ['Selesai',  'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'],
        'terlambat' => ['Terlambat','bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
    ];
@endphp

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

    {{-- Transaksi peminjaman aset --}}
    <div class="card p-5">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-4">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-500"><i data-lucide="hand-helping" class="w-4 h-4"></i></span>
            Transaksi Peminjaman Aset
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="pb-2 font-semibold">Peminjam</th><th class="pb-2 font-semibold">Barang Dipinjam</th><th class="pb-2 font-semibold">Batas Waktu</th><th class="pb-2 font-semibold">Status</th><th class="pb-2"></th>
                </tr></thead>
                <tbody>
                @foreach($peminjaman as $p)
                    @php [$pl, $pc] = $bStatus[$p->status] ?? [ucfirst($p->status), 'bg-slate-100 text-slate-500']; @endphp
                    <tr class="border-b border-slate-50 dark:border-slate-700/50">
                        <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ $p->peminjam?->name ?? '—' }}</td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300">{{ ($p->items_count ?? 0) }} item @if($p->ruangan)· {{ $p->ruangan->kode }}@endif</td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ optional($p->selesai ?? $p->tgl_kembali_rencana)->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-2.5"><span class="badge {{ $pc }}">{{ $pl }}</span></td>
                        <td class="py-2.5"><a href="{{ route('sarpras.peminjaman.show', $p) }}" class="text-blue-600 hover:underline text-xs">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        
    </div>

    {{-- Log reservasi & jadwal ruangan --}}
    <div class="card p-5">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-4">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500"><i data-lucide="calendar-clock" class="w-4 h-4"></i></span>
            Log Reservasi &amp; Jadwal Ruangan
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="pb-2 font-semibold">Ruangan</th><th class="pb-2 font-semibold">Kegiatan</th><th class="pb-2 font-semibold">Waktu / Tanggal</th><th class="pb-2 font-semibold">Status</th>
                </tr></thead>
                <tbody>
                @foreach($bookings as $b)
                    @php [$bl, $bc] = $bStatus[$b->status] ?? [ucfirst($b->status), 'bg-slate-100 text-slate-500']; @endphp
                    <tr class="border-b border-slate-50 dark:border-slate-700/50">
                        <td class="py-2.5">
                            <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $b->ruangan?->kode }}</p>
                            <p class="text-[11px] text-slate-400">{{ trim(($b->ruangan?->gedung ?? '').' '.($b->ruangan?->lantai ?? '')) ?: $b->ruangan?->nama }}</p>
                        </td>
                        <td class="py-2.5">
                            <p class="text-slate-700 dark:text-slate-200">{{ $b->keperluan }}</p>
                            <p class="text-[11px] text-slate-400">Oleh: {{ $b->pemohon?->name ?? '—' }}</p>
                        </td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $b->mulai->format('d/m/Y') }}<br><span class="text-[11px] text-slate-400">{{ $b->mulai->format('H:i') }} – {{ $b->selesai->format('H:i') }}</span>
                        </td>
                        <td class="py-2.5"><span class="badge {{ $bc }}">{{ $bl }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
