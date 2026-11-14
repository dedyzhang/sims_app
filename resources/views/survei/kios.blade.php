@extends('layouts.app')
@section('title', 'SIMS Kios')
@include('survei.gaya')
@section('content')
<div class="survei-masuk mx-auto max-w-lg">
    <section class="card survei-akses">
        <h1 class="page-title">{{ $periode->judul }}</h1>
        <h2>Buka di SIMS Kios</h2>
        @if($kiosSiap)
            <p id="survei-kios-pesan" role="status">Gunakan aplikasi SIMS Kios pada perangkat yang disiapkan sekolah.</p>
            <form id="survei-verifikasi-kios" action="{{ route('survei.kios.verifikasi', $periode) }}" method="post"
                data-periode="{{ $periode->uuid }}" data-tantangan="{{ $tantanganKios }}" hidden>
                @csrf
                <input type="hidden" name="tanda_sesi" value="{{ $tandaSesi }}">
            </form>
        @else
            <p>Perangkat SIMS Kios belum terdaftar. Hubungi surveyor.</p>
        @endif
    </section>
    @include('survei.privasi')
</div>
@endsection
@push('scripts')
    <script src="{{ asset('js/survei-kios.js') }}" defer></script>
@endpush
