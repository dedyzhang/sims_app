@extends('layouts.app')
@section('title', 'Edit Guru')

@section('content')
@php $breadcrumbs = [['label'=>'Data Guru','url'=>route('guru.index')], ['label'=>$guru->nama,'url'=>route('guru.show',$guru->uuid)], ['label'=>'Edit','url'=>'#']]; @endphp

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('guru.show', $guru->uuid) }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Edit Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $guru->nama }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('guru.update', $guru->uuid) }}" class="space-y-5">
        @csrf @method('PUT')
        <div class="card p-6">
            <div class="flex items-center gap-2 mb-5 pb-4 border-b border-slate-100 dark:border-slate-700">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-primary-50 text-primary"><i data-lucide="user-round" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Data Guru</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Nama Lengkap <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" value="{{ old('nama', $guru->nama) }}" required class="form-input">
                </div>
                <div>
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" value="{{ old('nik', $guru->nik) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">NIP</label>
                    <input type="text" name="nip" value="{{ old('nip', $guru->nip) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jk" class="form-select">
                        <option value="L" @selected(old('jk', $guru->jk)==='L')>Laki-laki</option>
                        <option value="P" @selected(old('jk', $guru->jk)==='P')>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_telp" value="{{ old('no_telp', $guru->no_telp) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $guru->tempat_lahir) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $guru->tanggal_lahir) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Agama</label>
                    <select name="agama" class="form-select">
                        <option value="">— Pilih —</option>
                        @foreach(['Islam','Kristen Protestan','Katolik','Hindu','Buddha','Konghucu'] as $ag)
                        <option value="{{ $ag }}" @selected(old('agama', $guru->agama)===$ag)>{{ $ag }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">TMT Mengajar</label>
                    <input type="date" name="tmt_ngajar" value="{{ old('tmt_ngajar', $guru->tmt_ngajar) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">TMT di Sekolah Ini</label>
                    <input type="date" name="tmt_smp" value="{{ old('tmt_smp', $guru->tmt_smp) }}" class="form-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" rows="2" class="form-input">{{ old('alamat', $guru->alamat) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Akun & Akses (admin) --}}
        <div class="card p-6">
            <div class="flex items-center gap-2 mb-5 pb-4 border-b border-slate-100 dark:border-slate-700">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-primary-50 text-primary"><i data-lucide="shield" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Akun &amp; Akses</h2>
            </div>
            <div class="sm:max-w-xs">
                <label class="form-label">Role / Akses Login</label>
                <select name="access" class="form-select" {{ $guru->user?->access === 'superadmin' ? 'disabled' : '' }}>
                    @foreach(\App\Http\Controllers\GuruController::ROLES as $key => $lbl)
                    <option value="{{ $key }}" @selected(old('access', $guru->user?->access) === $key)>{{ $lbl }}</option>
                    @endforeach
                </select>
                @if(!$guru->user)
                <p class="text-xs text-amber-600 mt-1.5">Guru ini belum punya akun login.</p>
                @elseif($guru->user->access === 'superadmin')
                <p class="text-xs text-amber-600 mt-1.5">Akun superadmin — role tidak dapat diubah.</p>
                @endif
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
            </button>
            <a href="{{ route('guru.show', $guru->uuid) }}" class="px-6 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800 transition">Batal</a>
        </div>
    </form>
</div>
@endsection
