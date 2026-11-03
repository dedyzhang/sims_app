@extends('layouts.app')
@section('title', 'Poin Saya')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Poin {{ auth()->user()->access === 'orangtua' ? $siswa->nama : 'Saya' }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Riwayat poin kedisiplinan (basis 100)</p>
        </div>
        <a href="{{ route('poin.siswa.print', $siswa) }}" target="_blank" class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="printer" class="w-4 h-4"></i> Cetak Dokumen</a>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <p class="text-xs text-slate-400">Kelas {{ $siswa->kelas ? $siswa->kelas->tingkat.$siswa->kelas->kelas : '-' }} &bull; NIS {{ $siswa->nis }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400">Sisa Poin</p>
                <p class="text-3xl font-extrabold {{ $sisa < 50 ? 'text-rose-600' : ($sisa < 75 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $sisa }}</p>
                @if($peringatan !== '-')
                <span class="badge bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-300 font-semibold">{{ $peringatan }}</span>
                @else
                <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 font-semibold">Aman</span>
                @endif
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <x-sortable-th field="tanggal" label="Tanggal" />
                        <x-sortable-th field="jenis" label="Jenis" />
                        <th>Aturan</th>
                        <x-sortable-th field="poin" label="Poin" align="right" />
                        <x-sortable-th field="sisa" label="Sisa" align="right" />
                    </tr>
                </thead>
                <tbody>
                    @if($ledger->onFirstPage())
                    <tr class="bg-slate-50 dark:bg-slate-800/50">
                        <td colspan="4" class="text-xs font-semibold text-slate-500">Poin Awal</td>
                        <td class="text-right font-bold text-slate-700 dark:text-slate-200">100</td>
                        <td></td>
                    </tr>
                    @endif
                    @forelse($ledger as $i => $l)
                    <tr>
                        <td class="text-slate-400 text-xs">{{ $ledger->firstItem() + $i }}</td>
                        <td class="text-sm text-slate-500">{{ $l['row']->tanggal->isoFormat('D MMM Y') }}</td>
                        <td>
                            @if($l['row']->aturan?->jenis === 'tambah')
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Tambah</span>
                            @else
                            <span class="badge bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-300">Kurang</span>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600 dark:text-slate-300">{{ $l['row']->aturan?->aturan }}</td>
                        <td class="text-right font-semibold {{ $l['delta'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $l['delta'] > 0 ? '+' : '' }}{{ $l['delta'] }}</td>
                        <td class="text-right font-bold text-slate-700 dark:text-slate-200">{{ $l['sisa'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada catatan poin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700">{{ $ledger->links() }}</div>
    </div>
</div>
@endsection
