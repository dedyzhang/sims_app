@extends('layouts.app')
@section('title', 'Detail Pemanggilan — '.($panggilan->siswa?->nama ?? ''))

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <a href="{{ auth()->user()->canAccess('manage_disiplin') ? route('pemanggilan.index') : route('pemanggilan.riwayat') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>

    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">{{ $panggilan->perihal }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $panggilan->siswa?->nama ?? '-' }} &bull; Kelas {{ $panggilan->siswa?->kelas ? $panggilan->siswa->kelas->tingkat.$panggilan->siswa->kelas->kelas : '-' }}
            </p>
        </div>
        @if($bisaKelola)
        <div class="flex items-center gap-2">
            <a href="{{ route('pemanggilan.edit', $panggilan) }}" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Ubah</a>
            <form method="POST" action="{{ route('pemanggilan.destroy', $panggilan) }}" onsubmit="return confirmDelete(this)">
                @csrf @method('DELETE')
                <button type="submit" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border border-rose-200 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button>
            </form>
        </div>
        @endif
    </div>

    <div class="card p-5 grid sm:grid-cols-2 gap-4">
        <div>
            <p class="text-xs text-slate-400">Tanggal</p>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $panggilan->tanggal->isoFormat('dddd, D MMMM Y') }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400">Yang Dipanggil</p>
            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ \App\Models\Pemanggilan::DIPANGGIL[$panggilan->dipanggil] ?? $panggilan->dipanggil }}</span>
        </div>
        <div>
            <p class="text-xs text-slate-400">Dicatat Oleh</p>
            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $panggilan->pencatat?->displayName() ?? '-' }}</p>
        </div>
        <div>
            <p class="text-xs text-slate-400">Status</p>
            @if($panggilan->hasil)
            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Selesai</span>
            @else
            <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">Menunggu Hasil</span>
            @endif
        </div>
    </div>

    <div class="card p-5">
        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 mb-1.5 flex items-center gap-1.5"><i data-lucide="clipboard-list" class="w-4 h-4 text-primary"></i> Catatan Permasalahan</p>
        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $panggilan->permasalahan }}</p>
    </div>

    <div class="card p-5">
        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 mb-1.5 flex items-center gap-1.5"><i data-lucide="check-circle-2" class="w-4 h-4 text-primary"></i> Hasil Pertemuan</p>
        @if($panggilan->hasil)
        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $panggilan->hasil }}</p>
        @else
        <p class="text-sm text-slate-400 italic">Belum diisi.</p>
        @endif
    </div>

    <div class="card p-5">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3">Dokumentasi ({{ $panggilan->dokumentasi->count() }})</h2>
        @if($panggilan->dokumentasi->isEmpty())
        <p class="text-sm text-slate-400 italic">Tidak ada dokumentasi.</p>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach($panggilan->dokumentasi as $d)
            <div class="relative group aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-600">
                @if($d->isImage())
                <a href="{{ $d->url }}" target="_blank"><img src="{{ $d->url }}" class="w-full h-full object-cover" alt="{{ $d->original_name }}"></a>
                @else
                <a href="{{ $d->url }}" target="_blank" class="w-full h-full flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-primary">
                    <i data-lucide="file-text" class="w-6 h-6"></i>
                    <span class="text-[10px] truncate px-1">{{ $d->original_name }}</span>
                </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
