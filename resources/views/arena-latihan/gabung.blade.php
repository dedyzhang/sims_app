@extends('layouts.app')
@section('title', 'Gabung Latihan — '.$quiz->title)

@php($isKiosk = true)

@section('content')
<div class="max-w-sm mx-auto pt-10 space-y-5">
    <div class="text-center space-y-1">
        <div class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center text-white shadow-lg" style="background:var(--cp)">
            <i data-lucide="flask-conical" class="w-7 h-7"></i>
        </div>
        <h1 class="text-xl font-extrabold text-slate-800 dark:text-white mt-2">Gabung Sesi Latihan</h1>
        <p class="text-sm font-semibold text-primary">{{ $quiz->title }}</p>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Ini sesi UJI COBA Arena Belajar — tidak perlu login. Cukup ketik nama, lalu ikut seperti pemain lain.
        </p>
    </div>

    @if ($errors->any())
        <div class="card p-3 border-l-4 !border-l-rose-500 text-sm text-rose-600 dark:text-rose-400">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('latihan.publik.join', $joinToken) }}" class="card p-5 space-y-4">
        @csrf
        <div>
            <label class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1 block">Nama Kamu</label>
            <input type="text" name="guest_name" required maxlength="60" autofocus autocomplete="off"
                   class="form-input" placeholder="Mis. Bu Siti / Andi" value="{{ old('guest_name') }}">
        </div>
        <div>
            <label class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1 block">Kamu ikut sebagai (opsional)</label>
            <div class="flex gap-2">
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border-2 border-slate-200 dark:border-slate-600 py-2.5 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" name="claimed_role" value="guru" class="accent-[var(--cp)]"> <span class="text-sm font-semibold">Guru</span>
                </label>
                <label class="flex-1 flex items-center justify-center gap-2 rounded-xl border-2 border-slate-200 dark:border-slate-600 py-2.5 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                    <input type="radio" name="claimed_role" value="siswa" class="accent-[var(--cp)]"> <span class="text-sm font-semibold">Siswa</span>
                </label>
            </div>
        </div>
        <button type="submit" class="w-full py-3.5 rounded-2xl text-white font-bold shadow-lg" style="background:var(--cp)">
            Gabung Latihan
        </button>
    </form>
</div>
@endsection
