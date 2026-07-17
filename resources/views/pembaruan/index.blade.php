@extends('layouts.app')
@section('title', 'Info Pembaruan')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Info Pembaruan Aplikasi</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kelola daftar versi & catatan pembaruan yang ditampilkan sebagai popup ke semua pengguna setelah login</p>
        </div>
        <a href="{{ route('pembaruan.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Buat Info Pembaruan
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>Versi</th>
                        <th>Judul</th>
                        <th>Tanggal Rilis</th>
                        <th>Status</th>
                        <th class="w-24">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($updates as $i => $u)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $i + 1 }}</td>
                        <td class="text-sm font-bold text-slate-700 dark:text-slate-200">v{{ $u->version }}</td>
                        <td class="text-sm text-slate-600 dark:text-slate-300">{{ $u->title }}</td>
                        <td class="text-sm text-slate-500">{{ $u->released_at->isoFormat('D MMM Y') }}</td>
                        <td>
                            @if($u->is_published)
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Terbit</span>
                            @else
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">Draf</span>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center gap-1">
                                <a href="{{ route('pembaruan.edit', $u) }}" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700" title="Ubah"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                                <form method="POST" action="{{ route('pembaruan.destroy', $u) }}" onsubmit="return confirmDelete(this)">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-700" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada info pembaruan yang dibuat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
