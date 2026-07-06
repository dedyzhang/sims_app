@extends('sarpras.layouts.app')
@section('title', 'Aset ' . $aset->kode)

@section('sarpras_body')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $aset->nama }}</h2>
                <p class="text-sm text-gray-500">{{ $aset->kode }}</p>
            </div>
            <div class="flex items-center gap-2">
                @can('sarpras.aset.label')
                    <a href="{{ route('sarpras.aset.label', $aset) }}" target="_blank" 
                       class="inline-flex items-center gap-1.5 bg-[#fff1f2] text-[#9f1239] border border-[#fecdd3] px-4 py-2 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#ffe4e6]">
                        <i data-lucide="printer" class="w-4 h-4"></i> Cetak Label
                    </a>
                @endcan
                @can('sarpras.aset.kelola')
                    <a href="{{ route('sarpras.aset.edit', $aset) }}" 
                       class="inline-flex items-center gap-1.5 bg-[#f8fafc] text-[#1e293b] border border-[#cbd5e1] px-4 py-2 rounded-full text-xs sm:text-sm font-bold transition-all duration-200 shadow-sm hover:bg-[#f1f5f9]">
                        <i data-lucide="edit-2" class="w-4 h-4"></i> Edit
                    </a>
                @endcan
            </div>
        </div>

        @if ($aset->foto_path)
            <img src="{{ Storage::url($aset->foto_path) }}" class="mb-4 max-h-60 rounded border object-contain">
        @endif

        <dl class="text-sm grid grid-cols-2 gap-2">
            <dt class="text-gray-500">Kategori</dt><dd>{{ $aset->kategori?->nama ?? '-' }}</dd>
            <dt class="text-gray-500">Ruangan</dt><dd>{{ $aset->ruangan?->kode ?? '-' }}</dd>
            <dt class="text-gray-500">Merk</dt><dd>{{ $aset->merk ?? '-' }}</dd>
            <dt class="text-gray-500">Kondisi</dt><dd>{{ $aset->kondisi_label }}</dd>
            <dt class="text-gray-500">Status</dt><dd class="capitalize">{{ $aset->status }}</dd>
            <dt class="text-gray-500">Tgl Perolehan</dt><dd>{{ optional($aset->tgl_perolehan)->format('d/m/Y') ?? '-' }}</dd>
            <dt class="text-gray-500">Nilai Perolehan</dt><dd class="font-semibold">{{ $aset->nilai_perolehan_rp }}</dd>
            <dt class="text-gray-500">Sumber Dana</dt><dd>{{ $aset->sumber_dana ?? '-' }}</dd>
        </dl>

        @if (!empty($aset->spesifikasi))
            <h3 class="font-semibold text-gray-700 mt-5 mb-2 text-sm">Spesifikasi</h3>
            <table class="text-sm w-full">
                @foreach ($aset->spesifikasi as $k => $v)
                    <tr class="border-b"><td class="py-1 text-gray-500 w-1/3">{{ $k }}</td><td>{{ $v }}</td></tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="space-y-4">
        <div class="bg-white rounded-lg shadow p-5 text-center">
            <h3 class="font-semibold text-gray-700 mb-2 text-sm">QR Code</h3>
            {{-- QR berisi URL detail aset (SVG inline) --}}
            <img src="{{ route('sarpras.aset.qr', $aset) }}" alt="QR {{ $aset->kode }}" class="mx-auto w-40 h-40">
            <p class="text-xs text-gray-400 mt-2">Scan untuk membuka detail aset</p>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-gray-700 mb-3 text-sm">Riwayat Perubahan</h3>
            <ul class="text-xs space-y-2 max-h-72 overflow-y-auto">
                @foreach($riwayat as $r)
                    <li class="border-l-2 border-gray-200 pl-2">
                        <span class="capitalize font-medium">{{ $r->description ?? $r->event }}</span>
                        <span class="text-gray-400 block">{{ $r->created_at->format('d/m/Y H:i') }} · {{ $r->causer?->name ?? 'sistem' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection
