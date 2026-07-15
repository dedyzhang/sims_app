@extends('layouts.app')
@section('title', 'QR Absensi Hari Ini')

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">QR Absensi</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Pajang QR ini di pintu/lokasi absen.
                @if(($mode ?? 'harian') === 'tetap')
                <span class="font-semibold">QR tetap — tidak berganti tiap hari.</span>
                @else
                <span class="font-semibold">Berganti otomatis setiap hari.</span>
                @endif
            </p>
        </div>
        @unless($isKiosk ?? false)
        <div class="flex items-center gap-2">
            <a href="{{ route('qr.cetak') }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="printer" class="w-4 h-4"></i> Cetak QR
            </a>
            <a href="{{ route('setting.index') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="settings-2" class="w-4 h-4"></i> Atur Lokasi
            </a>
        </div>
        @endunless
    </div>

    @if(!$aktif)
    <div class="card p-4 border-l-4 !border-l-amber-500 text-sm text-amber-700 dark:text-amber-300 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4"></i> Absen QR sedang <b>dinonaktifkan</b>. Aktifkan di <a href="{{ route('setting.index') }}" class="underline">Pengaturan</a>.
    </div>
    @endif
    @if(!$lat || !$lng)
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300 flex items-center gap-2">
        <i data-lucide="map-pin-off" class="w-4 h-4"></i> Lokasi sekolah belum diatur — absen akan ditolak. Atur di <a href="{{ route('setting.index') }}" class="underline">Pengaturan</a>.
    </div>
    @endif

    <div class="card p-8 text-center">
        <div class="inline-block p-4 bg-white rounded-2xl shadow-inner">
            <canvas id="qrCanvas"></canvas>
        </div>
        <p class="mt-5 text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd') }}</p>
        <p class="text-sm text-slate-500">{{ \Carbon\Carbon::parse($tanggal)->isoFormat('D MMMM Y') }}</p>
        <p class="mt-4 text-xs text-slate-400 max-w-sm mx-auto">Siswa & guru memindai QR ini lewat menu <b>Absen QR</b> di HP masing-masing. Absen hanya berhasil bila berada di lokasi sekolah.</p>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
<script>
    new QRious({ element: document.getElementById('qrCanvas'), value: @js($token), size: 280, level: 'H', background:'#fff', foreground:'#0f172a' });
</script>
@endpush
@endsection
