@extends('layouts.app')
@section('title', 'Tambah Guru')

@section('content')
@php $breadcrumbs = [['label'=>'Data Guru','url'=>route('guru.index')], ['label'=>'Tambah','url'=>'#']]; @endphp

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('guru.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Tambah Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Akun login dibuat otomatis dari NIK/NIP</p>
        </div>
    </div>

    <form method="POST" action="{{ route('guru.store') }}" class="space-y-5">
        @csrf
        <div class="card p-6">
            <div class="flex items-center gap-2 mb-5 pb-4 border-b border-slate-100 dark:border-slate-700">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-primary-50 text-primary"><i data-lucide="user-round" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Data Guru</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" value="{{ old('nama') }}" required autofocus class="form-input" placeholder="Nama sesuai ijazah">
                </div>
                <div>
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" value="{{ old('nik') }}" class="form-input" placeholder="16 digit NIK">
                </div>
                <div>
                    <label class="form-label">NIP</label>
                    <input type="text" name="nip" value="{{ old('nip') }}" class="form-input" placeholder="NIP (jika PNS)">
                </div>
                <div>
                    <label class="form-label">Jenis Kelamin <span class="text-rose-500">*</span></label>
                    <select name="jk" required class="form-select">
                        <option value="L" @selected(old('jk')=='L')>Laki-laki</option>
                        <option value="P" @selected(old('jk')=='P')>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_telp" value="{{ old('no_telp') }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Agama</label>
                    <select name="agama" class="form-select">
                        <option value="">— Pilih —</option>
                        @foreach(['Islam','Kristen Protestan','Katolik','Hindu','Buddha','Konghucu'] as $ag)
                        <option value="{{ $ag }}" @selected(old('agama')===$ag)>{{ $ag }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">TMT Mengajar</label>
                    <input type="date" name="tmt_ngajar" value="{{ old('tmt_ngajar') }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">TMT di Sekolah Ini</label>
                    <input type="date" name="tmt_smp" value="{{ old('tmt_smp') }}" class="form-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" rows="2" class="form-input">{{ old('alamat') }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Guru
            </button>
            <a href="{{ route('guru.index') }}" class="px-6 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800 transition">Batal</a>
        </div>
    </form>
</div>
@endsection
