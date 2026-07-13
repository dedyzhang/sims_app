@extends('layouts.app')
@section('title', $rapat->judul)

@section('content')
<div class="space-y-5">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div class="min-w-0">
            <a href="{{ route('rapat.index') }}" class="text-xs text-slate-400 hover:text-primary flex items-center gap-1 mb-1"><i data-lucide="arrow-left" class="w-3 h-3"></i> Agenda Rapat</a>
            <h1 class="page-title break-words">{{ $rapat->judul }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1.5">
                <i data-lucide="calendar" class="w-3.5 h-3.5"></i> {{ $rapat->tanggal->isoFormat('dddd, D MMMM Y') }}
                @if($rapat->pencatat)<span class="text-slate-300 dark:text-slate-600">&bull;</span> dicatat oleh {{ $rapat->pencatat->nama }}@endif
            </p>
        </div>
        @if($canManage)
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('rapat.hadir', $rapat) }}" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="user-check" class="w-4 h-4"></i> Absensi
            </a>
            <a href="{{ route('rapat.dokumentasi', $rapat) }}" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="image-plus" class="w-4 h-4"></i> Dokumentasi
            </a>
            <a href="{{ route('rapat.cetak', $rapat) }}" target="_blank" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                <i data-lucide="printer" class="w-4 h-4"></i> Cetak
            </a>
            <a href="{{ route('rapat.edit', $rapat) }}" class="btn-primary flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition">
                <i data-lucide="pencil" class="w-4 h-4"></i> Ubah
            </a>
            <form method="POST" action="{{ route('rapat.destroy', $rapat) }}" onsubmit="return confirmDelete(this)">
                @csrf @method('DELETE')
                <button class="grid place-items-center w-10 h-10 rounded-xl border border-slate-200 dark:border-slate-600 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            </form>
        </div>
        @else
        <a href="{{ route('rapat.cetak', $rapat) }}" target="_blank" class="flex items-center gap-2 px-3.5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
            <i data-lucide="printer" class="w-4 h-4"></i> Cetak
        </a>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="card p-5">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="list-checks" class="w-[18px] h-[18px] text-primary"></i> Pokok Pembahasan</h2>
                @if($rapat->pokok_permasalahan)
                    @include('classroom.partials.richbody', ['html' => $rapat->pokok_permasalahan])
                @else
                    <p class="text-sm text-slate-400 italic">Belum diisi.</p>
                @endif
            </div>

            <div class="card p-5">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="clipboard-check" class="w-[18px] h-[18px] text-primary"></i> Hasil Rapat / Keputusan</h2>
                @if($rapat->hasil_rapat)
                    @include('classroom.partials.richbody', ['html' => $rapat->hasil_rapat])
                @else
                    <p class="text-sm text-slate-400 italic">Belum diisi.</p>
                @endif
            </div>

            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="image" class="w-[18px] h-[18px] text-primary"></i> Dokumentasi</h2>
                    @if($canManage)<a href="{{ route('rapat.dokumentasi', $rapat) }}" class="text-xs font-semibold text-primary hover:underline">Kelola &rarr;</a>@endif
                </div>
                @if($rapat->dokumentasi->isEmpty())
                <p class="text-sm text-slate-400 italic">Belum ada dokumentasi diunggah.</p>
                @else
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" x-data="{ zoom:null }">
                    @foreach($rapat->dokumentasi as $d)
                    @if($d->isImage())
                    <button type="button" @click="zoom='{{ $d->url }}'" class="aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-600">
                        <img src="{{ $d->url }}" class="w-full h-full object-cover" alt="Dokumentasi">
                    </button>
                    @else
                    <a href="{{ $d->url }}" target="_blank" class="aspect-square rounded-xl border border-slate-200 dark:border-slate-600 flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-primary">
                        <i data-lucide="file-text" class="w-6 h-6"></i>
                        <span class="text-[10px] truncate px-1">{{ $d->original_name }}</span>
                    </a>
                    @endif
                    @endforeach

                    <template x-teleport="body">
                        <div x-show="zoom" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center p-6" style="background:rgba(15,12,10,.82); backdrop-filter:blur(6px)" @click="zoom=null">
                            <img :src="zoom" class="max-h-[90vh] max-w-[94vw] rounded-2xl shadow-2xl">
                        </div>
                    </template>
                </div>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            <div class="card p-5">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="users" class="w-[18px] h-[18px] text-primary"></i> Guru Hadir ({{ $rapat->guruHadir->count() }})</h2>
                @if($rapat->guruHadir->isEmpty())
                <p class="text-sm text-slate-400 italic">Belum ada absensi kehadiran.</p>
                @else
                <div class="space-y-1.5 max-h-96 overflow-y-auto">
                    @foreach($rapat->guruHadir as $g)
                    <div class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <div class="w-6 h-6 rounded-full bg-primary/10 text-primary text-[10px] font-bold flex items-center justify-center flex-shrink-0">{{ strtoupper(substr($g->nama,0,1)) }}</div>
                        <span class="truncate">{{ $g->nama }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
