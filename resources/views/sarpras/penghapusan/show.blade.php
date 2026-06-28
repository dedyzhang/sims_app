@extends('sarpras.layouts.app')
@section('title', 'Penghapusan ' . $penghapusan->kode)

@section('sarpras_body')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $penghapusan->kode }}</h2>
                <p class="text-sm text-gray-500">{{ $penghapusan->aset?->kode }} — {{ $penghapusan->aset?->nama }}</p>
            </div>
            <span class="px-3 py-1 rounded text-xs capitalize bg-gray-100">{{ $penghapusan->status }}</span>
        </div>
        <dl class="mt-3 text-sm grid grid-cols-2 gap-2">
            <dt class="text-gray-500">Metode</dt><dd class="capitalize">{{ $penghapusan->metode }}</dd>
            <dt class="text-gray-500">Pengaju</dt><dd>{{ $penghapusan->pengaju?->name }}</dd>
        </dl>
        <p class="mt-3 text-sm text-gray-700">{{ $penghapusan->alasan }}</p>
        @if ($penghapusan->alasan_tolak)
            <div class="mt-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3"><b>Ditolak:</b> {{ $penghapusan->alasan_tolak }}</div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow p-6 space-y-3">
        <h3 class="font-semibold text-gray-800">Tindakan</h3>
        @can('sarpras.penghapusan.setujui')
            @if ($penghapusan->status === 'diajukan')
                <form method="POST" action="{{ route('sarpras.penghapusan.setujui', $penghapusan) }}">@csrf
                    <button class="w-full bg-emerald-600 text-white rounded py-2 text-sm">Setujui</button>
                </form>
                <form method="POST" action="{{ route('sarpras.penghapusan.tolak', $penghapusan) }}" class="space-y-2">@csrf
                    <textarea name="alasan_tolak" rows="2" placeholder="Alasan tolak" class="w-full border rounded px-3 py-2 text-sm"></textarea>
                    <button class="w-full bg-red-600 text-white rounded py-2 text-sm">Tolak</button>
                </form>
            @endif
        @endcan
        <a href="{{ route('sarpras.penghapusan.berita', $penghapusan) }}" target="_blank"
           class="block text-center bg-slate-900 text-white rounded py-2 text-sm">📄 Berita Acara (PDF)</a>
    </div>
</div>
@endsection
