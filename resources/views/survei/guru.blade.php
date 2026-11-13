@extends('layouts.app')
@section('title','Hasil Survei Pembelajaran')
@section('content')
<div class="max-w-3xl space-y-5"><h1 class="page-title">Hasil Survei Pembelajaran</h1><p>Hasil tersedia setelah survei berakhir dan memenuhi batas minimum respons.</p>@forelse($targets as $t)<div class="card p-4"><p>{{ $t->periode->judul }} · {{ $t->kelas_nama }}</p><strong>{{ $t->jenis === 'wali' ? 'Wali kelas' : $t->pelajaran_nama }}</strong><br><a class="text-blue-700 underline" href="{{ route('survei.hasil',$t) }}">Lihat hasil</a></div>@empty<p>Belum ada hasil survei.</p>@endforelse</div>
@endsection
