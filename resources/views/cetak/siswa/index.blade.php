@extends('layouts.app')
@section('title', 'Cetak Data Siswa')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="page-title">Cetak Data Siswa</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Unduh biodata &amp; statistik siswa dalam format Excel — semua kelas, per tingkat, atau per kelas.</p>
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200">Semua Kelas &amp; Per Tingkat</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center w-12">No</th>
                    <th class="text-left">Cakupan</th>
                    <th class="text-center w-32">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center text-slate-400">&bull;</td>
                    <td class="font-semibold text-slate-700 dark:text-slate-200">Semua Kelas</td>
                    <td class="text-center">
                        <a href="{{ route('cetak.siswa.excel', 'semua') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition">
                            <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Unduh
                        </a>
                    </td>
                </tr>
                @foreach($tingkatList as $i => $t)
                <tr>
                    <td class="text-center text-slate-400">{{ $i + 1 }}</td>
                    <td class="font-medium text-slate-700 dark:text-slate-200">Tingkat {{ $t }} <span class="text-slate-400 font-normal">(gabungan semua kelas {{ $t }})</span></td>
                    <td class="text-center">
                        <a href="{{ route('cetak.siswa.excel', 'tingkat-'.$t) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                            <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Unduh
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200">Per Kelas</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-center w-12">No</th>
                    <th class="text-left">Kelas</th>
                    <th class="text-center w-32">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kelas as $i => $k)
                <tr>
                    <td class="text-center text-slate-400">{{ $i + 1 }}</td>
                    <td class="font-medium text-slate-700 dark:text-slate-200">Kelas {{ $k->tingkat }}{{ $k->kelas }}</td>
                    <td class="text-center">
                        <a href="{{ route('cetak.siswa.excel', $k->uuid) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                            <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i> Unduh
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
