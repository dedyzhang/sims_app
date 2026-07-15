@extends('layouts.app')
@section('title', 'Riwayat Pemanggilan Saya')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Pemanggilan Ortu/Siswa</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Catatan pemanggilan yang Anda buat sendiri</p>
        </div>
        <a href="{{ route('pemanggilan.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Catat Pemanggilan
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <x-sortable-th field="tanggal" label="Tanggal" />
                        <x-sortable-th field="nama" label="Siswa" />
                        <th>Kelas</th>
                        <th>Perihal</th>
                        <th>Status</th>
                        <th class="w-16">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $i => $p)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $items->firstItem() + $i }}</td>
                        <td class="text-sm text-slate-500">{{ $p->tanggal->isoFormat('D MMM Y') }}</td>
                        <td class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $p->siswa?->nama ?? '-' }}</td>
                        <td class="text-sm text-slate-500">{{ $p->siswa?->kelas ? $p->siswa->kelas->tingkat.$p->siswa->kelas->kelas : '-' }}</td>
                        <td class="text-sm text-slate-600 dark:text-slate-300 max-w-xs truncate">{{ $p->perihal }}</td>
                        <td>
                            @if($p->hasil)
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Selesai</span>
                            @else
                            <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">Menunggu Hasil</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('pemanggilan.show', $p) }}" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700" title="Lihat"><i data-lucide="eye" class="w-4 h-4"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-slate-400 py-8">Anda belum pernah mencatat pemanggilan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700">{{ $items->links() }}</div>
    </div>
</div>
@endsection
