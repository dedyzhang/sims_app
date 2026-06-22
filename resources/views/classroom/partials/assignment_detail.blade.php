{{-- Detail tugas (header + instruksi + lampiran). Var: $assignment, $canManage --}}
<div class="flex items-start gap-3">
    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style="background:color-mix(in srgb, var(--cp) 14%, transparent)"><i data-lucide="clipboard-list" class="w-6 h-6" style="color:var(--cp)"></i></div>
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[11px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 capitalize">{{ $assignment->type }}</span>
            @if($assignment->status==='draft')<span class="text-[11px] px-2 py-0.5 rounded bg-amber-100 text-amber-700">Draf</span>@endif
            @if($assignment->is_locked)<span class="text-[11px] px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300 flex items-center gap-1 font-semibold"><i data-lucide="lock" class="w-3.5 h-3.5"></i> Terkunci</span>@endif
            @if($canManage && $assignment->hide_scores)<span class="text-[11px] px-2 py-0.5 rounded bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 flex items-center gap-1 font-semibold"><i data-lucide="eye-off" class="w-3.5 h-3.5"></i> Nilai Rahasia</span>@endif
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $assignment->title }}</h1>
        </div>
        <p class="text-xs text-slate-400 mt-1">Nilai maks {{ $assignment->max_score }}@if($assignment->due_at) · Batas {{ $assignment->due_at->locale('id')->translatedFormat('d M Y H:i') }}@endif @if($assignment->allow_late) · boleh terlambat @endif</p>
    </div>
    @if($canManage)
    <div class="flex items-center gap-1 flex-shrink-0">
        <a href="{{ route('classroom.assignment.grading', [$assignment, 'class' => $classroom->uuid]) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary whitespace-nowrap">Penilaian</a>
        <a href="{{ route('classroom.assignment.edit', $assignment) }}" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-400 hover:text-primary"><i data-lucide="pencil" class="w-4 h-4"></i></a>
    </div>
    @endif
</div>

@if($assignment->instructions)<div class="mt-3">@include('classroom.partials.richbody', ['html' => $assignment->instructions])</div>@endif

@if($assignment->files->isNotEmpty())
<div class="flex flex-wrap gap-2 mt-3">
    @foreach($assignment->files as $f)
    <a href="{{ route('classroom.assignment.file', $f) }}" class="text-xs inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary"><i data-lucide="paperclip" class="w-3 h-3"></i> {{ \Illuminate\Support\Str::limit($f->original_name, 28) }}</a>
    @endforeach
</div>
@endif
