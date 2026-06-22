{{-- Kartu komentar (top-level) + balasan nested 1 level. Var: $comment, $topic, $likedComments --}}
@php
    $roleBadge = fn ($u) => '<span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 font-medium">'.e($u?->roleLabel() ?? '-').'</span>';
@endphp

<div class="border-b border-slate-100 dark:border-slate-700 py-4" id="c-{{ $comment->uuid }}" x-data="{ reply:false, edit:false }">
    <div class="flex gap-3">
        <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background:var(--cp)">{{ $comment->user?->initial() ?? '?' }}</div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm">{{ $comment->user?->displayName() }}</span>
                {!! $roleBadge($comment->user) !!}
                <span class="text-xs text-slate-400">· {{ $comment->created_at?->locale('id')->diffForHumans() }}</span>
                @if($comment->edited_at)<span class="text-xs text-slate-300 italic">· disunting</span>@endif
                @if($comment->is_best_answer)<span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-semibold flex items-center gap-1"><i data-lucide="check-circle-2" class="w-3 h-3"></i> Jawaban Terbaik</span>@endif
            </div>

            {{-- Body / form edit --}}
            <div x-show="!edit" class="text-sm text-slate-700 dark:text-slate-200 mt-1 leading-relaxed">{!! nl2br(e($comment->body)) !!}</div>
            @can('update', $comment)
            <form x-show="edit" x-cloak method="POST" action="{{ route('forum.comment.update', $comment) }}" class="mt-2">
                @csrf @method('PUT')
                <textarea name="body" rows="2" class="form-input text-sm">{{ $comment->body }}</textarea>
                <div class="flex gap-2 mt-1"><button class="text-xs font-semibold text-white px-3 py-1.5 rounded-lg" style="background:var(--cp)">Simpan</button><button type="button" @click="edit=false" class="text-xs text-slate-400">Batal</button></div>
            </form>
            @endcan

            {{-- Aksi --}}
            <div class="flex items-center gap-3 mt-2 text-xs">
                @php $liked = in_array($comment->uuid, $likedComments ?? [], true); @endphp
                <form method="POST" action="{{ route('forum.reaction.toggle') }}">
                    @csrf
                    <input type="hidden" name="target" value="comment"><input type="hidden" name="id" value="{{ $comment->uuid }}">
                    <button class="flex items-center gap-1 {{ $liked ? 'text-primary font-semibold' : 'text-slate-400 hover:text-slate-600' }}" style="{{ $liked ? 'color:var(--cp)' : '' }}"><i data-lucide="thumbs-up" class="w-3.5 h-3.5"></i> Suka <span>{{ $comment->reactions->count() }}</span></button>
                </form>
                @can('reply', $topic)
                <button @click="reply=!reply" class="flex items-center gap-1 text-slate-400 hover:text-slate-600"><i data-lucide="reply" class="w-3.5 h-3.5"></i> Balas</button>
                @endcan
                @can('markBestAnswer', $topic)
                    @unless($comment->is_best_answer)
                    <form method="POST" action="{{ route('forum.comment.best', $comment) }}"><button class="flex items-center gap-1 text-slate-400 hover:text-emerald-600">@csrf<i data-lucide="award" class="w-3.5 h-3.5"></i> Jawaban Terbaik</button></form>
                    @endunless
                @endcan
                @can('update', $comment)<button @click="edit=!edit" class="text-slate-400 hover:text-slate-600">Sunting</button>@endcan
                @can('delete', $comment)
                <form method="POST" action="{{ route('forum.comment.destroy', $comment) }}" onsubmit="return confirmDelete(this)">@csrf @method('DELETE')<button class="text-slate-400 hover:text-rose-600">Hapus</button></form>
                @endcan
            </div>

            {{-- Form balas --}}
            @can('reply', $topic)
            <form x-show="reply" x-cloak method="POST" action="{{ route('forum.comment.store', $topic) }}" class="mt-2 flex gap-2">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->uuid }}">
                <input type="text" name="body" required placeholder="Tulis balasan…" class="form-input text-sm flex-1">
                <button class="text-sm font-semibold text-white px-3 py-2 rounded-lg" style="background:var(--cp)">Kirim</button>
            </form>
            @endcan

            {{-- Balasan nested (1 level) --}}
            @if($comment->replies->isNotEmpty())
            <div class="mt-3 pl-4 border-l-2 border-slate-100 dark:border-slate-700 space-y-3" x-data="{ showAll:false }">
                @foreach($comment->replies as $i => $r)
                <div class="{{ $i >= 3 ? '' : '' }}" @if($i >= 3) x-show="showAll" x-cloak @endif id="c-{{ $r->uuid }}">
                    <div class="flex gap-2">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" style="background:var(--cp)">{{ $r->user?->initial() ?? '?' }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm">{{ $r->user?->displayName() }}</span>
                                {!! $roleBadge($r->user) !!}
                                <span class="text-xs text-slate-400">· {{ $r->created_at?->locale('id')->diffForHumans() }}</span>
                            </div>
                            <div class="text-sm text-slate-700 dark:text-slate-200 mt-0.5 leading-relaxed">{!! nl2br(e($r->body)) !!}</div>
                            <div class="flex items-center gap-3 mt-1 text-xs">
                                @php $rliked = in_array($r->uuid, $likedComments ?? [], true); @endphp
                                <form method="POST" action="{{ route('forum.reaction.toggle') }}">@csrf<input type="hidden" name="target" value="comment"><input type="hidden" name="id" value="{{ $r->uuid }}"><button class="flex items-center gap-1 {{ $rliked ? 'font-semibold' : 'text-slate-400 hover:text-slate-600' }}" style="{{ $rliked ? 'color:var(--cp)' : '' }}"><i data-lucide="thumbs-up" class="w-3 h-3"></i> Suka <span>{{ $r->reactions->count() }}</span></button></form>
                                @can('delete', $r)<form method="POST" action="{{ route('forum.comment.destroy', $r) }}" onsubmit="return confirmDelete(this)">@csrf @method('DELETE')<button class="text-slate-400 hover:text-rose-600">Hapus</button></form>@endcan
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
                @if($comment->replies->count() > 3)
                <button @click="showAll=!showAll" class="text-xs font-semibold ml-9" style="color:var(--cp)"><span x-show="!showAll">Lihat {{ $comment->replies->count() - 3 }} balasan lainnya</span><span x-show="showAll" x-cloak>Sembunyikan balasan</span></button>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
