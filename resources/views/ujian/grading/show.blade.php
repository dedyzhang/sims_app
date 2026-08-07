@extends('layouts.app')
@section('title', 'Nilai Esai — ' . ($siswa?->nama ?? 'Siswa'))

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.grading.index', $ujian) }}" class="hover:underline">Penilaian Esai</a> / {{ $siswa?->nama }}
        </nav>
        <h1 class="page-title">{{ $siswa?->nama ?? 'Siswa' }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $ujian->judul }} · {{ $siswa?->kelas?->tingkat }}{{ $siswa?->kelas?->kelas }}</p>
    </div>

    <form method="POST" action="{{ route('ujian.grading.store', [$ujian, $attempt]) }}" class="space-y-4">
        @csrf
        @foreach($soalEssai as $i => $soal)
        @php $j = $jawaban->get($soal->uuid); @endphp
        <div class="card p-5 space-y-3">
            <p class="text-xs text-slate-400">Soal {{ $i + 1 }} · maks {{ $soal->poin }} poin</p>
            <div class="text-sm font-medium text-slate-800 dark:text-slate-100">
                @include('ujian.partials.rich-body', ['html' => $soal->teks_soal])
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/40 text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line">
                {{ $j?->jawaban_esai ?: '(Tidak dijawab)' }}
            </div>

            @if($soal->meta['kunci_jawaban'] ?? null)
            <p class="text-xs text-slate-400">Kunci/rubrik: {{ $soal->meta['kunci_jawaban'] }}</p>
            @endif

            <div class="grid sm:grid-cols-[120px_1fr] gap-3 items-start">
                <div>
                    <label class="form-label">Skor</label>
                    <input type="number" name="skor[{{ $soal->uuid }}]" min="0" max="{{ $soal->poin }}" step="0.5"
                           value="{{ $j?->skor_diperoleh ?? 0 }}" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Catatan (opsional)</label>
                    <input type="text" name="catatan[{{ $soal->uuid }}]" value="{{ $j?->catatan_guru }}" class="form-input">
                </div>
            </div>
        </div>
        @endforeach

        <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Simpan Penilaian</button>
    </form>
</div>
@endsection
