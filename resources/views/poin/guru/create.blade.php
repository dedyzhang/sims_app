@extends('layouts.app')
@section('title', 'Ajukan Poin — '.$siswa->nama)

@section('content')
<div class="max-w-xl mx-auto space-y-4" x-data="poinForm(@js(route('poin.aturan.json')))" x-init="init()">
    <a href="{{ route('poin.guru.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    <div>
        <h1 class="page-title">Ajukan Poin</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $siswa->nama }} &bull; Kelas {{ $siswa->kelas ? $siswa->kelas->tingkat.$siswa->kelas->kelas : '-' }}</p>
    </div>

    <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 px-4 py-3 text-sm flex items-start gap-2">
        <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
        <span>Pengajuan ini akan masuk daftar menunggu persetujuan kesiswaan sebelum tercatat resmi.</span>
    </div>

    <form method="POST" action="{{ route('poin.guru.store', $siswa) }}" class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Jenis Poin</label>
            <select class="form-select" x-model="jenis" @change="loadAturan()">
                <option value="">— Pilih —</option>
                <option value="tambah">Tambah</option>
                <option value="kurang">Kurang</option>
            </select>
        </div>
        <div>
            <label class="form-label flex items-center gap-2">
                Aturan
                <span x-show="loadingAturan" x-cloak class="inline-flex items-center gap-1 text-xs font-normal text-slate-400">
                    <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Memuat aturan...
                </span>
            </label>
            <select name="aturan" x-ref="aturanSelect" class="form-select" required></select>
        </div>
        <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="send" class="w-4 h-4"></i> Ajukan</button>
    </form>
</div>
@endsection

@push('scripts')
@include('poin.partials.poin-form-script')
@endpush
