@extends('layouts.app')
@section('title', 'Dashboard Kedisiplinan')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Dashboard Kedisiplinan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Peringkat 10 siswa dengan sisa poin terbesar (basis 100) &mdash; dari {{ $totalSiswa }} siswa</p>
        </div>
        <a href="{{ route('poin.siswa.index') }}" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"><i data-lucide="users" class="w-4 h-4"></i> Poin Siswa</a>
    </div>

    {{-- Scope tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1">
        <a href="{{ route('poin.dashboard', ['scope' => 'sekolah']) }}" class="whitespace-nowrap px-4 py-2 rounded-xl text-sm font-semibold transition {{ $scope === 'sekolah' ? 'btn-primary shadow-sm' : 'border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
            <i data-lucide="school" class="w-4 h-4 inline -mt-0.5"></i> Seluruh Sekolah
        </a>
        <a href="{{ route('poin.dashboard', ['scope' => 'tingkat', 'tingkat' => $selTingkat ?? $tingkatList->first()]) }}" class="whitespace-nowrap px-4 py-2 rounded-xl text-sm font-semibold transition {{ $scope === 'tingkat' ? 'btn-primary shadow-sm' : 'border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
            <i data-lucide="layers" class="w-4 h-4 inline -mt-0.5"></i> Per Tingkat
        </a>
        <a href="{{ route('poin.dashboard', ['scope' => 'kelas', 'kelas' => $selKelas ?? optional($kelasList->first())?->uuid]) }}" class="whitespace-nowrap px-4 py-2 rounded-xl text-sm font-semibold transition {{ $scope === 'kelas' ? 'btn-primary shadow-sm' : 'border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
            <i data-lucide="door-open" class="w-4 h-4 inline -mt-0.5"></i> Per Kelas
        </a>
    </div>

    @if($scope === 'kelas' && $kelasList->isNotEmpty())
    <form method="GET" class="card p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="scope" value="kelas">
        <div class="flex-1 min-w-40">
            <label class="form-label">Kelas</label>
            <select name="kelas" class="form-select" onchange="this.form.submit()">
                @foreach($kelasList as $k)
                <option value="{{ $k->uuid }}" @selected($selKelas === $k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
    </form>
    @elseif($scope === 'tingkat' && $tingkatList->isNotEmpty())
    <form method="GET" class="card p-4 flex flex-wrap gap-3 items-end">
        <input type="hidden" name="scope" value="tingkat">
        <div class="flex-1 min-w-40">
            <label class="form-label">Tingkat</label>
            <select name="tingkat" class="form-select" onchange="this.form.submit()">
                @foreach($tingkatList as $t)
                <option value="{{ $t }}" @selected((int) $selTingkat === (int) $t)>Tingkat {{ $t }}</option>
                @endforeach
            </select>
        </div>
    </form>
    @endif

    @if($top10->isEmpty())
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="trophy" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada data siswa untuk cakupan ini.</p>
    </div>
    @else
    {{-- Podium peringkat 1-3 --}}
    <div class="card p-4 sm:p-6 md:p-10 relative overflow-hidden">
        <div class="absolute inset-0 opacity-5 pointer-events-none" style="background:radial-gradient(circle at 50% 0%, #f59e0b, transparent 60%)"></div>
        <x-podium :items="$top10" />
    </div>

    {{-- Peringkat 4-10 --}}
    @if($top10->count() > 3)
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table w-full">
                <thead>
                    <tr>
                        <th class="w-20">Peringkat</th>
                        <th>Nama</th>
                        <th class="w-24">Kelas</th>
                        <th class="text-right w-32">Sisa Poin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($top10->slice(3)->values() as $i => $r)
                    <tr>
                        <td class="text-slate-400 font-semibold">#{{ $i + 4 }}</td>
                        <td class="font-semibold text-slate-800 dark:text-slate-200">{{ $r['siswa']->nama }}</td>
                        <td class="text-slate-500">{{ $r['siswa']->kelas ? $r['siswa']->kelas->tingkat . $r['siswa']->kelas->kelas : '-' }}</td>
                        <td class="text-right font-bold text-slate-700 dark:text-slate-200">{{ $r['sisa'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endif
</div>
@endsection
