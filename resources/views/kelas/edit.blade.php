@extends('layouts.app')
@section('title', 'Edit Kelas')

@section('content')
@php $breadcrumbs = [['label'=>'Data Kelas','url'=>route('kelas.index')], ['label'=>'Edit','url'=>'#']]; @endphp

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('kelas.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="page-title">Edit Kelas {{ $kelas->tingkat }}{{ $kelas->kelas }}</h1>
    </div>

    <form method="POST" action="{{ route('kelas.update', $kelas->uuid) }}" class="card p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="form-label">Tingkat</label>
            <select name="tingkat" required class="form-select">
                <optgroup label="SD">
                    @for($i=1;$i<=6;$i++)<option value="{{ $i }}" @selected(old('tingkat',$kelas->tingkat)==$i)>Kelas {{ $i }}</option>@endfor
                </optgroup>
                <optgroup label="SMP">
                    @for($i=7;$i<=9;$i++)<option value="{{ $i }}" @selected(old('tingkat',$kelas->tingkat)==$i)>Kelas {{ $i }}</option>@endfor
                </optgroup>
                <optgroup label="SMA / SMK">
                    @for($i=10;$i<=12;$i++)<option value="{{ $i }}" @selected(old('tingkat',$kelas->tingkat)==$i)>Kelas {{ $i }}</option>@endfor
                </optgroup>
            </select>
        </div>
        <div>
            <label class="form-label">Nama Kelas</label>
            <input type="text" name="kelas" value="{{ old('kelas', $kelas->kelas) }}" required class="form-input">
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
