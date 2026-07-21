@extends('layouts.app')
@section('title', $quiz ? 'Edit Kuis' : 'Buat Kuis')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
<style>
.edu-builder {
    --lobby-font: 'Fredoka', 'Plus Jakarta Sans', system-ui, sans-serif;
    position: relative;
    isolation: isolate;
}
.edu-builder, .edu-builder button, .edu-builder a, .edu-builder input, .edu-builder select, .edu-builder textarea {
    font-family: var(--lobby-font);
}
.edu-builder .edu-header {
    position: relative;
    z-index: 1;
    overflow: hidden;
    border-radius: 1.35rem;
    border: 3px solid rgba(18, 52, 91, 0.1);
    background:
        radial-gradient(circle at 12% 70%, rgba(0, 194, 178, 0.28), transparent 42%),
        radial-gradient(circle at 88% 30%, rgba(255, 176, 32, 0.22), transparent 40%),
        linear-gradient(180deg, #9ad4ff 0%, #c9ebff 45%, #e8f4ff 100%);
    box-shadow: 0 8px 0 rgba(18, 52, 91, 0.12), 0 18px 36px rgba(18, 52, 91, 0.12);
    color: var(--arena-navy);
}
.dark .edu-builder .edu-header {
    background:
        radial-gradient(circle at 12% 70%, rgba(0, 169, 157, 0.22), transparent 42%),
        linear-gradient(180deg, #0a1a2e 0%, #10253d 55%, #0f172a 100%);
    border-color: #334155;
    box-shadow: 0 8px 0 rgba(0,0,0,.35);
    color: #f1f5f9;
}
.edu-builder .edu-header-blocks {
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.edu-builder .edu-header-blocks span {
    position: absolute;
    border-radius: .55rem;
    box-shadow: inset 0 2px 0 rgba(255,255,255,.35), 0 6px 0 rgba(0,0,0,.12);
    animation: arena-block-float 5.5s ease-in-out infinite;
}
.edu-builder .edu-hb-a { left: 8%; top: 22%; width: 1.6rem; height: 1.6rem; background: linear-gradient(145deg,#00c2b2,#008f84); }
.edu-builder .edu-hb-b { right: 12%; top: 18%; width: 1.25rem; height: 1.25rem; background: linear-gradient(145deg,#ffb020,#e09410); animation-delay: .8s; }
.edu-builder .edu-hb-c { right: 22%; bottom: 18%; width: 1rem; height: 1rem; border-radius: 50%; background: radial-gradient(circle at 35% 30%,#ffe9a8,#ffb020); box-shadow: 0 0 0 3px rgba(255,176,32,.25); animation: arena-coin-spin 3.8s linear infinite; }

.edu-builder .edu-section {
    position: relative;
    z-index: 1;
    border: 3px solid rgba(18, 52, 91, 0.08);
    border-radius: 1.25rem;
    background: rgba(255,255,255,.94);
    box-shadow: 0 7px 0 rgba(18, 52, 91, 0.1);
}
.dark .edu-builder .edu-section {
    background: rgba(15, 23, 42, .94);
    border-color: #334155;
    box-shadow: 0 7px 0 rgba(0,0,0,.35);
}
.edu-builder .edu-section-title {
    display: flex;
    align-items: center;
    gap: .65rem;
    font-weight: 800;
    font-size: 1.05rem;
    color: var(--arena-ink);
}
.dark .edu-builder .edu-section-title { color: #f1f5f9; }
.edu-builder .edu-step {
    width: 2rem;
    height: 2rem;
    border-radius: .7rem;
    display: grid;
    place-items: center;
    font-size: .8rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(145deg, #00c2b2, #0b3d6e);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.3), 0 3px 0 rgba(11, 61, 110, 0.35);
    flex-shrink: 0;
}
.edu-builder .edu-label {
    display: block;
    font-size: .78rem;
    font-weight: 700;
    color: #3d5678;
    margin-bottom: .4rem;
}
.dark .edu-builder .edu-label { color: #94a3b8; }
.edu-builder .edu-input {
    width: 100%;
    min-height: 2.85rem;
    padding: .7rem .95rem;
    border-radius: 1rem;
    border: 2px solid rgba(18, 52, 91, 0.12);
    background: #fff;
    font-size: .9rem;
    font-weight: 600;
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.06);
}
.dark .edu-builder .edu-input {
    background: #0f172a;
    border-color: #334155;
    color: #f1f5f9;
    box-shadow: 0 3px 0 rgba(0,0,0,.25);
}
.edu-builder .edu-input:focus {
    outline: none;
    border-color: var(--arena-teal);
    box-shadow: 0 3px 0 rgba(0, 169, 157, 0.25), 0 0 0 3px rgba(0, 169, 157, 0.15);
}
.edu-builder .edu-check {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    min-height: 2.5rem;
    padding: .4rem .8rem;
    border-radius: .85rem;
    background: #fff;
    border: 2px solid rgba(18, 52, 91, 0.1);
    font-size: .8rem;
    font-weight: 700;
    color: #334155;
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.08);
}
.dark .edu-builder .edu-check {
    background: #0f172a;
    border-color: #334155;
    color: #e2e8f0;
    box-shadow: 0 3px 0 rgba(0,0,0,.3);
}
.edu-builder .edu-check input { accent-color: var(--arena-teal); }
.edu-builder .edu-q {
    border: 3px solid rgba(18, 52, 91, 0.08);
    border-radius: 1.2rem;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 7px 0 rgba(18, 52, 91, 0.1);
}
.dark .edu-builder .edu-q {
    background: #0f172a;
    border-color: #334155;
    box-shadow: 0 7px 0 rgba(0,0,0,.35);
}
.edu-builder .edu-q-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .85rem 1rem;
    background: linear-gradient(135deg, #e8f9f7, #eef6ff);
    border-bottom: 2px solid rgba(18, 52, 91, 0.08);
}
.dark .edu-builder .edu-q-head {
    background: linear-gradient(135deg, #12333a, #15253a);
    border-bottom-color: #334155;
}
.edu-builder .edu-q-num {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: .75rem;
    display: grid;
    place-items: center;
    font-size: .9rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(145deg, #00c2b2, #0b3d6e);
    box-shadow: inset 0 2px 0 rgba(255,255,255,.3), 0 3px 0 rgba(11, 61, 110, 0.3);
}
.edu-builder .edu-type {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}
.edu-builder .edu-type button {
    padding: .5rem .75rem;
    border-radius: .75rem;
    border: 2px solid rgba(18, 52, 91, 0.12);
    background: #fff;
    font-size: .72rem;
    font-weight: 800;
    color: #475569;
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.08);
    transition: transform .1s ease, box-shadow .1s ease;
}
.dark .edu-builder .edu-type button {
    background: #0f172a;
    border-color: #334155;
    color: #cbd5e1;
    box-shadow: 0 3px 0 rgba(0,0,0,.3);
}
.edu-builder .edu-type button:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(18, 52, 91, 0.08); }
.edu-builder .edu-type button.is-on {
    background: linear-gradient(180deg, #22e06b, #00c853);
    border-color: #00963e;
    color: #fff;
    box-shadow: 0 3px 0 #00963e;
}
.edu-builder .edu-letter {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: .7rem;
    border: 2px solid rgba(18, 52, 91, 0.12);
    display: grid;
    place-items: center;
    font-size: .8rem;
    font-weight: 800;
    color: #64748b;
    background: #fff;
    flex-shrink: 0;
    cursor: pointer;
    box-shadow: 0 3px 0 rgba(18, 52, 91, 0.1);
}
.dark .edu-builder .edu-letter {
    background: #0f172a;
    border-color: #334155;
    color: #94a3b8;
}
.edu-builder .edu-letter.is-correct {
    background: linear-gradient(180deg, #22e06b, #00c853);
    border-color: #00963e;
    color: #fff;
    box-shadow: 0 3px 0 #00963e;
}
.edu-builder .edu-sticky {
    position: sticky;
    bottom: .75rem;
    z-index: 20;
    display: flex;
    gap: .65rem;
    padding: .75rem;
    border-radius: 1.15rem;
    background: rgba(255,255,255,.95);
    border: 3px solid rgba(18, 52, 91, 0.1);
    box-shadow: 0 8px 0 rgba(18, 52, 91, 0.1), 0 16px 32px rgba(18, 52, 91, 0.12);
    backdrop-filter: blur(8px);
}
.dark .edu-builder .edu-sticky {
    background: rgba(15,23,42,.96);
    border-color: #334155;
    box-shadow: 0 8px 0 rgba(0,0,0,.35);
}
.edu-builder .edu-btn-primary {
    flex: 1.4;
}
.edu-builder .edu-btn-ghost {
    flex: 1;
    text-decoration: none;
}
.edu-builder .edu-add-q {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    min-height: 3rem;
    padding: .75rem 1rem;
    border-radius: 1rem;
    border: 3px dashed rgba(0, 169, 157, 0.45);
    background: rgba(255,255,255,.85);
    color: var(--arena-teal);
    font-weight: 800;
    font-size: .9rem;
    box-shadow: 0 5px 0 rgba(0, 169, 157, 0.15);
    transition: transform .1s ease;
}
.dark .edu-builder .edu-add-q {
    background: rgba(15, 23, 42, .85);
    border-color: rgba(0, 169, 157, 0.4);
}
.edu-builder .edu-add-q:hover { transform: translateY(-1px); }
.edu-builder .edu-add-q:active { transform: translateY(2px); box-shadow: 0 2px 0 rgba(0, 169, 157, 0.15); }
</style>
@endpush

@php
    $initialQuestions = [];
    if ($quiz) {
        foreach ($quiz->questions as $q) {
            $initialQuestions[] = [
                'type' => $q->type,
                'question_text' => $q->question_text,
                'points' => $q->points,
                'time_limit_seconds' => $q->time_limit_seconds,
                'explanation' => $q->explanation,
                'options' => $q->options->map(fn ($o) => [
                    'option_text' => $o->option_text,
                    'is_correct' => (bool) $o->is_correct,
                ])->values()->all() ?: [
                    ['option_text' => '', 'is_correct' => true],
                    ['option_text' => '', 'is_correct' => false],
                ],
                'meta' => [
                    'answers' => $q->meta['answers'] ?? [''],
                    'pairs' => $q->meta['pairs'] ?? [['left' => '', 'right' => ''], ['left' => '', 'right' => '']],
                ],
            ];
        }
    }
    if (!$initialQuestions) {
        $initialQuestions = [[
            'type' => 'mcq',
            'question_text' => '',
            'points' => 1,
            'time_limit_seconds' => null,
            'explanation' => '',
            'options' => [
                ['option_text' => '', 'is_correct' => true],
                ['option_text' => '', 'is_correct' => false],
                ['option_text' => '', 'is_correct' => false],
                ['option_text' => '', 'is_correct' => false],
            ],
            'meta' => [
                'answers' => [''],
                'pairs' => [['left' => '', 'right' => ''], ['left' => '', 'right' => '']],
            ],
        ]];
    }
@endphp

@section('content')
@php
    $asistenGuruAktif = $asistenGuruAktif ?? false;
    $aiImportText = $aiImportText ?? null;
    $aiImportTitle = $aiImportTitle ?? null;
@endphp
<div class="space-y-5 max-w-3xl mx-auto edu-builder arena-stage arena-lobby"
     x-data="arenaBuilder(@js($initialQuestions), @js([
         'importText' => $aiImportText ?? '',
         'asistenGuruAktif' => $asistenGuruAktif,
         'aiQuizUrl' => $asistenGuruAktif ? route('ai.teacher.quiz') : null,
     ]))"
     x-cloak>

    <div class="edu-header p-5 sm:p-7">
        <div class="edu-header-blocks" aria-hidden="true">
            <span class="edu-hb-a"></span>
            <span class="edu-hb-b"></span>
            <span class="edu-hb-c"></span>
        </div>
        <div class="relative z-[1]">
            <a href="{{ $quiz ? route('classroom.arena.show', [$classroom, $quiz]) : route('classroom.arena.index', $classroom) }}" class="arena-hud-back mb-3">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span>{{ $quiz ? 'Experience' : 'Lobby Arena' }}</span>
            </a>
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div>
                    <p class="arena-lobby-kicker mb-1">Studio kuis · Arena Belajar</p>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight" style="text-shadow:0 3px 0 rgba(255,255,255,.5)">
                        {{ $quiz ? 'Edit kuis' : 'Buat kuis' }}
                    </h1>
                    <p class="text-sm font-semibold mt-1.5 opacity-75">{{ $classroom->title }} · Susun soal, rebut podium</p>
                </div>
                <div class="arena-chip3d !min-w-[5.5rem]">
                    <strong x-text="questions.length"></strong>
                    <span>Soal</span>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm">
        <p class="font-bold mb-1">Periksa kembali isian berikut:</p>
        <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST"
          action="{{ $quiz ? route('classroom.arena.update', [$classroom, $quiz]) : route('classroom.arena.store', $classroom) }}"
          class="space-y-5"
          @submit="prepareSubmit">
        @csrf

        {{-- Pengaturan --}}
        <div class="edu-section p-4 sm:p-5 space-y-4">
            <div class="edu-section-title">
                <span class="edu-step">1</span>
                Pengaturan kuis
            </div>

            <div>
                <label class="edu-label">Judul kuis</label>
                <input type="text" name="title" value="{{ old('title', $quiz->title ?? ($aiImportTitle ?? '')) }}" required maxlength="200"
                       class="edu-input" placeholder="Contoh: Latihan Pecahan — Pertemuan 3">
            </div>
            <div>
                <label class="edu-label">Petunjuk untuk siswa (opsional)</label>
                <textarea name="instructions" rows="2" class="edu-input" placeholder="Tuliskan aturan atau materi yang diujikan…">{{ old('instructions', $quiz->instructions ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="edu-label">Mode penilaian</label>
                    <select name="scoring_mode" class="edu-input">
                        <option value="accuracy" @selected(old('scoring_mode', $quiz->scoring_mode ?? 'accuracy')==='accuracy')>Akurasi (disarankan untuk nilai rapor)</option>
                        <option value="competitive" @selected(old('scoring_mode', $quiz->scoring_mode ?? '')==='competitive')>Kompetitif (bonus kecepatan)</option>
                    </select>
                </div>
                <div>
                    <label class="edu-label">Cara main</label>
                    <select name="play_mode" class="edu-input">
                        <option value="bebas" @selected(old('play_mode', $quiz->play_mode ?? 'bebas')==='bebas')>Bebas (siswa pilih solo/live)</option>
                        <option value="solo" @selected(old('play_mode', $quiz->play_mode ?? '')==='solo')>Solo saja</option>
                        <option value="live" @selected(old('play_mode', $quiz->play_mode ?? '')==='live')>Live saja</option>
                    </select>
                </div>
                <div>
                    <label class="edu-label">Nilai maksimum</label>
                    <input type="number" name="max_score" min="1" max="1000" value="{{ old('max_score', $quiz->max_score ?? 100) }}" class="edu-input">
                </div>
                <div>
                    <label class="edu-label">Waktu dibuka</label>
                    <input type="datetime-local" name="opens_at" value="{{ old('opens_at', isset($quiz->opens_at) ? $quiz->opens_at->format('Y-m-d\TH:i') : '') }}" class="edu-input">
                </div>
                <div>
                    <label class="edu-label">Batas pengumpulan</label>
                    <input type="datetime-local" name="due_at" value="{{ old('due_at', isset($quiz->due_at) ? $quiz->due_at->format('Y-m-d\TH:i') : '') }}" class="edu-input">
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <label class="edu-check"><input type="checkbox" name="instant_feedback" value="1" @checked(old('instant_feedback', $quiz->instant_feedback ?? true))> Umpan balik langsung</label>
                <label class="edu-check"><input type="checkbox" name="hide_scores" value="1" @checked(old('hide_scores', $quiz->hide_scores ?? false))> Sembunyikan skor</label>
                <label class="edu-check"><input type="checkbox" name="show_leaderboard" value="1" @checked(old('show_leaderboard', $quiz->show_leaderboard ?? false))> Tampilkan peringkat</label>
                <label class="edu-check"><input type="checkbox" name="publish_now" value="1" @checked(old('publish_now'))> Terbitkan sekarang</label>
                <input type="hidden" name="assign_self" value="1">
            </div>
        </div>

        {{-- Generate & Impor dari Asisten Guru --}}
        <details class="edu-section p-4 sm:p-5 group" @if($aiImportText) open @endif>
            <summary class="cursor-pointer list-none edu-section-title">
                <span class="edu-step" style="background:linear-gradient(145deg,#ffb020,#e85d75)">AI</span>
                Generate / impor soal (Asisten Guru)
                <span class="ml-auto text-xs font-bold text-slate-400 group-open:hidden">BUKA</span>
            </summary>
            <div class="mt-3 space-y-4">
                @if($asistenGuruAktif)
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3 space-y-3" x-show="asistenGuruAktif">
                    <p class="text-xs text-slate-500">Generate soal lewat Asisten Guru, lalu otomatis diimpor ke daftar di bawah. Paling cocok: pilihan ganda &amp; benar/salah.</p>

                    <div>
                        <label class="edu-label">Sumber materi</label>
                        <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 dark:bg-slate-800 p-1">
                            <button type="button" @click="gen.source = 'ai'"
                                    :class="gen.source === 'ai' ? 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 shadow-sm' : 'text-slate-500'"
                                    class="rounded-lg px-3 py-2 text-xs font-bold transition">Dari topik</button>
                            <button type="button" @click="gen.source = 'file'"
                                    :class="gen.source === 'file' ? 'bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 shadow-sm' : 'text-slate-500'"
                                    class="rounded-lg px-3 py-2 text-xs font-bold transition">Upload materi</button>
                        </div>
                    </div>

                    <div>
                        <label class="edu-label">
                            Topik
                            <span class="text-rose-500" x-show="gen.source === 'ai'" x-cloak>*</span>
                            <span class="text-slate-400 font-medium" x-show="gen.source === 'file'" x-cloak>(opsional · fokus soal)</span>
                        </label>
                        <input type="text" x-model="gen.topik" class="edu-input" placeholder="mis. Pecahan, Fotosintesis…" maxlength="500">
                    </div>

                    <div x-show="gen.source === 'file'" x-cloak>
                        <label class="edu-label">File materi <span class="text-rose-500">*</span></label>
                        <label class="flex min-h-[96px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/40 px-4 py-3 text-center transition hover:border-[var(--cp)]">
                            <input x-ref="arenaQuizFile" type="file" class="sr-only"
                                   accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                   @change="setGenFile($event)">
                            <i data-lucide="upload-cloud" class="w-7 h-7 text-slate-400"></i>
                            <span class="mt-1.5 text-sm font-bold text-slate-700 dark:text-slate-200" x-text="gen.fileName || 'Unggah PDF atau Word'"></span>
                            <span class="mt-0.5 text-[11px] text-slate-400">Soal disusun dari isi file. Maks. 10 MB.</span>
                        </label>
                        <div x-show="gen.file" x-cloak class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-slate-100 dark:bg-slate-800 px-3 py-2 text-xs text-slate-600 dark:text-slate-300">
                            <span class="truncate font-semibold" x-text="gen.fileName"></span>
                            <button type="button" @click="clearGenFile()" class="inline-flex items-center gap-1 font-bold text-rose-600 hover:text-rose-700">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="edu-label">Jumlah</label>
                            <input type="number" x-model.number="gen.jumlah" min="1" max="20" class="edu-input">
                        </div>
                        <div>
                            <label class="edu-label">Tingkat</label>
                            <select x-model="gen.tingkat" class="edu-input">
                                <option value="mudah">Mudah</option>
                                <option value="sedang">Sedang</option>
                                <option value="sulit">Sulit</option>
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="edu-label">Jenjang</label>
                            <input type="text" x-model="gen.jenjang" class="edu-input" placeholder="Kelas 7">
                        </div>
                    </div>

                    <div>
                        <label class="edu-label">Jenis soal <span class="text-rose-500">*</span></label>
                        <div class="grid gap-2 sm:grid-cols-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-2">
                            <template x-for="option in quizTypeOptions" :key="option.value">
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold cursor-pointer transition"
                                       :class="gen.jenis_soal.includes(option.value) ? 'border-teal-500 bg-teal-50 text-teal-800 dark:bg-teal-900/30 dark:text-teal-200' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300'">
                                    <input type="checkbox" :value="option.value" x-model="gen.jenis_soal" class="h-4 w-4 rounded border-slate-300" style="accent-color:var(--cp)">
                                    <span x-text="option.label"></span>
                                </label>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-rose-500" x-show="gen.jenis_soal.length === 0" x-cloak>Pilih minimal satu jenis soal.</p>
                        <p class="mt-1 text-[11px] text-slate-400">PG kompleks mendukung lebih dari satu kunci benar. Isian &amp; mencocokkan masuk ke builder yang sesuai.</p>
                    </div>

                    <label class="flex items-start gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2.5 cursor-pointer">
                        <input type="checkbox" x-model="gen.soal_bergambar" class="mt-0.5 h-4 w-4 rounded border-slate-300" style="accent-color:var(--cp)">
                        <span class="text-xs">
                            <span class="font-bold text-slate-700 dark:text-slate-200">Soal bergambar</span>
                            <span class="block text-slate-400 mt-0.5">Gemini Image menambahkan diagram (teks tetap diimpor ke Arena; gambar tampil di Asisten Guru/PDF).</span>
                        </span>
                    </label>

                    <button type="button" @click="generateFromAi"
                            :disabled="generating || gen.jenis_soal.length === 0 || (gen.source === 'file' ? !gen.file : !gen.topik.trim())"
                            class="arena-play-btn">
                        <i data-lucide="sparkles" class="w-4 h-4" :class="generating && 'animate-spin'"></i>
                        <span x-text="generating ? 'Menghasilkan…' : 'Generate & impor'"></span>
                    </button>
                    <p class="text-xs text-slate-400">Memakai kuota Asisten Guru. <a href="{{ route('ai.teacher.index') }}" class="font-semibold underline" style="color:var(--cp)">Buka panel lengkap</a></p>
                </div>
                @endif

                <div class="space-y-3">
                    <p class="text-xs text-slate-500">Atau tempel teks soal bernomor. Contoh: <code class="text-[11px] px-1 rounded bg-slate-100 dark:bg-slate-800">1. Ibu kota Indonesia? A. Bandung B. Jakarta *</code></p>
                    <textarea x-model="importText" rows="4" class="edu-input" placeholder="Tempel teks soal dari Asisten Guru di sini…"></textarea>
                    <button type="button" @click="runImport" :disabled="importing"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 min-h-[44px]">
                        <i data-lucide="wand-2" class="w-4 h-4"></i>
                        <span x-text="importing ? 'Memproses…' : 'Impor ke daftar soal'"></span>
                    </button>
                </div>
                <p x-show="importMsg" class="text-sm font-semibold" :class="importOk ? 'text-emerald-600' : 'text-rose-600'" x-text="importMsg"></p>
            </div>
        </details>

        {{-- Soal --}}
        <div class="space-y-4 relative z-[1]">
            <div class="edu-section-title px-1">
                <span class="edu-step">2</span>
                Quest list
            </div>

            <template x-for="(q, qi) in questions" :key="qi">
                <div class="edu-q">
                    <div class="edu-q-head">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="edu-q-num shrink-0" x-text="qi+1"></span>
                            <span class="font-bold text-slate-800 dark:text-slate-100 truncate" x-text="'Quest ' + (qi+1)"></span>
                        </div>
                        <button type="button" @click="removeQuestion(qi)" class="text-sm font-bold text-rose-600 min-h-[40px] px-2" x-show="questions.length > 1">Hapus</button>
                    </div>

                    <div class="p-4 space-y-4">
                        <div>
                            <label class="edu-label">Pertanyaan</label>
                            <textarea x-model="q.question_text" :name="'questions['+qi+'][question_text]'" rows="2" required
                                      class="edu-input" placeholder="Tuliskan pertanyaan…"></textarea>
                        </div>

                        <div>
                            <label class="edu-label">Jenis soal</label>
                            <input type="hidden" :name="'questions['+qi+'][type]'" :value="q.type">
                            <div class="edu-type">
                                <button type="button" :class="q.type==='mcq_complex' && 'is-on'" @click="q.type='mcq_complex'; onTypeChange(qi)">Pilihan Ganda Kompleks</button>
                                <button type="button" :class="q.type==='mcq' && 'is-on'" @click="q.type='mcq'; onTypeChange(qi)">Pilihan Ganda</button>
                                <button type="button" :class="q.type==='true_false' && 'is-on'" @click="q.type='true_false'; onTypeChange(qi)">Benar/Salah</button>
                                <button type="button" :class="q.type==='match' && 'is-on'" @click="q.type='match'; onTypeChange(qi)">Mencocokkan</button>
                                <button type="button" :class="q.type==='short_answer' && 'is-on'" @click="q.type='short_answer'; onTypeChange(qi)">Isian</button>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="w-28">
                                <label class="edu-label">Bobot</label>
                                <input type="number" min="1" max="100" x-model.number="q.points" :name="'questions['+qi+'][points]'" class="edu-input">
                            </div>
                            <div class="w-36">
                                <label class="edu-label">Batas waktu (detik)</label>
                                <input type="number" min="5" max="600" x-model="q.time_limit_seconds" :name="'questions['+qi+'][time_limit_seconds]'" class="edu-input" placeholder="Tak terbatas">
                            </div>
                        </div>

                        <div class="space-y-2" x-show="q.type === 'mcq' || q.type === 'mcq_complex' || q.type === 'true_false'">
                            <p class="edu-label mb-0" x-text="q.type === 'mcq_complex' ? 'Pilihan jawaban — ketuk huruf untuk menandai semua kunci benar' : 'Pilihan jawaban — ketuk huruf untuk menandai kunci'"></p>
                            <template x-for="(opt, oi) in q.options" :key="oi">
                                <div class="flex items-center gap-2">
                                    <button type="button" class="edu-letter" :class="opt.is_correct && 'is-correct'"
                                            @click="q.type === 'mcq_complex' ? toggleCorrect(qi, oi) : setCorrect(qi, oi)"
                                            x-text="['A','B','C','D','E','F'][oi]"
                                            title="Tandai sebagai jawaban benar"></button>
                                    <input type="hidden" :name="'questions['+qi+'][options]['+oi+'][is_correct]'" :value="opt.is_correct ? 1 : 0">
                                    <input type="text" x-model="opt.option_text" :name="'questions['+qi+'][options]['+oi+'][option_text]'"
                                           :required="q.type === 'mcq' || q.type === 'mcq_complex' || q.type === 'true_false'"
                                           class="edu-input flex-1"
                                           :placeholder="'Pilihan ' + ['A','B','C','D','E','F'][oi]">
                                </div>
                            </template>
                        </div>

                        <div class="space-y-2" x-show="q.type === 'short_answer'" x-cloak>
                            <p class="edu-label mb-0">Kunci jawaban (boleh lebih dari satu)</p>
                            <template x-for="(ans, ai) in q.meta.answers" :key="ai">
                                <div class="flex gap-2">
                                    <input type="text" x-model="q.meta.answers[ai]" :name="'questions['+qi+'][meta][answers]['+ai+']'"
                                           class="edu-input flex-1" placeholder="Jawaban yang diterima">
                                    <button type="button" class="text-rose-600 text-sm font-semibold px-2" @click="q.meta.answers.splice(ai,1)" x-show="q.meta.answers.length > 1">Hapus</button>
                                </div>
                            </template>
                            <button type="button" class="text-sm font-semibold" style="color:var(--cp)" @click="q.meta.answers.push('')">+ Tambah kunci</button>
                        </div>

                        <div class="space-y-2" x-show="q.type === 'match'" x-cloak>
                            <p class="edu-label mb-0">Pasangan istilah dan definisi</p>
                            <template x-for="(pair, pi) in q.meta.pairs" :key="pi">
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="pair.left" :name="'questions['+qi+'][meta][pairs]['+pi+'][left]'"
                                           class="edu-input" placeholder="Istilah">
                                    <input type="text" x-model="pair.right" :name="'questions['+qi+'][meta][pairs]['+pi+'][right]'"
                                           class="edu-input" placeholder="Definisi">
                                </div>
                            </template>
                            <button type="button" class="text-sm font-semibold" style="color:var(--cp)" @click="q.meta.pairs.push({left:'',right:''})">+ Tambah pasangan</button>
                        </div>

                        <div>
                            <label class="edu-label">Pembahasan (opsional)</label>
                            <input type="text" x-model="q.explanation" :name="'questions['+qi+'][explanation]'"
                                   class="edu-input" placeholder="Penjelasan singkat setelah menjawab">
                        </div>
                    </div>
                </div>
            </template>

            <button type="button" @click="addQuestion" class="edu-add-q">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah quest
            </button>
        </div>

        <div class="edu-sticky">
            <a href="{{ route('classroom.arena.index', $classroom) }}" class="arena-play-btn arena-play-btn-ghost edu-btn-ghost">Batal</a>
            <button type="submit" class="arena-play-btn edu-btn-primary">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan kuis
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function arenaBuilder(initial, opts = {}) {
    return {
        questions: initial,
        importText: opts.importText || '',
        importing: false,
        importMsg: '',
        importOk: true,
        generating: false,
        asistenGuruAktif: !!opts.asistenGuruAktif,
        aiQuizUrl: opts.aiQuizUrl || null,
        quizTypeOptions: [
            { value: 'pg_kompleks', label: 'Pilihan Ganda Kompleks' },
            { value: 'pg', label: 'Pilihan Ganda' },
            { value: 'benar_salah', label: 'Benar/Salah' },
            { value: 'mencocokkan', label: 'Mencocokkan' },
            { value: 'isian', label: 'Isian' },
        ],
        gen: { topik: '', jumlah: 5, jenis_soal: ['pg'], tingkat: 'sedang', jenjang: '', source: 'ai', file: null, fileName: '', soal_bergambar: false },
        init() {
            if (this.importText.trim()) {
                this.runImport();
            }
        },
        setGenFile(event) {
            const file = event.target.files?.[0] || null;
            this.gen.file = file;
            this.gen.fileName = file ? file.name : '';
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        clearGenFile() {
            this.gen.file = null;
            this.gen.fileName = '';
            if (this.$refs.arenaQuizFile) this.$refs.arenaQuizFile.value = '';
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        canGenerate() {
            if (!this.gen.jenis_soal.length) return false;
            return this.gen.source === 'file' ? !!this.gen.file : !!this.gen.topik.trim();
        },
        addQuestion() {
            this.questions.push({
                type: 'mcq',
                question_text: '',
                points: 1,
                time_limit_seconds: null,
                explanation: '',
                options: [
                    { option_text: '', is_correct: true },
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false },
                ],
                meta: {
                    answers: [''],
                    pairs: [{ left: '', right: '' }, { left: '', right: '' }],
                },
            });
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        removeQuestion(i) {
            if (this.questions.length > 1) this.questions.splice(i, 1);
        },
        setCorrect(qi, oi) {
            this.questions[qi].options.forEach((o, idx) => o.is_correct = idx === oi);
        },
        toggleCorrect(qi, oi) {
            const opt = this.questions[qi].options[oi];
            opt.is_correct = !opt.is_correct;
            if (!this.questions[qi].options.some(o => o.is_correct)) {
                opt.is_correct = true;
            }
        },
        onTypeChange(qi) {
            const q = this.questions[qi];
            if (!q.meta) q.meta = { answers: [''], pairs: [{ left: '', right: '' }, { left: '', right: '' }] };
            if (q.type === 'true_false') {
                q.options = [
                    { option_text: 'Benar', is_correct: true },
                    { option_text: 'Salah', is_correct: false },
                ];
            } else if (q.type === 'mcq' || q.type === 'mcq_complex') {
                if (q.options.length < 4) {
                    while (q.options.length < 4) q.options.push({ option_text: '', is_correct: false });
                }
                const correctCount = q.options.filter(o => o.is_correct).length;
                if (q.type === 'mcq') {
                    this.setCorrect(qi, Math.max(0, q.options.findIndex(o => o.is_correct)));
                } else if (correctCount < 2) {
                    q.options[0].is_correct = true;
                    q.options[1].is_correct = true;
                }
            } else if (q.type === 'short_answer') {
                if (!q.meta.answers?.length) q.meta.answers = [''];
            } else if (q.type === 'match') {
                if (!q.meta.pairs?.length) q.meta.pairs = [{ left: '', right: '' }, { left: '', right: '' }];
            }
        },
        prepareSubmit() {
            this.questions.forEach((q, qi) => {
                if (q.type === 'mcq' || q.type === 'true_false') {
                    const idx = q.options.findIndex(o => o.is_correct);
                    this.setCorrect(qi, idx >= 0 ? idx : 0);
                }
            });
        },
        applyImported(questions) {
            this.questions = questions.map(q => ({
                type: q.type,
                question_text: q.question_text,
                points: 1,
                time_limit_seconds: null,
                explanation: q.explanation || '',
                options: q.options || [],
                meta: {
                    answers: q.meta?.answers || [''],
                    pairs: q.meta?.pairs || [{ left: '', right: '' }, { left: '', right: '' }],
                },
            }));
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        async runImport() {
            if (!this.importText.trim()) return;
            this.importing = true;
            this.importMsg = '';
            this.importOk = true;
            try {
                const res = await fetch(@js(route('classroom.arena.import', $classroom)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ raw_text: this.importText }),
                });
                const data = await res.json();
                if (!data.ok || !data.questions.length) {
                    this.importOk = false;
                    this.importMsg = 'Tidak ada soal yang dikenali. Periksa format teks.';
                } else {
                    this.applyImported(data.questions);
                    this.importOk = true;
                    this.importMsg = data.count + ' soal berhasil diimpor. Periksa kunci jawaban sebelum menyimpan.';
                }
            } catch (e) {
                this.importOk = false;
                this.importMsg = 'Gagal mengimpor. Coba lagi.';
            } finally {
                this.importing = false;
            }
        },
        async generateFromAi() {
            if (!this.aiQuizUrl || !this.canGenerate() || this.generating) return;
            this.generating = true;
            this.importMsg = '';
            this.importOk = true;
            try {
                const form = new FormData();
                form.append('topik', this.gen.topik || '');
                form.append('jumlah', String(this.gen.jumlah || 5));
                (this.gen.jenis_soal || ['pg']).forEach((jenis) => form.append('jenis_soal[]', jenis));
                form.append('tingkat', this.gen.tingkat || 'sedang');
                form.append('jenjang', this.gen.jenjang || '');
                form.append('soal_bergambar', this.gen.soal_bergambar ? '1' : '0');
                if (this.gen.source === 'file' && this.gen.file) {
                    form.append('file', this.gen.file);
                }

                const res = await fetch(this.aiQuizUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const data = await res.json();
                if (!res.ok || !data.ok || !data.answer) {
                    this.importOk = false;
                    const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                    this.importMsg = data.message || validation || 'Gagal generate soal. Coba lagi atau buka Asisten Guru.';
                    return;
                }
                this.importText = data.answer;
                const titleEl = document.querySelector('input[name="title"]');
                if (titleEl && !titleEl.value.trim()) {
                    const fromFile = this.gen.source === 'file' && this.gen.fileName
                        ? this.gen.fileName.replace(/\.[^.]+$/, '')
                        : '';
                    titleEl.value = 'Kuis: ' + (this.gen.topik || fromFile || 'dari materi');
                }
                await this.runImport();
                if (this.importOk) {
                    const src = this.gen.source === 'file' ? 'Asisten Guru (file)' : 'Asisten Guru';
                    this.importMsg = (this.importMsg || 'Soal diimpor.') + ' Sumber: ' + src + '.';
                }
            } catch (e) {
                this.importOk = false;
                this.importMsg = 'Gagal menghubungi Asisten Guru.';
            } finally {
                this.generating = false;
            }
        },
    };
}
</script>
@endpush
