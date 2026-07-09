@extends('layouts.app')
@section('title', 'Kartu Pelajar Digital')

@section('content')
<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <h1 class="page-title">Kartu Pelajar Digital</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kartu pelajar resmimu — dibuat otomatis dari data sekolah. Unduh & simpan di perangkatmu.</p>
    </div>

    @if($kartu)
        {{-- Kartu kustom yang diunggah admin --}}
        <div class="card p-6 space-y-5">
            @if($kartu->isImage())
                <img src="{{ route('kartu-pelajar.lihat') }}" alt="Kartu Pelajar {{ $siswa->nama }}"
                     class="w-full rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
            @else
                <a href="{{ route('kartu-pelajar.lihat') }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-5 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                    <span class="grid place-items-center w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-600 flex-shrink-0"><i data-lucide="file-text" class="w-6 h-6"></i></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $kartu->original_name }}</p>
                        <p class="text-xs text-slate-400">Dokumen PDF — klik untuk membuka</p>
                    </div>
                </a>
            @endif

            <a href="{{ route('kartu-pelajar.unduh') }}"
               class="btn-primary w-full px-5 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                <i data-lucide="download" class="w-4 h-4"></i> Unduh Kartu
            </a>
        </div>
    @else
        {{-- Kartu otomatis dari data siswa --}}
        @php
            $ttl = trim(($siswa->tempat_lahir ?: '') . ($siswa->tanggal_lahir ? ', ' . \Illuminate\Support\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d F Y') : ''));
            $ttl = trim($ttl, ', ') ?: '-';
            $jk  = $siswa->jk === 'P' ? 'Perempuan' : ($siswa->jk === 'L' ? 'Laki-laki' : '-');
        @endphp
        <div class="rounded-2xl overflow-hidden shadow-xl ring-1 ring-black/5 bg-white dark:bg-slate-800">
            {{-- Header --}}
            <div class="px-5 py-4 flex items-center gap-3 text-white bg-gradient-to-br from-blue-500 to-blue-900 relative">
                <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 90% 10%, #fff 0, transparent 40%);"></div>
                @if($logoPath)
                    <div class="w-11 h-11 rounded-full bg-white grid place-items-center flex-shrink-0 relative"><img src="{{ asset('storage/' . $logoPath) }}" class="w-8 h-8 object-contain" alt="Logo"></div>
                @else
                    <span class="grid place-items-center w-11 h-11 rounded-full bg-white/25 flex-shrink-0 relative"><i data-lucide="graduation-cap" class="w-5 h-5"></i></span>
                @endif
                <div class="min-w-0 relative">
                    <p class="font-bold leading-tight truncate">{{ $sekolah['nama'] }}</p>
                    <p class="text-[11px] text-white/80 truncate">{{ $sekolah['alamat'] }}@if($sekolah['npsn']) • NPSN {{ $sekolah['npsn'] }}@endif</p>
                </div>
            </div>

            {{-- Ribbon --}}
            <p class="text-center text-[11px] font-bold tracking-[0.35em] text-white py-1.5 bg-blue-500">KARTU TANDA PELAJAR</p>

            {{-- Body --}}
            <div class="px-5 py-5 flex gap-4">
                <div class="flex-1 min-w-0 text-sm">
                    <p class="text-lg font-black text-slate-800 dark:text-slate-100 leading-tight break-words">{{ $siswa->nama }}</p>
                    <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold mb-3">NIS {{ $siswa->nis ?: '-' }}</p>
                    <dl class="space-y-1.5">
                        @foreach([
                            'Tempat, Tgl Lahir' => $ttl,
                            'Jenis Kelamin' => $jk,
                            'Agama' => $siswa->agama ?: '-',
                            'Alamat' => $siswa->alamat ?: '-',
                        ] as $label => $val)
                        <div class="flex gap-2">
                            <dt class="w-28 flex-shrink-0 text-xs text-slate-400 pt-0.5">{{ $label }}</dt>
                            <dd class="flex-1 min-w-0 text-[13px] font-semibold text-slate-700 dark:text-slate-200 break-words">{{ $val }}</dd>
                        </div>
                        @endforeach
                    </dl>
                </div>
                <div class="flex-shrink-0 w-24 text-center">
                    <div class="w-24 [&_svg]:w-full [&_svg]:h-auto">
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(96)->margin(0)->generate((string) $qrPayload) !!}
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">{{ $siswa->nis ?: $siswa->nisn }}</p>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-3 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3">
                <div>
                    <p class="text-[10px] text-slate-400 uppercase tracking-wide">Masa Berlaku</p>
                    <span class="inline-flex items-center gap-1 mt-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 px-2.5 py-1 text-xs font-bold text-blue-700 dark:text-blue-400"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Sampai Tamat Sekolah</span>
                </div>
                @if($sekolah['kepala'])
                    <div class="text-right text-[11px] text-slate-500 dark:text-slate-400">
                        <p>Kepala Sekolah</p>
                        <p class="font-bold text-slate-700 dark:text-slate-200 mt-0.5">{{ $sekolah['kepala'] }}</p>
                    </div>
                @endif
            </div>
        </div>

        <a href="{{ route('kartu-pelajar.unduh') }}"
           class="w-full mt-5 px-5 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white transition">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh Kartu (PDF)
        </a>
        <p class="text-xs text-slate-400 text-center mt-2">Dibuat otomatis dari data sekolah — siap dicetak.</p>
    @endif
</div>
@endsection
