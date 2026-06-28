@extends('sarpras.layouts.app')
@section('title', 'Peminjaman ' . $peminjaman->kode)

@section('sarpras_body')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $peminjaman->kode }}</h2>
                <p class="text-sm text-gray-500">{{ $peminjaman->peminjam?->name }}</p>
            </div>
            <span class="px-3 py-1 rounded text-xs capitalize bg-gray-100">{{ $peminjaman->status }}</span>
        </div>
        <dl class="mt-3 text-sm grid grid-cols-2 gap-2">
            <dt class="text-gray-500">Keperluan</dt><dd>{{ $peminjaman->keperluan }}</dd>
            <dt class="text-gray-500">Ruangan</dt>
            <dd>{{ $peminjaman->ruangan ? $peminjaman->ruangan->kode . ' — ' . $peminjaman->ruangan->nama : '—' }}</dd>
            <dt class="text-gray-500">Mulai</dt>
            <dd>{{ optional($peminjaman->mulai)->format('d/m/Y H:i') ?? $peminjaman->tgl_pinjam->format('d/m/Y') }}</dd>
            <dt class="text-gray-500">Selesai</dt>
            <dd>{{ optional($peminjaman->selesai)->format('d/m/Y H:i') ?? $peminjaman->tgl_kembali_rencana->format('d/m/Y') }}</dd>
            <dt class="text-gray-500">Kembali Aktual</dt><dd>{{ optional($peminjaman->tgl_kembali_aktual)->format('d/m/Y') ?? '-' }}</dd>
        </dl>

        @if ($peminjaman->items->isNotEmpty())
            <h3 class="font-semibold text-gray-700 mt-5 mb-2 text-sm">Aset Dipinjam</h3>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b"><th class="py-1">Kode</th><th>Nama</th><th>Qty</th></tr></thead>
                <tbody>
                @foreach ($peminjaman->items as $it)
                    <tr class="border-b"><td class="py-1">{{ $it->aset?->kode }}</td><td>{{ $it->aset?->nama }}</td><td>{{ $it->qty }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($peminjaman->alasan_tolak)
            <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3"><b>Ditolak:</b> {{ $peminjaman->alasan_tolak }}</div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Tindakan</h3>
        @can('sarpras.peminjaman.setujui')
            @if ($peminjaman->status === 'diajukan')
                <form method="POST" action="{{ route('sarpras.peminjaman.setujui', $peminjaman) }}" class="mb-2">@csrf
                    <button class="w-full bg-emerald-600 text-white rounded py-2 text-sm">Setujui (tandai dipinjam)</button>
                </form>
                <form method="POST" action="{{ route('sarpras.peminjaman.tolak', $peminjaman) }}" class="space-y-2">@csrf
                    <textarea name="alasan_tolak" rows="2" placeholder="Alasan tolak" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                    <button class="w-full bg-red-600 text-white rounded py-2 text-sm">Tolak</button>
                </form>
            @endif
        @endcan
        @can('sarpras.peminjaman.kelola')
            @if (in_array($peminjaman->status, ['dipinjam','terlambat']))
                <form method="POST" action="{{ route('sarpras.peminjaman.kembalikan', $peminjaman) }}" class="mt-2">@csrf
                    <button class="w-full bg-blue-600 text-white rounded py-2 text-sm">Tandai Dikembalikan</button>
                </form>
            @endif
        @endcan
    </div>
</div>
@endsection
