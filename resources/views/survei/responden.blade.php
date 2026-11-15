@extends('layouts.app')
@section('title', 'Daftar Responden Rahasia')
@section('content')
<div class="mx-auto max-w-3xl space-y-4">
    <a href="{{ route('survei.periode', $periode) }}" class="text-blue-700 underline">← Detail survei</a>
    <h1 class="page-title">Daftar Responden Rahasia</h1>
    <p>{{ $periode->judul }}</p>
    <p class="text-sm">Daftar siswa yang sudah mengirim jawaban. Akses khusus pembuat survei.</p>

    @forelse($kelas as $siswa)
        <section class="card space-y-2 p-4">
            <h2 class="font-semibold">{{ $siswa->first()->kelas_nama }}</h2>
            <ul class="list-disc space-y-1 pl-5">
                @foreach($siswa as $responden)
                    <li>{{ $responden->nama }}</li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="card p-4">Belum ada responden yang dapat ditampilkan.</p>
    @endforelse
</div>
@endsection
