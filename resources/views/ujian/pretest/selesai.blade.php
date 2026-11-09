<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pre-Test Selesai — {{ $ujian->judul }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('build/assets/app-GtwHpXZD.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/lucide@0.468.0"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Inter', sans-serif; }
        .ujian-rich-body { line-height: 1.6; overflow-wrap: break-word; word-wrap: break-word; }
        .ujian-rich-body img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 0.5rem 0; }
        .ujian-rich-body table { border-collapse: collapse; width: 100%; margin: 0.5rem 0; font-size: 0.875rem; }
        .ujian-rich-body th, .ujian-rich-body td { border: 1px solid #cbd5e1; padding: 0.375rem 0.625rem; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased py-8 px-4 sm:px-6">
    <div class="max-w-2xl mx-auto space-y-6">

        {{-- Kartu Hasil Utama --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xl shadow-slate-200/50 p-6 sm:p-8 text-center">
            
            {{-- Icon Sukses --}}
            <div class="w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-4 ring-8 ring-emerald-50/50">
                <i data-lucide="check-check" class="w-8 h-8"></i>
            </div>

            <span class="inline-block px-3 py-1 text-xs font-extrabold text-emerald-700 bg-emerald-100 rounded-full uppercase tracking-wider mb-2">
                Jawaban Berhasil Disimpan
            </span>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Terima Kasih Telah Berpartisipasi!</h1>
            <p class="text-slate-500 text-sm mt-1 max-w-md mx-auto">
                Anda telah menyelesaikan simulasi pre-test untuk paket soal <strong class="text-slate-700">{{ $ujian->judul }}</strong>.
            </p>

            {{-- Ringkasan Identitas --}}
            <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-4 gap-3 text-left">
                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nama Peserta</div>
                    <div class="text-sm font-bold text-slate-800 truncate mt-0.5" title="{{ $peserta->nama }}">{{ $peserta->nama }}</div>
                </div>
                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Asal / Instansi</div>
                    <div class="text-sm font-bold text-slate-800 truncate mt-0.5" title="{{ $peserta->identitas ?? '-' }}">{{ $peserta->identitas ?? '-' }}</div>
                </div>
                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Mulai</div>
                    <div class="text-sm font-bold text-slate-800 mt-0.5">{{ $peserta->mulai_pada?->format('H:i') ?? '-' }} WIB</div>
                </div>
                <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Selesai</div>
                    <div class="text-sm font-bold text-slate-800 mt-0.5">{{ $peserta->selesai_pada?->format('H:i') ?? '-' }} WIB</div>
                </div>
            </div>

            {{-- Hasil Nilai (Jika Diizinkan Guru) --}}
            @if($pretest->tampilkan_nilai)
                <div class="mt-6 p-6 rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-800 text-white shadow-xl shadow-indigo-500/25">
                    <div class="text-xs uppercase tracking-wider font-extrabold text-indigo-200">Nilai Akhir Pre-Test</div>
                    <div class="text-5xl sm:text-6xl font-black mt-2 tracking-tight">{{ number_format($peserta->total_skor ?? 0, 1) }}</div>
                    
                    <div class="mt-6 grid grid-cols-3 gap-2 pt-5 border-t border-indigo-400/30 text-center">
                        <div class="bg-white/10 rounded-2xl py-2 px-1">
                            <div class="text-[11px] text-indigo-200 font-semibold uppercase">Total Soal</div>
                            <div class="text-xl font-black text-white mt-0.5">{{ $ujian->soal->count() }}</div>
                        </div>
                        <div class="bg-white/10 rounded-2xl py-2 px-1">
                            <div class="text-[11px] text-emerald-200 font-semibold uppercase">Benar</div>
                            <div class="text-xl font-black text-emerald-300 mt-0.5">{{ $peserta->jumlah_benar }}</div>
                        </div>
                        <div class="bg-white/10 rounded-2xl py-2 px-1">
                            <div class="text-[11px] text-rose-200 font-semibold uppercase">Salah</div>
                            <div class="text-xl font-black text-rose-300 mt-0.5">{{ $peserta->jumlah_salah }}</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs flex items-center justify-center gap-2.5">
                    <i data-lucide="eye-off" class="w-4 h-4 text-amber-600 flex-shrink-0"></i>
                    <span>Nilai dan pembahasan disembunyikan oleh pembuat soal. Rekap pengerjaan Anda telah tersimpan rapi untuk pengajar.</span>
                </div>
            @endif

            {{-- Tombol Aksi Tambahan --}}
            <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-center gap-3">
                <button type="button" onclick="window.print()" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition flex items-center gap-1.5">
                    <i data-lucide="printer" class="w-4 h-4"></i> Cetak Bukti Selesai
                </button>
            </div>
        </div>

        {{-- Review Pembahasan & Kunci Jawaban (Jika Diizinkan) --}}
        @if($pretest->tampilkan_pembahasan)
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
                <div>
                    <h2 class="text-lg font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i data-lucide="book-open-check" class="w-5 h-5 text-indigo-600"></i>
                        Pembahasan &amp; Kunci Jawaban
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Tinjau kembali butir soal dan jawaban yang telah Anda kerjakan di bawah ini.</p>
                </div>

                <div class="space-y-4">
                    @foreach($ujian->soal as $index => $soal)
                        @php
                            $jawaban = $jawabanBySoal->get($soal->uuid);
                            $isBenar = $jawaban?->is_benar;
                        @endphp
                        <div class="p-4 sm:p-5 rounded-2xl border {{ $isBenar ? 'border-emerald-200 bg-emerald-50/20' : ($jawaban ? 'border-rose-200 bg-rose-50/20' : 'border-slate-200 bg-slate-50/50') }}">
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-xs font-black px-2.5 py-1 rounded-lg {{ $isBenar ? 'bg-emerald-100 text-emerald-800' : ($jawaban ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700') }}">
                                    Nomor {{ $index + 1 }}
                                </span>
                                @if($isBenar)
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                        Benar (+{{ $jawaban->skor_diperoleh }})
                                    </span>
                                @elseif($jawaban && $soal->tipe !== 'essay')
                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-rose-700 bg-rose-100 px-2.5 py-0.5 rounded-full">
                                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                        Salah (0)
                                    </span>
                                @else
                                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">
                                        {{ $soal->tipe === 'essay' ? 'Esai (Menunggu Penilaian)' : 'Tidak Dijawab' }}
                                    </span>
                                @endif
                            </div>

                            {{-- Teks Soal --}}
                            <div class="ujian-rich-body text-slate-800 mb-3 text-sm">
                                {!! $soal->teks_soal !!}
                            </div>

                            {{-- Pilihan Opsi / Jawaban --}}
                            @if(in_array($soal->tipe, ['mcq', 'true_false']))
                                <div class="space-y-1.5 text-xs">
                                    @foreach($soal->opsi as $opsi)
                                        @php
                                            $dipilih = $jawaban && $jawaban->id_opsi_dipilih === $opsi->uuid;
                                            $isKunci = (bool) $opsi->is_benar;
                                            $labelOpsi = chr(64 + ($opsi->urutan ?? 1));
                                        @endphp
                                        <div class="p-2.5 rounded-xl flex items-start gap-2 border {{ $isKunci ? 'border-emerald-300 bg-emerald-50 font-semibold text-emerald-950' : ($dipilih ? 'border-rose-300 bg-rose-50 text-rose-950' : 'border-slate-200 bg-white text-slate-600') }}">
                                            <span class="w-5 text-center font-bold text-slate-500">{{ $labelOpsi }}.</span>
                                            <div class="flex-1">{!! $opsi->teks_opsi !!}</div>
                                            @if($dipilih && $isKunci)
                                                <span class="text-[10px] bg-emerald-600 text-white font-bold px-2 py-0.5 rounded-full whitespace-nowrap">Jawaban Anda &bull; Kunci</span>
                                            @elseif($dipilih)
                                                <span class="text-[10px] bg-rose-600 text-white font-bold px-2 py-0.5 rounded-full whitespace-nowrap">Jawaban Anda</span>
                                            @elseif($isKunci)
                                                <span class="text-[10px] bg-emerald-600 text-white font-bold px-2 py-0.5 rounded-full whitespace-nowrap">Kunci Jawaban</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($soal->tipe === 'essay')
                                <div class="mt-2 p-3 bg-white rounded-xl border border-slate-200 text-xs">
                                    <div class="font-bold text-slate-500 mb-1">Jawaban Anda:</div>
                                    <div class="text-slate-800 whitespace-pre-line">{{ $jawaban?->jawaban_esai ?? '— (Kosong) —' }}</div>
                                </div>
                            @endif

                            {{-- Penjelasan / Pembahasan Guru --}}
                            @if(!empty($soal->penjelasan))
                                <div class="mt-3 p-3 rounded-xl bg-indigo-50/70 border border-indigo-100 text-xs text-indigo-900">
                                    <strong class="font-bold flex items-center gap-1 mb-1">
                                        <i data-lucide="lightbulb" class="w-3.5 h-3.5 text-indigo-600"></i> Pembahasan:
                                    </strong>
                                    <div class="leading-relaxed">{!! $soal->penjelasan !!}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <div class="text-center text-xs text-slate-400">
            SMP V6 Examination System &bull; Pre-Test Mode &bull; {{ config('app.name') }}
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
