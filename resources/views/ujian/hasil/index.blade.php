@extends('layouts.app')
@section('title', 'Hasil — ' . $ujian->judul)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Hasil
        </nav>
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="page-title">Hasil Ujian</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Target nilai: {{ strtoupper($ujian->target_nilai) }} — nilai ditransfer otomatis begitu attempt selesai dinilai.</p>
            </div>
            @if($kelasFilter && $ujianKelasList->count() > 1)
            <a href="{{ route('ujian.hasil.index', $ujian) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Kembali ke Pilih Kelas</span>
            </a>
            @endif
        </div>
    </div>

    @if(!$kelasFilter)
    {{-- TAMPILAN 1: PILIH KELAS (Ringkasan per-kelas agar server ringan & tidak meload ratusan siswa sekaligus) --}}
    <div class="card p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/60 rounded-2xl flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 text-blue-600 shrink-0 mt-0.5"></i>
        <div class="text-xs text-blue-900 dark:text-blue-200 space-y-0.5">
            <p class="font-bold">Ujian Multi-Kelas ({{ $ujianKelasList->count() }} Kelas Ditugaskan)</p>
            <p>Pilih kelas di bawah untuk melihat rincian hasil pengerjaan siswa dan transfer nilai. Membuka per kelas memastikan data dimuat lebih cepat dan menghemat kinerja server.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($kelasSummary as $c)
        <div class="card p-5 border border-slate-200 dark:border-slate-700/60 rounded-2xl flex flex-col justify-between hover:shadow-md hover:border-primary/50 transition">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="inline-block px-2.5 py-1 rounded-lg bg-primary/10 text-primary font-bold text-base">
                            Kelas {{ $c['kelas']?->tingkat }}{{ $c['kelas']?->kelas }}
                        </span>
                        @if($c['ujianKelas']->guruPengampu)
                        <p class="text-xs text-slate-400 mt-1 truncate" title="{{ $c['ujianKelas']->guruPengampu->nama }}">
                            Pengampu: {{ $c['ujianKelas']->guruPengampu->nama }}
                        </p>
                        @endif
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                        {{ $c['total_siswa'] }} Siswa
                    </span>
                </div>

                {{-- Status Breakdown --}}
                <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div class="bg-emerald-50 dark:bg-emerald-950/30 p-2 rounded-xl border border-emerald-100 dark:border-emerald-900/50">
                        <span class="text-slate-500 block text-[11px]">Selesai Dinilai</span>
                        <span class="text-emerald-700 dark:text-emerald-400 font-bold text-sm">{{ $c['dinilai'] }}</span>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-950/30 p-2 rounded-xl border border-amber-100 dark:border-amber-900/50">
                        <span class="text-slate-500 block text-[11px]">Sedang / Menunggu</span>
                        <span class="text-amber-700 dark:text-amber-400 font-bold text-sm">{{ $c['submitted'] + $c['in_progress'] }}</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl border border-slate-200/60 dark:border-slate-700">
                        <span class="text-slate-500 block text-[11px]">Belum Mulai</span>
                        <span class="text-slate-600 dark:text-slate-300 font-bold text-sm">{{ $c['belum_mulai'] }}</span>
                    </div>
                    <div class="bg-indigo-50 dark:bg-indigo-950/30 p-2 rounded-xl border border-indigo-100 dark:border-indigo-900/50">
                        <span class="text-slate-500 block text-[11px]">Nilai Masuk Rapor</span>
                        <span class="text-indigo-700 dark:text-indigo-400 font-bold text-sm">{{ $c['tertransfer'] }}</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-3 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('ujian.hasil.index', ['ujian' => $ujian, 'kelas' => $c['ujianKelas']->id_kelas]) }}" class="btn-primary w-full py-2 rounded-xl text-center text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm">
                    <span>Buka Hasil Siswa</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    @else
    {{-- TAMPILAN 2: DAFTAR SISWA KELAS TERPILIH --}}
    @if(!$bisaTransferNilai)
    <div class="card p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 rounded-2xl flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
        <div class="text-xs text-amber-800 dark:text-amber-300 space-y-0.5">
            <p class="font-bold">Pengampu Pengganti (Di Luar Data Ngajar Sekolah)</p>
            <p>Anda ditugaskan sebagai pengampu ujian di luar data penugasan mengajar sekolah. Anda dapat melihat hasil pengerjaan siswa dan memeriksa esai, tetapi <strong>tidak dapat mengumpulkan/mentransfer nilai</strong> langsung ke dalam buku nilai rapor. Pengumpulan nilai hanya dapat diproses oleh guru mata pelajaran resmi atau admin.</p>
        </div>
    </div>
    @endif

    <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
        @if($ujianKelasList->count() > 1)
        <form method="GET" action="{{ route('ujian.hasil.index', $ujian) }}" class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-500">Kelas:</label>
            <select name="kelas" class="form-select py-1.5 text-sm w-auto font-semibold" onchange="this.form.submit()">
                @foreach($ujianKelasList->sortBy(fn($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas]) as $uk)
                    <option value="{{ $uk->id_kelas }}" @selected($kelasFilter === $uk->id_kelas)>Kelas {{ $uk->kelas?->tingkat }}{{ $uk->kelas?->kelas }}</option>
                @endforeach
            </select>
        </form>
        @else
        <div></div>
        @endif

        <div class="flex items-center flex-wrap gap-2">
            <form method="POST" action="{{ route('ujian.hasil.paksaSelesaiSemua', $ujian) }}?kelas={{ $kelasFilter }}" onsubmit="return confirmAction(this, 'Kumpulkan secara paksa siswa kelas ini yang berstatus Sedang Mengerjakan? Mereka akan dinilai secara otomatis saat ini juga.', 'red')">
                @csrf
                <button type="submit" class="btn-primary bg-rose-500 hover:bg-rose-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i data-lucide="check-square" class="w-4 h-4"></i> Kumpul Semua
                </button>
            </form>
            @if($bisaTransferNilai)
            <form method="POST" action="{{ route('ujian.hasil.transferSemua', $ujian) }}?kelas={{ $kelasFilter }}" onsubmit="return confirmAction(this, 'Transfer nilai siswa kelas ini yang sudah selesai dinilai ke buku nilai secara massal?', 'blue')">
                @csrf
                <button type="submit" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Transfer Semua Nilai
                </button>
            </form>
            @else
            <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800" title="Anda bukan guru mata pelajaran resmi di data penugasan mengajar sekolah.">
                <i data-lucide="lock" class="w-3.5 h-3.5 text-amber-600 shrink-0"></i>
                <span>Transfer Nilai Terkunci (Di Luar Data Ngajar)</span>
            </div>
            @endif
            <form method="POST" action="{{ route('ujian.hasil.resetSemua', $ujian) }}?kelas={{ $kelasFilter }}" onsubmit="return confirmAction(this, 'Hapus SELURUH hasil pengerjaan siswa di kelas ini? Siswa harus mengulang dari awal dan nilai yang sudah ditransfer ke buku nilai akan dicabut.', 'red')">
                @csrf
                <button type="submit" class="btn-primary bg-slate-800 hover:bg-slate-900 text-white px-4 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Reset Semua
                </button>
            </form>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
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
                    <td class="px-4 py-2.5 font-mono">
                        @php $skor = $attempt?->skorSementara($totalPoin, $modeSkor); @endphp
                        @if($skor !== null)
                            {{ number_format($skor, 1) }}
                            @if($attempt->status !== 'dinilai')<span class="text-[10px] text-amber-500 font-sans font-normal">(sementara)</span>@endif
                        @else
                            —
                        @endif
                    </td>
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
                    <td class="px-4 py-2.5 text-right space-x-1 whitespace-nowrap">
                        @if($attempt && $attempt->status === 'in_progress')
                        <form method="POST" action="{{ route('ujian.hasil.paksaSelesai', [$ujian, $attempt]) }}" class="inline" onsubmit="return confirmAction(this, 'Kumpulkan paksa ujian {{ $siswa->nama }} sekarang? Semua jawaban yang tersimpan akan langsung dinilai.', 'red')">
                            @csrf
                            <button type="submit" title="Paksa Selesai & Nilai" aria-label="Paksa Selesai & Nilai" class="inline-flex p-1.5 rounded-lg text-rose-500 hover:bg-rose-500/10">
                                <i data-lucide="check-square" class="w-4 h-4"></i>
                            </button>
                        </form>
                        @endif
                        @if($bisaTransferNilai && $attempt && $attempt->status === 'dinilai' && $attempt->status_transfer_nilai !== 'berhasil')
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
                        @if($attempt && $attempt->status !== 'dibatalkan')
                        <form method="POST" action="{{ route('ujian.monitor.resetAttempt', [$ujian, $attempt]) }}" class="inline"
                              onsubmit="return confirmAction(this, 'Reset ulang ujian {{ $siswa->nama }}? Jawaban akan dihapus dan siswa bisa mengulang dari awal.', 'red')">
                            @csrf
                            <button type="submit" title="Reset Ulang" aria-label="Reset Ulang" class="inline-flex p-1.5 rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30">
                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
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
    @endif
</div>
@endsection
