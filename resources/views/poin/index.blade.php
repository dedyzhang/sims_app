@extends('layouts.app')
@section('title', 'Master Aturan Poin')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Master Aturan Poin</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftar aturan penambahan/pengurangan poin siswa (basis 100)</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('poin.siswa.index') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="users" class="w-4 h-4"></i> Poin Siswa</a>
            <a href="{{ route('poin.export') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="download" class="w-4 h-4"></i> Export Excel</a>
            <a href="{{ route('poin.importForm') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="upload" class="w-4 h-4"></i> Import Excel</a>
            <a href="{{ route('poin.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition"><i data-lucide="plus" class="w-4 h-4"></i> Tambah Aturan</a>
        </div>
    </div>

    @if($aturans->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="list-checks" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada aturan.</p>
        <a href="{{ route('poin.create') }}" class="text-primary hover:underline text-sm mt-1 inline-block">Tambah sekarang</a>
    </div>
    @else
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <x-sortable-th field="kode" label="Kode" />
                        <x-sortable-th field="jenis" label="Jenis" />
                        <th>Aturan</th>
                        <x-sortable-th field="poin" label="Poin" align="right" />
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aturans as $i => $a)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $aturans->firstItem() + $i }}</td>
                        <td><span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $a->kode }}</span></td>
                        <td>
                            @if($a->jenis === 'tambah')
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Tambah</span>
                            @else
                            <span class="badge bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-300">Kurang</span>
                            @endif
                        </td>
                        <td class="text-slate-600 dark:text-slate-300 text-sm max-w-md whitespace-normal">{{ $a->aturan }}</td>
                        <td class="text-right font-bold text-slate-700 dark:text-slate-200">{{ $a->poin }}</td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('poin.edit', $a) }}" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-400 hover:text-primary"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a>
                                <form method="POST" action="{{ route('poin.destroy', $a) }}" onsubmit="return confirmDelete(this)">
                                    @csrf @method('DELETE')
                                    <button class="p-1.5 rounded-lg border border-rose-200 dark:border-rose-800 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700">{{ $aturans->links() }}</div>
    </div>
    @endif
</div>
@endsection
