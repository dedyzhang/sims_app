@extends('layouts.app')
@section('title', 'Cetak Absensi Siswa')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="page-title">Cetak Absensi Siswa</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Unduh rekap kehadiran siswa pada rentang tanggal tertentu dalam format Excel.</p>
    </div>

    <form method="POST" action="{{ route('cetak.absensiSiswa') }}" class="card p-6 flex flex-wrap gap-4 items-end"
          onsubmit="setTimeout(() => window.hideGlobalSpinner && hideGlobalSpinner(), 1200)">
        @csrf
        <div class="space-y-1.5 flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" required>
                @foreach($kelas as $k)
                <option value="{{ $k->uuid }}">Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1.5 min-w-36">
            <label class="form-label">Dari Tanggal</label>
            <input type="date" name="dari" required class="form-input" value="{{ now()->startOfMonth()->format('Y-m-d') }}">
        </div>
        <div class="space-y-1.5 min-w-36">
            <label class="form-label">Sampai Tanggal</label>
            <input type="date" name="sampai" required class="form-input" value="{{ now()->format('Y-m-d') }}">
        </div>
        <button type="submit" class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh Excel
        </button>
    </form>
</div>
@endsection
