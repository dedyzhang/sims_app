@if(($audioLinks ?? collect())->isNotEmpty())
<section class="mt-4 rounded-2xl border border-violet-200 bg-violet-50/70 dark:border-violet-800 dark:bg-violet-950/20 p-4 space-y-3">
    <div class="flex items-center gap-2 text-violet-700 dark:text-violet-300">
        <i data-lucide="volume-2" class="w-4 h-4"></i><strong class="text-sm">Media suara pembelajaran</strong>
    </div>
    @foreach($audioLinks as $audioLink)
        @if($audioLink->audio?->isReady())
        <div class="rounded-xl bg-white/80 dark:bg-slate-900/60 p-3 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $audioLink->audio->title }}</span>
                <a href="{{ route('ai.teacher.audio.download', $audioLink->audio) }}" class="text-xs font-semibold text-violet-700 dark:text-violet-300">Unduh</a>
            </div>
            <audio controls preload="none" class="w-full" src="{{ route('ai.teacher.audio.stream', $audioLink->audio) }}">
                Browser Anda belum mendukung pemutar audio.
            </audio>
        </div>
        @endif
    @endforeach
</section>
@endif