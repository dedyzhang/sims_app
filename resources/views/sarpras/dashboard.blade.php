@extends('sarpras.layouts.app')
@section('title', 'Dashboard Sarpras')

@section('sarpras_body')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @php
        $card = function ($label, $val, $color) {
            return '<div class="bg-white rounded-lg shadow p-5">
                <div class="text-sm text-gray-500">'.$label.'</div>
                <div class="text-2xl font-bold '.$color.'">'.$val.'</div></div>';
        };
    @endphp
    {!! $card('Total Aset', number_format($totalAset, 0, ',', '.'), 'text-slate-800') !!}
    {!! $card('Kerusakan Terbuka', $kerusakanTerbuka, 'text-red-600') !!}
    {!! $card('Peminjaman Aktif', $peminjamanAktif, 'text-amber-600') !!}
    {!! $card('Pengadaan Pending', $pengadaanPending, 'text-blue-600') !!}
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
    <div class="bg-white rounded-lg shadow p-5 lg:col-span-1">
        <h3 class="font-semibold text-gray-800 mb-3">Nilai Total Aset</h3>
        <p class="text-3xl font-bold text-emerald-700">{{ $nilaiTotalRp }}</p>
        <p class="text-xs text-gray-400 mt-1">Dihitung via BCMath (integer rupiah)</p>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Aset per Kondisi</h3>
        <ul class="text-sm space-y-1">
            @forelse ($asetPerKondisi as $kondisi => $jml)
                <li class="flex justify-between border-b py-1">
                    <span class="capitalize">{{ str_replace('_', ' ', $kondisi) }}</span>
                    <span class="font-semibold">{{ $jml }}</span>
                </li>
            @empty
                <li class="text-gray-400">Belum ada data.</li>
            @endforelse
        </ul>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <h3 class="font-semibold text-gray-800 mb-3">Aset per Kategori</h3>
        <ul class="text-sm space-y-1">
            @forelse ($asetPerKategori as $row)
                <li class="flex justify-between border-b py-1">
                    <span>{{ $row->kategori?->nama ?? 'Tanpa Kategori' }}</span>
                    <span class="font-semibold">{{ $row->jml }}</span>
                </li>
            @empty
                <li class="text-gray-400">Belum ada data.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-5 mt-6">
    <h3 class="font-semibold text-gray-800 mb-3">Laporan Kerusakan Terbaru</h3>
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2">Kode</th><th>Pelapor</th><th>Urgensi</th><th>Status</th><th>Waktu</th>
        </tr></thead>
        <tbody>
        @forelse ($kerusakanTerbaru as $k)
            <tr class="border-b">
                <td class="py-2"><a class="text-blue-600 hover:underline" href="{{ route('sarpras.kerusakan.show', $k) }}">{{ $k->kode }}</a></td>
                <td>{{ $k->pelapor?->name }}</td>
                <td class="capitalize">{{ $k->urgensi }}</td>
                <td class="capitalize">{{ $k->status }}</td>
                <td>{{ $k->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-3 text-gray-400">Belum ada laporan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
