@extends('layouts.app')
@section('title', 'Pelajaran Guru')

@section('content')
@php $breadcrumbs = [['label'=>'Data Guru','url'=>route('guru.index')], ['label'=>$guru->nama,'url'=>route('guru.show',$guru->uuid)], ['label'=>'Pelajaran','url'=>'#']]; @endphp

<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('guru.show', $guru->uuid) }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Pelajaran Diajar</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $guru->nama }}</p>
        </div>
    </div>

    {{-- Form Tambah --}}
    <form method="POST" action="{{ route('guru.ngajar', $guru->uuid) }}" class="card p-5">
        @csrf
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2"><i data-lucide="plus-circle" class="w-[18px] h-[18px] text-primary"></i> Tambah Pelajaran</h2>
        <div class="flex flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <select name="id_pelajaran" required class="form-select" data-tom>
                    <option value="">Pilih Pelajaran</option>
                    @foreach($pelajarans as $p)
                    <option value="{{ $p->uuid }}">{{ $p->nama }}{{ $p->kode ? " ({$p->kode})" : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-40">
                <select name="id_kelas" class="form-select" data-tom>
                    <option value="">Semua Kelas</option>
                    @foreach($kelas as $k)
                    <option value="{{ $k->uuid }}">Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah
            </button>
        </div>
    </form>

    {{-- Daftar --}}
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Pelajaran</th>
                        <th>Kelas</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ngajars as $ngajar)
                    <tr>
                        <td class="font-medium text-slate-800 dark:text-slate-200">{{ $ngajar->pelajaran?->nama ?? '-' }}</td>
                        <td>
                            <span class="badge bg-primary-50 text-primary">{{ $ngajar->kelas ? 'Kelas '.$ngajar->kelas->tingkat.$ngajar->kelas->kelas : 'Semua Kelas' }}</span>
                        </td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('guru.hapusNgajar', $ngajar->uuid) }}" onsubmit="return confirmDelete(this)">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900 text-rose-500 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center py-10 text-slate-400">
                        <i data-lucide="book-open" class="w-9 h-9 mx-auto mb-2 opacity-30"></i>
                        <p>Belum ada pelajaran yang diajar</p>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('[data-tom]').forEach(el => new TomSelect(el, { create:false }));
</script>
@endpush
@endsection
