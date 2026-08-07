@extends('layouts.app')
@section('title', 'Antrian Prioritas Verifikasi')

@section('content')
@php
    $defaultTab = $menungguCount > 0 ? 'cek' : ($terverifikasiCount > 0 ? 'validasi' : 'cek');
@endphp
<div x-data="{ tab: '{{ $defaultTab }}' }" class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> /
                <a href="{{ route('keuangan.bendahara-ai.index', ['ta'=>$ta]) }}" class="hover:underline">Asisten</a> /
                Antrian Prioritas
            </nav>
            <h1 class="page-title">Antrian Prioritas Verifikasi</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Diurutkan skor mendesak (jatuh tempo, nominal, usia bukti) · T.A. {{ $ta }}</p>
        </div>
        <form method="GET" class="flex items-center gap-2 flex-wrap">
            <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama / kelas…" class="form-input text-sm w-48">
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Cari</button>
        </form>
    </div>

    <div class="flex gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl">
        <button @click="tab='cek'" type="button" class="flex-1 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='cek' ? 'bg-white dark:bg-slate-700 text-amber-600 shadow-sm' : 'text-slate-500'">
            Cek Bukti @if($menungguCount)<span class="badge bg-amber-100 text-amber-700 ml-1">{{ $menungguCount }}</span>@endif
        </button>
        <button @click="tab='validasi'" type="button" class="flex-1 py-2.5 px-3 rounded-xl text-sm font-semibold transition"
                :class="tab==='validasi' ? 'bg-white dark:bg-slate-700 text-sky-600 shadow-sm' : 'text-slate-500'">
            Validasi Bank @if($terverifikasiCount)<span class="badge bg-sky-100 text-sky-700 ml-1">{{ $terverifikasiCount }}</span>@endif
        </button>
    </div>

    <div x-show="tab==='cek'" class="space-y-3">
        @forelse($menungguGroups as $scoredGroup)
            @php
                $payments = $scoredGroup->pluck('pembayaran');
                $topScore = $scoredGroup->max('skor');
                $alasan = $scoredGroup->first()['alasan'] ?? [];
            @endphp
            <div class="relative">
                <div class="absolute -left-1 top-4 z-10" title="Skor prioritas: {{ $topScore }}">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                        <i data-lucide="zap" class="w-3 h-3"></i> {{ $topScore }}
                    </span>
                </div>
                @if(!empty($alasan))
                <div class="px-3 pb-1 flex flex-wrap gap-1 text-[10px] text-slate-500">
                    @foreach($alasan as $k => $v)
                        <span class="badge bg-slate-100 dark:bg-slate-800" title="{{ $k }}">{{ $v }}</span>
                    @endforeach
                </div>
                @endif
                @include('keuangan.partials.verif-card', [
                    'group' => $payments,
                    'mode' => 'verify',
                    'anomaliFlags' => $payments->flatMap(fn($p) => ($anomaliMap[$p->uuid]['flags'] ?? []))->unique('kode')->values()->all(),
                ])
            </div>
        @empty
            <div class="card p-10 text-center text-slate-400"><p class="text-sm">Tidak ada antrian menunggu cek bukti.</p></div>
        @endforelse
    </div>

    <div x-show="tab==='validasi'" x-cloak class="space-y-3">
        @forelse($terverifikasiGroups as $scoredGroup)
            @php
                $payments = $scoredGroup->pluck('pembayaran');
                $topScore = $scoredGroup->max('skor');
                $alasan = $scoredGroup->first()['alasan'] ?? [];
            @endphp
            <div class="relative">
                <div class="absolute -left-1 top-4 z-10">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-bold bg-indigo-100 dark:bg-indigo-900 text-indigo-700">
                        <i data-lucide="zap" class="w-3 h-3"></i> {{ $topScore }}
                    </span>
                </div>
                @include('keuangan.partials.verif-card', [
                    'group' => $payments,
                    'mode' => 'validate',
                    'anomaliFlags' => $payments->flatMap(fn($p) => ($anomaliMap[$p->uuid]['flags'] ?? []))->unique('kode')->values()->all(),
                ])
            </div>
        @empty
            <div class="card p-10 text-center text-slate-400"><p class="text-sm">Tidak ada antrian validasi rekening koran.</p></div>
        @endforelse
    </div>
</div>
@endsection
