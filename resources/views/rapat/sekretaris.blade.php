@extends('layouts.app')
@section('title', 'Kelola Sekretaris Rapat')

@section('content')
<div class="space-y-5">
    <div>
        <a href="{{ route('rapat.index') }}" class="text-xs text-slate-400 hover:text-primary flex items-center gap-1 mb-1"><i data-lucide="arrow-left" class="w-3 h-3"></i> Agenda Rapat</a>
        <h1 class="page-title">Kelola Sekretaris Rapat</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Guru yang ditunjuk sekretaris bisa mengelola semua Agenda Rapat (catat, absensi, dokumentasi, cetak) tanpa perlu izin RBAC khusus.</p>
    </div>

    <form method="GET" action="{{ route('rapat.sekretaris') }}" class="card p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label">Cari Nama Guru</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Ketik nama..." class="form-input">
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition">Cari</button>
    </form>

    <div class="card overflow-hidden">
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($gurus as $g)
            <div class="p-3.5 flex items-center gap-3 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background:{{ $g->jk==='P' ? '#ec4899' : 'var(--cp)' }}">{{ strtoupper(substr($g->nama,0,1)) }}</div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-slate-700 dark:text-slate-200 truncate">{{ $g->nama }}</p>
                    <p class="text-xs text-slate-400">{{ $g->nip ?: ($g->nik ?: 'Guru') }}</p>
                </div>
                @if($g->sekretaris_rapat)
                <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-300 font-semibold">Sekretaris</span>
                @endif
                <form method="POST" action="{{ route('rapat.sekretaris.toggle', $g) }}" onsubmit="return confirmAction(this, '{{ $g->sekretaris_rapat ? 'Hapus status sekretaris untuk '.addslashes($g->nama).'?' : 'Jadikan '.addslashes($g->nama).' sebagai sekretaris rapat?' }}')">
                    @csrf
                    <button class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition {{ $g->sekretaris_rapat ? 'border-rose-200 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20' : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
                        {{ $g->sekretaris_rapat ? 'Cabut' : 'Jadikan Sekretaris' }}
                    </button>
                </form>
            </div>
            @empty
            <div class="p-12 text-center text-slate-400">
                <i data-lucide="user-x" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
                <p class="font-medium">Tidak ada guru ditemukan.</p>
            </div>
            @endforelse
        </div>
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $gurus->links() }}
        </div>
    </div>
</div>
@endsection
