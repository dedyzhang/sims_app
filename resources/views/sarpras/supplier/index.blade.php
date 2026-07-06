@extends('sarpras.layouts.app')
@section('title', 'Supplier')
@section('sarpras_title', 'Manajemen Supplier Aset')
@section('sarpras_subtitle', 'Pencatatan data mitra / supplier penyedia barang sarana prasarana sekolah.')

@section('sarpras_actions')
    @can('sarpras.supplier.kelola')
        <a href="{{ route('sarpras.supplier.create') }}" 
           class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 dark:bg-primary dark:hover:bg-primary-hover text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Supplier
        </a>
    @endcan
@endsection

@section('sarpras_body')
<div class="card p-5">
    <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-4">
        <span class="grid place-items-center w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/40 text-amber-500"><i data-lucide="truck" class="w-4 h-4"></i></span>
        Daftar Supplier Terdaftar
    </h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                <th class="pb-2 font-semibold">Nama Supplier</th><th class="pb-2 font-semibold">Kontak</th><th class="pb-2 font-semibold">Alamat</th><th class="pb-2 font-semibold">NPWP</th><th class="pb-2 font-semibold text-right">Aksi</th>
            </tr></thead>
            <tbody>
            @foreach($supplier as $s)
                <tr class="border-b border-slate-50 dark:border-slate-700/50">
                    <td class="py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $s->nama }}</td>
                    <td class="py-3 text-slate-600 dark:text-slate-300">{{ $s->telepon ?: $s->kontak ?: '—' }}</td>
                    <td class="py-3 text-slate-600 dark:text-slate-300">{{ $s->alamat ?: '—' }}</td>
                    <td class="py-3 text-slate-500 dark:text-slate-400 font-mono text-xs">{{ $s->npwp ?: '—' }}</td>
                    <td class="py-3">
                        <div class="flex items-center justify-end gap-1.5">
                            @can('sarpras.supplier.kelola')
                            <a href="{{ route('sarpras.supplier.edit', $s) }}" class="p-2 rounded-lg text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20" title="Edit"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                            <form method="POST" action="{{ route('sarpras.supplier.destroy', $s) }}" onsubmit="return confirmDelete(this)">
                                @csrf @method('DELETE')
                                <button class="p-2 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    
</div>
@endsection
