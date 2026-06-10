@extends('layouts.app')
@section('title', 'Histori Rombel')

@section('content')
@php $breadcrumbs = [['label'=>'Data Kelas','url'=>route('kelas.index')], ['label'=>'Penempatan','url'=>route('kelas.setKelas')], ['label'=>'Histori','url'=>'#']]; @endphp

<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('kelas.setKelas') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="page-title">Histori Rombel</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Riwayat penempatan siswa per semester</p>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas</th>
                        <th>Semester</th>
                        <th class="hide-mobile">Tanggal</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rombels as $r)
                    <tr>
                        <td class="font-medium text-slate-800 dark:text-slate-200">{{ $r->siswa?->nama ?? '-' }}</td>
                        <td><span class="badge bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">{{ $r->kelas ? $r->kelas->tingkat.$r->kelas->kelas : '-' }}</span></td>
                        <td class="text-slate-600 dark:text-slate-400 font-mono text-xs">{{ $r->semester }}</td>
                        <td class="hide-mobile text-slate-500 text-xs">{{ $r->created_at->format('d/m/Y') }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('kelas.historiHapus', $r->uuid) }}" onsubmit="return confirmDelete(this)">
                                @csrf
                                <button type="submit" class="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900 text-rose-500 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-10 text-slate-400">
                        <i data-lucide="history" class="w-9 h-9 mx-auto mb-2 opacity-30"></i>
                        <p>Belum ada histori penempatan</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rombels->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">{{ $rombels->links() }}</div>
        @endif
    </div>
</div>
@endsection
