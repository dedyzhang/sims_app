@extends('layouts.app')
@section('title', 'Wajib Scan QR Ruangan')

@section('content')
<div class="max-w-md mx-auto">
    <div class="card p-8 text-center space-y-4">
        <i data-lucide="qr-code" class="w-14 h-14 text-amber-500 mx-auto"></i>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Anda Belum Scan QR Ruangan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
            Untuk mengikuti <span class="font-semibold">{{ $ujian->paket?->nama }}</span>, Anda harus scan QR yang ditempel di ruangan ujian Anda terlebih dahulu.
            Setelah scan sekali, Anda bisa langsung mengikuti seluruh ujian hari ini tanpa perlu scan ulang per mata pelajaran.
        </p>
        @if($ruangan)
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Ruangan Anda: {{ $ruangan->nama }}</p>
        @else
        <p class="text-xs text-amber-600 dark:text-amber-400">Anda belum terdaftar di ruangan ujian manapun — hubungi panitia ujian.</p>
        @endif
        <x-qr-scan-button label="Scan Sekarang" />
        <a href="{{ route('ujian.siswa.gate', $ujian) }}" class="block text-xs text-slate-400 hover:text-primary">Sudah scan? Muat ulang halaman ini</a>
        <a href="{{ route('ujian.siswa.index') }}" class="block text-xs text-slate-400 hover:text-primary">Kembali ke Daftar Ujian</a>
    </div>
</div>
@endsection
