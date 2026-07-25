@extends('layouts.app')
@section('title', 'Kartu ID')

@push('styles')
{{-- Style ASLI kartu (sumber tunggal, sama persis dgn PDF/cetak-massal) — sudah di-scope ke
     .kg-card sendiri, jadi aman disisipkan di tengah halaman app tanpa membocorkan style. --}}
@include('kartu-guru._card-style')
<style>
    /* Bingkai reservasi ukuran kartu yang SUDAH diperbesar (transform:scale tidak mengubah ukuran
       box dalam alur dokumen, jadi wrapper luar perlu ukuran hasil kali skalanya sendiri). */
    .kg-scale-frame { width: calc(54mm * var(--kg-scale)); height: calc(85.6mm * var(--kg-scale)); margin: 0 auto; }
    .kg-scale-inner { width: 54mm; height: 85.6mm; transform: scale(var(--kg-scale)); transform-origin: top left; }
</style>
@endpush

@section('content')
<div class="max-w-md mx-auto" x-data="{ zoomQr: false }">
    <div class="mb-6">
        <h1 class="page-title">Kartu ID</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kartu identitas digitalmu — tunjukkan halaman ini atau perbesar QR-nya untuk absen di kiosk.</p>
    </div>

    @php $g = $card['guru']; @endphp

    {{-- Kartu digital — pakai partial ASLI yang sama dgn PDF/kelola (bukan tiruan Tailwind
         terpisah, supaya tak pernah lagi menyimpang dari desain resmi), diperbesar via CSS scale
         supaya nyaman dilihat di layar HP. --}}
    <div class="kg-scale-frame" style="--kg-scale: 1.65;">
        <div class="kg-scale-inner">
            @include('kartu-guru._card', ['card' => $card])
        </div>
    </div>

    {{-- QR + tombol perbesar — baris QR+teks TERPISAH dari tombol (bukan 3 item flex sebaris)
         supaya di viewport sempit tombol tidak menumpuk/tertimpa di atas teks deskripsi. Padding
         & jarak digenapkan supaya tidak terasa mepet/berdesakan. --}}
    <div class="card p-6 mt-4">
        <div class="flex items-start gap-4">
            <div class="w-20 h-20 flex-shrink-0 bg-white rounded-xl border border-slate-200 grid place-items-center p-2.5">
                <div class="w-full h-full [&_svg]:w-full [&_svg]:h-full">
                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(72)->margin(0)->generate($card['qrPayload']) !!}
                </div>
            </div>
            <div class="flex-1 min-w-0 pt-1">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">QR Absensi</p>
                <p class="text-xs text-slate-400 mt-2 leading-relaxed">Perbesar lalu tunjukkan ke kamera kiosk absensi untuk scan masuk/pulang.</p>
            </div>
        </div>
        <button @click="zoomQr = true" type="button" class="btn-primary w-full mt-5 px-4 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
            <i data-lucide="maximize" class="w-4 h-4"></i> Perbesar
        </button>
    </div>

    <a href="{{ route('kartu-guru.lihat', $g->uuid) }}" target="_blank" rel="noopener"
       class="w-full mt-3 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
        <i data-lucide="download" class="w-4 h-4"></i> Unduh Kartu (PDF)
    </a>

    {{-- Modal QR besar --}}
    <div x-show="zoomQr" x-cloak class="modal-backdrop" @click.self="zoomQr=false" @keydown.escape.window="zoomQr=false">
        <div class="bg-white rounded-3xl p-6 max-w-xs w-full mx-4 text-center shadow-2xl" @click.stop>
            <p class="font-bold text-slate-800">{{ $g->nama }}</p>
            <p class="text-xs text-slate-400 mb-4">{{ $card['nomor'] }}</p>
            <div class="mx-auto w-full [&_svg]:w-full [&_svg]:h-auto">
                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(320)->margin(1)->generate($card['qrPayload']) !!}
            </div>
            <p class="text-xs text-slate-500 mt-4">Tunjukkan layar ini ke kamera kiosk absensi.</p>
            <button @click="zoomQr=false" type="button" class="mt-4 w-full px-4 py-2.5 rounded-xl text-sm font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                Tutup
            </button>
        </div>
    </div>
</div>
@endsection
