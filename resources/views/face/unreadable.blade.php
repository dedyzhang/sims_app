@extends('layouts.app')
@section('title', 'Cek Wajah Tak Terdeteksi')

@section('content')
<div class="space-y-5" x-data="{ zoomSrc:null }">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('wajah.galeri') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title">Cek Wajah Tak Terdeteksi</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Wajah yang datanya rusak/kosong atau sampelnya tidak konsisten — berpotensi gagal dikenali saat scan absensi.</p>
            </div>
        </div>
    </div>

    @if(empty($items))
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="shield-check" class="w-12 h-12 mx-auto mb-3 text-emerald-400 opacity-60"></i>
        <p class="font-medium text-slate-600 dark:text-slate-300">Semua wajah terdaftar aman.</p>
        <p class="text-sm mt-1">Tidak ada data wajah yang rusak atau sampelnya tidak konsisten.</p>
    </div>
    @else
    @php
        $critical = collect($items)->where('level', 'critical');
        $warning = collect($items)->where('level', 'warning');
    @endphp
    <p class="text-sm text-slate-500">
        Ditemukan <span class="font-bold text-rose-600">{{ $critical->count() }}</span> wajah kemungkinan besar
        <span class="font-semibold">tidak akan terdeteksi</span>, dan
        <span class="font-bold text-amber-600">{{ $warning->count() }}</span> wajah dengan konsistensi rendah.
        Disarankan daftar ulang wajah untuk semua yang tercantum di bawah.
    </p>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($items as $it)
        <div class="card p-4 border-l-4 {{ $it['level']==='critical' ? 'border-l-rose-500' : 'border-l-amber-500' }}">
            <div class="flex items-start gap-3">
                <div class="w-14 h-14 rounded-xl overflow-hidden flex-shrink-0 grid place-items-center text-white text-lg font-bold bg-slate-300 dark:bg-slate-600">
                    @if($it['foto'])
                    <img src="{{ $it['foto'] }}" loading="lazy" class="w-full h-full object-cover cursor-zoom-in" @click="zoomSrc=@js($it['foto'])">
                    @else
                    {{ strtoupper(substr($it['nama'],0,1)) }}
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate">{{ $it['nama'] }}</p>
                        <span class="text-[10px] px-1.5 py-0.5 rounded flex-shrink-0 {{ $it['tipe']==='guru' ? 'bg-primary-50 text-primary' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }}">{{ ucfirst($it['tipe']) }}</span>
                    </div>
                    <span class="badge mt-1.5 {{ $it['level']==='critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300' }} font-bold inline-flex items-center gap-1">
                        <i data-lucide="{{ $it['level']==='critical' ? 'circle-x' : 'triangle-alert' }}" class="w-3 h-3"></i> {{ $it['issue'] }}
                    </span>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">{{ $it['detail'] }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Zoom --}}
    <div x-show="zoomSrc" x-cloak class="modal-backdrop" style="display:none" @click="zoomSrc=null" x-transition>
        <div @click.stop class="text-center">
            <img :src="zoomSrc" class="max-h-[72vh] max-w-[90vw] rounded-2xl shadow-2xl ring-4 ring-white/20">
            <p class="text-white/60 text-xs mt-3">Klik di mana saja untuk menutup</p>
        </div>
    </div>
</div>
@endsection
