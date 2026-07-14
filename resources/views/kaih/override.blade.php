@extends('layouts.app')
@section('title', 'Isi Manual 7 KAIH')

@section('content')
<div class="max-w-2xl mx-auto space-y-5" x-data="{ tab: 'isi' }">
    <div>
        <a href="{{ route('kaih.rekap', array_filter(['kelas' => $siswa->id_kelas, 'tampilan' => $tampilan, 'dari' => $dari, 'sampai' => $sampai, 'tanggal' => $tampilan === 'harian' ? $tanggal : null])) }}" class="text-xs text-slate-400 hover:text-primary flex items-center gap-1 mb-1"><i data-lucide="arrow-left" class="w-3 h-3"></i> Rekap 7 KAIH</a>
        <h1 class="page-title">Isi Manual &mdash; {{ $siswa->nama }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMMM Y') }}</p>
    </div>

    <div class="flex items-center gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800 w-max">
        <button type="button" @click="tab='isi'" :class="tab==='isi' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Isi Jawaban</button>
        <button type="button" @click="tab='lewati'" :class="tab==='lewati' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary' : 'text-slate-500'" class="px-4 py-2 rounded-lg text-sm font-semibold transition">Tandai Dilewati</button>
    </div>

    @php
        $hidden = '<input type="hidden" name="tanggal" value="' . $tanggal . '">'
            . '<input type="hidden" name="tampilan" value="' . $tampilan . '">'
            . '<input type="hidden" name="dari" value="' . $dari . '">'
            . '<input type="hidden" name="sampai" value="' . $sampai . '">';
    @endphp

    <div x-show="tab==='isi'">
        @include('kaih._form', [
            'pertanyaans' => $pertanyaans,
            'actionUrl'   => route('kaih.override.store', $siswa),
            'extraFields' => $hidden . '<input type="hidden" name="aksi" value="isi">',
        ])
    </div>

    <div x-show="tab==='lewati'" x-cloak class="card p-5 space-y-4">
        <form method="POST" action="{{ route('kaih.override.store', $siswa) }}" class="space-y-4">
            @csrf
            {!! $hidden !!}
            <input type="hidden" name="aksi" value="lewati">
            <div>
                <label class="form-label">Alasan Dilewati</label>
                <textarea name="keterangan" required rows="3" placeholder="mis. Siswa sakit, HP rusak, dsb." class="form-input"></textarea>
                @error('keterangan')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                <i data-lucide="skip-forward" class="w-4 h-4"></i> Tandai Dilewati
            </button>
        </form>
    </div>
</div>
@endsection
