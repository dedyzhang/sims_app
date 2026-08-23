@extends('layouts.app')
@section('title', 'Buat Paket Ujian')

@section('content')
<div class="max-w-lg mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('ujian.paket.index') }}" class="hover:underline">Paket Ujian</a> / Buat Paket</nav>
        <h1 class="page-title">Buat Paket Ujian</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Folder periode ujian — ruangan, jadwal, pengawas, dan ujian anggota diatur setelah paket ini dibuat.</p>
    </div>

    <form method="POST" action="{{ route('ujian.paket.store') }}" class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nama Paket <span class="text-rose-500">*</span></label>
            <input type="text" name="nama" value="{{ old('nama') }}" placeholder="Contoh: PAS Semester 1 2026/2027" class="form-input" required>
            @error('nama')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Jenis <span class="text-rose-500">*</span></label>
            <select name="jenis" class="form-select" required>
                <option value="">— pilih jenis —</option>
                <option value="harian" @selected(old('jenis')==='harian')>Ulangan Harian</option>
                <option value="pts" @selected(old('jenis')==='pts')>PTS</option>
                <option value="pas" @selected(old('jenis')==='pas')>PAS</option>
                <option value="uas" @selected(old('jenis')==='uas')>UAS</option>
            </select>
            @error('jenis')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Semester</label>
            <select name="id_semester" class="form-select">
                <option value="">— tidak ditentukan —</option>
                @foreach($semesterList as $s)
                <option value="{{ $s->id }}" @selected((string) old('id_semester') === (string) $s->id)>Semester {{ $s->semester }} · {{ $s->tahun }}{{ $s->aktif ? ' (aktif)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" class="form-input">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" class="form-input">
            </div>
        </div>
        @error('tanggal_selesai')<p class="text-xs text-rose-500">{{ $message }}</p>@enderror

        <div class="pt-2 flex justify-end gap-2">
            <a href="{{ route('ujian.paket.index') }}" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</a>
            <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold">Buat Paket</button>
        </div>
    </form>
</div>
@endsection
