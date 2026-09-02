@extends('layouts.app')
@section('title', 'Jawaban Siswa — ' . $ujian->judul)

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> /
            <a href="{{ route('ujian.hasil.index', $ujian) }}" class="hover:underline">Hasil</a> / Jawaban
        </nav>
        <h1 class="page-title">{{ $siswaProfil?->nama ?? 'Siswa' }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $siswaProfil?->kelas?->tingkat }}{{ $siswaProfil?->kelas?->kelas }} · {{ $ujian->judul }}</p>
    </div>

    @if(!$attempt)
    <div class="card p-8 text-center text-slate-400">
        <i data-lucide="circle-slash" class="w-10 h-10 mx-auto mb-2"></i>
        Siswa ini belum mengerjakan ujian ini.
    </div>
    @else
    <div class="card p-5 flex items-center justify-between flex-wrap gap-3">
        <div>
            <p class="text-xs text-slate-400">Status</p>
            <p class="font-semibold">{{ $attempt->statusLabel() }}</p>
        </div>
        @php $skor = $attempt->skorSementara($totalPoin, $modeSkor); @endphp
        <div>
            <p class="text-xs text-slate-400">{{ $attempt->status === 'dinilai' ? 'Skor Akhir' : 'Skor Sementara' }}</p>
            <p class="font-mono font-bold">{{ $skor !== null ? number_format($skor, 1) : '—' }}</p>
            @if($skor !== null && $attempt->status !== 'dinilai')
            <p class="text-[10px] text-amber-500 mt-0.5">Soal objektif saja — esai belum dinilai</p>
            @endif
        </div>
        <div>
            <p class="text-xs text-slate-400">Selesai</p>
            <p class="text-sm">{{ optional($attempt->selesai_pada)->format('d/m/Y H:i') ?? '—' }}</p>
        </div>
        @if(in_array($attempt->status, ['submitted', 'dinilai']))
        <form method="POST" action="{{ route('ujian.hasil.bukaAkses', [$ujian, $attempt]) }}"
              onsubmit="return confirmAction(this, 'Buka kembali akses ujian ini utk {{ $siswaProfil?->nama }}? {{ $attempt->status_transfer_nilai === 'berhasil' ? 'NILAI YANG SUDAH TERTRANSFER ke buku nilai ikut terhapus. ' : '' }}Jawaban yang sudah tersimpan TETAP ADA, siswa lanjut dari titik terakhir dengan waktu pengerjaan baru — siswa tetap harus masukkan token lagi demi keamanan.', 'orange')">
            @csrf
            <button type="submit" class="px-3 py-2 rounded-xl text-xs font-semibold text-rose-600 border border-rose-200 dark:border-rose-900 hover:bg-rose-50 dark:hover:bg-rose-950/30">Buka Kembali Akses</button>
        </form>
        @endif
    </div>

    <div class="card p-5 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 text-sm">Jawaban per Soal</h2>
        @foreach($ujian->soal as $i => $soal)
        @php $jawaban = $jawabanBySoal->get($soal->uuid); @endphp
        <div class="border-b border-slate-100 dark:border-slate-700 pb-4 last:border-0 space-y-2">
            <div class="flex items-start justify-between gap-3">
                <div class="text-sm font-medium text-slate-700 dark:text-slate-200 flex gap-1.5 min-w-0">
                    <span>{{ $i + 1 }}.</span>
                    @include('ujian.partials.rich-body', ['html' => $soal->teks_soal])
                </div>
                <span class="text-xs whitespace-nowrap {{ $jawaban?->is_benar ? 'text-emerald-600' : 'text-rose-500' }}">
                    {{ $jawaban?->skor_diperoleh ?? 0 }} / {{ $soal->poinEfektif() }} poin
                </span>
            </div>

            @if(in_array($soal->tipe, ['mcq', 'true_false', 'mcq_complex']))
                <div class="space-y-1 pl-4">
                    @foreach($soal->opsi as $opsi)
                    @php
                        $dipilih = $soal->tipe === 'mcq_complex'
                            ? in_array($opsi->uuid, $jawaban?->opsi_dipilih_multi ?? [])
                            : $jawaban?->id_opsi_dipilih === $opsi->uuid;
                    @endphp
                    <div class="text-xs flex items-center gap-2 {{ $opsi->is_benar ? 'text-emerald-600 font-semibold' : ($dipilih ? 'text-rose-500' : 'text-slate-400') }}">
                        <i data-lucide="{{ $dipilih ? 'check-square' : 'square' }}" class="w-3.5 h-3.5 flex-shrink-0"></i>
                        <span>{{ strip_tags($opsi->teks_opsi) }}</span>
                        @if($opsi->is_benar) <span class="text-[10px] uppercase tracking-wide">(kunci)</span> @endif
                    </div>
                    @endforeach
                </div>
            @elseif($soal->tipe === 'match')
                <div class="space-y-1 pl-4">
                    @foreach($soal->meta['pairs'] ?? [] as $p)
                    @php $jawabPasangan = ($jawaban?->jawaban_pasangan ?? [])[$p['left']] ?? null; @endphp
                    <div class="text-xs flex items-center gap-2 {{ $jawabPasangan === $p['right'] ? 'text-emerald-600' : 'text-rose-500' }}">
                        <span>{{ strip_tags($p['left']) }} → {{ $jawabPasangan ? strip_tags($jawabPasangan) : '(tak dijawab)' }}</span>
                        @if($jawabPasangan !== $p['right'])
                        <span class="text-slate-400">(kunci: {{ strip_tags($p['right']) }})</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            @elseif($soal->tipe === 'essay')
                <div class="pl-4 text-xs text-slate-600 dark:text-slate-300 whitespace-pre-line">
                    {{ $jawaban?->jawaban_esai ?: '(tak dijawab)' }}
                </div>
                @if($jawaban?->catatan_guru)
                <p class="pl-4 text-xs text-slate-400 italic">Catatan guru: {{ $jawaban->catatan_guru }}</p>
                @endif
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
