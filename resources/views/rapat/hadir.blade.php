@extends('layouts.app')
@section('title', 'Absensi Rapat')

@section('content')
<div class="space-y-5 max-w-2xl">
    <div>
        <a href="{{ route('rapat.show', $rapat) }}" class="text-xs text-slate-400 hover:text-primary flex items-center gap-1 mb-1"><i data-lucide="arrow-left" class="w-3 h-3"></i> {{ $rapat->judul }}</a>
        <h1 class="page-title">Absensi Kehadiran</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $rapat->tanggal->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    <form method="POST" action="{{ route('rapat.hadir.store', $rapat) }}">
        @csrf
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $gurus->count() }} guru</p>
                <button type="button" onclick="document.querySelectorAll('.chk-guru').forEach(c=>c.checked=true)" class="text-xs font-semibold text-emerald-600 hover:underline flex items-center gap-1">
                    <i data-lucide="check-check" class="w-3.5 h-3.5"></i> Hadir Semua
                </button>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-700 max-h-[28rem] overflow-y-auto">
                @foreach($gurus as $g)
                <label class="p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition cursor-pointer">
                    <input type="checkbox" name="guru[]" value="{{ $g->uuid }}" class="chk-guru w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary" @checked(in_array($g->uuid, $hadirIds))>
                    <div class="w-8 h-8 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background:{{ $g->jk==='P' ? '#ec4899' : 'var(--cp)' }}">{{ strtoupper(substr($g->nama,0,1)) }}</div>
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $g->nama }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-4">
            <a href="{{ route('rapat.show', $rapat) }}" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Batal</a>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Absensi
            </button>
        </div>
    </form>
</div>
@endsection
