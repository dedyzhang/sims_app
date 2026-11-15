@extends('layouts.app')
@section('title', 'Masuk Sesi Survei')
@include('survei.gaya')
@section('content')
<div class="survei-masuk mx-auto max-w-lg">
    <a href="{{ route('survei.index') }}" class="survei-kembali">
        <i data-lucide="arrow-left" aria-hidden="true"></i> Daftar survei
    </a>
    <header class="survei-hero">
        <div class="survei-identitas">
            <div class="survei-logo">
                @if($sekolahLogoUrl)
                    <img src="{{ $sekolahLogoUrl }}" alt="" width="52" height="52">
                @else
                    <i data-lucide="clipboard-list" aria-hidden="true"></i>
                @endif
            </div>
            <div>
                <p class="survei-sekolah">{{ $namaSekolah }}</p>
                <h1>{{ $periode->judul }}</h1>
            </div>
        </div>
    </header>
    <section class="card survei-akses" aria-labelledby="survei-akses-judul">
        <div class="survei-akses-ikon">
            <i data-lucide="{{ $bisaMasukkanToken ? 'key-round' : 'clock' }}" aria-hidden="true"></i>
        </div>
        @if($bisaMasukkanToken)
            <h2 id="survei-akses-judul">Masukkan token sesi</h2>
            <form method="post" action="{{ route('survei.token', $periode) }}" class="survei-akses-form">
                @csrf
                <label for="token-survei">Token dari surveyor</label>
                <input id="token-survei" name="token" class="survei-token-input" type="text" inputmode="numeric"
                    pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="off" placeholder="6 digit"
                    @error('token') aria-invalid="true" aria-describedby="token-survei-error" @enderror required>
                @error('token')
                    <p id="token-survei-error" role="alert" class="survei-token-error">{{ $message }}</p>
                @enderror
                <button type="submit" class="survei-tombol survei-aksi-utama">Buka pertanyaan</button>
            </form>
        @elseif($kelasLainBerjalan)
            <h2 id="survei-akses-judul">Kelasmu belum dibuka</h2>
            <p>Sesi yang sedang berjalan hanya untuk kelas yang dicentang surveyor. Kelas lain tidak dapat mengisi borang, termasuk dengan token kelas tersebut.</p>
            @error('token')
                <p role="alert" class="survei-token-error">{{ $message }}</p>
            @enderror
            <a href="{{ route('survei.form', $periode) }}" class="survei-tombol survei-aksi-utama">Cek sesi</a>
        @else
            <h2 id="survei-akses-judul">Menunggu sesi dibuka</h2>
            <p>Ketuk tombol di bawah saat surveyor memberi token untuk kelasmu.</p>
            <a href="{{ route('survei.form', $periode) }}" class="survei-tombol survei-aksi-utama">Cek sesi</a>
            @error('token')
                <p role="alert" class="survei-token-error">{{ $message }}</p>
            @enderror
        @endif
    </section>
    @include('survei.privasi')
</div>
@endsection
