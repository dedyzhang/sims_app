@extends('layouts.app')
@section('title', 'Dashboard Piket & Substitusi')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">Dashboard Piket & Substitusi</h1>
            <p class="text-sm text-slate-500 mt-1">Pemantauan real-time kondisi piket dan ketidakhadiran guru hari ini.</p>
        </div>
        
        <form method="GET" action="{{ route('piket.dashboard') }}" class="flex items-center gap-2">
            <input type="date" name="tanggal" value="{{ $tanggal }}" class="form-input text-sm !py-2" onchange="this.form.submit()">
            @if($tanggal !== now()->toDateString())
            <a href="{{ route('piket.dashboard') }}" class="btn-white px-3 py-2 text-xs font-semibold rounded-lg shadow-sm">Hari Ini</a>
            @endif
        </form>
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card: Guru Piket -->
        <div class="card p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                </div>
                <h3 class="font-semibold text-slate-700">Guru Piket</h3>
            </div>
            @if($summary['piket']->isEmpty())
                <p class="text-2xl font-bold text-slate-800">Tidak ada</p>
            @else
                @foreach($summary['piket'] as $p)
                <p class="text-lg font-bold text-slate-800">{{ $p->guru?->nama }}</p>
                @endforeach
            @endif
            <p class="text-xs text-slate-500 mt-2">Bertugas hari ini</p>
        </div>

        <!-- Card: Guru Tidak Hadir -->
        <div class="card p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                    <i data-lucide="user-x" class="w-5 h-5"></i>
                </div>
                <h3 class="font-semibold text-slate-700">Tidak Hadir</h3>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ $summary['tidak_hadir'] }} <span class="text-sm font-normal text-slate-500">guru</span></p>
            <p class="text-xs text-slate-500 mt-2">
                <a href="{{ route('piket.tidak-hadir', ['tanggal' => $tanggal]) }}" class="text-primary hover:underline">Lihat rincian &rarr;</a>
            </p>
        </div>

        <!-- Card: Jam Kosong (Total) -->
        <div class="card p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <h3 class="font-semibold text-slate-700">Jam Kosong</h3>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ $summary['jam_kosong'] }} <span class="text-sm font-normal text-slate-500">jam</span></p>
            <p class="text-xs text-slate-500 mt-2">Akibat guru tidak hadir</p>
        </div>

        <!-- Card: Tercover -->
        <div class="card p-5">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full {{ $summary['belum_tercover'] > 0 ? 'bg-orange-100 text-orange-600' : 'bg-emerald-100 text-emerald-600' }} flex items-center justify-center">
                    <i data-lucide="{{ $summary['belum_tercover'] > 0 ? 'alert-circle' : 'check-circle' }}" class="w-5 h-5"></i>
                </div>
                <h3 class="font-semibold text-slate-700">Status Penugasan</h3>
            </div>
            <p class="text-3xl font-bold text-slate-800">{{ $summary['tercover'] }} <span class="text-sm font-normal text-slate-500">/ {{ $summary['jam_kosong'] }} jam</span></p>
            @if($summary['belum_tercover'] > 0)
                <p class="text-xs font-semibold text-orange-600 mt-2">{{ $summary['belum_tercover'] }} jam belum ter-cover!</p>
            @else
                <p class="text-xs text-emerald-600 font-semibold mt-2">Semua aman (ter-cover)</p>
            @endif
        </div>
    </div>
    
    <div class="flex gap-4">
        <a href="{{ route('piket.rekap') }}" class="btn-primary px-4 py-2 text-sm rounded-lg shadow-sm">
            <i data-lucide="file-bar-chart" class="w-4 h-4 mr-2 inline-block"></i> Rekap Bulanan
        </a>
    </div>
</div>
@endsection
