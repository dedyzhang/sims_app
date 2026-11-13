@extends('layouts.app')
@section('title','Survei Pembelajaran')
@section('content')
<div class="max-w-3xl space-y-5"><h1 class="page-title">Survei Pembelajaran</h1><p>Isi hanya untuk pelajaran yang kamu ikuti; jika kamu tidak mengikutinya, abaikan survei tersebut. Jawabanmu membantu sekolah memperbaiki pembelajaran. Jawaban bersifat rahasia, tetapi bukan anonim: sistem mencatat akun untuk mencegah pengisian ganda. Guru hanya melihat hasil gabungan yang memenuhi batas minimum.</p><p>Jika perlu bantuan segera atau ingin melaporkan masalah serius, hubungi orang dewasa tepercaya atau kanal pelaporan sekolah; survei ini bukan kanal bantuan segera.</p>
@forelse($targets as $t)<div class="card p-4"><p class="text-sm">{{ $t->periode->judul }} · Berakhir {{ $t->periode->selesai->format('d/m/Y H:i') }}</p><h2 class="font-bold">{{ $t->guru_nama }} · {{ $t->jenis === 'wali' ? 'Wali kelas' : $t->pelajaran_nama }}</h2>@if(in_array($t->uuid,$done))<span>Selesai</span>@else<a class="inline-block text-blue-700 underline mt-2" href="{{ route('survei.isi',$t) }}">Isi survei</a>@endif</div>@empty<div class="card p-5">Belum ada survei terbuka untuk kelasmu.</div>@endforelse</div>
@endsection
