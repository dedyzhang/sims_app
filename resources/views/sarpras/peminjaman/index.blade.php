@extends('sarpras.layouts.app')
@section('title', 'Pinjam Barang')
@section('sarpras_title', 'Pinjam Barang')

@section('sarpras_subtitle')
@php $hanyaSayaSub = $hanyaMilikSaya ?? false; @endphp
{{ $hanyaSayaSub
    ? 'Ajukan pinjam barang inventaris dan pantau status pengajuan Anda. Untuk jadwal ruangan, buka Booking Ruangan.'
    : 'Kelola peminjaman barang inventaris sekolah. Untuk jadwal pemakaian ruangan, gunakan menu Booking Ruangan.' }}
@endsection

@section('sarpras_actions')
    @can('sarpras.peminjaman.ajukan')
        <a href="{{ route('sarpras.peminjaman.create') }}" class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Ajukan Pinjam
        </a>
    @endcan
    <a href="{{ route('sarpras.booking.index') }}" class="sarpras-google-btn px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
        <i data-lucide="calendar-clock" class="w-4 h-4"></i> Booking Ruangan
    </a>
@endsection

@section('sarpras_body')
@php
    $hanyaSaya = $hanyaMilikSaya ?? false;
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

    <div class="card p-5">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-4">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-500"><i data-lucide="hand-helping" class="w-4 h-4"></i></span>
            {{ $hanyaSaya ? 'Pengajuan Pinjam Saya' : 'Transaksi Peminjaman Barang' }}
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    @unless($hanyaSaya)<th class="pb-2 font-semibold">Peminjam</th>@endunless
                    <th class="pb-2 font-semibold">Barang Dipinjam</th><th class="pb-2 font-semibold">Batas Waktu</th><th class="pb-2 font-semibold">Status</th><th class="pb-2"></th>
                </tr></thead>
                <tbody>
                @forelse($peminjaman as $p)
                    @php [$pl, $pc] = $bStatus[$p->status] ?? [ucfirst($p->status), 'bg-slate-100 text-slate-500']; @endphp
                    <tr class="border-b border-slate-50 dark:border-slate-700/50">
                        @unless($hanyaSaya)
                            <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ $p->peminjam?->name ?? '—' }}</td>
                        @endunless
                        <td class="py-2.5 text-slate-600 dark:text-slate-300">{{ ($p->items_count ?? 0) }} item @if($p->ruangan)· {{ $p->ruangan->kode }}@endif</td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ optional($p->selesai ?? $p->tgl_kembali_rencana)->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-2.5"><span class="badge {{ $pc }}">{{ $pl }}</span></td>
                        <td class="py-2.5"><a href="{{ route('sarpras.peminjaman.show', $p) }}" class="text-blue-600 hover:underline text-xs">Detail</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $hanyaSaya ? 4 : 5 }}" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            Belum ada pengajuan. Klik <span class="font-semibold">Ajukan Pinjam</span> untuk memulai.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-5">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-1">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-500"><i data-lucide="calendar-clock" class="w-4 h-4"></i></span>
            {{ $hanyaSaya ? 'Booking Ruangan Saya' : 'Ringkasan Booking Ruangan' }}
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Kelola jadwal ruang di menu <a href="{{ route('sarpras.booking.index') }}" class="font-bold text-primary hover:underline">Booking Ruangan</a>.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="pb-2 font-semibold">Ruangan</th><th class="pb-2 font-semibold">Kegiatan</th><th class="pb-2 font-semibold">Waktu / Tanggal</th><th class="pb-2 font-semibold">Status</th>
                </tr></thead>
                <tbody>
                @forelse($bookings as $b)
                    @php [$bl, $bc] = $bStatus[$b->status] ?? [ucfirst($b->status), 'bg-slate-100 text-slate-500']; @endphp
                    <tr class="border-b border-slate-50 dark:border-slate-700/50">
                        <td class="py-2.5">
                            <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $b->ruangan?->kode }}</p>
                            <p class="text-[11px] text-slate-400">{{ trim(($b->ruangan?->gedung ?? '').' '.($b->ruangan?->lantai ?? '')) ?: $b->ruangan?->nama }}</p>
                        </td>
                        <td class="py-2.5">
                            <p class="text-slate-700 dark:text-slate-200">{{ $b->keperluan }}</p>
                            @unless($hanyaSaya)
                                <p class="text-[11px] text-slate-400">Oleh: {{ $b->pemohon?->name ?? '—' }}</p>
                            @endunless
                        </td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            {{ $b->mulai->format('d/m/Y') }}<br><span class="text-[11px] text-slate-400">{{ $b->mulai->format('H:i') }} – {{ $b->selesai->format('H:i') }}</span>
                        </td>
                        <td class="py-2.5"><span class="badge {{ $bc }}">{{ $bl }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                            Belum ada booking. Ajukan lewat menu Booking Ruangan.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
