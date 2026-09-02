@extends('layouts.app')
@section('title', 'Kehadiran — ' . $ruangan->nama)

@section('content')
<div class="max-w-sm mx-auto mt-6">
    <div class="card p-8 text-center space-y-3">
        <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto">
            <i data-lucide="check" class="w-8 h-8"></i>
        </div>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">
            {{ $baruSajaDicatat ? 'Kehadiran Tercatat' : 'Sudah Tercatat Hadir' }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $siswa->nama }}</p>
        <div class="pt-2 border-t border-slate-100 dark:border-slate-700 text-sm space-y-1">
            <p class="text-slate-600 dark:text-slate-300"><span class="font-semibold">Ruangan:</span> {{ $ruangan->nama }}</p>
            <p class="text-slate-600 dark:text-slate-300"><span class="font-semibold">Status:</span> {{ $hadir->statusLabel() }}</p>
            <p class="text-slate-600 dark:text-slate-300"><span class="font-semibold">Pukul:</span> {{ $hadir->dicatat_pada?->translatedFormat('H:i, d M Y') }}</p>
        </div>
        @if($sesiList->count() > 1)
        <div class="pt-2 border-t border-slate-100 dark:border-slate-700 text-xs text-left">
            <p class="font-semibold text-slate-500 mb-1">Mapel yang bisa Anda ikuti hari ini:</p>
            <ul class="list-disc list-inside text-slate-600 dark:text-slate-300 space-y-0.5">
                @foreach($sesiList as $s)
                <li>{{ $s->mapelNama() }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <p class="text-xs text-slate-400 pt-2">Anda boleh menutup halaman ini dan menunggu instruksi pengawas.</p>
    </div>
</div>
@endsection
