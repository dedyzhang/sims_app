@extends('layouts.app')
@section('title', 'Isi 7 KAIH')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">7 Kebiasaan Anak Indonesia Hebat</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMMM Y') }} &mdash; isi jujur ya, ini refleksi harianmu, bukan penilaian benar/salah.</p>
    </div>

    @if($existing)
    <div class="card p-8 text-center">
        <i data-lucide="check-circle-2" class="w-14 h-14 mx-auto mb-3 text-emerald-500"></i>
        <p class="font-bold text-lg text-slate-800 dark:text-slate-100">Kuesioner hari ini sudah diisi!</p>
        @if($existing->status === 'dilewati')
        <p class="text-sm text-slate-500 mt-1">Ditandai dilewati oleh sekolah.</p>
        @else
        <p class="text-sm text-slate-500 mt-1">Terima kasih sudah mengisi dengan jujur.</p>
        @endif
        <a href="{{ route('absen.qr') }}" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold mt-5">
            <i data-lucide="qr-code" class="w-4 h-4"></i> Lanjut Absen
        </a>
    </div>
    @if($existing->refleksi)
    <div class="card p-5">
        <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 mb-1.5 flex items-center gap-1.5"><i data-lucide="pencil-line" class="w-4 h-4 text-primary"></i> Refleksi Hari Ini</p>
        <p class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $existing->refleksi }}</p>
    </div>
    @endif
    @else
    @include('kaih._form', ['pertanyaans' => $pertanyaans])
    @endif
</div>
@endsection
