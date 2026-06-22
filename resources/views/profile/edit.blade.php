@extends('layouts.app')
@section('title', 'Edit Profil')

@section('content')
@php $breadcrumbs = [['label'=>'Profil','url'=>route('profile.index')], ['label'=>'Edit','url'=>'#']]; @endphp

<div class="{{ $user->guru ? 'max-w-2xl' : 'max-w-md' }} mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="page-title">Edit Profil</h1>
    </div>

    <form method="POST" action="{{ route('profile.update') }}" class="card p-6 space-y-5">
        @csrf @method('PUT')

        {{-- Akun --}}
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Akun</p>
            <label class="form-label">Username</label>
            <input type="text" name="username" value="{{ old('username', $user->username) }}" required class="form-input font-mono">
            @error('username')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
        </div>

        @if($user->guru)
        @php $g = $user->guru; @endphp
        {{-- Data Diri Guru --}}
        <div class="border-t border-slate-100 dark:border-slate-700 pt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3">Data Diri</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama" value="{{ old('nama', $g->nama) }}" required class="form-input">
                    @error('nama')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">NIK</label>
                    <input type="text" name="nik" value="{{ old('nik', $g->nik) }}" class="form-input font-mono">
                    @error('nik')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">NIP</label>
                    <input type="text" name="nip" value="{{ old('nip', $g->nip) }}" class="form-input font-mono">
                </div>
                <div>
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jk" class="form-select">
                        <option value="L" @selected(old('jk', $g->jk)==='L')>Laki-laki</option>
                        <option value="P" @selected(old('jk', $g->jk)==='P')>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Agama</label>
                    <input type="text" name="agama" value="{{ old('agama', $g->agama) }}" class="form-input" placeholder="mis. Islam">
                </div>
                <div>
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $g->tempat_lahir) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', optional($g->tanggal_lahir)->format('Y-m-d')) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="no_telp" value="{{ old('no_telp', $g->no_telp) }}" class="form-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" rows="2" class="form-input">{{ old('alamat', $g->alamat) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Pendidikan --}}
        <div class="border-t border-slate-100 dark:border-slate-700 pt-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3">Pendidikan</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Tingkat Studi</label>
                    <input type="text" name="tingkat_studi" value="{{ old('tingkat_studi', $g->tingkat_studi) }}" class="form-input" placeholder="mis. S1, S2">
                </div>
                <div>
                    <label class="form-label">Program Studi</label>
                    <input type="text" name="program_studi" value="{{ old('program_studi', $g->program_studi) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Universitas</label>
                    <input type="text" name="universitas" value="{{ old('universitas', $g->universitas) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tahun Tamat</label>
                    <input type="text" name="tahun_tamat" value="{{ old('tahun_tamat', $g->tahun_tamat) }}" class="form-input" placeholder="mis. 2015">
                </div>
            </div>
        </div>
        @endif

        <div class="flex gap-3 pt-1 border-t border-slate-100 dark:border-slate-700">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 mt-4">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
            </button>
            <a href="{{ route('profile.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition mt-4">Batal</a>
        </div>
    </form>
</div>
@endsection
