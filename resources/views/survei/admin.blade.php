@extends('layouts.app')
@section('title','Survei Pembelajaran')
@section('content')
<div class="max-w-5xl space-y-5">
<h1 class="page-title">Survei Pembelajaran</h1>
<p class="text-sm">Buat survei untuk kelas pilihan. Hasil dipakai untuk perbaikan pembelajaran; identitas siswa tidak ditampilkan dalam rekap.</p>
@if($errors->any())<div class="card p-4 text-red-600">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('survei.store') }}" class="card p-5 space-y-4">@csrf
<h2 class="font-bold">Buat periode survei</h2>
<label class="block">Nama periode <input class="form-input" name="judul" value="{{ old('judul') }}" placeholder="Survei Semester Ganjil 2026/2027" required maxlength="150"></label>
<div class="grid md:grid-cols-2 gap-3"><label>Mulai <input class="form-input" type="datetime-local" name="mulai" value="{{ old('mulai') }}" required></label><label>Berakhir <input class="form-input" type="datetime-local" name="selesai" value="{{ old('selesai') }}" required></label></div>
<fieldset><legend class="font-semibold mb-2">Pilih kelas</legend><div class="grid grid-cols-2 md:grid-cols-4 gap-2">@foreach($kelas as $k)<label><input type="checkbox" name="kelas[]" value="{{ $k->uuid }}" @checked(in_array($k->uuid, old('kelas', [])))> {{ $k->nama_lengkap }}</label>@endforeach</div></fieldset>
<p class="text-sm text-slate-500">Guru, mata pelajaran dan wali kelas diambil dari data penugasan saat dibuat. Periksa data penugasan sebelum membuat survei.</p>
<button class="px-4 py-2 rounded-lg bg-blue-700 text-white font-semibold" type="submit">Buat survei</button>
</form>
<div class="card p-5"><h2 class="font-bold mb-3">Daftar periode</h2>@forelse($periodes as $p)<p class="py-2 border-b"><a class="text-blue-700 underline" href="{{ route('survei.periode',$p) }}">{{ $p->judul }}</a> · {{ $p->mulai->format('d/m/Y H:i') }} – {{ $p->selesai->format('d/m/Y H:i') }} · {{ $p->ditutup ? 'Ditutup' : ($p->terbuka() ? 'Terbuka' : 'Belum terbuka / berakhir') }}</p>@empty<p>Belum ada survei.</p>@endforelse</div>
</div>
@endsection
