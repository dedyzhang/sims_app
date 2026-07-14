@extends('layouts.app')
@section('title', 'Data Alumni')

@section('content')
@php $breadcrumbs = [['label'=>'Alumni','url'=>route('alumni.index')]]; @endphp
<div class="space-y-5" x-data="{ modalLulus: false }">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="page-title">Data Alumni</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftar siswa yang telah lulus dan tersimpan permanen di sistem.</p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()->canAccess('manage_siswa'))
            <button @click="modalLulus = true" class="px-4 py-2.5 rounded-xl text-sm font-medium bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center gap-2 shadow-sm">
                <i data-lucide="graduation-cap" class="w-4 h-4"></i> Luluskan Kelas 9
            </button>
            @endif
        </div>
    </div>

    {{-- Filter --}}
    <form class="card p-4 flex flex-wrap gap-3 items-end" method="GET" action="{{ route('alumni.index') }}">
        <div class="flex-1 min-w-40">
            <label class="form-label">Cari Nama / NIS</label>
            <input type="text" name="search" class="form-input" placeholder="Masukkan pencarian..." value="{{ request('search') }}">
        </div>
        <div class="min-w-40">
            <label class="form-label">Angkatan / Tahun Lulus</label>
            <select name="tahun_lulus" class="form-select" onchange="this.form.submit()">
                <option value="">Semua Angkatan</option>
                @foreach($angkatans as $thn)
                <option value="{{ $thn }}" @selected(request('tahun_lulus') == $thn)>{{ $thn }}</option>
                @endforeach
            </select>
        </div>
        <button class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-medium transition">
            Cari
        </button>
    </form>

    {{-- Table --}}
    <div class="card p-0 overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-semibold w-12 text-center">No</th>
                    <th class="px-4 py-3 font-semibold">Nama Lengkap</th>
                    <th class="px-4 py-3 font-semibold">NIS/NISN</th>
                    <th class="px-4 py-3 font-semibold">L/P</th>
                    <th class="px-4 py-3 font-semibold">Angkatan</th>
                    <th class="px-4 py-3 font-semibold">Tahun Lulus</th>
                    <th class="px-4 py-3 font-semibold">No HP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                @forelse($alumnis as $k => $s)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                    <td class="px-4 py-3 text-center">{{ $alumnis->firstItem() + $k }}</td>
                    <td class="px-4 py-3 font-medium">
                        <a href="{{ route('siswa.show', $s->uuid) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ $s->nama }}</a>
                    </td>
                    <td class="px-4 py-3">
                        <div>{{ $s->nis ?: '-' }}</div>
                        <div class="text-xs text-slate-400">{{ $s->nisn ?: '-' }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $s->jk }}</td>
                    <td class="px-4 py-3 font-medium text-amber-600 dark:text-amber-400">{{ $s->angkatan ?: '-' }}</td>
                    <td class="px-4 py-3 font-medium text-indigo-600 dark:text-indigo-400">{{ $s->tahun_lulus ?: '-' }}</td>
                    <td class="px-4 py-3">{{ $s->no_handphone ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="p-8 text-center text-slate-500">
                        <i data-lucide="graduation-cap" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                        Tidak ada data alumni ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($alumnis->hasPages())
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">
            {{ $alumnis->links() }}
        </div>
        @endif
    </div>

    {{-- Modal Luluskan --}}
    <div x-show="modalLulus" style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.outside="modalLulus = false" class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-indigo-50 dark:bg-indigo-900/20">
                <h3 class="font-bold text-lg text-indigo-700 dark:text-indigo-400 flex items-center gap-2"><i data-lucide="graduation-cap" class="w-5 h-5"></i> Luluskan Kelas 9</h3>
                <button @click="modalLulus = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form action="{{ route('alumni.luluskan') }}" method="POST">
                @csrf
                <div class="p-5 space-y-4">
                    <div class="p-3 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-xl text-amber-700 dark:text-amber-400 text-sm flex gap-3 items-start">
                        <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                        <p><strong>Peringatan!</strong> Aksi ini akan mengubah status <strong>seluruh siswa yang saat ini berada di tingkat kelas 9</strong> menjadi "Lulus". Kelas mereka saat ini akan dikosongkan. Aksi ini tidak dapat dibatalkan secara massal.</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label block mb-1">Angkatan <span class="text-red-500">*</span></label>
                            <input type="text" name="angkatan" required class="form-input w-full" placeholder="Contoh: 12">
                        </div>
                        <div>
                            <label class="form-label block mb-1">Tahun Lulus <span class="text-red-500">*</span></label>
                            <input type="text" name="tahun_lulus" required class="form-input w-full" placeholder="Contoh: 2025/2026" value="{{ date('Y') }}">
                        </div>
                    </div>
                </div>
                <div class="p-5 border-t border-slate-100 dark:border-slate-700 flex gap-3 justify-end bg-slate-50 dark:bg-slate-800/50">
                    <button type="button" @click="modalLulus = false" class="px-4 py-2 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold bg-indigo-600 text-white hover:bg-indigo-700 transition">Luluskan Sekarang</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
