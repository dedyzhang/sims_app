@extends('layouts.app')
@section('title','Hasil Survei')
@section('content')
<div class="max-w-3xl space-y-4"><a href="{{ $admin ? route('survei.periode',$target->periode) : route('survei.index') }}" class="text-blue-700 underline">← Kembali</a><h1 class="page-title">Hasil Survei Pembelajaran</h1><p>{{ $target->periode->judul }} · {{ $target->kelas_nama }} · {{ $target->guru_nama }} · {{ $target->jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama }}</p>
@if($jumlah < 5)<div class="card p-5">Hasil belum dapat ditampilkan karena jumlah respons kurang dari lima.</div>@else<p>{{ $jumlah }} respons. “Tidak dapat menilai” tidak masuk perhitungan rata-rata. Hasil ini adalah masukan pembelajaran, bukan satu-satunya penilaian kinerja.</p>@foreach($hasil as $h)<div class="card p-4"><strong>{{ $h['text'] }}</strong><p>@if($h['n'] >= 5)Rata-rata: {{ $h['rata'] }} / 5 · {{ $h['n'] }} jawaban dinilai · {{ $h['tidak'] }} tidak dapat menilai @else Belum cukup jawaban yang dapat dinilai untuk menampilkan hasil pertanyaan ini. @endif</p></div>@endforeach
@if($admin)<div class="card p-4"><h2 class="font-bold">Komentar untuk ditinjau petugas</h2><p>Tinjau dan hilangkan informasi pribadi sebelum membagikan komentar kepada guru.</p>@forelse($komentar as $k)<p class="py-2 border-b whitespace-pre-wrap">{{ $k }}</p>@empty<p>Tidak ada komentar.</p>@endforelse</div>@endif
@endif</div>
@endsection
