@extends('sarpras.layouts.app')
@section('title', 'Dashboard Sarpras')

@section('sarpras_body')
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @php
        // Kartu statistik beraksen warna: garis atas, chip ikon, & angka berwarna senada.
        $card = function ($label, $val, $tema, $icon, $href) {
            $c = [
                'slate'   => ['from-slate-400 to-slate-600',   'bg-slate-100 text-slate-600',   'text-slate-800'],
                'red'     => ['from-red-400 to-red-600',       'bg-red-100 text-red-600',       'text-red-600'],
                'amber'   => ['from-amber-400 to-amber-600',   'bg-amber-100 text-amber-600',   'text-amber-600'],
                'blue'    => ['from-blue-400 to-blue-600',     'bg-blue-100 text-blue-600',     'text-blue-600'],
            ][$tema];
            return '<a href="'.$href.'" class="group relative block bg-white rounded-lg shadow overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition">
                <div class="h-1.5 bg-gradient-to-r '.$c[0].'"></div>
                <div class="p-5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="grid place-items-center w-9 h-9 rounded-lg '.$c[1].'"><i data-lucide="'.$icon.'" class="w-5 h-5"></i></span>
                        <i data-lucide="arrow-up-right" class="w-4 h-4 text-gray-300 group-hover:text-gray-500 transition"></i>
                    </div>
                    <div class="text-sm text-gray-500">'.$label.'</div>
                    <div class="text-2xl font-bold '.$c[2].'">'.$val.'</div>
                </div></a>';
        };
    @endphp
    {!! $card('Total Aset', number_format($totalAset, 0, ',', '.'), 'slate', 'package', route('sarpras.aset.index')) !!}
    {!! $card('Kerusakan Terbuka', $kerusakanTerbuka, 'red', 'alert-triangle', route('sarpras.kerusakan.index')) !!}
    {!! $card('Peminjaman Aktif', $peminjamanAktif, 'amber', 'hand-helping', route('sarpras.peminjaman.index')) !!}
    {!! $card('Pengadaan Pending', $pengadaanPending, 'blue', 'shopping-cart', route('sarpras.pengadaan.index')) !!}
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-6">
    <div class="bg-white rounded-lg shadow p-5 lg:col-span-1">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-800">Nilai Total Aset</h3>
            <a href="{{ route('sarpras.laporan.index') }}" class="text-xs font-semibold text-emerald-700 hover:underline">Laporan &rarr;</a>
        </div>
        <p class="text-3xl font-bold text-emerald-700">{{ $nilaiTotalRp }}</p>
        <p class="text-xs text-gray-400 mt-1">Dihitung via BCMath (integer rupiah)</p>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-800">Aset per Kondisi</h3>
            <a href="{{ route('sarpras.aset.index') }}" class="text-xs font-semibold text-blue-600 hover:underline">Semua &rarr;</a>
        </div>
        @php
            $kondisiWarna = [
                'baik' => 'bg-emerald-500',
                'rusak_ringan' => 'bg-amber-500',
                'rusak_berat' => 'bg-red-500',
                'hilang' => 'bg-gray-400',
            ];
            $totalKondisi = max(1, (int) $asetPerKondisi->sum());
        @endphp
        <ul class="text-sm space-y-2.5">
            @forelse ($asetPerKondisi as $kondisi => $jml)
                @php $pct = round($jml / $totalKondisi * 100); @endphp
                <li>
                    <a href="{{ route('sarpras.aset.index', ['kondisi' => $kondisi]) }}" class="group block">
                        <div class="flex justify-between mb-1">
                            <span class="capitalize flex items-center gap-1.5 group-hover:text-blue-600">
                                <span class="w-2.5 h-2.5 rounded-full {{ $kondisiWarna[$kondisi] ?? 'bg-slate-400' }}"></span>
                                {{ str_replace('_', ' ', $kondisi) }}
                            </span>
                            <span class="font-semibold text-gray-700">{{ $jml }} <span class="text-gray-400 font-normal">({{ $pct }}%)</span></span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $kondisiWarna[$kondisi] ?? 'bg-slate-400' }} transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="text-gray-400">Belum ada data.</li>
            @endforelse
        </ul>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-gray-800">Aset per Kategori</h3>
            <a href="{{ route('sarpras.aset.index') }}" class="text-xs font-semibold text-blue-600 hover:underline">Semua &rarr;</a>
        </div>
        <ul class="text-sm space-y-1">
            @forelse ($asetPerKategori as $row)
                <li>
                    <a href="{{ route('sarpras.aset.index', ['kategori_id' => $row->kategori_id]) }}" class="flex justify-between border-b py-1 hover:text-blue-600">
                        <span>{{ $row->kategori?->nama ?? 'Tanpa Kategori' }}</span>
                        <span class="font-semibold">{{ $row->jml }}</span>
                    </a>
                </li>
            @empty
                <li class="text-gray-400">Belum ada data.</li>
            @endforelse
        </ul>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-5 mt-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-gray-800">Laporan Kerusakan Terbaru</h3>
        <a href="{{ route('sarpras.kerusakan.index') }}" class="text-xs font-semibold text-red-600 hover:underline">Lihat semua &rarr;</a>
    </div>
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
