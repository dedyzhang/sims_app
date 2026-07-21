@extends('layouts.app')
@section('title', ($mission ? 'Edit' : 'Buat') . ' Misi')

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@php
    $currentJenjang = old('jenjang', $mission?->jenjangKey() ?? 'smp');
    if (! in_array($currentJenjang, ['sd', 'smp', 'sma', 'umum'], true)) {
        $currentJenjang = 'smp';
    }
    $gradeRaw = (string) old('grade_detail', $mission?->grade_level ?? '');
    $gradeDetail = trim(preg_replace('/^(SD|SMP|SMA\/SMK|Umum)\s*/iu', '', $gradeRaw) ?? $gradeRaw);
    if (strcasecmp($gradeDetail, $gradeRaw) === 0 && in_array(mb_strtolower($gradeRaw), ['sd', 'smp', 'sma/smk', 'sma', 'smk', 'umum'], true)) {
        $gradeDetail = '';
    }
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-4 arena-stage">
    <a href="{{ route('jagat-misi.builder.index') }}" class="arena-hud-back">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
        <span>Kelola katalog</span>
    </a>
    <div>
        <p class="arena-eyebrow" style="color:var(--arena-teal)">Arena Belajar · Metadata misi</p>
        <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ $mission ? 'Edit metadata misi' : 'Buat metadata misi' }}</h1>
        <p class="text-sm text-slate-500 mt-1">Form ini menyimpan judul/mapel/mekanik. Tanpa langkah permainan, misi belum bisa diterbitkan untuk siswa.</p>
    </div>

    <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
        <strong>Catatan:</strong> Editor langkah belum tersedia. Untuk kelas, tugaskan misi berlabel <em>Siap main</em> dari katalog.
        Ini berbeda dari <strong>Nalar Guru</strong> (chat AI di Asisten Guru).
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm">
        <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="post" action="{{ $mission ? route('jagat-misi.builder.update', $mission) : route('jagat-misi.builder.store') }}" class="space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 bg-white dark:bg-slate-900">
        @csrf

        {{-- Jenjang pendidikan --}}
        <div class="rounded-2xl border-2 border-slate-100 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/40 p-4 space-y-3">
            <div>
                <label class="text-xs font-bold uppercase tracking-wide text-slate-500">Jenjang pendidikan <span class="text-rose-500">*</span></label>
                <p class="text-xs text-slate-400 mt-0.5">Pilih jenjang target misi — tampil sebagai badge di katalog &amp; Arena.</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" x-data="{ jenjang: @js($currentJenjang) }">
                @foreach([
                    'sd' => ['SD', 'Kelas 1–6'],
                    'smp' => ['SMP', 'Kelas 7–9'],
                    'sma' => ['SMA/SMK', 'Kelas 10–12'],
                    'umum' => ['Umum', 'Semua jenjang'],
                ] as $key => [$label, $hint])
                <label class="cursor-pointer rounded-xl border-2 px-3 py-3 text-center transition"
                       :class="jenjang === '{{ $key }}' ? 'border-teal-500 bg-teal-50 dark:bg-teal-900/30' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900'">
                    <input type="radio" name="jenjang" value="{{ $key }}" class="sr-only" x-model="jenjang" @checked($currentJenjang === $key) required>
                    <span class="block text-sm font-black text-slate-800 dark:text-slate-100">{{ $label }}</span>
                    <span class="block text-[11px] text-slate-500 mt-0.5">{{ $hint }}</span>
                </label>
                @endforeach
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500">Detail kelas (opsional)</label>
                <input name="grade_detail" value="{{ $gradeDetail }}" placeholder="mis. Kelas 7–8, Fase E"
                       class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900">
                <p class="text-[11px] text-slate-400 mt-1">Akan digabung dengan jenjang, contoh: <em>SMP Kelas 7–8</em></p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="text-xs font-bold text-slate-500">Judul misi</label>
                <input name="title" value="{{ old('title', $mission?->title) }}" placeholder="Judul misi" class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900" required>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500">Mata pelajaran</label>
                <input name="subject" value="{{ old('subject', $mission?->subject) }}" placeholder="Mapel" class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900" required>
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500">Durasi (menit)</label>
                <input name="duration_minutes" type="number" value="{{ old('duration_minutes', $mission?->duration_minutes ?? 30) }}" class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900" required>
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-bold text-slate-500">Mekanik</label>
                <select name="mechanic_type" class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900" required>
                    @php $mech = old('mechanic_type', $mission?->mechanic_type ?? 'recall_quiz_bundle'); @endphp
                    @foreach(\App\Support\ArenaMechanics::labels() as $val => $lab)
                    <option value="{{ $val }}" @selected($mech === $val)>{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="text-xs font-bold text-slate-500">Ringkasan</label>
            <textarea name="summary" rows="3" class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900" placeholder="Ringkasan" required>{{ old('summary', $mission?->summary) }}</textarea>
        </div>
        <div>
            <label class="text-xs font-bold text-slate-500">Prompt refleksi</label>
            <textarea name="reflections[]" rows="2" class="mt-1 w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm bg-white dark:bg-slate-900" placeholder="Prompt refleksi 1">{{ $mission?->reflectionPrompts->first()?->prompt_text }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="requires_reflection" value="1" {{ old('requires_reflection', $mission?->requires_reflection ?? true) ? 'checked' : '' }}> Wajib refleksi</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="visible_to_teachers" value="1" {{ old('visible_to_teachers', $mission?->visible_to_teachers) ? 'checked' : '' }}> Bagikan ke guru lain</label>
        <div class="flex flex-wrap items-center gap-3 pt-1">
            <button type="submit" class="arena-cta">Simpan</button>
            @if($mission && $mission->isPlayable())
            <a href="{{ route('jagat-misi.builder.publish', $mission) }}" onclick="event.preventDefault(); document.getElementById('publishForm').submit();" class="text-sm font-bold" style="color:var(--arena-teal)">Terbitkan</a>
            @elseif($mission)
            <span class="text-xs text-amber-700 dark:text-amber-300 font-semibold">Belum bisa diterbitkan (belum ada langkah permainan).</span>
            @endif
        </div>
    </form>
    @if($mission && $mission->isPlayable())
    <form id="publishForm" method="post" action="{{ route('jagat-misi.builder.publish', $mission) }}">@csrf</form>
    @endif
</div>
@endsection
