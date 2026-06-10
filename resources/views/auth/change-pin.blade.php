@extends('layouts.app')
@section('title', 'Set PIN')

@section('content')
@php $breadcrumbs = [['label'=>'Profil','url'=>route('profile.index')], ['label'=>'Set PIN','url'=>'#']]; @endphp

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Set PIN Login</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">PIN untuk login cepat di perangkat mobile</p>
        </div>
    </div>

    <div class="flex items-center gap-3 mb-4 p-4 rounded-xl bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700">
        <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900 grid place-items-center flex-shrink-0">
            <i data-lucide="lock-keyhole" class="w-5 h-5 text-violet-600"></i>
        </div>
        <p class="text-sm text-violet-800 dark:text-violet-300">PIN 6 digit angka memungkinkan login kilat tanpa mengetik password.</p>
    </div>

    <form method="POST" action="/ganti-pin" class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="form-label">Password (verifikasi)</label>
            <input type="password" name="password" required placeholder="Masukkan password Anda" class="form-input">
            @error('password')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="form-label">PIN Baru (6 digit)</label>
            <input type="password" name="pin" inputmode="numeric" maxlength="6" pattern="\d{6}" required placeholder="••••••" class="form-input text-center text-2xl tracking-[0.5em] font-mono">
        </div>
        <div>
            <label class="form-label">Konfirmasi PIN</label>
            <input type="password" name="pin_confirmation" inputmode="numeric" maxlength="6" pattern="\d{6}" required placeholder="••••••" class="form-input text-center text-2xl tracking-[0.5em] font-mono">
        </div>
        <button type="submit" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
            <i data-lucide="lock-keyhole" class="w-4 h-4"></i> Simpan PIN
        </button>
    </form>
</div>
@endsection
