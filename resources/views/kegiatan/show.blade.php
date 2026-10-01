@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-gray-800">Detail Kegiatan: {{ $kegiatan->nama_kegiatan }}</h1>
        <div class="flex gap-2 shrink-0">
            <a href="{{ route('kegiatan.pdf', $kegiatan) }}" target="_blank" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium whitespace-nowrap transition-colors shadow-sm">Cetak PDF</a>
            <a href="{{ route('kegiatan.qr', $kegiatan) }}" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium whitespace-nowrap transition-colors shadow-sm">Cetak Semua QR</a>
        </div>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow mb-6">
        <p><strong>Link Pendaftaran Publik:</strong> 
            <a href="{{ route('kegiatan.daftar', $kegiatan) }}" target="_blank" class="text-blue-600 underline">
                {{ route('kegiatan.daftar', $kegiatan) }}
            </a>
        </p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left">No</th>
                    <th class="px-6 py-3 text-left">Nama</th>
                    <th class="px-6 py-3 text-left">Instansi</th>
                    <th class="px-6 py-3 text-left">Token QR</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Waktu Hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kegiatan->pesertas as $i => $peserta)
                <tr>
                    <td class="px-6 py-4">{{ $i + 1 }}</td>
                    <td class="px-6 py-4">{{ $peserta->biodata['nama'] ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $peserta->biodata['instansi'] ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $peserta->qr_token }}</td>
                    <td class="px-6 py-4">
                        @if($peserta->status_kehadiran == 'hadir')
                            <span class="text-green-600 font-bold">Hadir</span>
                        @else
                            <span class="text-gray-500">Belum</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">{{ $peserta->waktu_hadir ? $peserta->waktu_hadir->format('H:i:s') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
