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
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Target nilai: {{ strtoupper($ujian->target_nilai) }} — nilai ditransfer otomatis begitu attempt selesai dinilai. Semua siswa kelas ter-assign ditampilkan, termasuk yang belum mengerjakan.</p>
    </div>

    @if($ujianKelasList->count() > 1)
    <form method="GET" action="{{ route('ujian.hasil.index', $ujian) }}" class="flex items-center gap-2">
        <label class="text-xs font-semibold text-slate-500">Kelas:</label>
        <select name="kelas" class="form-select py-1.5 text-sm w-auto" onchange="this.form.submit()">
            <option value="" @selected(!$kelasFilter)>Semua kelas</option>
            @foreach($ujianKelasList->sortBy(fn($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas]) as $uk)
                <option value="{{ $uk->id_kelas }}" @selected($kelasFilter === $uk->id_kelas)>{{ $uk->kelas?->tingkat }}{{ $uk->kelas?->kelas }}</option>
            @endforeach
        </select>
    </form>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
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
                @forelse($roster as $row)
                @php $siswa = $row['siswa']; $attempt = $row['attempt']; $uk = $row['ujianKelas']; @endphp
                <tr>
                    <td class="px-4 py-2.5 font-medium">{{ $siswa->nama }}</td>
                    <td class="px-4 py-2.5 text-slate-500">{{ $uk?->kelas?->tingkat }}{{ $uk?->kelas?->kelas }}</td>
                    <td class="px-4 py-2.5">
                        @if(!$attempt)
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500">Belum Mulai</span>
                        @else
                            <span class="badge {{ $attempt->status === 'dinilai' ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' : 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' }}">
                                {{ $attempt->statusLabel() }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 font-mono">{{ $attempt?->status === 'dinilai' ? number_format($attempt->total_skor, 1) : '—' }}</td>
                    <td class="px-4 py-2.5">
                        @if(!$attempt)
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500">—</span>
                        @elseif($attempt->status_transfer_nilai === 'berhasil')
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Berhasil</span>
                        @elseif($attempt->status_transfer_nilai === 'gagal_terkunci')
                            <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Gagal — rapor terkunci</span>
                        @elseif($attempt->status_transfer_nilai === 'gagal_lain')
                            <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Gagal — data ngajar/materi tak lengkap</span>
                        @else
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500">Belum</span>
                        @endif
                    </td>
                    {{-- Ikon-saja (bukan tautan teks) — hemat ruang di tabel, apalagi 3 aksi
                         sekaligus bisa muncul berbarengan di satu baris. title/aria-label
                         menjaga konteksnya tetap ada (tooltip hover + pembaca layar). --}}
                    <td class="px-4 py-2.5 text-right space-x-1 whitespace-nowrap">
                        @if($attempt && $attempt->status === 'dinilai' && $attempt->status_transfer_nilai !== 'berhasil')
                        <form method="POST" action="{{ route('ujian.hasil.transferUlang', [$ujian, $attempt]) }}" class="inline">
                            @csrf
                            <button type="submit" title="Transfer Ulang" aria-label="Transfer Ulang" class="inline-flex p-1.5 rounded-lg text-primary hover:bg-primary/10">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                            </button>
                        </form>
                        @endif
                        @if($attempt && in_array($attempt->status, ['submitted', 'dinilai']))
                        <form method="POST" action="{{ route('ujian.hasil.bukaAkses', [$ujian, $attempt]) }}" class="inline"
                              onsubmit="return confirmAction(this, 'Buka kembali akses ujian ini utk {{ $siswa->nama }}? {{ $attempt->status_transfer_nilai === 'berhasil' ? 'NILAI YANG SUDAH TERTRANSFER ke buku nilai ikut terhapus. ' : '' }}Jawaban yang sudah tersimpan TETAP ADA, siswa lanjut dari titik terakhir dengan waktu pengerjaan baru — siswa tetap harus masukkan token lagi demi keamanan.', 'orange')">
                            @csrf
                            <button type="submit" title="Buka Kembali Akses" aria-label="Buka Kembali Akses" class="inline-flex p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30">
                                <i data-lucide="lock-open" class="w-4 h-4"></i>
                            </button>
                        </form>
                        @endif
                        @if($siswa->id_login)
                        <a href="{{ route('ujian.hasil.detail', [$ujian, $siswa->id_login]) }}" title="Lihat Jawaban" aria-label="Lihat Jawaban" class="inline-flex p-1.5 rounded-lg text-primary hover:bg-primary/10">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Belum ada siswa di kelas ujian ini.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
