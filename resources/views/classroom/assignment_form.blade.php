@extends('layouts.app')
@section('title', isset($assignment) ? 'Sunting Tugas' : 'Tambah Latihan/Tugas')

@php
    $editing = isset($assignment);
    $a = $assignment ?? null;
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ $editing ? route('classroom.assignment.show', $a) : route('classroom.show', $classroom) }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <nav class="text-xs text-slate-400">{{ $classroom->pelajaran?->nama }} · Kelas {{ $classroom->rombel?->tingkat }}{{ $classroom->rombel?->kelas }}</nav>
            <h1 class="page-title">{{ $editing ? 'Sunting Latihan/Tugas' : 'Tambah Latihan/Tugas' }}</h1>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm"><ul class="list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ $editing ? route('classroom.assignment.update', $a) : route('classroom.assignment.store', $classroom) }}" enctype="multipart/form-data" class="card p-6 space-y-4">
        @csrf

        <div>
            <label class="form-label">Judul</label>
            <input type="text" name="title" value="{{ old('title', $a->title ?? '') }}" required maxlength="160" class="form-input" placeholder="Judul latihan/tugas">
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div><label class="form-label text-xs">Jenis</label>
                <select name="type" class="form-select">
                    @foreach(['tugas'=>'Tugas','latihan'=>'Latihan','kuis'=>'Kuis'] as $v=>$l)
                    <option value="{{ $v }}" @selected(old('type', $a->type ?? 'tugas')===$v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="form-label text-xs">Nilai Maks</label><input type="number" name="max_score" value="{{ old('max_score', $a->max_score ?? 100) }}" min="1" class="form-input"></div>
            <div><label class="form-label text-xs">Mulai</label><input type="datetime-local" name="opens_at" value="{{ old('opens_at', $dt($a->opens_at ?? null)) }}" class="form-input"></div>
            <div><label class="form-label text-xs">Batas</label><input type="datetime-local" name="due_at" value="{{ old('due_at', $dt($a->due_at ?? null)) }}" class="form-input"></div>
        </div>

        <div>
            <label class="form-label">Instruksi / Soal</label>
            <p class="text-[11px] text-slate-400 mb-1.5">Bisa sisipkan <b>∑ Rumus</b> (jadi SVG) &amp; <b>▶ YouTube</b>.</p>
            @include('classroom.partials.editor', ['name' => 'instructions', 'value' => old('instructions', $a->instructions ?? '')])
        </div>

        <div>
            <label class="form-label">Berikan ke Kelas</label>
            <p class="text-[11px] text-slate-400 mb-2">Centang kelas penerima. Mengedit akan mengubah di semua kelas tertaut.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @forelse($kelasOptions as $k)
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 cursor-pointer text-sm hover:border-primary">
                    <input type="checkbox" name="kelas[]" value="{{ $k->uuid }}" @checked(in_array($k->uuid, old('kelas', $checked), true)) @disabled($k->uuid === $classroom->id_kelas) class="rounded">
                    <span>Kelas {{ $k->tingkat }}{{ $k->kelas }}</span>
                </label>
                @empty
                <p class="text-sm text-slate-400">Tidak ada kelas lain untuk mapel ini.</p>
                @endforelse
            </div>
            @if($classroom->id_kelas)<input type="hidden" name="kelas[]" value="{{ $classroom->id_kelas }}">@endif
        </div>

        <div>
            <label class="form-label">Lampiran soal <span class="text-slate-400 font-normal">(opsional)</span></label>
            @include('classroom.partials.upload', ['label' => 'Tambah lampiran'])
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                    <input type="checkbox" name="publish_now" value="1" @checked(old('publish_now', ($a->status ?? 'published')==='published')) class="rounded text-primary focus:ring-primary">
                    <span>Terbitkan</span>
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer select-none text-rose-600 dark:text-rose-400 font-semibold">
                    <input type="checkbox" name="hide_scores" value="1" @checked(old('hide_scores', $a->hide_scores ?? false)) class="rounded text-rose-500 focus:ring-rose-500">
                    <span>Rahasiakan Nilai dari Siswa</span>
                </label>
            </div>
            <div class="flex gap-2">
                <a href="{{ $editing ? route('classroom.assignment.show', $a) : route('classroom.show', $classroom) }}" class="px-5 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</a>
                <button class="px-6 py-2.5 rounded-xl text-sm font-bold text-white" style="background:var(--cp)">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
