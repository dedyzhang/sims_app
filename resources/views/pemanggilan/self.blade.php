@extends('layouts.app')
@section('title', 'Pemanggilan Saya')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">Pemanggilan {{ auth()->user()->access === 'orangtua' ? $siswa->nama : 'Saya' }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Riwayat pemanggilan orang tua/siswa, beserta permasalahan dan hasilnya</p>
    </div>

    @if($items->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="phone-call" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada catatan pemanggilan.</p>
    </div>
    @else
    <div class="space-y-3" x-data="{ open: null }">
        @foreach($items as $p)
        <div class="card overflow-hidden">
            <button type="button" @click="open = open === '{{ $p->uuid }}' ? null : '{{ $p->uuid }}'" class="w-full p-4 flex items-center justify-between gap-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                <div class="min-w-0">
                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $p->perihal }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $p->tanggal->isoFormat('D MMM Y') }} &bull; {{ \App\Models\Pemanggilan::DIPANGGIL[$p->dipanggil] ?? $p->dipanggil }}</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($p->hasil)
                    <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Selesai</span>
                    @else
                    <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">Menunggu Hasil</span>
                    @endif
                    <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition" :class="open === '{{ $p->uuid }}' ? 'rotate-180' : ''"></i>
                </div>
            </button>
            <div x-show="open === '{{ $p->uuid }}'" x-collapse x-cloak class="px-4 pb-4 space-y-4 border-t border-slate-100 dark:border-slate-700 pt-4">
                <div>
                    <p class="font-semibold text-xs uppercase tracking-wide text-slate-400 mb-1">Catatan Permasalahan</p>
                    <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $p->permasalahan }}</p>
                </div>
                <div>
                    <p class="font-semibold text-xs uppercase tracking-wide text-slate-400 mb-1">Hasil Pertemuan</p>
                    @if($p->hasil)
                    <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $p->hasil }}</p>
                    @else
                    <p class="text-sm text-slate-400 italic">Belum diisi.</p>
                    @endif
                </div>
                @if($p->dokumentasi->isNotEmpty())
                <div>
                    <p class="font-semibold text-xs uppercase tracking-wide text-slate-400 mb-2">Dokumentasi</p>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        @foreach($p->dokumentasi as $d)
                        <div class="aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-600">
                            @if($d->isImage())
                            <a href="{{ $d->url }}" target="_blank"><img src="{{ $d->url }}" class="w-full h-full object-cover" alt="{{ $d->original_name }}"></a>
                            @else
                            <a href="{{ $d->url }}" target="_blank" class="w-full h-full flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-primary">
                                <i data-lucide="file-text" class="w-5 h-5"></i>
                                <span class="text-[9px] truncate px-1">{{ $d->original_name }}</span>
                            </a>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    <div class="card p-4">{{ $items->links() }}</div>
    @endif
</div>
@endsection
