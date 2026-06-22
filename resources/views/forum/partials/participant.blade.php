{{-- Item peserta aktif. Var: $p (User) --}}
@php
    $dot = ['online' => 'bg-emerald-500', 'recent' => 'bg-amber-400', 'offline' => 'bg-slate-300'][$p->presenceStatus()];
@endphp
<div class="flex items-center gap-2.5" data-uid="{{ $p->uuid }}">
    <div class="relative flex-shrink-0">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:var(--cp)">{{ $p->initial() }}</div>
        <span class="presence-dot absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800 {{ $dot }}"></span>
    </div>
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">{{ $p->displayName() }}</p>
        <p class="presence-label text-[11px] text-slate-400">{{ $p->roleLabel() }} · {{ $p->presenceLabel() }}</p>
    </div>
</div>
