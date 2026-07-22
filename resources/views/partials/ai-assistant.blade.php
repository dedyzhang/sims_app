{{-- ─── Floating AI Asisten SIMS (Chatbot AI — Fase 2) ──────────────────────────────
     Widget chat AI untuk STAF & ADMIN (siswa/ortu dikecualikan di layout).
     Berbeda dari "Asisten Sekolah" (handoff ke admin manusia): ini menjawab
     langsung via gateway AI. Karena tak ada role yang memegang dua bola sekaligus,
     posisinya selalu di sudut standar (bottom-14). --}}
<div x-data="aiAssistant()" x-cloak
     class="fixed right-4 bottom-11 z-[9992] flex flex-col items-end gap-3 print:hidden">

    {{-- Panel --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-3 scale-95"
         class="fixed inset-0 z-[9992] w-screen h-[100dvh] flex flex-col overflow-hidden bg-white dark:bg-slate-900
                sm:static sm:inset-auto sm:w-[380px] sm:h-[600px] sm:max-h-[80vh] sm:rounded-2xl sm:shadow-2xl sm:ring-1 sm:ring-slate-200 sm:dark:ring-slate-700">

        {{-- Header --}}
        <div class="flex items-center gap-2 px-4 py-3 bg-gradient-to-r from-primary to-primary-700 text-white shrink-0">
            <div class="grid h-9 w-9 place-items-center rounded-full bg-white/20">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3l1.6 3.9L17.5 8.5 13.6 10 12 14l-1.6-4L6.5 8.5l3.9-1.6z"/>
                    <path d="M18 15l.7 1.8L20.5 17.5 18.7 18.2 18 20l-.7-1.8L15.5 17.5l1.8-.7z"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-semibold leading-tight">AI Asisten SIMS</p>
                <p class="text-[11px] text-white/80 leading-tight truncate" x-text="view === 'history' ? 'Riwayat percakapan' : 'Tanya seputar sekolah &amp; aplikasi'"></p>
            </div>
            <button type="button" @click="loadHistory()" title="Riwayat" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white/20 transition" x-show="view === 'chat'">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
            </button>
            <button type="button" @click="newChat()" title="Percakapan baru" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white/20 transition" x-show="view === 'chat'">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <button type="button" @click="view = 'chat'" title="Kembali" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white/20 transition" x-show="view === 'history'">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" @click="open = false" title="Tutup" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-white/20 transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- ─── Tampilan CHAT ─── --}}
        <template x-if="view === 'chat'">
            <div class="flex flex-1 flex-col min-h-0">
                {{-- Pesan --}}
                <div x-ref="scroll" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-slate-50 dark:bg-slate-800/40">
                    {{-- Sambutan / empty state --}}
                    <template x-if="messages.length === 0">
                        <div class="text-center py-6">
                            <p class="text-slate-600 dark:text-slate-300 text-sm">Halo! 👋 Aku <b>AI Asisten SIMS</b>.<br>Tanya apa saja soal sekolah atau cara pakai aplikasi.</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                <template x-for="c in suggestions" :key="c">
                                    <button type="button" @click="input = c; send()" class="rounded-full border border-slate-300 dark:border-slate-600 px-3 py-1.5 text-xs text-slate-600 dark:text-slate-300 hover:bg-primary hover:text-white hover:border-primary transition" x-text="c"></button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Daftar pesan --}}
                    <template x-for="(m, i) in messages" :key="i">
                        <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                            <div :class="m.role === 'user'
                                    ? 'bg-primary text-white rounded-2xl rounded-br-sm'
                                    : (m.error ? 'bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800 rounded-2xl rounded-bl-sm'
                                               : 'bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 ring-1 ring-slate-200 dark:ring-slate-600 rounded-2xl rounded-bl-sm')"
                                 class="ai-answer max-w-[85%] px-3.5 py-2 text-sm break-words shadow-sm" x-html="renderAiMarkdown(m.content)"></div>
                        </div>
                    </template>

                    {{-- Indikator mengetik --}}
                    <template x-if="loading">
                        <div class="flex justify-start">
                            <div class="bg-white dark:bg-slate-700 ring-1 ring-slate-200 dark:ring-slate-600 rounded-2xl rounded-bl-sm px-4 py-3 shadow-sm">
                                <div class="flex gap-1">
                                    <span class="h-2 w-2 rounded-full bg-slate-400 animate-bounce" style="animation-delay:0ms"></span>
                                    <span class="h-2 w-2 rounded-full bg-slate-400 animate-bounce" style="animation-delay:150ms"></span>
                                    <span class="h-2 w-2 rounded-full bg-slate-400 animate-bounce" style="animation-delay:300ms"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Input --}}
                <div class="border-t border-slate-200 dark:border-slate-700 p-3 bg-white dark:bg-slate-900 shrink-0">
                    <div class="flex items-end gap-2">
                        <textarea x-ref="input" x-model="input" rows="1" :disabled="loading"
                                  @keydown.enter.prevent="if(!$event.shiftKey) send()"
                                  @input="autoGrow($event.target)"
                                  placeholder="Ketik pertanyaan…"
                                  class="flex-1 resize-none max-h-28 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 px-3 py-2 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                        <button type="button" @click="send()" :disabled="loading || input.trim() === ''"
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary text-white disabled:opacity-40 hover:bg-primary-700 transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></svg>
                        </button>
                    </div>
                    <p class="mt-1.5 text-[10px] text-slate-400 text-center">AI Asisten SIMS bisa keliru. Jangan bagikan data sensitif.</p>
                </div>
            </div>
        </template>

        {{-- ─── Tampilan RIWAYAT ─── --}}
        <template x-if="view === 'history'">
            <div class="flex-1 overflow-y-auto p-3 bg-slate-50 dark:bg-slate-800/40">
                <template x-if="conversations.length === 0">
                    <p class="text-center text-sm text-slate-500 py-8">Belum ada percakapan tersimpan.</p>
                </template>
                <template x-for="c in conversations" :key="c.id">
                    <div class="group flex items-center gap-2 rounded-xl bg-white dark:bg-slate-700 ring-1 ring-slate-200 dark:ring-slate-600 px-3 py-2.5 mb-2 cursor-pointer hover:ring-primary transition"
                         @click="openConversation(c.id)">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate" x-text="c.title"></p>
                            <p class="text-[11px] text-slate-400" x-text="c.time"></p>
                        </div>
                        <button type="button" @click.stop="deleteConversation(c.id)" title="Hapus"
                                class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Bola pemicu --}}
    <button type="button" @click="toggle()" :aria-expanded="open"
            :class="open ? 'hidden sm:grid' : 'grid'"
            class="relative h-12 w-12 rounded-full bg-gradient-to-br from-primary to-primary-700 text-white shadow-lg shadow-primary/30 place-items-center hover:scale-105 active:scale-95 transition focus:outline-none focus:ring-4 focus:ring-primary/40"
            title="AI Asisten SIMS">
        <svg x-show="!open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l1.6 3.9L17.5 8.5 13.6 10 12 14l-1.6-4L6.5 8.5l3.9-1.6z"/>
            <path d="M18 15l.7 1.8L20.5 17.5 18.7 18.2 18 20l-.7-1.8L15.5 17.5l1.8-.7z"/>
        </svg>
        <svg x-show="open" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

@include('partials.ai-markdown')

<script>
    function aiAssistant() {
        return {
            open: false,
            view: 'chat',            // 'chat' | 'history'
            input: '',
            messages: [],            // { role, content, error? }
            conversations: [],
            conversationId: null,
            loading: false,
            suggestions: ['Kapan pembagian rapor?', 'Cara lihat nilai di aplikasi?', 'Jam operasional sekolah?'],

            csrf() { return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); },

            toggle() {
                this.open = !this.open;
                if (this.open) this.$nextTick(() => this.$refs.input?.focus());
            },

            autoGrow(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 112) + 'px';
            },

            scrollDown() {
                this.$nextTick(() => { const s = this.$refs.scroll; if (s) s.scrollTop = s.scrollHeight; });
            },

            newChat() {
                this.conversationId = null;
                this.messages = [];
                this.view = 'chat';
                this.$nextTick(() => this.$refs.input?.focus());
            },

            async send() {
                const text = this.input.trim();
                if (text === '' || this.loading) return;

                this.messages.push({ role: 'user', content: text });
                this.input = '';
                this.$nextTick(() => { if (this.$refs.input) this.$refs.input.style.height = 'auto'; });
                this.loading = true;
                this.scrollDown();

                try {
                    const r = await fetch('{{ route('ai.chat.send') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ message: text, conversation_id: this.conversationId }),
                    });
                    const d = await r.json();
                    if (r.ok && d.ok) {
                        this.conversationId = d.conversation_id;
                        this.messages.push({ role: 'assistant', content: d.answer });
                    } else {
                        this.messages.push({ role: 'assistant', content: d.message || 'Maaf, terjadi kesalahan. Coba lagi.', error: true });
                    }
                } catch (_) {
                    this.messages.push({ role: 'assistant', content: 'Gagal terhubung. Periksa koneksi lalu coba lagi.', error: true });
                } finally {
                    this.loading = false;
                    this.scrollDown();
                }
            },

            async loadHistory() {
                this.view = 'history';
                try {
                    const r = await fetch('{{ route('ai.chat.history') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const d = await r.json();
                    if (d.ok) this.conversations = d.conversations;
                } catch (_) {}
            },

            async openConversation(id) {
                try {
                    const r = await fetch('{{ url('ai/chat') }}/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const d = await r.json();
                    if (d.ok) {
                        this.conversationId = d.id;
                        this.messages = d.messages;
                        this.view = 'chat';
                        this.scrollDown();
                    }
                } catch (_) {}
            },

            async deleteConversation(id) {
                try {
                    await fetch('{{ url('ai/chat') }}/' + id, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    this.conversations = this.conversations.filter(c => c.id !== id);
                    if (this.conversationId === id) this.newChat();
                } catch (_) {}
            },
        }
    }
</script>
