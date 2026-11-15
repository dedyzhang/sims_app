@extends('layouts.app')
@section('title', 'Akses Survei')
@section('content')
<div class="mx-auto max-w-xl">
    <div class="card space-y-4 p-6">
        <h1 class="page-title">Akun ini tidak memiliki akses</h1>
        <p>{{ $pesan }}</p>
        <p class="text-sm">Kamu sedang masuk sebagai <strong>{{ auth()->user()->username }}</strong>.</p>
        <div class="flex flex-wrap items-center gap-4">
            @if(auth()->user()->canFacilitateSurvei() || auth()->user()->isAdmin())
                <a href="{{ route('survei.periode', $periode) }}" class="btn-primary">Kembali ke pemantauan</a>
            @endif
            <form method="post" action="{{ route('survei.ganti-akun', [$periode, $tujuan]) }}">
                @csrf
                <button type="submit" class="btn-primary">Ganti akun</button>
            </form>
            <a href="{{ route('dashboard') }}" class="text-blue-700 underline dark:text-sky-300">Kembali ke Dashboard</a>
        </div>
    </div>
</div>
@endsection
