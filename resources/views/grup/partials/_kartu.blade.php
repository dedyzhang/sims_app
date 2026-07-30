{{-- Satu baris grup di halaman daftar. Semua data berasal dari kolom denormal
     grup_chats.last_message_* — tabel pesan tidak disentuh sama sekali. --}}
<a href="{{ route('grup.show', $g->uuid) }}"
   class="flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">

    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-white font-bold text-sm"
         style="background: {{ $g->is_paguyuban ? '#0d9488' : 'var(--cp)' }}">
        <i data-lucide="{{ $g->is_paguyuban ? 'users-round' : 'graduation-cap' }}" class="w-5 h-5"></i>
    </div>

    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
            <span class="font-semibold text-sm text-slate-800 dark:text-slate-100 truncate">{{ $g->nama }}</span>

            @if($g->mode === 'pengumuman')
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 flex-shrink-0">
                Pengumuman
            </span>
            @endif

            @if($g->status === 'arsip')
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400 flex-shrink-0">
                Arsip
            </span>
            @endif
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5">
            @if($g->last_message_preview)
                <span class="font-medium">{{ $g->last_message_by }}:</span> {{ $g->last_message_preview }}
            @else
                <span class="italic">Belum ada pesan</span>
            @endif
        </p>
    </div>

    <div class="flex flex-col items-end gap-1 flex-shrink-0">
        @if($g->last_message_at)
        <span class="text-[11px] text-slate-400">
            {{ \Illuminate\Support\Carbon::parse($g->last_message_at)->locale('id')->diffForHumans() }}
        </span>
        @endif

        @if($g->unread > 0)
        <span class="min-w-5 h-5 px-1.5 rounded-full bg-rose-500 text-white text-[11px] font-bold flex items-center justify-center">
            {{ $g->unread > 99 ? '99+' : $g->unread }}
        </span>
        @endif
    </div>
</a>
