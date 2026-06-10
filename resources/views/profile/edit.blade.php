@extends('layouts.app')
@section('title', 'Edit Profil')

@section('content')
@php $breadcrumbs = [['label'=>'Profil','url'=>route('profile.index')], ['label'=>'Edit','url'=>'#']]; @endphp

<div class="max-w-md mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('profile.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <h1 class="page-title">Edit Profil</h1>
    </div>

    <form method="POST" action="{{ route('profile.update') }}" class="card p-6 space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="form-label">Username</label>
            <input type="text" name="username" value="{{ old('username', $user->username) }}" required class="form-input font-mono">
            @error('username')<p class="text-rose-500 text-xs mt-1.5">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3 pt-1">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan
            </button>
            <a href="{{ route('profile.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Batal</a>
        </div>
    </form>
</div>
@endsection
