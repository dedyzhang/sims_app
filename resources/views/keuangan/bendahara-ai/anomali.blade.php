@extends('layouts.app')
@section('title', 'Anomali & Peringatan')

@section('content')
<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('keuangan.bendahara-ai.index', ['ta'=>$ta]) }}" class="hover:underline">Asisten Bendahara</a> / Anomali
            </nav>
            <h1 class="page-title">Anomali & Peringatan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Flag peringatan saja — verifikasi tetap bisa dilanjutkan bendahara · T.A. {{ $ta }}</p>
        </div>
        <form method="GET">
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @forelse($items as $item)
        @php $p = $item['pembayaran']; @endphp
        <div class="card p-4 border-l-4 {{ collect($item['flags'])->contains(fn($f) => $f['tingkat']==='tinggi') ? 'border-rose-400' : 'border-amber-400' }}">
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $p->siswa?->nama }}</p>
                    <p class="text-xs text-slate-400">{{ $p->siswa?->kelas?->nama_lengkap }} · {{ $p->label_bulan }} · {{ ucfirst($p->status) }}</p>
                    <p class="text-sm font-semibold text-emerald-600 mt-1">Rp {{ number_format($p->nominal, 0, ',', '.') }}</p>
                    <ul class="mt-2 space-y-1">
                        @foreach($item['flags'] as $flag)
                            <li class="text-xs flex items-start gap-1.5 {{ $flag['tingkat']==='tinggi' ? 'text-rose-600' : 'text-amber-700 dark:text-amber-400' }}">
                                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
                                {{ $flag['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
                <a href="{{ route('keuangan.bendahara-ai.antrian', ['ta'=>$ta]) }}" class="text-xs font-semibold text-primary hover:underline whitespace-nowrap">Buka antrian →</a>
            </div>
        </div>
    @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="shield-check" class="w-10 h-10 mx-auto mb-2 opacity-50"></i>
            <p class="text-sm">Tidak ada anomali terdeteksi pada antrian aktif.</p>
        </div>
    @endforelse
</div>
@endsection
