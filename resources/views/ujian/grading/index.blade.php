@extends('layouts.app')
@section('title', 'Penilaian Esai — ' . $ujian->judul)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Penilaian Esai
        </nav>
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h1 class="page-title">Penilaian Esai</h1>
                @if($kelasFilter)
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $attempts->count() }} attempt menunggu penilaian pada kelas ini.</p>
                @else
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Penilaian manual jawaban esai peserta ujian per-kelas.</p>
                @endif
            </div>
            @if($kelasFilter && $ujianKelasList->count() > 1)
            <a href="{{ route('ujian.grading.index', $ujian) }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Kembali ke Pilih Kelas</span>
            </a>
            @endif
        </div>
    </div>

    @if(!$kelasFilter && isset($kelasSummary))
    {{-- TAMPILAN 1: PILIH KELAS (Ringkasan per-kelas agar server ringan & tidak meload ratusan esai sekaligus) --}}
    <div class="card p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/60 rounded-2xl flex items-start gap-3">
        <i data-lucide="info" class="w-5 h-5 text-blue-600 shrink-0 mt-0.5"></i>
        <div class="text-xs text-blue-900 dark:text-blue-200 space-y-0.5">
            <p class="font-bold">Ujian Multi-Kelas ({{ $ujianKelasList->count() }} Kelas Ditugaskan)</p>
            <p>Pilih kelas di bawah untuk memeriksa dan menilai jawaban esai siswa. Memeriksa per-kelas menjaga proses penilaian tetap fokus dan menghemat beban server.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($kelasSummary as $c)
        <div class="card p-5 border border-slate-200 dark:border-slate-700/60 rounded-2xl flex flex-col justify-between hover:shadow-md hover:border-primary/50 transition">
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="inline-block px-2.5 py-1 rounded-lg bg-primary/10 text-primary font-bold text-base">
                            Kelas {{ $c['kelas']?->tingkat }}{{ $c['kelas']?->kelas }}
                        </span>
                        @if($c['ujianKelas']->guruPengampu)
                        <p class="text-xs text-slate-400 mt-1 truncate" title="{{ $c['ujianKelas']->guruPengampu->nama }}">
                            Pengampu: {{ $c['ujianKelas']->guruPengampu->nama }}
                        </p>
                        @endif
                    </div>
                    @if($c['butuh_dinilai'] > 0)
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400">
                        {{ $c['butuh_dinilai'] }} Menunggu
                    </span>
                    @else
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400">
                        Selesai
                    </span>
                    @endif
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500 space-y-1">
                    <div class="flex justify-between">
                        <span>Menunggu Penilaian:</span>
                        <strong class="{{ $c['butuh_dinilai'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">{{ $c['butuh_dinilai'] }} attempt</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Selesai Dinilai:</span>
                        <span class="text-slate-600 dark:text-slate-300">{{ $c['selesai_dinilai'] }} attempt</span>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-3 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('ujian.grading.index', ['ujian' => $ujian, 'kelas' => $c['ujianKelas']->id_kelas]) }}" class="btn-primary w-full py-2 rounded-xl text-center text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm">
                    <span>{{ $c['butuh_dinilai'] > 0 ? 'Nilai Esai Kelas Ini' : 'Buka Kelas Ini' }}</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    @else
    {{-- TAMPILAN 2: DAFTAR SISWA KELAS TERPILIH --}}
    @if($ujianKelasList->count() > 1)
    <div class="flex items-center justify-between flex-wrap gap-4">
        <form method="GET" action="{{ route('ujian.grading.index', $ujian) }}" class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-500">Kelas:</label>
            <select name="kelas" class="form-select py-1.5 text-sm w-auto font-semibold" onchange="this.form.submit()">
                @foreach($ujianKelasList->sortBy(fn($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas]) as $uk)
                    <option value="{{ $uk->id_kelas }}" @selected($kelasFilter === $uk->id_kelas)>Kelas {{ $uk->kelas?->tingkat }}{{ $uk->kelas?->kelas }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @endif

    <div class="space-y-3">
        @forelse($attempts as $attempt)
        @php $siswa = $siswaByLogin->get($attempt->id_siswa); @endphp
        <div class="card p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="font-semibold text-sm text-slate-800 dark:text-slate-100">{{ $siswa?->nama ?? 'Siswa tidak dikenal' }}</p>
                <p class="text-xs text-slate-400">Kelas {{ $siswa?->kelas?->tingkat }}{{ $siswa?->kelas?->kelas }} · Dikumpulkan {{ $attempt->selesai_pada?->format('d M Y H:i') }}</p>
            </div>
            <a href="{{ route('ujian.grading.show', [$ujian, $attempt]) }}" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap">Nilai</a>
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="check-circle-2" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Tidak ada esai yang menunggu penilaian pada kelas ini.</p>
        </div>
        @endforelse
    </div>
    @endif
</div>
@endsection
