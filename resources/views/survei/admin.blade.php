@extends('layouts.app')
@section('title','Survei Pembelajaran')
@section('content')
<div class="max-w-5xl space-y-5">
<h1 class="page-title">Survei Pembelajaran</h1>
<p class="text-sm">Buat survei untuk kelas pilihan. Hasil dipakai untuk perbaikan pembelajaran; identitas siswa tidak ditampilkan dalam rekap.</p>
<div class="card p-5 space-y-2 text-sm">
    <h2 class="font-bold">Cara pakai</h2>
    <ol class="list-decimal space-y-1 pl-5">
        <li>Surveyor yang sudah ditunjuk: <strong>Waka Humas</strong> dan <strong>Waka Sarpras</strong>. Ubah akses akun guru itu di Data Guru. Peran lain bisa ditambah di <a class="text-blue-700 underline" href="{{ route('setting.roles') }}">Hak Akses Peran (RBAC)</a>.</li>
        <li>Buat periode di bawah. Guru mapel diambil dari jadwal, wali kelas dari data kelas.</li>
        <li>Surveyor membuka periode ini, mencentang kelas yang dimulai sekarang, lalu menekan Mulai sesi. Durasi tidak diisi.</li>
        <li>Surveyor menampilkan QR dan membacakan token 6 digit. Siswa memindai, masuk dengan akun SIMS, memasukkan token, mengisi satu formulir, lalu mengirim. Surveyor menekan Siap bila kelas selesai.</li>
        <li>Tutup periode setelah jadwal selesai. Rekap terbuka bila minimal lima siswa mengisi. Nama pengisi hanya terlihat oleh admin pembuat periode.</li>
    </ol>
</div>
@if($errors->any())<div class="card p-4 text-red-600">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('survei.store') }}" class="card p-5 space-y-4">@csrf
<h2 class="font-bold">Buat periode survei</h2>
<label class="block">Nama periode <input class="form-input" name="judul" value="{{ old('judul') }}" placeholder="Survei Pengalaman Belajar Siswa · Semester Ganjil 2026/2027" required maxlength="150"></label>
<div class="grid md:grid-cols-2 gap-3"><label>Mulai <input class="form-input" type="datetime-local" name="mulai" value="{{ old('mulai') }}" required></label><label>Berakhir <input class="form-input" type="datetime-local" name="selesai" value="{{ old('selesai') }}" required></label></div>
<fieldset><legend class="font-semibold mb-2">Pilih kelas</legend><div class="grid grid-cols-2 md:grid-cols-4 gap-2">@foreach($kelas as $k)<label><input type="checkbox" name="kelas[]" value="{{ $k->uuid }}" @checked(in_array($k->uuid, old('kelas', [])))> {{ $k->nama_lengkap }}</label>@endforeach</div></fieldset>
<p class="text-sm text-slate-500">Guru dan mata pelajaran diambil dari Jadwal Pelajaran setiap rombel yang dipilih. Wali kelas diambil dari data kelas. Daftar disimpan saat periode dibuat agar hasil konsisten.</p>
<p class="text-sm">Dalam jadwal periode ini, mulai sesi saat kelas siap. Siswa membuka pertanyaan dengan token yang Anda bagikan.</p>
<input type="hidden" name="wajib_seb" value="0">
<label class="flex items-center gap-2">
    <input type="checkbox" name="wajib_seb" value="1" @checked(old('wajib_seb'))>
    Wajib Safe Exam Browser (opsional)
</label>
<label class="flex items-center gap-2">
    <input type="checkbox" name="wajib_kios" value="1" @checked(old('wajib_kios'))>
    Gunakan SIMS Kios
</label>
<button class="px-4 py-2 rounded-lg bg-blue-700 text-white font-semibold" type="submit">Buat survei</button>
</form>
<div class="card p-5"><h2 class="font-bold mb-3">Daftar periode</h2>@forelse($periodes as $p)<p class="py-2 border-b"><a class="text-blue-700 underline" href="{{ route('survei.periode',$p) }}">{{ $p->judul }}</a> · {{ $p->mulai->format('d/m/Y H:i') }} – {{ $p->selesai->format('d/m/Y H:i') }} · {{ $p->ditutup ? 'Ditutup' : ($p->terbuka() ? 'Terbuka' : 'Belum terbuka / berakhir') }}</p>@empty<p>Belum ada survei.</p>@endforelse</div>
</div>
@endsection
