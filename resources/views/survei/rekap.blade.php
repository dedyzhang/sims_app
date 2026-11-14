@extends('layouts.app')
@section('title','Hasil Survei Pembelajaran')
@section('content')
<div class="max-w-3xl space-y-5">
    <h1 class="page-title">Rekap Survei Pembelajaran</h1>
    <p>Rekap bersifat rahasia dan tersedia setelah periode berakhir.</p>
    @forelse($periodes as $periode)
        <div class="card space-y-2 p-4">
            <h2 class="font-semibold">{{ $periode->judul }}</h2>
            <a class="text-blue-700 underline" href="{{ route('survei.analisis', $periode) }}">{{ $lihatSemua ? 'Lihat rekap dan analisis guru' : 'Lihat rekap saya' }}</a>
        </div>
    @empty
        <p>Belum ada periode survei yang berakhir.</p>
    @endforelse
</div>
@endsection
