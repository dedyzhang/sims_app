@extends('layouts.app')

@section('title', 'Manajemen Kegiatan')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Manajemen Kegiatan Acara</h1>
        <a href="{{ route('kegiatan.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium inline-block">
            + Buat Kegiatan Baru
        </a>
    </div>

    <!-- Data -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Acara</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tempat</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendaftar</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($kegiatans as $kegiatan)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $kegiatan->nama_kegiatan }}</div>
                        <div class="text-sm text-gray-500">{{ Str::limit($kegiatan->tema ?? $kegiatan->deskripsi, 40) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $kegiatan->tanggal_mulai->translatedFormat('d F Y') }}<br>{{ $kegiatan->waktu_mulai ?? '07:30' }} s/d selesai
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ $kegiatan->tempat ?? 'Menyusul' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            {{ ucfirst($kegiatan->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-bold">
                        {{ $kegiatan->pesertas_count }} Peserta
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('kegiatan.show', $kegiatan) }}" class="text-blue-600 hover:text-blue-900" title="Detail & Pendaftar">
                                <i data-lucide="eye" class="w-5 h-5"></i>
                            </a>
                            <a href="{{ route('kegiatan.edit', $kegiatan) }}" class="text-amber-500 hover:text-amber-700" title="Edit Kegiatan">
                                <i data-lucide="edit" class="w-5 h-5"></i>
                            </a>
                            <a href="{{ route('kegiatan.qr', $kegiatan) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900" title="Cetak Semua QR">
                                <i data-lucide="qr-code" class="w-5 h-5"></i>
                            </a>
                            <a href="{{ route('kegiatan.pdf', $kegiatan) }}" target="_blank" class="text-purple-600 hover:text-purple-900" title="Cetak Daftar Hadir">
                                <i data-lucide="printer" class="w-5 h-5"></i>
                            </a>
                            <form action="{{ route('kegiatan.destroy', $kegiatan) }}" method="POST" class="inline-block" onsubmit="return confirm('Hapus kegiatan ini beserta seluruh data pesertanya?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700" title="Hapus">
                                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">Belum ada kegiatan.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
