@extends('layouts.app')
@section('title', 'Ganti Password')

@section('content')
@php $breadcrumbs = [['label'=>'Profil','url'=>route('profile.index')], ['label'=>'Ganti Password','url'=>'#']]; @endphp

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Ganti Password</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Perbarui kata sandi akun Anda</p>
        </div>
    </div>

    <form method="POST" action="/ganti-password" class="card p-6 space-y-4" x-data="{ s1:false, s2:false, s3:false }">
        @csrf
        <div>
            <label class="form-label">Password Lama</label>
            <div class="relative">
                <input :type="s1?'text':'password'" name="current_password" required class="form-input pr-11">
                <button type="button" @click="s1=!s1" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <i :data-lucide="s1?'eye-off':'eye'" class="w-4 h-4"></i>
                </button>
            </div>
            @error('current_password')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">Password Baru</label>
            <div class="relative">
                <input :type="s2?'text':'password'" name="new_password" required class="form-input pr-11">
                <button type="button" @click="s2=!s2" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                    <i :data-lucide="s2?'eye-off':'eye'" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
        <div>
            <label class="form-label">Konfirmasi Password Baru</label>
            <input type="password" name="new_password_confirmation" required class="form-input">
        </div>
        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
            <i data-lucide="key-round" class="w-4 h-4"></i> Simpan Password
        </button>
    </form>
</div>

@push('scripts')<script>lucide.createIcons();</script>@endpush
@endsection
