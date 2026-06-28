{{-- Diskusi materi/tugas. Var: $comments (initial), $storeUrl, $canManage, $commentable, $type --}}
<div x-data="classroomComments('{{ $storeUrl }}', '{{ $commentable->uuid }}', '{{ $type }}')" x-init="init()" class="card p-5">
    <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2 mb-4">
        <i data-lucide="messages-square" class="w-[18px] h-[18px]" style="color:var(--cp)"></i>
        Diskusi 
        <span class="text-sm font-normal text-slate-400" x-text="'(' + countAll() + ')'">({{ $comments->sum(fn($c) => 1 + $c->replies->count()) }})</span>
    </h3>

    {{-- Form komentar utama --}}
    <form @submit.prevent="submitComment()" class="flex gap-2 mb-5">
        <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background:var(--cp)">{{ auth()->user()->initial() }}</div>
        <input type="text" x-model="newCommentBody" required placeholder="Tulis komentar…" class="form-input flex-1">
        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold text-white flex items-center gap-1.5" style="background:var(--cp)" :disabled="loading">
            <span x-show="loading" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>Kirim</span>
        </button>
    </form>

    <div class="space-y-4">
        {{-- List komentar --}}
        <template x-for="c in comments" :key="c.uuid">
            <div x-data="{ reply:false }" :id="'c-' + c.uuid" class="border-b border-slate-100 dark:border-slate-700 pb-4 last:border-0">
                <div class="flex gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background:var(--cp)" x-text="c.user_initial"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm" x-text="c.user_name"></span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300" x-text="c.user_role"></span>
                            <span class="text-xs text-slate-400" x-text="'· ' + c.created_at"></span>
                        </div>
                        <div class="text-sm text-slate-700 dark:text-slate-200 mt-1 leading-relaxed whitespace-pre-wrap" x-text="c.body"></div>
                        <div class="flex items-center gap-3 mt-1.5 text-xs">
                            <button @click="reply=!reply; if(reply) { $nextTick(() => $refs['replyInput_' + c.uuid]?.focus()); }" class="text-slate-400 hover:text-slate-600 flex items-center gap-1">
                                <i data-lucide="reply" class="w-3.5 h-3.5"></i> Balas
                            </button>
                            <template x-if="c.can_delete">
                                <button type="button" @click="deleteComment(c.uuid)" class="text-slate-400 hover:text-rose-600">Hapus</button>
                            </template>
                        </div>

                        {{-- Form balas --}}
                        <form x-show="reply" x-cloak @submit.prevent="submitComment(c.uuid); reply=false" class="mt-2 flex gap-2">
                            <input :x-ref="'replyInput_' + c.uuid" type="text" x-model="replyBodies[c.uuid]" required placeholder="Tulis balasan…" class="form-input text-sm flex-1">
                            <button type="submit" class="px-3 py-2 rounded-lg text-sm font-semibold text-white flex items-center gap-1.5" style="background:var(--cp)" :disabled="loading">
                                <span x-show="loading" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                <span>Kirim</span>
                            </button>
                        </form>

                        {{-- Balasan (1 level) --}}
                        <template x-if="c.replies && c.replies.length > 0">
                            <div class="mt-3 pl-4 border-l-2 border-slate-100 dark:border-slate-700 space-y-3">
                                <template x-for="r in c.replies" :key="r.uuid">
                                    <div :id="'c-' + r.uuid" class="flex gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" style="background:var(--cp)" x-text="r.user_initial"></div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold text-slate-800 dark:text-slate-100 text-sm" x-text="r.user_name"></span>
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300" x-text="r.user_role"></span>
                                                <span class="text-xs text-slate-400" x-text="'· ' + r.created_at"></span>
                                            </div>
                                            <div class="text-sm text-slate-700 dark:text-slate-200 mt-0.5 leading-relaxed whitespace-pre-wrap" x-text="r.body"></div>
                                            <template x-if="r.can_delete">
                                                <button type="button" @click="deleteComment(r.uuid)" class="mt-1 text-xs text-slate-400 hover:text-rose-600">Hapus</button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
        
        <template x-if="comments.length === 0">
            <p class="text-center text-sm text-slate-400 py-4">Belum ada komentar. Mulai diskusi pertama.</p>
        </template>
    </div>
</div>

@push('scripts')
<script>
    function classroomComments(storeUrl, uuid, type) {
        return {
            comments: [],
            newCommentBody: '',
            replyBodies: {},
            loading: false,
            init() {
                this.fetchComments();
                // Polling setiap 5 detik
                setInterval(() => this.fetchComments(), 5000);
            },
            countAll() {
                let total = this.comments.length;
                this.comments.forEach(c => {
                    if (c.replies) total += c.replies.length;
                });
                return total;
            },
            async fetchComments() {
                try {
                    let url = `/ruang-kelas/comments-json/${type}/${uuid}`;
                    let classUuid = new URLSearchParams(window.location.search).get('class');
                    if (classUuid) {
                        url += `?class=${classUuid}`;
                    }
                    let response = await fetch(url);
                    if (response.ok) {
                        let data = await response.json();
                        this.comments = data.comments;
                        this.$nextTick(() => {
                            if (window.lucide) window.lucide.createIcons();
                        });
                    }
                } catch (e) {
                    console.error("Error fetching comments:", e);
                }
            },
            async submitComment(parentId = null) {
                let body = parentId ? this.replyBodies[parentId] : this.newCommentBody;
                if (!body || body.trim() === '') return;

                this.loading = true;
                showGlobalSpinner();
                try {
                    let classUuid = new URLSearchParams(window.location.search).get('class');
                    let payload = {
                        body: body,
                        parent_id: parentId
                    };
                    if (classUuid) {
                        payload.class = classUuid;
                    }
                    let response = await fetch(storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(payload)
                    });
                    if (response.ok) {
                        if (parentId) {
                            this.replyBodies[parentId] = '';
                        } else {
                            this.newCommentBody = '';
                        }
                        await this.fetchComments();
                    } else {
                        let err = await response.json();
                        $.alert({ title: 'Gagal', content: err.message || 'Gagal mengirim komentar.', type: 'red' });
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.loading = false;
                    hideGlobalSpinner();
                }
            },
            deleteComment(commentUuid) {
                $.confirm({
                    title: 'Hapus komentar ini?',
                    content: 'Komentar yang dihapus tidak dapat dikembalikan.',
                    type: 'red',
                    buttons: {
                        hapus: { text: 'Ya, Hapus', btnClass: 'btn-red', keys: ['enter'], action: async () => {
                            showGlobalSpinner();
                            try {
                                let response = await fetch(`/ruang-kelas/komentar/${commentUuid}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    }
                                });
                                if (response.ok) {
                                    await this.fetchComments();
                                }
                            } catch (e) {
                                console.error(e);
                            } finally {
                                hideGlobalSpinner();
                            }
                        } },
                        batal: { text: 'Batal' }
                    }
                });
            }
        }
    }
</script>
@endpush
