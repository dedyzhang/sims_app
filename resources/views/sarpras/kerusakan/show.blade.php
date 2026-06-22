@extends('sarpras.layouts.app')
@section('title', 'Laporan ' . $kerusakan->kode)

@section('sarpras_body')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $kerusakan->kode }}</h2>
                <p class="text-sm text-gray-500">Dilaporkan oleh {{ $kerusakan->pelapor?->name }} · {{ $kerusakan->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <span class="px-3 py-1 rounded text-xs capitalize bg-gray-100">{{ $kerusakan->status }}</span>
        </div>

        <dl class="mt-4 text-sm grid grid-cols-2 gap-2">
            <dt class="text-gray-500">Objek</dt><dd>{{ $kerusakan->aset?->nama ?? $kerusakan->ruangan?->kode ?? '-' }}</dd>
            <dt class="text-gray-500">Urgensi</dt><dd class="capitalize">{{ $kerusakan->urgensi }}</dd>
        </dl>
        <p class="mt-3 text-sm text-gray-700">{{ $kerusakan->deskripsi }}</p>

        @if ($kerusakan->alasan_tolak)
            <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3">
                <b>Ditolak:</b> {{ $kerusakan->alasan_tolak }}
            </div>
        @endif

        @if ($kerusakan->foto->count())
            <h3 class="font-semibold text-gray-700 mt-5 mb-2 text-sm">Foto</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach ($kerusakan->foto as $f)
                    <a href="{{ $f->url }}" target="_blank">
                        <img loading="lazy" src="{{ $f->url }}" class="w-full h-24 object-cover rounded border">
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Aksi Waka Sarpras --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="font-semibold text-gray-800 mb-3">Tindakan</h3>
        @can('sarpras.kerusakan.kelola')
            @if ($kerusakan->status === 'dilaporkan')
                <form method="POST" action="{{ route('sarpras.kerusakan.terima', $kerusakan) }}" class="mb-3">
                    @csrf
                    <button class="w-full bg-emerald-600 text-white rounded py-2 text-sm">Terima & Buat Order Perbaikan</button>
                </form>
                <form method="POST" action="{{ route('sarpras.kerusakan.tolak', $kerusakan) }}" class="space-y-2">
                    @csrf
                    <textarea name="alasan_tolak" rows="2" placeholder="Alasan penolakan (wajib)" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                    <button class="w-full bg-red-600 text-white rounded py-2 text-sm">Tolak</button>
                </form>
            @else
                <p class="text-sm text-gray-500">Laporan sudah diproses.</p>
            @endif
        @else
            <p class="text-sm text-gray-400">Anda tidak memiliki akses untuk menindak laporan.</p>
        @endcan

        @if ($kerusakan->perbaikan->count())
            <h4 class="font-semibold text-gray-700 mt-5 mb-2 text-sm">Order Perbaikan</h4>
            <ul class="text-sm space-y-1">
                @foreach ($kerusakan->perbaikan as $p)
                    <li><a href="{{ route('sarpras.perbaikan.show', $p) }}" class="text-blue-600 hover:underline">{{ $p->kode }}</a> · {{ $p->status }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
