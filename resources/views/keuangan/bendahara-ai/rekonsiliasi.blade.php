@extends('layouts.app')
@section('title', 'Rekonsiliasi Bank')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> /
                <a href="{{ route('keuangan.bendahara-ai.index', ['ta'=>$ta]) }}" class="hover:underline">Asisten</a> /
                Rekonsiliasi
            </nav>
            <h1 class="page-title">Rekonsiliasi Mutasi ↔ Tagihan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tagihan terverifikasi yang menunggu validasi rekening koran · T.A. {{ $ta }}</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="card p-4 border-l-4 border-sky-400 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="font-semibold text-slate-800 dark:text-slate-100">Impor rekening koran</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Unggah laporan VA bank — sistem menyarankan pencocokan berdasarkan skor aturan (VA, nominal, tanggal).</p>
        </div>
        <a href="{{ route('keuangan.verifikasi', ['ta'=>$ta]) }}#validasi" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2">
            <i data-lucide="upload" class="w-4 h-4"></i> Buka Impor di Verifikasi
        </a>
    </div>

    <div class="card overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-700">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Antrian Validasi Bank</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $antrian->count() }} tagihan terverifikasi menunggu cocokkan mutasi</p>
        </div>
        @forelse($antrian as $row)
            @php $p = $row['pembayaran']; @endphp
            <div class="p-4 border-b border-slate-50 dark:border-slate-800 last:border-0 flex flex-col sm:flex-row gap-4">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $p->siswa?->nama }}</p>
                    <p class="text-xs text-slate-400">{{ $p->siswa?->kelas?->nama_lengkap }} · {{ $p->label_bulan }}</p>
                    <div class="flex flex-wrap gap-2 mt-2 text-sm">
                        <span class="font-bold text-emerald-600">Rp {{ number_format($p->nominal, 0, ',', '.') }}</span>
                        <span class="text-slate-400">·</span>
                        <span class="text-slate-600 dark:text-slate-300">{{ optional($p->tanggal_bayar)->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    @if(!empty($row['alasan']))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($row['alasan'] as $v)
                            <span class="badge bg-slate-100 dark:bg-slate-800 text-[10px]">{{ $v }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge bg-indigo-100 dark:bg-indigo-900 text-indigo-700 text-xs" title="Prioritas antrian validasi">Prio {{ $row['skor'] }}</span>
                    <a href="{{ route('keuangan.verifikasi', ['ta'=>$ta]) }}#validasi" class="text-xs font-semibold text-primary hover:underline">Validasi →</a>
                </div>
            </div>
        @empty
            <div class="p-10 text-center text-slate-400 text-sm">Tidak ada tagihan menunggu validasi bank.</div>
        @endforelse
    </div>
</div>
@endsection
