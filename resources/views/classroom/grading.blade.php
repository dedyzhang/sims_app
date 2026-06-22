@extends('layouts.app')
@section('title', 'Penilaian — ' . $assignment->title)

@php $fmt = function ($b) { $u=['B','KB','MB','GB']; $i=0; $b=(int)$b; while($b>=1024 && $i<3){ $b/=1024; $i++; } return round($b,1).' '.$u[$i]; }; @endphp

@section('content')
<div class="space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('classroom.show', $classroom) }}?tab=tugas" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">Penilaian: {{ $assignment->title }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Nilai maks {{ $assignment->max_score }} · {{ $submissions->count() }} pengumpulan</p>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>@endif

    @forelse($submissions as $s)
    <div class="card p-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:var(--cp)">{{ $s->student?->initial() ?? '?' }}</div>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $s->student?->displayName() }}</p>
                        <p class="text-[11px] text-slate-400">Dikumpulkan {{ $s->submitted_at?->locale('id')->diffForHumans() }} @if($s->is_late)<span class="text-rose-500">· terlambat</span>@endif</p>
                    </div>
                </div>
                @if($s->body)<div class="text-sm text-slate-700 dark:text-slate-200 mt-2 leading-relaxed">@include('classroom.partials.richbody', ['html' => $s->body])</div>@endif
                @if($s->files->isNotEmpty())
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach($s->files as $f)
                    <a href="{{ route('classroom.submission.file', $f) }}" class="text-xs inline-flex items-center gap-1 px-2 py-1 rounded border border-slate-200 dark:border-slate-600 hover:border-primary">
                        <i data-lucide="{{ $f->isImage() ? 'image' : 'file-text' }}" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::limit($f->original_name, 26) }} <span class="text-slate-400">({{ $fmt($f->size_compressed ?? $f->size_original) }})</span>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
            @if(in_array($s->status, ['submitted', 'graded']))
            <div class="flex items-end gap-2 flex-shrink-0">
                <form method="POST" action="{{ route('classroom.submission.grade', $s) }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="form-label text-xs">Nilai</label>
                        <input type="number" name="score" value="{{ $s->score }}" min="0" max="{{ $assignment->max_score }}" class="form-input w-24" required>
                    </div>
                    <div>
                        <label class="form-label text-xs">Feedback</label>
                        <input type="text" name="feedback" value="{{ $s->feedback }}" class="form-input w-56" placeholder="Catatan (opsional)">
                    </div>
                    <button class="px-4 py-2.5 rounded-xl text-sm font-bold text-white shadow" style="background:var(--cp)">Simpan</button>
                </form>

                <form method="POST" action="{{ route('classroom.submission.return', $s) }}" onsubmit="return confirmAction(this, 'Batalkan pengumpulan tugas dari siswa ini agar siswa dapat merevisi jawabannya?', 'orange')" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-rose-200 dark:border-rose-800 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 whitespace-nowrap transition" title="Kembalikan jawaban untuk direvisi">
                        Batalkan Jawaban
                    </button>
                </form>
            </div>
            @else
            <div class="flex items-center gap-2 flex-shrink-0">
                @if($s->status === 'draft')
                    <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300">Draf (Belum Dikumpulkan)</span>
                @elseif($s->status === 'returned')
                    <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400">Dikembalikan (Dalam Revisi)</span>
                @endif
            </div>
            @endif
        </div>
        @if($s->status==='graded')<p class="text-xs text-emerald-600 mt-2">✓ Dinilai: {{ $s->score }}/{{ $assignment->max_score }}</p>@endif
    </div>
    @empty
    <div class="card p-10 text-center text-slate-400"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-30"></i><p>Belum ada pengumpulan.</p></div>
    @endforelse
</div>
@endsection
