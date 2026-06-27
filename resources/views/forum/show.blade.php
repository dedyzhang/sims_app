@extends('layouts.app')
@section('title', 'Forum: ' . $topic->title)

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    #forum-toast { position: fixed; top: 78px; right: 18px; z-index: 60; }
</style>
@endpush

@php
    $catColor = [
        'akademik' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'kesiswaan' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'sarpras' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'umum' => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        'pengumuman' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    ];
@endphp

@section('content')
<div class="space-y-4" x-data="{ rightOpen:false }">
    {{-- Breadcrumb --}}
    <nav class="text-xs text-slate-400 flex items-center gap-1 flex-wrap">
        <a href="{{ route('forum.index') }}" class="hover:text-primary">Forum</a>
        @if($topic->pelajaran)<span>/</span><span>{{ $topic->pelajaran->nama }}</span>@endif
        @if($topic->kelas)<span>/</span><span>Kelas {{ $topic->kelas->tingkat }}{{ $topic->kelas->kelas }}</span>@endif
        <span>/</span><span class="text-slate-500 dark:text-slate-300 truncate max-w-[40ch]">{{ $topic->title }}</span>
    </nav>

    <div class="flex items-center justify-between gap-2">
        <h1 class="text-lg font-bold text-slate-800 dark:text-slate-100">Forum Diskusi Kelas: {{ $topic->pelajaran?->nama ?? ($topic->kelas ? 'Kelas '.$topic->kelas->tingkat.$topic->kelas->kelas : 'Umum') }} | {{ $topic->title }}</h1>
        <button @click="rightOpen=!rightOpen" class="lg:hidden p-2 rounded-lg border border-slate-200 dark:border-slate-600"><i data-lucide="panel-right" class="w-4 h-4"></i></button>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- TENGAH: thread + komentar --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Thread utama --}}
            <div class="card p-5">
                <div class="flex items-start gap-3">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0" style="background:var(--cp)">{{ $topic->author?->initial() ?? '?' }}</div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ $topic->author?->displayName() }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 font-medium">{{ $topic->author?->roleLabel() }}</span>
                            <span class="text-xs text-slate-400">· {{ $topic->created_at?->locale('id')->diffForHumans() }}</span>
                            <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold {{ $catColor[$topic->category] ?? '' }}">{{ \App\Support\Forum::CATEGORIES[$topic->category] ?? $topic->category }}</span>
                            @if($topic->is_pinned)<span class="text-[11px] text-rose-500 flex items-center gap-1"><i data-lucide="pin" class="w-3 h-3"></i> Disematkan</span>@endif
                            @if($topic->is_locked)<span class="text-[11px] text-slate-400 flex items-center gap-1"><i data-lucide="lock" class="w-3 h-3"></i> Dikunci</span>@endif
                        </div>
                    </div>
                    {{-- Moderasi --}}
                    <div class="flex items-center gap-1 flex-shrink-0">
                        @can('moderate', $topic)
                        <form method="POST" action="{{ route('forum.pin', $topic) }}"><button class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400" title="Pin">@csrf<i data-lucide="pin" class="w-4 h-4 {{ $topic->is_pinned ? 'text-rose-500' : '' }}"></i></button></form>
                        <form method="POST" action="{{ route('forum.lock', $topic) }}"><button class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400" title="Lock">@csrf<i data-lucide="{{ $topic->is_locked ? 'lock' : 'unlock' }}" class="w-4 h-4"></i></button></form>
                        @endcan
                        @can('update', $topic)<a href="{{ route('forum.edit', $topic) }}" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400" title="Sunting"><i data-lucide="pencil" class="w-4 h-4"></i></a>@endcan
                        @can('delete', $topic)<form method="POST" action="{{ route('forum.destroy', $topic) }}" onsubmit="return confirmDelete(this)">@csrf @method('DELETE')<button class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400 hover:text-rose-600" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>@endcan
                    </div>
                </div>
                <div class="text-slate-700 dark:text-slate-200 mt-3 leading-relaxed">{!! nl2br(e($topic->body)) !!}</div>
                <div class="flex items-center gap-4 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700 text-sm">
                    <form method="POST" action="{{ route('forum.reaction.toggle') }}">
                        @csrf<input type="hidden" name="target" value="topic"><input type="hidden" name="id" value="{{ $topic->uuid }}">
                        <button class="flex items-center gap-1.5 {{ $likedTopic ? 'font-semibold' : 'text-slate-400 hover:text-slate-600' }}" style="{{ $likedTopic ? 'color:var(--cp)' : '' }}"><i data-lucide="thumbs-up" class="w-4 h-4"></i> Suka <span>{{ $topic->reactions_count }}</span></button>
                    </form>
                    <span class="flex items-center gap-1.5 text-slate-400"><i data-lucide="message-circle" class="w-4 h-4"></i> {{ $topic->replies_count }} balasan</span>
                </div>
            </div>

            {{-- Form kirim komentar --}}
            @can('reply', $topic)
            <form method="POST" action="{{ route('forum.comment.store', $topic) }}" class="card p-4">
                @csrf
                <label class="form-label">Kirim Komentar</label>
                <textarea name="body" rows="3" required class="form-input @error('body') border-rose-400 @enderror" placeholder="Tulis komentar Anda…">{{ old('body') }}</textarea>
                @error('body')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                <div class="flex justify-end mt-2"><button class="px-5 py-2 rounded-xl text-sm font-bold text-white" style="background:var(--cp)">Kirim</button></div>
            </form>
            @elseif($topic->is_locked)
            <div class="card p-4 text-center text-sm text-slate-400"><i data-lucide="lock" class="w-4 h-4 inline"></i> Topik dikunci — komentar dinonaktifkan.</div>
            @endif

            {{-- Daftar komentar --}}
            <div class="card px-5 py-1" id="forum-comments-container">
                @forelse($topic->comments as $comment)
                    @include('forum.partials.comment', ['comment' => $comment, 'topic' => $topic, 'likedComments' => $likedComments])
                @empty
                    <p class="py-8 text-center text-sm text-slate-400">Belum ada komentar. Jadilah yang pertama berdiskusi.</p>
                @endforelse
            </div>
        </div>

        {{-- KANAN: peserta aktif + topik populer (collapsible di mobile) --}}
        <div class="space-y-4 lg:!block" :class="{ 'hidden': !rightOpen }">
            <div class="card p-4">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 mb-3">
                    <i data-lucide="users" class="w-[18px] h-[18px]" style="color:var(--cp)"></i> Peserta Aktif
                    <span class="text-xs font-normal text-slate-400">(<span id="peserta-count">{{ collect($participants)->filter->isOnline()->count() }}</span> online)</span>
                </h3>
                <div id="peserta-list" class="space-y-3">
                    @foreach($participants as $p)
                        @include('forum.partials.participant', ['p' => $p])
                    @endforeach
                </div>
            </div>

            <div class="card p-4">
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 mb-3"><i data-lucide="flame" class="w-[18px] h-[18px] text-rose-500"></i> Topik Populer</h3>
                <div class="space-y-3">
                    @forelse($popular as $p)
                    <a href="{{ route('forum.show', $p) }}" class="flex items-start gap-2 group">
                        <i data-lucide="message-square" class="w-4 h-4 text-slate-300 mt-0.5 flex-shrink-0"></i>
                        <div class="min-w-0"><p class="text-sm font-medium text-slate-700 dark:text-slate-200 group-hover:text-primary truncate">{{ $p->title }}</p><p class="text-xs text-slate-400">{{ $p->replies_count }} balasan</p></div>
                    </a>
                    @empty<p class="text-sm text-slate-400">Belum ada.</p>@endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Toast balasan baru --}}
<div id="forum-toast" class="hidden">
    <a href="{{ route('forum.show', $topic) }}" class="flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-white text-sm font-semibold" style="background:var(--cp)">
        <i data-lucide="bell" class="w-4 h-4"></i> <span id="forum-toast-text">balasan baru</span>
    </a>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const url = "{{ route('forum.presence', $topic) }}";
    const list = document.getElementById('peserta-list');
    const countEl = document.getElementById('peserta-count');
    const toast = document.getElementById('forum-toast');
    const toastText = document.getElementById('forum-toast-text');
    let baseReplies = {{ (int) $topic->replies_count }};

    const dotClass = { online: 'bg-emerald-500', recent: 'bg-amber-400', offline: 'bg-slate-300' };
    const esc = (s) => (s || '').replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]));

    function render(participants) {
        if (!list) return;
        list.innerHTML = participants.map(p => `
            <div class="flex items-center gap-2.5">
              <div class="relative flex-shrink-0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background:var(--cp)">${esc(p.inisial)}</div>
                <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white ${dotClass[p.status] || 'bg-slate-300'}"></span>
              </div>
              <div class="min-w-0"><p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">${esc(p.nama)}</p>
              <p class="text-[11px] text-slate-400">${esc(p.role)} · ${esc(p.label)}</p></div>
            </div>`).join('');
    }

    async function poll() {
        if (document.hidden) return;
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            render(data.participants || []);
            if (countEl) countEl.textContent = data.online ?? 0;
            if (typeof data.replies === 'number' && data.replies > baseReplies) {
                const n = data.replies - baseReplies;
                toastText.textContent = n + ' balasan baru';
                toast.classList.remove('hidden');
            }
            if (window.lucide) lucide.createIcons();
        } catch (e) { /* diam */ }
    }

    setInterval(poll, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    // Realtime Forum Comments Polling & AJAX Submissions
    async function refreshForumComments() {
        try {
            const container = document.getElementById('forum-comments-container');
            if (!container) return;

            // Jangan refresh jika user sedang mengetik (ada input/textarea yang aktif/focus)
            if (container.querySelector('input:focus, textarea:focus')) {
                return;
            }

            const res = await fetch('{{ route('forum.comment.html', $topic) }}');
            if (res.ok) {
                const html = await res.text();
                container.innerHTML = html || '<p class="py-8 text-center text-sm text-slate-400">Belum ada komentar. Jadilah yang pertama berdiskusi.</p>';
                if (window.Alpine) {
                    window.Alpine.initTree(container);
                }
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            }
        } catch (e) {
            console.error("Error refreshing comments:", e);
        }
    }

    // Jalankan polling setiap 5 detik
    setInterval(refreshForumComments, 5000);

    // Intersepsi submit form untuk kirim komentar & balasan agar AJAX + spinner
    document.addEventListener('submit', async function(e) {
        const form = e.target;
        
        // Kita hanya intersepsi form kirim/balas komentar di forum topik ini
        const isCommentStore = form.action && form.action.includes('/komentar') && form.method.toUpperCase() === 'POST' && !form.querySelector('input[name="_method"]');
        if (!isCommentStore) return;

        e.preventDefault();
        showGlobalSpinner();

        try {
            const formData = new FormData(form);
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            });

            if (res.ok) {
                // Reset input teks
                const input = form.querySelector('input[type="text"], textarea');
                if (input) input.value = '';

                await refreshForumComments();
            } else {
                const err = await res.json();
                $.alert({ title: 'Gagal', content: err.message || 'Gagal mengirim komentar.', type: 'red' });
            }
        } catch (err) {
            console.error(err);
        } finally {
            hideGlobalSpinner();
        }
    });
})();
</script>
@endpush
