@extends('layouts.app')
@section('title', $ujian->judul)

@section('content')
<div class="max-w-lg mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('ujian.siswa.index') }}" class="hover:underline">Ujian Saya</a> / {{ $ujian->judul }}</nav>
        <h1 class="page-title">{{ $ujian->judul }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $ujian->pelajaran?->nama }} · {{ $ujian->jenisLabel() }} · {{ $ujian->durasi_menit }} menit</p>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
    </div>
    @endif

    @if($ujian->instruksi)
    <div class="card p-5">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-2 text-sm">Instruksi</h2>
        <div class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $ujian->instruksi }}</div>
    </div>
    @endif

    <div class="card p-5 space-y-3">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 text-sm flex items-center gap-2">
            <i data-lucide="shield-alert" class="w-4 h-4 text-amber-500"></i> Perhatian Sebelum Mulai
        </h2>
        <ul class="text-xs text-slate-500 dark:text-slate-400 space-y-1.5 list-disc list-inside">
            <li>Ujian akan berjalan dalam mode layar penuh. Keluar dari layar penuh atau berpindah tab akan <b>mengunci</b> ujian Anda dan perlu dibuka kembali oleh guru/panitia.</li>
            <li>Waktu berjalan otomatis sejak Anda menekan "Mulai Ujian" dan tidak bisa dijeda.</li>
            <li>Jawaban tersimpan otomatis setiap kali Anda menjawab.</li>
            <li>Salin-tempel dinonaktifkan selama ujian berlangsung.</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('ujian.siswa.start', $ujian) }}" id="form-mulai-ujian" class="card p-5 space-y-3">
        @csrf
        <div>
            <label class="form-label">Token Masuk</label>
            <input type="text" name="token" required autocomplete="off" autocapitalize="characters"
                   class="form-input uppercase tracking-widest text-center font-mono text-lg" placeholder="XXXXXX" maxlength="16">
            <p class="text-xs text-slate-400 mt-1">Minta token ini ke guru/panitia ujian.</p>
        </div>
        <button type="submit" id="btn-mulai-ujian" class="btn-primary w-full py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
            <i data-lucide="log-in" class="w-4 h-4"></i> Mulai Ujian
        </button>
    </form>
</div>
@endsection
