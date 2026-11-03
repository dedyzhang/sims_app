@extends('layouts.app')
@section('title', 'Lihat Poin — '.$siswa->nama)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <a href="{{ route('poin.siswa.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>

    {{-- Info siswa --}}
    <div class="card p-5">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl grid place-items-center text-white text-lg font-bold flex-shrink-0" style="background:var(--cp)">{{ strtoupper(substr($siswa->nama,0,1)) }}</div>
                <div>
                    <p class="font-bold text-lg text-slate-800 dark:text-slate-100">{{ $siswa->nama }}</p>
                    <p class="text-xs text-slate-400">NIS {{ $siswa->nis }} &bull; Kelas {{ $siswa->kelas ? $siswa->kelas->tingkat.$siswa->kelas->kelas : '-' }}</p>
                </div>
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

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('poin.siswa.print', $siswa) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="printer" class="w-4 h-4"></i> Cetak Poin</a>
        @if(in_array(auth()->user()->access, ['superadmin', 'admin', 'kesiswaan']))
        <a href="{{ route('poin.siswa.create', $siswa) }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="plus" class="w-4 h-4"></i> Tambah Poin</a>
        @endif
    </div>

    {{-- Ledger --}}
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
                        @if(in_array(auth()->user()->access, ['superadmin', 'admin', 'kesiswaan']))
                        <th class="text-right">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @if($ledger->onFirstPage())
                    <tr class="bg-slate-50 dark:bg-slate-800/50">
                        <td colspan="5" class="text-xs font-semibold text-slate-500">Poin Awal</td>
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
                        <td class="text-sm text-slate-600 dark:text-slate-300"><span class="font-mono text-xs text-slate-400">{{ $l['row']->aturan?->kode }}</span> {{ $l['row']->aturan?->aturan }}</td>
                        <td class="text-right font-semibold {{ $l['delta'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $l['delta'] > 0 ? '+' : '' }}{{ $l['delta'] }}</td>
                        <td class="text-right font-bold text-slate-700 dark:text-slate-200">{{ $l['sisa'] }}</td>
                        @if(in_array(auth()->user()->access, ['superadmin', 'admin', 'kesiswaan']))
                        <td class="text-right">
                            <form method="POST" action="{{ route('poin.entri.delete', $l['row']) }}" onsubmit="return confirmDelete(this)">
                                @csrf @method('DELETE')
                                <button class="p-1.5 rounded-lg border border-rose-200 dark:border-rose-800 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada catatan poin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700">{{ $ledger->links() }}</div>
    </div>
</div>
@endsection
