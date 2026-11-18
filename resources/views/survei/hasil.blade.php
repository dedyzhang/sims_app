@extends('layouts.app')
@section('title','Hasil Survei')
@section('content')
<div class="max-w-3xl space-y-4"><a href="{{ route('survei.analisis', $target->periode) }}" class="text-blue-700 underline">← Analisis per guru</a><h1 class="page-title">Hasil Survei Pembelajaran</h1><p>{{ $target->periode->judul }} · {{ $target->kelas_nama }} · {{ $target->guru_nama }} · {{ $target->jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama }}</p>
@if($jumlah < \App\Survei\AnalisisGuru::MINIMUM)<div class="card p-5">Hasil belum dapat ditampilkan karena jumlah respons kurang dari lima.</div>@else<p>{{ $jumlah }} respons. “Tidak tahu” dikeluarkan dari rata-rata. Skor mengikuti arah setiap butir pada periode ini.</p>@foreach($hasil as $h)<div class="card p-4"><strong>{{ $h['text'] }}</strong><p>@if($h['n'] >= \App\Survei\AnalisisGuru::MINIMUM)Rata-rata: {{ number_format($h['rata'], 2, ',', '.') }} / 5 · {{ $h['n'] }} jawaban dinilai · {{ $h['tidak'] }} tidak tahu @else Belum cukup jawaban yang dapat dinilai untuk menampilkan hasil pertanyaan ini. @endif</p></div>@endforeach
@if(count($kendala))<div class="card p-4"><h2 class="font-bold">Kendala yang paling sering dipilih</h2><div class="mt-2 space-y-1">@foreach($kendala as $label=>$count)<p>{{ $label }}: {{ $count }}</p>@endforeach</div></div>@endif
@if(!empty($karakter))<div class="card p-4"><h2 class="font-bold">Kesan Siswa: "Menurut kamu, guru ini termasuk guru yang ..."</h2><div class="mt-2 flex flex-wrap gap-2">@foreach($karakter as $label=>$count)<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800"><span>{{ $label }}</span><span class="bg-amber-200 text-amber-900 px-1.5 py-0.5 rounded-full font-bold text-[10px]">{{ $count }}</span></span>@endforeach</div></div>@endif
@if($pembuat)
<div class="card space-y-4 p-4">
    <h2 class="font-bold">Masukan tertulis untuk pembuat survei</h2>
    <p class="text-sm">Gunakan masukan ini untuk menyusun ringkasan tindak lanjut dan jaga kerahasiaan komentar asli.</p>
    @forelse(array_filter($komentar, fn ($jawaban) => $jawaban->isNotEmpty()) as $pertanyaan => $jawaban)
        <div>
            <h3 class="font-semibold">{{ $pertanyaan }}</h3>
            @foreach($jawaban as $teks)
                <p class="whitespace-pre-wrap break-words border-b py-2">{{ $teks }}</p>
            @endforeach
        </div>
    @empty
        <p>Tidak ada masukan tertulis.</p>
    @endforelse
</div>
@endif
@endif</div>
@endsection
