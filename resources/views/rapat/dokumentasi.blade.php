@extends('layouts.app')
@section('title', 'Dokumentasi Rapat')

@section('content')
<div class="space-y-5 max-w-3xl">
    <div>
        <a href="{{ route('rapat.show', $rapat) }}" class="text-xs text-slate-400 hover:text-primary flex items-center gap-1 mb-1"><i data-lucide="arrow-left" class="w-3 h-3"></i> {{ $rapat->judul }}</a>
        <h1 class="page-title">Dokumentasi Rapat</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Foto otomatis dikompres agar ringan tanpa mengurangi kejelasan.</p>
    </div>

    <form method="POST" action="{{ route('rapat.dokumentasi.store', $rapat) }}" enctype="multipart/form-data" class="card p-5 space-y-4">
        @csrf
        @include('classroom.partials.upload', [
            'label' => 'Seret & lepas foto dokumentasi rapat, atau klik untuk pilih',
            'acceptLabel' => 'Gambar (JPG/PNG/WEBP/HEIC) atau PDF',
            'acceptAttr' => 'image/*,application/pdf',
            'maxMb' => 5,
        ])
        @error('files')<p class="text-xs text-rose-500">{{ $message }}</p>@enderror
        @error('files.*')<p class="text-xs text-rose-500">{{ $message }}</p>@enderror
        <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="upload" class="w-4 h-4"></i> Unggah
        </button>
    </form>

    <div class="card p-5">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3">Dokumentasi Tersimpan ({{ $rapat->dokumentasi->count() }})</h2>
        @if($rapat->dokumentasi->isEmpty())
        <p class="text-sm text-slate-400 italic">Belum ada dokumentasi.</p>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach($rapat->dokumentasi as $d)
            <div class="relative group aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-600">
                @if($d->isImage())
                <img src="{{ $d->url }}" class="w-full h-full object-cover" alt="{{ $d->original_name }}">
                @else
                <a href="{{ $d->url }}" target="_blank" class="w-full h-full flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-primary">
                    <i data-lucide="file-text" class="w-6 h-6"></i>
                    <span class="text-[10px] truncate px-1">{{ $d->original_name }}</span>
                </a>
                @endif
                <form method="POST" action="{{ route('rapat.dokumentasi.destroy', [$rapat, $d]) }}" onsubmit="return confirmDelete(this)" class="absolute top-1.5 right-1.5">
                    @csrf @method('DELETE')
                    <button class="w-7 h-7 rounded-full bg-rose-500/90 text-white grid place-items-center opacity-0 group-hover:opacity-100 transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
