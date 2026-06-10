@extends('layouts.app')
@section('title', 'Tambah Kelas')

@section('content')
@php $breadcrumbs = [['label'=>'Data Kelas','url'=>route('kelas.index')], ['label'=>'Tambah','url'=>'#']]; @endphp

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kelas.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="page-title">Tambah Kelas</h1>
    </div>

    <form method="POST" action="{{ route('kelas.store') }}" class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="form-label">Tingkat <span class="text-rose-500">*</span></label>
            <select name="tingkat" required class="form-select">
                <option value="">Pilih tingkat</option>
                <option value="7" @selected(old('tingkat')=='7')>Kelas 7</option>
                <option value="8" @selected(old('tingkat')=='8')>Kelas 8</option>
                <option value="9" @selected(old('tingkat')=='9')>Kelas 9</option>
            </select>
        </div>
        <div>
            <label class="form-label">Nama Kelas <span class="text-rose-500">*</span></label>
            <input type="text" name="kelas" value="{{ old('kelas') }}" placeholder="A / B / C / Unggulan" required class="form-input">
        </div>
        <div class="flex gap-3 pt-1">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
            </button>
            <a href="{{ route('kelas.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Batal</a>
        </div>
    </form>
</div>
@endsection
