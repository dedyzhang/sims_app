@extends('layouts.app')
@section('title', $update->exists ? 'Ubah Info Pembaruan' : 'Buat Info Pembaruan')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">{{ $update->exists ? 'Ubah Info Pembaruan' : 'Buat Info Pembaruan' }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Isi versi & daftar poin pembaruan yang akan tampil sebagai popup ke semua pengguna</p>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)
            <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST"
          action="{{ $update->exists ? route('pembaruan.update', $update) : route('pembaruan.store') }}"
          class="card p-6 space-y-4">
        @csrf
        @if($update->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Versi</label>
                <input type="text" name="version" value="{{ old('version', $update->version) }}" placeholder="Contoh: 6.2.0" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Tanggal Rilis</label>
                <input type="date" name="released_at" value="{{ old('released_at', optional($update->released_at)->toDateString()) }}" class="form-input" required>
            </div>
        </div>

        <div>
            <label class="form-label">Judul</label>
            <input type="text" name="title" value="{{ old('title', $update->title) }}" placeholder="Contoh: Pembaruan Juli 2026" class="form-input" required>
        </div>

        <div>
            <label class="form-label">Poin Pembaruan</label>
            @include('classroom.partials.editor', ['name' => 'content', 'value' => old('content', $update->content)])
        </div>

        <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $update->is_published ?? true)) class="accent-[color:var(--cp)] w-4 h-4">
            Terbitkan sekarang (tampil ke semua pengguna)
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
            <a href="{{ route('pembaruan.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Batal</a>
        </div>
    </form>
</div>
@endsection
