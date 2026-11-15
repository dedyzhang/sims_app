@extends('layouts.app')
@section('title','Survei Pembelajaran')
@include('survei.gaya')
@section('content')
<div class="survei-masuk mx-auto max-w-2xl">
<h1 class="page-title">Survei Pembelajaran</h1>
@forelse($periodes as $item)
    <section class="card survei-akses">
        <h2>{{ $item['periode']->judul }}</h2>
        @if($item['selesai'])
            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">Selesai</span>
            <p class="text-sm">Jawaban sudah dikirim dan bersifat rahasia.</p>
        @else
            <p>{{ $item['targets']->first()->kelas_nama }} · Berakhir {{ $item['periode']->selesai->format('d/m/Y H:i') }}</p>
            @if($item['jumlah_selesai'] > 0)
                <p>{{ $item['jumlah_selesai'] }} dari {{ $item['targets']->count() }} bagian sudah terisi.</p>
            @endif
            @if($item['periode']->wajib_token)
                <p class="text-sm">Borang terbuka hanya ketika surveyor memulai sesi untuk kelasmu.</p>
            @else
                <p class="border-t pt-3 text-sm text-slate-600">Bagian survei · {{ $item['targets']->map(fn ($target) => $target->jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama)->implode(', ') }}</p>
            @endif
            <a class="survei-tombol survei-aksi-utama" href="{{ route('survei.form', $item['periode']) }}">Isi survei kelas</a>
        @endif
    </section>
@empty
    <div class="card p-5">Belum ada survei terbuka untuk kelasmu.</div>
@endforelse
@include('survei.privasi')
</div>
@endsection
