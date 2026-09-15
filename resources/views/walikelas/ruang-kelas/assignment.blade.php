@extends('layouts.app')
@section('title', 'Status Tugas - ' . $assignment->title)

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('walikelas.ruang_kelas.show', $classroom) }}" class="btn-secondary px-3 py-2"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
            <div>
                <h1 class="page-title">{{ $assignment->title }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">Tugas &bull; Kelas {{ $kelas->tingkat }}{{ $kelas->kelas }}</p>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700">
                        <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300 w-16">No</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300">Nama Siswa</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300 w-32">Status</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300 w-24">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse($siswas as $i => $siswa)
                        @php
                            $sub = $submissions->get($siswa->id_login);
                            $status = $sub ? $sub->status : 'unsubmitted';
                            
                            if ($status === 'graded') {
                                $badge = '<span class="badge bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Dinilai</span>';
                            } elseif ($status === 'submitted') {
                                $badge = '<span class="badge bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">Menunggu Penilaian</span>';
                            } else {
                                $badge = '<span class="badge bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300">Belum Mengerjakan</span>';
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <td class="px-4 py-3 text-slate-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $siswa->nama }}</td>
                            <td class="px-4 py-3">{!! $badge !!}</td>
                            <td class="px-4 py-3 font-bold text-slate-700 dark:text-slate-300">
                                {{ $status === 'graded' ? $sub->score : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">Tidak ada data siswa.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
