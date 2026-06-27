@extends('sarpras.layouts.app')
@section('title', 'Pengadaan ' . $pengadaan->kode)

@section('sarpras_body')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $pengadaan->judul }}</h2>
                <p class="text-sm text-gray-500">{{ $pengadaan->kode }} · {{ $pengadaan->pengaju?->name }}</p>
            </div>
            <span class="px-3 py-1 rounded text-xs capitalize bg-gray-100">{{ $pengadaan->status }}</span>
        </div>
        <p class="mt-2 text-sm text-gray-700">{{ $pengadaan->deskripsi }}</p>

        <table class="w-full text-sm mt-4">
            <thead><tr class="text-left text-gray-500 border-b">
                <th class="py-2">Barang</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Diterima</th>
            </tr></thead>
            <tbody>
            @foreach ($pengadaan->items as $it)
                <tr class="border-b">
                    <td class="py-2">{{ $it->nama_barang }} <span class="text-gray-400 text-xs">{{ $it->kategori?->nama }}</span></td>
                    <td>{{ $it->qty }} {{ $it->satuan }}</td>
                    <td>{{ \App\Sarpras\Support\Rupiah::format($it->estimasi_harga) }}</td>
                    <td>{{ $it->subtotal_rp }}</td>
                    <td>{{ $it->qty_diterima }} {{ $it->kondisi_terima ? '('.$it->kondisi_terima.')' : '' }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="font-semibold"><td colspan="3" class="py-2 text-right">Total Estimasi</td><td colspan="2">{{ $pengadaan->total_estimasi_rp }}</td></tr></tfoot>
        </table>

        @if ($pengadaan->alasan_tolak)
            <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3"><b>Ditolak:</b> {{ $pengadaan->alasan_tolak }}</div>
        @endif

        {{-- Pencatatan penerimaan --}}
        @can('sarpras.pengadaan.kelola')
            @if ($pengadaan->status === 'disetujui')
                <h3 class="font-semibold text-gray-700 mt-6 mb-2 text-sm">Catat Penerimaan</h3>
                <form method="POST" action="{{ route('sarpras.pengadaan.terima', $pengadaan) }}" class="space-y-2 text-sm">
                    @csrf
                    <input type="date" name="tgl_terima" value="{{ now()->format('Y-m-d') }}" class="border rounded px-3 py-2">
                    @foreach ($pengadaan->items as $it)
                        <div class="flex gap-2 items-center">
                            <span class="w-1/3">{{ $it->nama_barang }}</span>
                            <input type="number" min="0" name="qty_diterima[{{ $it->id }}]" placeholder="qty diterima" class="border rounded px-2 py-1 w-32">
                            <input name="kondisi_terima[{{ $it->id }}]" placeholder="kondisi" class="border rounded px-2 py-1">
                        </div>
                    @endforeach
                    <button class="bg-emerald-600 text-white rounded px-4 py-2">Simpan Penerimaan</button>
                </form>
            @endif
        @endcan
    </div>

    <div class="space-y-4">
        @can('sarpras.pengadaan.setujui')
            @if ($pengadaan->status === 'diajukan')
                <div class="bg-white rounded-lg shadow p-5">
                    <form method="POST" action="{{ route('sarpras.pengadaan.setujui', $pengadaan) }}" class="mb-2">@csrf
                        <button class="w-full bg-emerald-600 text-white rounded py-2 text-sm">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route('sarpras.pengadaan.tolak', $pengadaan) }}" class="space-y-2">@csrf
                        <textarea name="alasan_tolak" rows="2" placeholder="Alasan tolak" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                        <button class="w-full bg-red-600 text-white rounded py-2 text-sm">Tolak</button>
                    </form>
                </div>
            @endif
        @endcan

        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-2 text-sm">Dokumen / Nota</h3>
            @forelse ($pengadaan->dokumen as $d)
                <a href="{{ $d->url }}" target="_blank" class="block text-blue-600 hover:underline text-sm">{{ $d->nama }}</a>
            @empty
                <p class="text-gray-400 text-sm">Belum ada dokumen.</p>
            @endforelse
            @can('sarpras.pengadaan.kelola')
                <form method="POST" action="{{ route('sarpras.pengadaan.dokumen', $pengadaan) }}" enctype="multipart/form-data" class="mt-3 space-y-2 text-sm">
                    @csrf
                    <input name="nama" placeholder="Nama dokumen" required class="w-full border rounded px-3 py-2">
                    <input name="file" type="file" accept="image/*" required class="w-full text-xs">
                    <button class="w-full bg-slate-900 text-white rounded py-2">Upload (dikompres ≤2MB)</button>
                </form>
            @endcan
        </div>
    </div>
</div>
@endsection
