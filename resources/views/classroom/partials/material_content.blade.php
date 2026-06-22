{{-- Isi materi (dipakai di tampilan normal & mode terkunci). Var: $material, $canManage --}}
@php $fmt = fn ($b) => ($b = (int) $b) >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B'); @endphp
<div class="flex items-start gap-3">
    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style="background:color-mix(in srgb, var(--cp) 14%, transparent)"><i data-lucide="book-open" class="w-6 h-6" style="color:var(--cp)"></i></div>
    <div class="min-w-0 flex-1">
        <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">{{ $material->title }} @if($material->is_locked)<i data-lucide="lock" class="w-4 h-4 text-amber-500"></i>@endif</h1>
        <p class="text-xs text-slate-400 mt-0.5">{{ $material->uploader?->displayName() }} · {{ $material->created_at?->locale('id')->diffForHumans() }}</p>
    </div>
    @if($canManage)
    <div class="flex items-center gap-1 flex-shrink-0">
        <a href="{{ route('classroom.material.edit', $material) }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-400 hover:text-primary"><i data-lucide="pencil" class="w-4 h-4"></i></a>
        <form method="POST" action="{{ route('classroom.material.destroy', $material) }}" onsubmit="return confirmDelete(this)">@csrf @method('DELETE')<button class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-400 hover:text-rose-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
    </div>
    @endif
</div>

@if($material->classrooms->count() > 1 && $canManage)
<div class="flex flex-wrap items-center gap-1.5 mt-3 text-xs text-slate-400">
    <i data-lucide="link" class="w-3.5 h-3.5"></i> Tampil di:
    @foreach($material->classrooms as $rc)<span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $rc->rombel?->tingkat }}{{ $rc->rombel?->kelas }}</span>@endforeach
</div>
@endif

@if($material->meet_url)
<div class="flex flex-wrap items-center gap-2 mt-4">
    <a href="{{ $material->meet_url }}" target="_blank" rel="noopener" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-bold text-white shadow-sm hover:opacity-90 transition" style="background:#16a34a">
        <i data-lucide="video" class="w-4 h-4"></i> Gabung Google Meet
        <span class="text-[11px] font-normal opacity-80 hidden sm:inline">· {{ \Illuminate\Support\Str::after($material->meet_url, 'meet.google.com/') }}</span>
    </a>
    @if($canManage)
    <form method="POST" action="{{ route('classroom.material.closemeet', $material) }}" onsubmit="return confirmAction(this, 'Tutup kelas online? Link Google Meet akan dihapus dari materi ini.', 'orange')">@csrf
        <button class="inline-flex items-center gap-1.5 px-4 py-3 rounded-xl text-sm font-semibold border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-700 dark:hover:bg-rose-900/30"><i data-lucide="video-off" class="w-4 h-4"></i> Tutup Google Meet</button>
    </form>
    @endif
</div>
@endif

@if($material->description)<p class="text-slate-600 dark:text-slate-300 mt-3">{{ $material->description }}</p>@endif
@if($material->body)<div class="mt-3">@include('classroom.partials.richbody', ['html' => $material->body])</div>@endif
@if($material->link_url)<a href="{{ $material->link_url }}" target="_blank" class="text-sm inline-flex items-center gap-1 mt-3" style="color:var(--cp)"><i data-lucide="link" class="w-4 h-4"></i> {{ \Illuminate\Support\Str::limit($material->link_url, 60) }}</a>@endif

@if($material->files->isNotEmpty())
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-4">
    @foreach($material->files as $f)
    <a href="{{ route('classroom.material.file', $f) }}" class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 dark:border-slate-600 hover:border-primary text-sm">
        <i data-lucide="{{ $f->isImage() ? 'image' : 'file-text' }}" class="w-4 h-4 text-slate-400 flex-shrink-0"></i>
        <span class="truncate flex-1">{{ $f->original_name }}</span>
        <span class="text-[11px] text-slate-400">{{ $fmt($f->size_compressed ?? $f->size_original) }}</span>
        <i data-lucide="download" class="w-3.5 h-3.5 text-slate-400"></i>
    </a>
    @endforeach
</div>
@endif
