@extends('layouts.app')
@section('title', 'Hasil — ' . $ujian->judul)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Hasil
        </nav>
        <h1 class="page-title">Hasil Ujian</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Target nilai: {{ strtoupper($ujian->target_nilai) }} — nilai ditransfer otomatis begitu attempt selesai dinilai.</p>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="text-left px-4 py-2.5">Siswa</th>
                    <th class="text-left px-4 py-2.5">Kelas</th>
                    <th class="text-left px-4 py-2.5">Status</th>
                    <th class="text-left px-4 py-2.5">Skor</th>
                    <th class="text-left px-4 py-2.5">Transfer Nilai</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($attempts as $attempt)
                @php $siswa = $siswaByLogin->get($attempt->id_siswa); @endphp
                <tr>
                    <td class="px-4 py-2.5 font-medium">{{ $siswa?->nama ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-500">{{ $siswa?->kelas?->tingkat }}{{ $siswa?->kelas?->kelas }}</td>
                    <td class="px-4 py-2.5">
                        <span class="badge {{ $attempt->status === 'dinilai' ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' }}">
                            {{ $attempt->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 font-mono">{{ $attempt->status === 'dinilai' ? number_format($attempt->total_skor, 1) : '—' }}</td>
                    <td class="px-4 py-2.5">
                        @if($attempt->status_transfer_nilai === 'berhasil')
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Berhasil</span>
                        @elseif($attempt->status_transfer_nilai === 'gagal_terkunci')
                            <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Gagal — rapor terkunci</span>
                        @elseif($attempt->status_transfer_nilai === 'gagal_lain')
                            <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Gagal — data ngajar/materi tak lengkap</span>
                        @else
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500">Belum</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        @if($attempt->status === 'dinilai' && $attempt->status_transfer_nilai !== 'berhasil')
                        <form method="POST" action="{{ route('ujian.hasil.transferUlang', [$ujian, $attempt]) }}">
                            @csrf
                            <button type="submit" class="text-xs text-primary hover:underline">Transfer Ulang</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada attempt yang dikumpulkan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
