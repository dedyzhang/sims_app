@extends('layouts.app')
@section('title', 'Data Kelas')

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Data Kelas</h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ $kelas->count() }} kelas terdaftar</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('kelas.setKelas') }}"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-indigo-200 dark:border-indigo-700 text-indigo-700 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span class="hidden sm:inline">Set Siswa</span>
            </a>
            <a href="{{ route('kelas.create') }}" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Kelas
            </a>
        </div>
    </div>

    @if($kelas->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="school" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada kelas</p>
        <a href="{{ route('kelas.create') }}" class="text-indigo-500 hover:underline text-sm mt-1 inline-block">Tambah sekarang</a>
    </div>
    @else
    @foreach($kelas->groupBy('tingkat') as $tingkat => $kelasGroup)
    <div>
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3 px-1">Tingkat {{ $tingkat }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($kelasGroup as $k)
            @php $jumlahSiswa = \App\Models\Siswa::where('id_kelas', $k->uuid)->count(); @endphp
            <div class="card p-5 flex flex-col gap-4 hover:shadow-md transition">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <div class="w-10 h-10 rounded-xl text-white font-bold text-lg flex items-center justify-center"
                                 style="background:var(--cp)">{{ $k->kelas }}</div>
                            <div>
                                <p class="font-bold text-slate-800 dark:text-slate-200">Kelas {{ $k->tingkat }}{{ $k->kelas }}</p>
                                <p class="text-xs text-slate-400">Tingkat {{ $k->tingkat }}</p>
                            </div>
                        </div>
                    </div>
                    <span class="badge {{ $jumlahSiswa>0 ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-500' }}">
                        {{ $jumlahSiswa }} siswa
                    </span>
                </div>

                <div class="flex items-center gap-2 py-2 border-y border-slate-100 dark:border-slate-700">
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 dark:bg-emerald-900 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="user-check" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-500">Wali Kelas</p>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">
                            {{ $k->walikelas?->guru?->nama ?? 'Belum ditentukan' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <a href="{{ route('kelas.showWalikelas', $k->uuid) }}"
                       class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        <i data-lucide="user-check" class="w-3.5 h-3.5"></i> Wali
                    </a>
                    <a href="{{ route('kelas.edit', $k->uuid) }}"
                       class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-medium border border-amber-200 dark:border-amber-700 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                    </a>
                    <form method="POST" action="{{ route('kelas.destroy', $k->uuid) }}" onsubmit="return confirmDelete(this)">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg text-xs font-medium border border-rose-200 dark:border-rose-700 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif
</div>
@endsection
