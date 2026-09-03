@extends('layouts.app')
@section('title', 'Pesan Langsung')

@section('content')
<div class="max-w-3xl mx-auto h-[calc(100vh-11rem)] min-h-[400px] flex flex-col bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200/70 dark:border-slate-800"
     x-data="privateChat({
         pollUrl: @js(route('private-chat.poll', $conversation)),
         sendUrl: @js(route('private-chat.send', $conversation)),
         meId: @js(auth()->id()),
         awal: @js($messages),
         lastSeq: {{ $lastSeq }},
     })"
     x-init="init()">
    <div class="px-5 py-4 flex items-center gap-3 border-b border-slate-200 dark:border-slate-800">
        <a href="{{ url()->previous() }}" class="p-2 -ml-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800" title="Kembali">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div class="w-10 h-10 rounded-full grid place-items-center text-white font-bold bg-gradient-to-br from-indigo-500 to-purple-500">
            {{ strtoupper(mb_substr($other?->displayName() ?? 'A', 0, 1)) }}
        </div>
        <div class="min-w-0">
            <h1 class="font-extrabold text-slate-800 dark:text-slate-100 truncate">{{ $other?->displayName() ?? 'Pengguna' }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pesan langsung</p>
        </div>
    </div>

    <div x-ref="scroll" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50 dark:bg-slate-950/40">
        <template x-if="!messages.length">
            <p class="text-center text-sm text-slate-400 py-10">Belum ada pesan. Mulai percakapan.</p>
        </template>
        <template x-for="m in messages" :key="m.uuid">
            <div class="flex" :class="m.sender_id === meId ? 'justify-end' : 'justify-start'">
                <div class="max-w-[85%] rounded-2xl px-4 py-2.5 shadow-sm"
                     :class="m.sender_id === meId ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-bl-sm'">
                    <p class="text-sm whitespace-pre-wrap break-words" x-text="m.body"></p>
                    <p class="text-[10px] mt-1 text-right" :class="m.sender_id === meId ? 'text-white/70' : 'text-slate-400'" x-text="m.jam"></p>
                </div>
            </div>
        </template>
    </div>

    <form @submit.prevent="send()" class="p-3 border-t border-slate-200 dark:border-slate-800 flex items-end gap-2 relative">
        <div x-show="emojiOpen" x-cloak @click.away="emojiOpen = false"
             class="absolute bottom-16 right-16 z-30 p-2 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl grid grid-cols-6 gap-1">
            <span class="col-span-6 text-[11px] text-slate-400 px-1 pb-1">Pilih emoji</span>
            <button type="button" @click="tambahEmoji('😊')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😊</button>
            <button type="button" @click="tambahEmoji('👍')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">👍</button>
            <button type="button" @click="tambahEmoji('❤️')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">❤️</button>
            <button type="button" @click="tambahEmoji('🙏')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🙏</button>
            <button type="button" @click="tambahEmoji('😂')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😂</button>
            <button type="button" @click="tambahEmoji('🎉')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🎉</button>
            <button type="button" @click="tambahEmoji('😍')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😍</button>
            <button type="button" @click="tambahEmoji('😢')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😢</button>
            <button type="button" @click="tambahEmoji('😮')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😮</button>
            <button type="button" @click="tambahEmoji('🙌')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🙌</button>
            <button type="button" @click="tambahEmoji('😄')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😄</button>
            <button type="button" @click="tambahEmoji('😎')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😎</button>
            <button type="button" @click="tambahEmoji('🤩')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🤩</button>
            <button type="button" @click="tambahEmoji('🤣')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🤣</button>
            <button type="button" @click="tambahEmoji('😉')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😉</button>
            <button type="button" @click="tambahEmoji('😅')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😅</button>
            <button type="button" @click="tambahEmoji('😇')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😇</button>
            <button type="button" @click="tambahEmoji('🤔')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🤔</button>
            <button type="button" @click="tambahEmoji('😴')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😴</button>
            <button type="button" @click="tambahEmoji('😡')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😡</button>
            <button type="button" @click="tambahEmoji('😭')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">😭</button>
            <button type="button" @click="tambahEmoji('💪')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">💪</button>
            <button type="button" @click="tambahEmoji('👏')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">👏</button>
            <button type="button" @click="tambahEmoji('👌')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">👌</button>
            <button type="button" @click="tambahEmoji('✅')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">✅</button>
            <button type="button" @click="tambahEmoji('⭐')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">⭐</button>
            <button type="button" @click="tambahEmoji('🔥')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🔥</button>
            <button type="button" @click="tambahEmoji('💯')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">💯</button>
            <button type="button" @click="tambahEmoji('🎓')" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-lg">🎓</button>
            <button type="button" @click="send('👍')" :disabled="sending" class="col-span-2 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-sm font-bold hover:bg-amber-200 disabled:opacity-50" title="Kirim jempol cepat">👍 Sip</button>
        </div>
        <div class="flex items-end gap-2 flex-1 min-w-0">
            <textarea x-ref="input" x-model="draft" rows="1" maxlength="{{ \App\Services\PrivateChatService::MAX_BODY }}"
                      @keydown.enter.exact.prevent="send()" placeholder="Ketik pesan langsung atau pilih emoji..."
                      class="form-input flex-1 resize-none rounded-2xl"></textarea>
        </div>
        <button type="button" @click="emojiOpen = !emojiOpen" class="w-11 h-11 rounded-full grid place-items-center text-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700" title="Pilih emoji" aria-label="Pilih emoji">😊</button>
        <button type="submit" :disabled="sending || !draft.trim()" class="w-11 h-11 rounded-full grid place-items-center text-white bg-indigo-600 disabled:opacity-50" title="Kirim">
            <i data-lucide="send" class="w-5 h-5"></i>
        </button>
    </form>
</div>

@push('scripts')
<script>
function privateChat(cfg) {
    return {
        ...cfg,
        messages: [],
        seen: new Set(),
        cursor: cfg.lastSeq,
        draft: '',
        sending: false,
        emojiOpen: false,
        timer: null,
        init() {
            this.absorb(this.awal);
            this.$nextTick(() => { this.bottom(); window.lucide?.createIcons(); });
            this.timer = window.simsPollInterval(() => this.poll(), 4000); // jeda otomatis saat tab hidden; ikut mode darurat hemat server
        },
        absorb(list) {
            for (const message of list || []) {
                if (this.seen.has(message.uuid)) continue;
                this.seen.add(message.uuid);
                this.messages.push(message);
                if (message.seq > this.cursor) this.cursor = message.seq;
            }
        },
        async poll() {
            try {
                const response = await fetch(`${this.pollUrl}?after=${this.cursor}`, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                const before = this.messages.length;
                this.absorb(data.messages);
                if (this.messages.length > before) this.$nextTick(() => this.bottom());
            } catch (_) {}
        },
        tambahEmoji(emoji) {
            this.draft = `${this.draft}${this.draft ? ' ' : ''}${emoji}`;
            this.emojiOpen = false;
            this.$nextTick(() => this.$refs.input?.focus());
        },
        async send(quickBody = null) {
            const body = (quickBody ?? this.draft).trim();
            if (!body || this.sending) return;
            this.sending = true;
            try {
                const response = await fetch(this.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ body }),
                });
                if (!response.ok) return;
                const data = await response.json();
                this.draft = '';
                this.absorb([data.message]);
                this.$nextTick(() => this.bottom());
            } finally {
                this.sending = false;
            }
        },
        bottom() {
            if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
        },
    };
}
</script>
@endpush
@endsection
