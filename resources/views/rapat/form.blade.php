@extends('layouts.app')
@section('title', $rapat->exists ? 'Ubah Rapat' : 'Catat Rapat')

@section('content')
<div class="space-y-5 max-w-3xl">
    <div>
        <h1 class="page-title">{{ $rapat->exists ? 'Ubah Rapat' : 'Catat Rapat Baru' }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Isi pokok pembahasan &amp; hasil rapat. Kehadiran guru dan dokumentasi bisa dilengkapi setelah disimpan.</p>
    </div>

    <form method="POST" action="{{ $rapat->exists ? route('rapat.update', $rapat) : route('rapat.store') }}" class="card p-5 space-y-5">
        @csrf
        @if($rapat->exists) @method('PUT') @endif

        <div>
            <label class="form-label">Judul Rapat</label>
            <input type="text" name="judul" value="{{ old('judul', $rapat->judul) }}" required placeholder="mis. Rapat Koordinasi Semester Ganjil" class="form-input">
            @error('judul')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="max-w-xs">
            <label class="form-label">Tanggal Rapat</label>
            <input type="date" name="tanggal" value="{{ old('tanggal', optional($rapat->tanggal)->format('Y-m-d')) }}" required class="form-input">
            @error('tanggal')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="form-label">Pokok Pembahasan</label>
            @include('classroom.partials.editor', ['name' => 'pokok_permasalahan', 'value' => old('pokok_permasalahan', $rapat->pokok_permasalahan)])
        </div>

        <div>
            <label class="form-label">Hasil Rapat / Keputusan</label>
            @include('classroom.partials.editor', ['name' => 'hasil_rapat', 'value' => old('hasil_rapat', $rapat->hasil_rapat)])
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
            <a href="{{ $rapat->exists ? route('rapat.show', $rapat) : route('rapat.index') }}" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Batal</a>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
            </button>
        </div>
    </form>
</div>
@endsection
