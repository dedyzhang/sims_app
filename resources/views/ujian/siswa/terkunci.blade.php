@extends('layouts.app')
@section('title', 'Ujian Terkunci')

@section('content')
<div class="max-w-md mx-auto">
    <div class="card p-8 text-center space-y-4">
        <i data-lucide="lock" class="w-14 h-14 text-rose-500 mx-auto"></i>
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Ujian Terkunci</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
            Ujian Anda terkunci karena keluar dari layar penuh atau berpindah tab.
            Hubungi guru/panitia ujian untuk membuka kembali. Jawaban yang sudah tersimpan tidak hilang.
        </p>
        <p class="text-xs text-slate-400">{{ $ujian->judul }}</p>
        <a href="{{ route('ujian.siswa.index') }}" class="inline-block px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600">Kembali ke Daftar Ujian</a>
    </div>
</div>
@endsection
