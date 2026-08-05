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
            @php
                [$min, $max] = \App\Support\JenjangSekolah::rentangTingkat();
                $tingkatDiLuarJenjang = $kelas->tingkat < $min || $kelas->tingkat > $max;
            @endphp
            <select name="tingkat" required class="form-select">
                @if($tingkatDiLuarJenjang)
                    {{-- Kelas ini dibuat sebelum jenjang aktif diganti — tetap ditawarkan
                         supaya tersimpan tanpa sengaja berubah kalau admin cuma ganti nama kelas. --}}
                    <option value="{{ $kelas->tingkat }}" @selected(old('tingkat')===null)>Kelas {{ $kelas->tingkat }} (di luar jenjang aktif)</option>
                @endif
                @for($i=$min;$i<=$max;$i++)<option value="{{ $i }}" @selected(old('tingkat',$kelas->tingkat)==$i)>Kelas {{ $i }}</option>@endfor
            </select>
            @if($tingkatDiLuarJenjang)
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Kelas ini bertingkat {{ $kelas->tingkat }}, di luar jenjang aktif saat ini (<b>{{ \App\Support\JenjangSekolah::label() }}</b>). Pilih ulang kalau ingin disesuaikan.</p>
            @endif
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
