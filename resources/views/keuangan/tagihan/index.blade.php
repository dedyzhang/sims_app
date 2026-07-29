@extends('layouts.app')
@section('title', 'Tagihan SPP')

@php
    // [label, kelas badge, ikon, keterangan untuk ortu]
    $statusMeta = [
        'lunas'         => ['Lunas', 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300', 'check-circle-2', 'Pembayaran sudah divalidasi lewat rekening koran bank. Selesai.'],
        'terverifikasi' => ['Sudah terverifikasi', 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300', 'badge-check', 'Bukti sudah dicek bendahara. Tinggal menunggu validasi dana lewat rekening koran bank.'],
        'menunggu'      => ['Menunggu verifikasi', 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300', 'clock', 'Bukti pembayaran sudah dikirim & sedang diperiksa bendahara.'],
        'ditolak'       => ['Ditolak — unggah ulang', 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300', 'x-circle', 'Pembayaran ditolak. Silakan unggah ulang bukti yang benar.'],
        'belum'         => ['Belum dibayar', 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300', 'circle-dashed', 'Belum ada pembayaran untuk bulan ini.'],
    ];
@endphp

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    {{-- Header --}}
    <div>
        <h1 class="page-title flex items-center gap-2">
            <span class="grid place-items-center w-9 h-9 rounded-xl text-white" style="background:linear-gradient(135deg,var(--cp),var(--cps))"><i data-lucide="wallet" class="w-5 h-5"></i></span>
            Tagihan SPP
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tahun Ajaran {{ $ta }} (Juli – Juni)</p>
    </div>

    {{-- Pemilih anak (untuk orang tua dengan >1 anak) --}}
    @if($children->count() > 1)
    <div class="flex gap-2 flex-wrap">
        @foreach($children as $c)
        <a href="{{ route('keuangan.tagihan.index', ['anak'=>$c->uuid]) }}"
           class="px-4 py-2 rounded-xl text-sm font-semibold border transition {{ $c->uuid===$siswa->uuid ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}">
            {{ $c->nama }}
        </a>
        @endforeach
    </div>
    @endif

    {{-- Kartu identitas + VA --}}
    <div class="card p-5">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary grid place-items-center font-bold text-lg">{{ \Illuminate\Support\Str::substr($siswa->nama,0,1) }}</div>
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $siswa->nama }}</p>
                    <p class="text-xs text-slate-400">{{ $siswa->kelas?->nama_lengkap }} · NIS {{ $siswa->nis }}</p>
                </div>
            </div>
            @if($siswa->va)
            <div x-data="{copied:false}" class="text-right">
                <p class="text-[11px] text-slate-400 uppercase tracking-wide">Virtual Account</p>
                <button @click="navigator.clipboard.writeText('{{ $siswa->va }}'); copied=true; setTimeout(()=>copied=false,1500)"
                        class="font-mono font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5 hover:text-primary">
                    {{ $siswa->va }} <i data-lucide="copy" class="w-3.5 h-3.5" x-show="!copied"></i><i data-lucide="check" class="w-3.5 h-3.5 text-emerald-500" x-show="copied" x-cloak></i>
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="card p-4 text-center">
            <p class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ $ringkasan['lunas'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Lunas</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-extrabold text-sky-500">{{ $ringkasan['terverifikasi'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Terverifikasi</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-extrabold text-amber-500">{{ $ringkasan['menunggu'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Menunggu</p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-2xl font-extrabold text-rose-500">{{ $ringkasan['belum'] }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Belum bayar</p>
        </div>
    </div>

    {{-- Keterangan alur status untuk orang tua --}}
    <div x-data="{ open:false }" class="card p-4">
        <button @click="open=!open" type="button" class="w-full flex items-center justify-between gap-2 text-left">
            <span class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200"><i data-lucide="info" class="w-4 h-4 text-primary"></i> Arti status pembayaran</span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform" :class="open && 'rotate-180'"></i>
        </button>
        <div x-show="open" x-collapse x-cloak class="mt-3 space-y-2.5 text-xs">
            <div class="flex gap-2.5">
                <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300 flex items-center gap-1 flex-shrink-0 h-fit"><i data-lucide="clock" class="w-3 h-3"></i> Menunggu</span>
                <p class="text-slate-500 dark:text-slate-400">Bukti pembayaran sudah kamu kirim & sedang diperiksa bendahara.</p>
            </div>
            <div class="flex gap-2.5">
                <span class="badge bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300 flex items-center gap-1 flex-shrink-0 h-fit"><i data-lucide="badge-check" class="w-3 h-3"></i> Terverifikasi</span>
                <p class="text-slate-500 dark:text-slate-400">Bukti sudah dicek bendahara. Menunggu validasi dana masuk lewat rekening koran resmi bank.</p>
            </div>
            <div class="flex gap-2.5">
                <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 flex items-center gap-1 flex-shrink-0 h-fit"><i data-lucide="check-circle-2" class="w-3 h-3"></i> Lunas</span>
                <p class="text-slate-500 dark:text-slate-400">Pembayaran sudah divalidasi lewat rekening koran bank. Selesai ✅</p>
            </div>
        </div>
    </div>

    @if($ringkasan['tunggakan'] > 0)
    <div class="card p-4 border-l-4 border-rose-400 flex items-center gap-3">
        <span class="grid place-items-center w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-900/40 text-rose-600"><i data-lucide="alert-circle" class="w-5 h-5"></i></span>
        <div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Total tunggakan</p>
            <p class="font-bold text-rose-600 dark:text-rose-400 text-lg">Rp {{ number_format($ringkasan['tunggakan'],0,',','.') }}</p>
        </div>
    </div>
    @endif

    @php $firstPayable = collect($bayar)->first(fn($p) => in_array($p->status, ['belum','ditolak'])); @endphp
    @if($firstPayable && $ringkasan['belum'] > 1)
    <a href="{{ route('keuangan.tagihan.show', $firstPayable) }}" class="card p-4 flex items-center gap-3 hover:shadow-md transition border border-primary/30">
        <span class="grid place-items-center w-10 h-10 rounded-xl bg-primary/10 text-primary flex-shrink-0"><i data-lucide="layers" class="w-5 h-5"></i></span>
        <div class="flex-1">
            <p class="font-bold text-slate-800 dark:text-slate-100">Bayar beberapa bulan sekaligus</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pilih beberapa bulan & unggah satu bukti transfer untuk semuanya.</p>
        </div>
        <i data-lucide="chevron-right" class="w-5 h-5 text-slate-300"></i>
    </a>
    @endif

    {{-- Daftar bulan --}}
    <div class="space-y-2.5">
        @foreach($bulanList as $b)
            @php
                $p = $bayar[$b['idx']] ?? null;
                $tglBulan = \App\Support\TahunAjaran::tanggal($ta, $b['idx'])->startOfMonth();
                $belumTiba = $tglBulan->isAfter(now()->startOfMonth());

                if ($p && $p->status === 'belum' && $belumTiba) {
                    $label = 'Belum ditagih';
                    $cls = 'bg-slate-50 dark:bg-slate-800/40 text-slate-400 dark:text-slate-500 border border-dashed border-slate-200 dark:border-slate-700/60';
                    $icon = 'calendar';
                    $ket = 'Pembayaran dapat dilakukan lebih awal.';
                    $bisaBayar = true;
                } else {
                    [$label, $cls, $icon, $ket] = $statusMeta[$p?->status ?? 'belum'];
                    $bisaBayar = $p && in_array($p->status, ['belum','ditolak']);
                }
            @endphp
            <div class="card p-4 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-slate-100 dark:bg-slate-700 grid place-items-center flex-shrink-0">
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-300 text-center leading-tight">{{ \Illuminate\Support\Str::substr($b['label'],0,3) }}<br>'{{ substr($b['year'],2) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-800 dark:text-slate-100">SPP {{ $b['label'] }} {{ $b['year'] }}</p>
                    <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                        <span class="badge {{ $cls }} flex items-center gap-1"><i data-lucide="{{ $icon }}" class="w-3 h-3"></i> {{ $label }}</span>
                        @if($p && $p->status==='ditolak' && $p->catatan)
                            <span class="text-[11px] text-rose-500">· {{ $p->catatan }}</span>
                        @endif
                    </div>
                    @if($p && in_array($p->status, ['menunggu','terverifikasi']))
                        <p class="text-[11px] text-slate-400 mt-1">{{ $ket }}</p>
                    @endif
                    @if($p && $p->catatan_bendahara)
                        <p class="text-[11px] text-sky-600 dark:text-sky-400 mt-1 flex items-start gap-1"><i data-lucide="message-square-text" class="w-3 h-3 flex-shrink-0 mt-0.5"></i> {{ $p->catatan_bendahara }}</p>
                    @endif
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-slate-700 dark:text-slate-200">Rp {{ number_format($p?->nominal ?? 0,0,',','.') }}</p>
                    @if($bisaBayar)
                        <a href="{{ route('keuangan.tagihan.show', $p) }}" class="btn-primary inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold mt-1"><i data-lucide="credit-card" class="w-3.5 h-3.5"></i> Bayar</a>
                    @elseif($p && $p->status==='menunggu')
                        <a href="{{ route('keuangan.tagihan.show', $p) }}" class="text-xs text-amber-600 dark:text-amber-400 hover:underline mt-1 inline-flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> Lihat bukti</a>
                    @elseif($p && $p->status==='terverifikasi')
                        <a href="{{ route('keuangan.tagihan.show', $p) }}" class="text-xs text-sky-600 dark:text-sky-400 hover:underline mt-1 inline-flex items-center gap-1"><i data-lucide="badge-check" class="w-3 h-3"></i> Lihat bukti</a>
                    @elseif($p && $p->status==='lunas')
                        <a href="{{ route('keuangan.tagihan.show', $p) }}" class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline mt-1 inline-flex items-center gap-1"><i data-lucide="receipt-text" class="w-3 h-3"></i> Lihat bukti</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
