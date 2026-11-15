@extends('layouts.app')
@section('title', 'Safe Exam Browser')
@include('survei.gaya')
@section('content')
<div class="survei-masuk mx-auto max-w-lg">
    <section class="card survei-akses">
        <h1 class="page-title">{{ $periode->judul }}</h1>
        <h2>Gunakan Safe Exam Browser</h2>
        @if($sebSiap)
            <p id="survei-seb-pesan" role="status">Buka survei dengan konfigurasi Safe Exam Browser dari surveyor. Perangkat akan dibuka setelah jawaban tersimpan.</p>
            <form id="survei-verifikasi-seb" action="{{ route('survei.seb.verifikasi', $periode) }}" method="post" hidden>
                @csrf
            </form>
        @else
            <p>Konfigurasi belum tersedia. Hubungi surveyor.</p>
        @endif
    </section>
    @include('survei.privasi')
</div>
@endsection
@push('scripts')
    <script src="{{ asset('js/survei-seb.js') }}?v={{ filemtime(public_path('js/survei-seb.js')) }}" defer></script>
@endpush
