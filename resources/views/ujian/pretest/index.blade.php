@extends('layouts.app')
@section('title', 'Pre-Test Publik — ' . $ujian->judul)

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{ copySuccess: false, searchPeserta: '' }">
    {{-- Breadcrumb & Judul --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
                <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Pre-Test
            </nav>
            <div class="flex items-center gap-2.5 flex-wrap">
                <h1 class="page-title text-xl sm:text-2xl font-bold text-slate-800 dark:text-slate-100 m-0">Pre-Test &amp; Uji Coba Publik</h1>
                <span class="badge {{ $pretest->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400' }}">
                    <i data-lucide="{{ $pretest->is_active ? 'check-circle' : 'x-circle' }}" class="w-3.5 h-3.5 inline mr-1"></i>
                    {{ $pretest->is_active ? 'Akses Terbuka' : 'Akses Ditutup' }}
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $ujian->pelajaran?->nama }} · {{ $ujian->durasi_menit }} Menit · {{ $ujian->soal->count() }} Butir Soal
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('ujian.pretest.unduh-analisis', $ujian) }}"
               class="btn-primary px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold flex items-center gap-1.5 shadow-sm">
                <i data-lucide="download" class="w-4 h-4"></i> Unduh Excel
            </a>
            <a href="{{ route('ujian.pretest.cetak-analisis', $ujian) }}" target="_blank"
               class="btn-secondary px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-1.5 shadow-sm">
                <i data-lucide="printer" class="w-4 h-4 text-slate-600 dark:text-slate-300"></i> Cetak Analisis
            </a>
            <a href="{{ route('ujian.show', $ujian) }}"
               class="btn-secondary px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke Ujian
            </a>
        </div>
    </div>

    {{-- Grid 2 Kolom: QR & Link + Statistik & Pengaturan --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Kolom Kiri: QR Code & Link Publik --}}
        <div class="lg:col-span-5 space-y-5">
            <div class="card p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i data-lucide="qr-code" class="w-4 h-4 text-primary"></i> Scan Barcode &amp; Link
                    </h2>
                    <form method="POST" action="{{ route('ujian.pretest.toggle', $ujian) }}">
                        @csrf
                        <button type="submit" class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition {{ $pretest->is_active ? 'border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-800 dark:hover:bg-rose-950/40' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-800 dark:hover:bg-emerald-950/40' }}">
                            {{ $pretest->is_active ? 'Tutup Akses' : 'Buka Akses' }}
                        </button>
                    </form>
                </div>

                <div class="flex flex-col items-center text-center space-y-3">
                    <div class="p-3 bg-white border border-slate-200 dark:border-slate-700 rounded-2xl shadow-sm">
                        <img src="{{ $qrUri }}" alt="QR Pre-Test {{ $ujian->judul }}" class="w-48 h-48 block">
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-xs">
                        Arahkan kamera HP ke barcode di atas untuk langsung mulai mengerjakan pre-test tanpa login.
                    </p>
                </div>

                {{-- Input Link & Tombol Salin --}}
                <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                    <label class="text-xs font-medium text-slate-600 dark:text-slate-300">Tautan Langsung Pre-Test</label>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="{{ $joinUrl }}" id="input-pretest-url"
                               class="form-input text-xs font-mono bg-slate-50 dark:bg-slate-900/50 flex-1 select-all cursor-pointer">
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $joinUrl }}'); copySuccess = true; setTimeout(() => copySuccess = false, 2000)"
                                class="btn-primary px-3 py-2 rounded-xl text-xs font-bold whitespace-nowrap flex items-center gap-1.5">
                            <i data-lucide="copy" class="w-3.5 h-3.5" x-show="!copySuccess"></i>
                            <i data-lucide="check" class="w-3.5 h-3.5 text-white" x-show="copySuccess" x-cloak></i>
                            <span x-text="copySuccess ? 'Tersalin!' : 'Salin'">Salin</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <a href="{{ $joinUrl }}" target="_blank" class="text-primary hover:underline flex items-center gap-1 font-medium">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Coba Buka Link Peserta
                    </a>
                    <form method="POST" action="{{ route('ujian.pretest.regenerate', $ujian) }}" onsubmit="return confirmAction(this, 'Buat ulang token link pre-test? Tautan dan QR code lama tidak akan berlaku lagi.', 'orange')">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-amber-600 transition flex items-center gap-1">
                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Buat Ulang Link
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Ringkasan Nilai & Pengaturan Pre-test --}}
        <div class="lg:col-span-7 space-y-5">
            {{-- Kartu Statistik Ringkas --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="card p-4 text-center">
                    <span class="text-xs text-slate-400 block mb-0.5">Total Peserta</span>
                    <span class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ $totalPeserta }}</span>
                </div>
                <div class="card p-4 text-center">
                    <span class="text-xs text-slate-400 block mb-0.5">Sudah Selesai</span>
                    <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $totalSelesai }}</span>
                </div>
                <div class="card p-4 text-center">
                    <span class="text-xs text-slate-400 block mb-0.5">Rata-Rata Nilai</span>
                    <span class="text-2xl font-extrabold text-primary">{{ $rataRata }}</span>
                </div>
                <div class="card p-4 text-center">
                    <span class="text-xs text-slate-400 block mb-0.5">Tertinggi / Terendah</span>
                    <span class="text-lg font-bold text-slate-700 dark:text-slate-200">{{ $nilaiTertinggi }} / {{ $nilaiTerendah }}</span>
                </div>
            </div>

            {{-- Form Pengaturan Pre-Test --}}
            <form method="POST" action="{{ route('ujian.pretest.update', $ujian) }}" class="card p-6 space-y-4">
                @csrf
                @method('PUT')
                <div class="border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <h2 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4 text-primary"></i> Pengaturan Uji Coba Pre-Test
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label text-xs">Judul Sesi Pre-Test</label>
                        <input type="text" name="judul" value="{{ old('judul', $pretest->judul) }}" required class="form-input text-xs w-full">
                    </div>
                    <div>
                        <label class="form-label text-xs">Durasi Pengerjaan (Menit)</label>
                        <input type="number" name="durasi_menit" value="{{ old('durasi_menit', $pretest->durasi_menit) }}" min="0" max="300" required class="form-input text-xs w-full" placeholder="0 = Tanpa batas waktu">
                        <span class="text-[11px] text-slate-400 mt-0.5 block">Isi 0 jika tidak ingin membatasi durasi.</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-slate-700/60 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="acak_soal" value="1" @checked($pretest->acak_soal) class="rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-slate-700 dark:text-slate-300">Acak urutan nomor soal</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="acak_opsi" value="1" @checked($pretest->acak_opsi) class="rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-slate-700 dark:text-slate-300">Acak urutan opsi pilihan ganda</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="tampilkan_nilai" value="1" @checked($pretest->tampilkan_nilai) class="rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-slate-700 dark:text-slate-300">Tampilkan nilai setelah submit</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="tampilkan_pembahasan" value="1" @checked($pretest->tampilkan_pembahasan) class="rounded border-slate-300 text-primary focus:ring-primary">
                        <span class="text-slate-700 dark:text-slate-300">Tampilkan kunci &amp; pembahasan</span>
                    </label>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold shadow-sm">
                        Simpan Pengaturan Pre-Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabel Hasil & Jawaban Peserta Pre-Test --}}
    <div class="card p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700/60 pb-4">
            <div>
                <h2 class="font-bold text-base text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i data-lucide="users" class="w-4 h-4 text-primary"></i> Hasil Jawaban Peserta Pre-Test
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Daftar siapa saja yang telah membuka dan mengerjakan pre-test ini.</p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <input type="text" x-model="searchPeserta" placeholder="Cari nama / kelas..."
                       class="form-input text-xs py-1.5 px-3 rounded-xl w-48">

                @if($pesertaList->isNotEmpty())
                <form method="POST" action="{{ route('ujian.pretest.peserta.reset', $ujian) }}"
                      onsubmit="return confirmAction(this, 'PERINGATAN: Seluruh data hasil jawaban peserta pre-test ini akan dihapus permanen. Lanjutkan?', 'red')">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-900 transition flex items-center gap-1">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Bersihkan Semua
                    </button>
                </form>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-3">No</th>
                        <th class="py-3 px-3">Nama Peserta</th>
                        <th class="py-3 px-3">Asal / Kelas</th>
                        <th class="py-3 px-3">Waktu Mulai</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-center">Benar / Salah</th>
                        <th class="py-3 px-3 text-center">Nilai Akhir</th>
                        <th class="py-3 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                    @forelse($pesertaList as $idx => $p)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition"
                        x-show="!searchPeserta || '{{ strtolower($p->nama . ' ' . $p->identitas) }}'.includes(searchPeserta.toLowerCase())">
                        <td class="py-3 px-3 text-slate-400">{{ $idx + 1 }}</td>
                        <td class="py-3 px-3">
                            <span class="font-bold text-slate-800 dark:text-slate-100 block">{{ $p->nama }}</span>
                            @if($p->nomor_kontak)
                                <span class="text-[11px] text-slate-400 font-mono">{{ $p->nomor_kontak }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $p->identitas }}</span>
                        </td>
                        <td class="py-3 px-3 text-slate-500">
                            <div>{{ $p->mulai_pada?->translatedFormat('d M Y, H:i') ?? '-' }}</div>
                            @if($p->selesai_pada)
                                <span class="text-[10px] text-slate-400">Durasi: {{ $p->mulai_pada?->diffInMinutes($p->selesai_pada) }} mnt</span>
                            @endif
                        </td>
                        <td class="py-3 px-3">
                            @if($p->status === 'submitted')
                                <span class="badge bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300">Selesai</span>
                            @else
                                <span class="badge bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300">Sedang Mengerjakan</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-center font-mono">
                            @if($p->status === 'submitted')
                                <span class="text-emerald-600 font-semibold">{{ $p->jumlah_benar }}</span>
                                <span class="text-slate-400">/</span>
                                <span class="text-rose-500 font-semibold">{{ $p->jumlah_salah }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-center font-mono text-sm">
                            @if($p->status === 'submitted')
                                <span class="font-bold {{ $p->total_skor >= ($ujian->pelajaran?->kkm ?? 75) ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ number_format($p->total_skor, 1) }}
                                </span>
                            @else
                                <span class="text-slate-400 text-xs italic">Belum submit</span>
                            @endif
                        </td>
                        <td class="py-3 px-3 text-right">
                            <div class="inline-flex items-center gap-1.5">
                                <a href="{{ route('ujian.pretest.peserta.detail', [$ujian, $p]) }}"
                                   class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition flex items-center gap-1">
                                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary"></i> Rincian
                                </a>
                                <form method="POST" action="{{ route('ujian.pretest.peserta.destroy', [$ujian, $p]) }}"
                                      onsubmit="return confirmAction(this, 'Hapus hasil pre-test peserta {{ $p->nama }}?', 'red')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-10 text-slate-400">
                            <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                            <p class="text-xs">Belum ada peserta yang mengerjakan pre-test ini.</p>
                            <p class="text-[11px] text-slate-400 mt-1">Bagikan tautan atau cetak barcode di atas agar peserta dapat mulai mencoba.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
