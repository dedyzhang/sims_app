@extends('layouts.app')
@section('title','Isi Survei')
@section('content')
<div class="max-w-3xl space-y-4"><a href="{{ route('survei.index') }}" class="text-blue-700 underline">← Daftar survei</a><h1 class="page-title">{{ $target->guru_nama }} · {{ $target->jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama }}</h1><p>Jawablah berdasarkan pengalamanmu selama periode ini. Pilih “Tidak dapat menilai” jika kamu tidak memiliki cukup pengalaman.</p>
@if($errors->any())<div class="card p-4 text-red-700">Mohon jawab semua pertanyaan skala. {{ $errors->first() }}</div>@endif
<form action="{{ route('survei.kirim',$target) }}" method="post" class="space-y-4">@csrf
@foreach($pertanyaan as $i=>$teks)<fieldset class="card p-4"><legend class="font-semibold">{{ $i+1 }}. {{ $teks }}</legend><div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-3">@foreach(['1'=>'Tidak pernah','2'=>'Jarang','3'=>'Kadang-kadang','4'=>'Sering','5'=>'Selalu','0'=>'Tidak dapat menilai'] as $value=>$label)<label class="flex gap-2 items-center"><input required type="radio" name="jawaban[{{ $i }}]" value="{{ $value }}" @checked((string) old('jawaban.'.$i) === (string) $value)> {{ $label }}</label>@endforeach</div></fieldset>@endforeach
<p>Tuliskan pengalaman atau saran dengan sopan. Jangan mencantumkan nama siswa lain atau informasi pribadi. Pertanyaan berikut boleh dikosongkan.</p>
@foreach($komentar as $i=>$teks)<label class="card p-4 block">{{ $teks }}<textarea class="form-input mt-2" name="komentar[{{ $i }}]" maxlength="1000" rows="3">{{ old('komentar.'.$i) }}</textarea></label>@endforeach
<p>Jawaban yang sudah dikirim tidak dapat diubah. Kamu bisa kembali dan mengisi survei guru lain selama periode masih terbuka.</p><button type="submit" class="px-5 py-3 bg-blue-700 text-white rounded-lg font-semibold">Kirim jawaban</button></form></div>
@endsection
