@extends('layouts.app')

@section('title', 'Inbox Admin — Asisten Sekolah')

@section('content')
@push('styles')
<style>
    .no-scrollbar::-webkit-scrollbar{display:none}.no-scrollbar{-ms-overflow-style:none;scrollbar-width:none}
    /* Item antrian — kontras eksplisit untuk light & dark (tidak bergantung pada
       generate kelas dark: dinamis dari Tailwind CDN). */
    .q-item:hover{ background: #f8fafc; }                               /* slate-50 */
    .q-item.is-active{ background: color-mix(in srgb, var(--cp) 9%, white); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--cp) 28%, white); }
    .dark .q-item:hover{ background: #1e293b; }                         /* slate-800 */
    .dark .q-item.is-active{ background: #1e293b; box-shadow: inset 0 0 0 1px var(--cp); }
    .dropdown-container.is-open { opacity: 1 !important; }
    .inbox-resize-handle {
        flex: 0 0 10px; cursor: col-resize; position: relative; z-index: 12;
        background: transparent; touch-action: none;
    }
    .inbox-resize-handle::after {
        content:""; position:absolute; top:50%; left:50%; width:3px; height:52px;
        transform:translate(-50%,-50%); border-radius:9999px;
        background: color-mix(in srgb, var(--cp) 35%, transparent); opacity:0; transition:opacity .15s, height .15s;
    }
    .inbox-resize-handle:hover::after, .inbox-resize-handle.is-dragging::after { opacity:1; height:76px; }
    #chat-active.chat-collapsed #chat-archived,
    #chat-active.chat-collapsed #chat-messages,
    #chat-active.chat-collapsed #chat-composer { display:none !important; }
    #chat-collapsed-note { display:none; }
    #chat-active.chat-collapsed #chat-collapsed-note { display:flex; }

    @media (max-width: 767.98px) {
        #queue-sidebar {
            width: 100% !important;
            flex: 1 1 0% !important;
            display: flex !important;
        }
        #inbox.has-active-chat #queue-sidebar {
            display: none !important;
        }
        .inbox-resize-handle { display: none !important; }

        #chat-panel {
            display: none !important;
        }
        #inbox.has-active-chat #chat-panel {
            display: flex !important;
            width: 100% !important;
            flex: 1 1 0% !important;
        }

        .btn-mobile-back {
            display: inline-flex !important;
        }
    }

    .btn-mobile-back {
        display: none;
    }
</style>
@endpush
<div class="w-full" style="height: calc(100vh - 7.5rem); min-height: 520px;">
<div class="h-full w-full flex bg-transparent overflow-hidden"
     id="inbox"
     data-poll-interval="{{ $pollInterval }}"
     data-queue-url="{{ route('chatbot.admin.queue') }}"
     data-history-url="{{ route('chatbot.admin.history') }}"
     data-msg-base="{{ url('/chatbot/admin') }}"
     data-settings-url="{{ route('chatbot.admin.settings') }}"
     data-admin-id="{{ $admin->id }}"
     data-notif-enabled="{{ $settings->notif_enabled ? '1' : '0' }}"
     data-sound-enabled="{{ $settings->sound_enabled ? '1' : '0' }}"
     data-message-notif-enabled="{{ $settings->message_notif_enabled ? '1' : '0' }}">

    @php
        $navs = [
            ['key' => 'all',        'label' => 'Semua',            'icon' => 'M4 6h16M4 12h16M4 18h16'],
            ['key' => 'mine',       'label' => 'Ditugaskan ke saya','icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            ['key' => 'unassigned', 'label' => 'Menunggu',         'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['key' => 'assigned',   'label' => 'Sedang ditangani', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['key' => 'closed',     'label' => 'Selesai',          'icon' => 'M5 8h14M5 8a2 2 0 100-4h14a2 2 0 100 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M10 12h4'],
        ];
    @endphp

    {{-- ============================ MAIN ============================ --}}
    <div class="flex-1 flex flex-col min-w-0">

        {{-- Top bar --}}
        <header class="flex items-center gap-4 px-6 py-3 border-b border-slate-100 dark:border-slate-700">
            {{-- Brand --}}
            <div class="flex items-center gap-2 shrink-0" id="brand-avatar-container">
                @if($avatarType !== 'default')
                    <img src="{{ asset('images/chatbot/avatar_' . $avatarType . '.png') }}" class="h-9 w-9 rounded-xl object-cover shadow-sm bg-white" id="brand-avatar-img" alt="Avatar">
                @else
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-primary to-primary-700 flex items-center justify-center text-white font-black text-lg" id="brand-avatar-fallback">A</div>
                @endif
                <span class="hidden lg:inline text-slate-800 dark:text-slate-100 font-semibold text-base tracking-tight">Asisten</span>
            </div>

            {{-- Pill filter (menggantikan sidebar) --}}
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar -my-1 py-1">
                @foreach ($navs as $nav)
                    <button data-filter="{{ $nav['key'] }}"
                            class="nav-btn shrink-0 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-sm font-medium transition
                                   {{ $loop->first ? 'bg-primary-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $nav['icon'] }}"/>
                        </svg>
                        <span class="whitespace-nowrap">{{ $nav['label'] }}</span>
                        <span class="nav-count text-[11px] font-bold rounded-full bg-black/15 px-1.5 min-w-[18px] text-center hidden">0</span>
                    </button>
                @endforeach
            </div>

            <div class="ml-auto flex items-center gap-3">
                {{-- Quick Questions button --}}
                <button id="btn-quick-questions" class="h-9 w-9 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400" title="Kelola Pertanyaan Cepat">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12M8.25 17.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </button>

                {{-- Settings dropdown --}}
                <div class="relative">
                    <button id="btn-settings" class="h-9 w-9 rounded-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 3.3a1 1 0 011.4 0l.7.7a1 1 0 00.9.3l1-.2a1 1 0 011.2 1l.1 1a1 1 0 00.5.8l.9.5a1 1 0 01.4 1.3l-.4.9a1 1 0 000 .9l.4.9a1 1 0 01-.4 1.3l-.9.5a1 1 0 00-.5.8l-.1 1a1 1 0 01-1.2 1l-1-.2a1 1 0 00-.9.3l-.7.7a1 1 0 01-1.4 0l-.7-.7a1 1 0 00-.9-.3l-1 .2a1 1 0 01-1.2-1l-.1-1a1 1 0 00-.5-.8l-.9-.5a1 1 0 01-.4-1.3l.4-.9a1 1 0 000-.9l-.4-.9a1 1 0 01.4-1.3l.9-.5a1 1 0 00.5-.8l.1-1a1 1 0 011.2-1l1 .2a1 1 0 00.9-.3z"/>
                            <circle cx="12" cy="12" r="2.5"/>
                        </svg>
                    </button>
                    <div id="settings-menu" class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-white dark:bg-slate-800 shadow-xl ring-1 ring-slate-100 dark:ring-slate-700 p-2 z-30">
                        <p class="px-3 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wide">Notifikasi</p>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/60 cursor-pointer text-sm text-slate-700 dark:text-slate-200">
                            Badge antrian <input type="checkbox" id="toggle-notif" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        </label>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/60 cursor-pointer text-sm text-slate-700 dark:text-slate-200">
                            Suara <input type="checkbox" id="toggle-sound" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        </label>
                        <label class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/60 cursor-pointer text-sm text-slate-700 dark:text-slate-200">
                            Notif pesan masuk <input type="checkbox" id="toggle-msg" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        </label>
                        <p class="px-3 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wide border-t border-slate-100 dark:border-slate-700 mt-2 pt-2">Maskot / CS</p>
                        <div class="px-3 py-1.5 grid grid-cols-4 gap-2">
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl bg-gradient-to-br from-primary to-primary-700 text-white font-black flex items-center justify-center border-2 {{ $avatarType === 'default' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="default" title="Logo Default">
                                A
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'robot' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="robot" title="Robot Maskot">
                                <img src="{{ asset('images/chatbot/avatar_robot.png') }}" class="h-full w-full object-cover" alt="Robot">
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'owl' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="owl" title="Burung Hantu">
                                <img src="{{ asset('images/chatbot/avatar_owl.png') }}" class="h-full w-full object-cover" alt="Owl">
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'cs' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="cs" title="Petugas CS">
                                <img src="{{ asset('images/chatbot/avatar_cs.png') }}" class="h-full w-full object-cover" alt="CS">
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'cat' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="cat" title="Kucing Guru">
                                <img src="{{ asset('images/chatbot/avatar_cat.png') }}" class="h-full w-full object-cover" alt="Cat">
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'fox' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="fox" title="Rubah Pintar">
                                <img src="{{ asset('images/chatbot/avatar_fox.png') }}" class="h-full w-full object-cover" alt="Fox">
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'panda' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="panda" title="Panda Lucu">
                                <img src="{{ asset('images/chatbot/avatar_panda.png') }}" class="h-full w-full object-cover" alt="Panda">
                            </button>
                            <button type="button" class="avatar-select-btn relative h-9 w-9 rounded-xl overflow-hidden border-2 {{ $avatarType === 'bear' ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent' }}" data-avatar="bear" title="Beruang Baik">
                                <img src="{{ asset('images/chatbot/avatar_bear.png') }}" class="h-full w-full object-cover" alt="Bear">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex-1 flex min-h-0">
            {{-- ---------------- Conversation list ---------------- --}}
            <section id="queue-sidebar" class="w-80 shrink-0 border-r border-slate-100 dark:border-slate-700 flex flex-col min-h-0">
                <div class="p-4">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M11 19a8 8 0 100-16 8 8 0 000 16z"/>
                        </svg>
                        <input id="search" type="text" placeholder="Cari nama / pesan…"
                               class="w-full rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border-0 pl-9 pr-3 py-2.5 text-sm placeholder:text-slate-400 focus:ring-2 focus:ring-primary-400">
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-2 pb-2" id="queue-list">
                    <p class="p-4 text-sm text-slate-400">Memuat antrian…</p>
                </div>
            </section>

            <div id="queue-resizer" class="inbox-resize-handle" role="separator" aria-orientation="vertical" title="Seret untuk mengubah lebar daftar percakapan. Klik dua kali untuk reset."></div>

            {{-- ---------------- Chat panel ---------------- --}}
            <section class="flex-1 flex flex-col bg-slate-50/60 dark:bg-slate-800/40 min-w-0" id="chat-panel">
                <div class="flex-1 flex flex-col items-center justify-center text-center px-6 text-slate-400" id="chat-empty">
                    <div class="h-16 w-16 rounded-2xl bg-primary-100 flex items-center justify-center mb-3">
                        <svg class="h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 7l3.2-2.4A2 2 0 0110 18h7a3 3 0 003-3V7a3 3 0 00-3-3H6a3 3 0 00-3 3v14z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-slate-500">Pilih percakapan untuk mulai membalas</p>
                    <p class="text-xs text-slate-400 mt-1">Percakapan yang menunggu admin muncul di daftar kiri.</p>
                </div>

                <div class="hidden flex-col h-full min-h-0" id="chat-active">
                    <div class="px-5 py-3 bg-white/40 dark:bg-slate-900/40 backdrop-blur-sm border-b border-slate-100 dark:border-slate-700 flex items-center gap-3">
                        <button id="btn-chat-back" class="btn-mobile-back inline-flex items-center justify-center h-9 w-9 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 mr-1">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <div id="chat-avatar" class="h-10 w-10 rounded-full bg-primary-500 flex items-center justify-center text-white font-semibold">—</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate" id="chat-user">—</p>
                            <p class="text-xs text-primary-500" id="chat-status">—</p>
                        </div>
                        <button id="btn-back-to-bot"
                                class="rounded-full border border-primary-200 bg-primary-50 px-4 py-1.5 text-xs font-medium text-primary-700 hover:bg-primary-100">
                            Kembalikan ke Bot
                        </button>
                        <button id="btn-toggle-chat-collapse"
                                class="inline-flex items-center justify-center h-8 w-8 rounded-full border border-slate-200 bg-white/70 text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                title="Ciutkan percakapan">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 15l-7-7-7 7"/>
                            </svg>
                        </button>
                        <button id="btn-close-chat"
                                class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Selesaikan
                        </button>
                    </div>

                    <div id="chat-collapsed-note" class="flex-1 flex-col items-center justify-center gap-2 px-6 text-center text-slate-400 dark:text-slate-500">
                        <div class="h-12 w-12 rounded-2xl bg-slate-100 dark:bg-slate-800 grid place-items-center text-primary">
                            <i data-lucide="panel-top-close" class="w-6 h-6"></i>
                        </div>
                        <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">Percakapan diciutkan</p>
                        <p class="text-xs max-w-sm">Klik tombol panah di header percakapan untuk membuka kembali area pesan.</p>
                    </div>

                    {{-- Banner arsip: muncul saat membuka percakapan yang sudah ditutup (read-only) --}}
                    <div id="chat-archived" class="hidden items-center gap-2 px-5 py-2 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 100-4h14a2 2 0 100 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8"/>
                        </svg>
                        <span id="chat-archived-text">Percakapan telah ditutup — tersimpan sebagai histori/bukti (hanya baca).</span>
                    </div>

                    <div id="chat-messages" class="flex-1 overflow-y-auto px-5 py-5 space-y-3"></div>

                    <div id="chat-composer" class="p-3 px-4 border-t border-slate-100 dark:border-slate-700 bg-white/40 dark:bg-slate-900/40 backdrop-blur-sm relative">
                        {{-- Pratinjau gambar terpilih --}}
                        <div id="a-img-preview" class="hidden mb-2 flex items-center gap-2">
                            <div class="relative">
                                <img id="a-img-preview-el" alt="pratinjau" class="h-16 w-16 object-cover rounded-xl border border-slate-200">
                                <button id="a-img-remove" type="button"
                                        class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-slate-700 text-white text-[11px] leading-none flex items-center justify-center hover:bg-slate-900">✕</button>
                            </div>
                            <span id="a-img-size" class="text-[11px] text-slate-400"></span>
                        </div>

                        {{-- Pratinjau file/dokumen terpilih --}}
                        <div id="a-file-preview" class="hidden mb-2 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2">
                            <svg class="h-7 w-7 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                            </svg>
                            <div class="min-w-0 flex-1">
                                <p id="a-file-name" class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">—</p>
                                <p id="a-file-size" class="text-[11px] text-slate-400">—</p>
                            </div>
                            <button id="a-file-remove" type="button"
                                    class="h-6 w-6 shrink-0 rounded-full bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-200 text-[11px] leading-none flex items-center justify-center hover:bg-slate-300 dark:hover:bg-slate-500">✕</button>
                        </div>

                        <div class="rounded-2xl bg-slate-100 dark:bg-slate-800 px-3 pt-2 pb-1.5">
                            <textarea id="chat-input" rows="1" placeholder="Tulis balasan…"
                                      class="w-full resize-none bg-transparent text-slate-800 dark:text-slate-100 border-0 p-0 text-sm focus:ring-0 focus:outline-none max-h-28 placeholder:text-slate-400 dark:placeholder:text-slate-500"></textarea>
                            <div class="flex items-center gap-1 mt-1">
                                <button id="a-btn-attach" type="button" title="Kirim gambar"
                                        class="h-8 w-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-primary-600 flex items-center justify-center transition">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 15l-5-5L5 21M3 16.5V7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        <circle cx="8.5" cy="9.5" r="1.5"/>
                                    </svg>
                                </button>
                                <button id="a-btn-file" type="button" title="Lampirkan file (PDF/Word/Excel)"
                                        class="h-8 w-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-primary-600 flex items-center justify-center transition">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05l-9.19 9.19a5 5 0 01-7.07-7.07l9.19-9.19a3.5 3.5 0 014.95 4.95l-9.2 9.19a1.5 1.5 0 01-2.12-2.12l8.49-8.49"/>
                                    </svg>
                                </button>
                                <button id="a-btn-emoji" type="button" title="Emoji"
                                        class="h-8 w-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-primary-600 flex items-center justify-center transition">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 10h.01M15 10h.01M8.5 14.5a4 4 0 007 0"/>
                                    </svg>
                                </button>
                                <button id="chat-send" type="button"
                                        class="ml-auto shrink-0 h-9 w-9 rounded-full bg-primary-600 text-white flex items-center justify-center hover:bg-primary-700 disabled:opacity-50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <input id="a-file-input" type="file" accept="image/*" class="hidden">
                        <input id="a-file-input-doc" type="file"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                               class="hidden">
                        <div id="a-emoji-pop" class="hidden absolute left-4 w-64 max-w-[calc(100%-2rem)] bottom-[84px] z-20 bg-white dark:bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-100 dark:ring-slate-700 p-2 max-h-44 overflow-y-auto grid grid-cols-8 gap-0.5"></div>
                    </div>
                </div>
            </section>

            {{-- ---------------- Info panel ---------------- --}}
            <aside class="w-72 shrink-0 border-l border-slate-100 dark:border-slate-700 overflow-y-auto hidden xl:block" id="info-panel">
                <div id="info-empty" class="p-6 text-sm text-slate-400">Detail kontak akan tampil di sini.</div>

                <div id="info-active" class="hidden p-5 space-y-5">
                    <section>
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Info umum</h3>
                            <span id="i-status" class="text-[11px] rounded-full px-2.5 py-0.5 font-medium">—</span>
                        </div>
                        <div class="mt-3 flex items-center gap-3">
                            <div id="i-avatar" class="h-11 w-11 rounded-full bg-primary-500 flex items-center justify-center text-white font-semibold">—</div>
                            <div class="min-w-0">
                                <p id="i-name" class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">—</p>
                                <p id="i-role" class="text-xs text-slate-400">—</p>
                            </div>
                        </div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-slate-400">Email</dt>
                                <dd id="i-email" class="text-slate-700 dark:text-slate-300 break-words">—</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-slate-400">Tanggal dibuat</dt>
                                <dd id="i-created" class="text-slate-700 dark:text-slate-300">—</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="border-t border-slate-100 dark:border-slate-700 pt-4">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-3">Info percakapan</h3>
                        <dl class="space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-400">Mode</dt><dd id="i-mode" class="text-slate-700 dark:text-slate-300 font-medium">—</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-400">Status</dt><dd id="i-cstatus" class="text-slate-700 dark:text-slate-300 font-medium">—</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-400">Belum dibaca</dt><dd id="i-unread" class="text-slate-700 dark:text-slate-300 font-medium">0</dd></div>
                        </dl>
                    </section>
                </div>
            </aside>
        </div>
    </div>
</div>
</div>
{{-- Modal Kelola Pertanyaan Cepat --}}
<div id="modal-quick-questions" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden">
    <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 dark:text-slate-100 text-base">Kelola Pertanyaan Cepat</h3>
            <button type="button" id="btn-close-quick-modal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="px-6 pt-4">
            <p class="text-xs text-slate-500 dark:text-slate-400">Isi <b>pertanyaan</b> + <b>jawaban</b>. Bot akan membalas jawaban saat pertanyaan ditanyakan. Kosongkan jawaban bila ingin ditangani sistem (mis. <i>jadwal hari ini</i> dijawab dari data) atau diteruskan ke admin.</p>
        </div>
        <div class="p-6 pt-3 max-h-[60vh] overflow-y-auto space-y-3" id="quick-questions-list">
            @foreach($quickQuestions as $index => $qa)
                <div class="flex items-start gap-2 quick-question-row rounded-xl border border-slate-200 dark:border-slate-700 p-2.5">
                    <div class="flex-1 space-y-2">
                        <input type="text" value="{{ $qa['q'] ?? '' }}" class="quick-question-input w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Pertanyaan (mis. Bagaimana cara absensi QR?)">
                        <textarea rows="2" class="quick-answer-input w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200 text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-y" placeholder="Jawaban bot (kosongkan bila ditangani sistem / handoff ke admin)">{{ $qa['a'] ?? '' }}</textarea>
                    </div>
                    <button type="button" class="btn-remove-quick h-9 w-9 shrink-0 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            @endforeach
        </div>
        <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <button type="button" id="btn-add-quick" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline" style="color:var(--cp)">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Baris
            </button>
            <div class="flex items-center gap-2">
                <button type="button" id="btn-cancel-quick" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750">
                    Batal
                </button>
                <button type="button" id="btn-save-quick" class="rounded-xl bg-primary-600 hover:bg-primary-700 px-4 py-2 text-xs font-semibold text-white transition disabled:opacity-50">
                    Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const root   = document.getElementById('inbox');
    const queueSidebar = document.getElementById('queue-sidebar');
    const queueResizer = document.getElementById('queue-resizer');
    const csrf   = document.querySelector('meta[name="csrf-token"]').content;
    const btnQuickQuestions = document.getElementById('btn-quick-questions');
    const modalQuickQuestions = document.getElementById('modal-quick-questions');
    const btnCloseQuickModal = document.getElementById('btn-close-quick-modal');
    const btnCancelQuick = document.getElementById('btn-cancel-quick');
    const btnSaveQuick = document.getElementById('btn-save-quick');
    const btnAddQuick = document.getElementById('btn-add-quick');
    const quickQuestionsList = document.getElementById('quick-questions-list');
    const queueUrl  = root.dataset.queueUrl;
    const historyUrl = root.dataset.historyUrl;
    const msgBase   = root.dataset.msgBase;
    const settingsUrl = root.dataset.settingsUrl;
    const adminId   = root.dataset.adminId;
    const pollEvery = parseInt(root.dataset.pollInterval, 10) || 5000;

    const elQueue   = document.getElementById('queue-list');
    const elEmpty   = document.getElementById('chat-empty');
    const elActive  = document.getElementById('chat-active');
    const elUser    = document.getElementById('chat-user');
    const elStatus  = document.getElementById('chat-status');
    const elAvatar  = document.getElementById('chat-avatar');
    const elMsgs    = document.getElementById('chat-messages');
    const elInput   = document.getElementById('chat-input');
    const elSend    = document.getElementById('chat-send');
    const elSearch  = document.getElementById('search');
    const btnBack   = document.getElementById('btn-back-to-bot');
    const btnClose  = document.getElementById('btn-close-chat');
    const btnChatBack = document.getElementById('btn-chat-back');
    const btnToggleChatCollapse = document.getElementById('btn-toggle-chat-collapse');
    const elComposer = document.getElementById('chat-composer');
    const elArchived = document.getElementById('chat-archived');
    const elArchivedText = document.getElementById('chat-archived-text');
    const aBtnAttach = document.getElementById('a-btn-attach');
    const aBtnEmoji  = document.getElementById('a-btn-emoji');
    const aEmojiPop  = document.getElementById('a-emoji-pop');
    const aFileInput = document.getElementById('a-file-input');
    const aBtnFile      = document.getElementById('a-btn-file');
    const aFileInputDoc = document.getElementById('a-file-input-doc');
    const aImgPreview   = document.getElementById('a-img-preview');
    const aImgPreviewEl = document.getElementById('a-img-preview-el');
    const aImgRemove    = document.getElementById('a-img-remove');
    const aImgSize      = document.getElementById('a-img-size');
    const aFilePreview  = document.getElementById('a-file-preview');
    const aFileNameEl   = document.getElementById('a-file-name');
    const aFileSizeEl   = document.getElementById('a-file-size');
    const aFileRemove   = document.getElementById('a-file-remove');
    const A_MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB
    const tglNotif  = document.getElementById('toggle-notif');
    const tglSound  = document.getElementById('toggle-sound');
    const tglMsg    = document.getElementById('toggle-msg');
    const btnSettings = document.getElementById('btn-settings');
    const settingsMenu = document.getElementById('settings-menu');
    const navBtns   = [...document.querySelectorAll('.nav-btn')];

    // Info panel
    const infoEmpty  = document.getElementById('info-empty');
    const infoActive = document.getElementById('info-active');
    const iAvatar = document.getElementById('i-avatar');
    const iName   = document.getElementById('i-name');
    const iRole   = document.getElementById('i-role');
    const iEmail  = document.getElementById('i-email');
    const iCreated= document.getElementById('i-created');
    const iStatus = document.getElementById('i-status');
    const iMode   = document.getElementById('i-mode');
    const iCStatus= document.getElementById('i-cstatus');
    const iUnread = document.getElementById('i-unread');

    let notifEnabled = root.dataset.notifEnabled === '1';
    let soundEnabled = root.dataset.soundEnabled === '1';
    let messageNotif = root.dataset.messageNotifEnabled === '1';
    tglNotif.checked = notifEnabled;
    tglSound.checked = soundEnabled;
    tglMsg.checked   = messageNotif;

    let activeId = null;
    let cursor   = null;
    const seen   = new Set();
    let lastWaiting = null;
    const handled = new Set();
    const lastSig = {};
    const unread  = new Set();
    let sigInit   = false;
    let lastDayKey = null;
    let readWatermark = null;
    let pollTimer = null;
    let allList   = [];          // percakapan aktif (waiting/assigned) dari polling queue
    let closedList = [];         // arsip percakapan tertutup (lazy, dari endpoint history)
    let closedLoaded = false;    // sudah pernah memuat arsip?
    let filter    = 'all';       // filter pill aktif
    let search    = '';          // kata kunci pencarian
    let aPendingBlob = null;     // gambar admin terkompres siap kirim
    let aPendingFile = null;     // file/dokumen admin mentah siap kirim (tanpa kompresi)
    let chatCollapsed = localStorage.getItem('chat_inbox_conversation_collapsed') === '1';
    let aPendingUrl  = null;     // object URL pratinjau

    // ---------- Avatar ----------
    const AV_COLORS = ['bg-primary-500','bg-fuchsia-500','bg-indigo-500','bg-sky-500','bg-emerald-500','bg-amber-500','bg-rose-500','bg-teal-500'];
    function initials(name) {
        const p = (name || '?').trim().split(/\s+/);
        return ((p[0]?.[0] || '') + (p[1]?.[0] || '')).toUpperCase() || '?';
    }
    function avatarColor(key) {
        let h = 0; for (const ch of (key || '')) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
        return AV_COLORS[h % AV_COLORS.length];
    }

    // ---------- Waktu ----------
    const HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    function fmtTime(iso) {
        const d = iso ? new Date(iso) : new Date();
        return String(d.getHours()).padStart(2,'0') + '.' + String(d.getMinutes()).padStart(2,'0');
    }
    function fmtDate(iso) {
        if (!iso) return '—';
        const d = new Date(iso);
        return d.getDate() + ' ' + BULAN[d.getMonth()] + ' ' + d.getFullYear() + ' • ' + fmtTime(iso);
    }
    function dayLabel(d) {
        const today = new Date(); today.setHours(0,0,0,0);
        const md = new Date(d); md.setHours(0,0,0,0);
        const diff = Math.round((today - md) / 86400000);
        if (diff === 0) return 'Hari ini';
        if (diff === 1) return 'Kemarin';
        if (diff > 1 && diff < 7) return HARI[md.getDay()];
        return HARI[md.getDay()] + ', ' + md.getDate() + ' ' + BULAN[md.getMonth()] + ' ' + md.getFullYear();
    }
    function appendDayChipIfNeeded(iso) {
        const d = iso ? new Date(iso) : new Date();
        const key = d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
        if (key === lastDayKey) return;
        lastDayKey = key;
        const wrap = document.createElement('div');
        wrap.className = 'flex justify-center my-3';
        const chip = document.createElement('span');
        chip.className = 'text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300 rounded-full px-3 py-0.5';
        chip.textContent = dayLabel(d);
        wrap.appendChild(chip);
        elMsgs.appendChild(wrap);
    }
    function shortWhen(iso) {
        if (!iso) return '';
        const today = new Date(); today.setHours(0,0,0,0);
        const md = new Date(iso); md.setHours(0,0,0,0);
        const diff = Math.round((today - md) / 86400000);
        if (diff === 0) return fmtTime(iso);
        if (diff === 1) return 'Kemarin';
        if (diff > 1 && diff < 7) return HARI[md.getDay()];
        return md.getDate() + '/' + (md.getMonth() + 1);
    }

    // ---------- Audio ----------
    let audioCtx = null;
    function primeAudio() {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
        } catch (_) {}
    }
    document.addEventListener('click', primeAudio);
    document.addEventListener('keydown', primeAudio);
    function ding() {
        if (!soundEnabled) return;
        try {
            primeAudio(); if (!audioCtx) return;
            const t = audioCtx.currentTime;
            const o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.connect(g); g.connect(audioCtx.destination);
            o.type = 'sine';
            o.frequency.setValueAtTime(880, t);
            o.frequency.setValueAtTime(1175, t + 0.12);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.4);
            o.start(t); o.stop(t + 0.42);
        } catch (_) {}
    }

    // ---------- Queue ----------
    async function loadQueue() {
        if (document.visibilityState !== 'visible') return;
        try {
            const res = await fetch(queueUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            allList = data.conversations || [];
            detectNewMessages(allList);
            renderQueue();
            updateCounts(data.waiting_count || 0);
        } catch (_) {}
    }

    // Arsip percakapan tertutup (histori/bukti). Dimuat saat dibutuhkan, bukan tiap polling.
    async function loadHistory() {
        try {
            const url = new URL(historyUrl, window.location.origin);
            if (search) url.searchParams.set('q', search);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            closedList = data.conversations || [];
            closedLoaded = true;
            if (filter === 'closed') renderQueue();
            updateCounts(lastWaiting || 0);
        } catch (_) {}
    }

    function detectNewMessages(list) {
        let dingNeeded = false;
        list.forEach(c => {
            const sig = (c.last_sender || '') + '|' + (c.last_message || '');
            const prev = lastSig[c.id];
            const isNewUserMsg = sigInit && prev !== undefined && sig !== prev && c.last_sender === 'user';
            if (isNewUserMsg && c.id !== activeId) { unread.add(c.id); dingNeeded = true; }
            lastSig[c.id] = sig;
        });
        if (dingNeeded && messageNotif) ding();
        sigInit = true;
    }

    function matchesFilter(c) {
        if (filter === 'mine')       return c.status === 'assigned' && String(c.assigned_admin_id) === String(adminId);
        if (filter === 'unassigned') return c.status === 'waiting';
        if (filter === 'assigned')   return c.status === 'assigned';
        return true; // all
    }
    function matchesSearch(c) {
        if (!search) return true;
        const hay = ((c.user_name || '') + ' ' + (c.user_email || '') + ' ' + (c.last_message || '')).toLowerCase();
        return hay.includes(search);
    }

    function updateCounts(waitingCount) {
        const counts = {
            all: allList.length,
            mine: allList.filter(c => c.status === 'assigned' && String(c.assigned_admin_id) === String(adminId)).length,
            unassigned: allList.filter(c => c.status === 'waiting').length,
            assigned: allList.filter(c => c.status === 'assigned').length,
            closed: closedList.length,
        };
        navBtns.forEach(b => {
            const c = counts[b.dataset.filter] || 0;
            const span = b.querySelector('.nav-count');
            span.textContent = c;
            span.classList.toggle('hidden', c === 0);
        });
        // Ding saat antrian naik.
        if (lastWaiting !== null && waitingCount > lastWaiting && notifEnabled) ding();
        lastWaiting = waitingCount;
    }

    function renderQueue() {
        // Percakapan tertutup punya sumber data terpisah (arsip), tidak ikut polling antrian aktif.
        const list = (filter === 'closed')
            ? closedList.filter(matchesSearch)
            : allList.filter(matchesFilter).filter(matchesSearch);
        if (!list.length) {
            const kosong = filter === 'closed' ? 'Belum ada percakapan yang ditutup.' : 'Tidak ada percakapan.';
            elQueue.innerHTML = '<p class="p-6 text-sm text-slate-400 text-center">' + kosong + '</p>';
            return;
        }
        elQueue.innerHTML = '';
        list.forEach(c => {
            const item = document.createElement('div');
            const isActive = c.id === activeId;
            const hasUnread = unread.has(c.id) && messageNotif;
            item.className = 'q-item w-full cursor-pointer text-left px-3 py-3 rounded-2xl mb-1 transition flex gap-3 items-start relative group' +
                             (isActive ? ' is-active' : '');

            const dot = (c.status === 'waiting')
                ? '<span class="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-amber-400 ring-2 ring-white"></span>'
                : (c.status === 'assigned'
                    ? '<span class="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-white"></span>' : '');

            const reply = c.status === 'closed'
                ? '<span class="inline-flex items-center gap-1 text-[11px] text-slate-500 font-medium"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Selesai' + (c.closed_at ? ' • ' + shortWhen(c.closed_at) : '') + '</span>'
                : (c.last_sender === 'user'
                    ? '<span class="text-[11px] text-amber-600 font-medium">Belum dibalas</span>'
                    : (c.last_sender === 'admin'
                        ? '<span class="text-[11px] text-emerald-600 font-medium">Sudah dibalas</span>'
                        : '<span class="text-[11px] text-slate-400">Otomatis</span>'));
            const when = c.last_at ? '<span class="text-[11px] text-slate-400 shrink-0">' + shortWhen(c.last_at) + '</span>' : '';
            const badge = hasUnread
                ? '<span class="ml-1 shrink-0 inline-flex items-center justify-center h-5 min-w-[20px] px-1.5 text-[10px] font-bold rounded-full bg-primary-600 text-white">' + (c.unread_count || '•') + '</span>'
                : '';

            item.innerHTML =
                '<div class="relative shrink-0">' +
                    '<div class="h-10 w-10 rounded-full ' + avatarColor(c.user_name) + ' flex items-center justify-center text-white text-sm font-semibold">' + escapeHtml(initials(c.user_name)) + '</div>' +
                    dot +
                '</div>' +
                '<div class="flex-1 min-w-0 pr-6">' +
                    '<div class="flex items-center gap-1 mb-0.5"><span class="text-sm font-semibold text-slate-800 dark:text-slate-100 flex-1 truncate">' + escapeHtml(c.user_name) + '</span>' + when + '</div>' +
                    '<p class="text-xs text-slate-500 dark:text-slate-400 truncate mb-1">' + escapeHtml(c.last_message || 'Belum ada pesan') + '</p>' +
                    '<div class="flex items-center gap-1">' + reply + badge + '</div>' +
                '</div>' +
                '<div class="absolute right-2 top-3 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity dropdown-container z-10">' +
                    '<button class="dropdown-trigger h-7 w-7 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 flex items-center justify-center transition">' +
                        '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>' +
                        '</svg>' +
                    '</button>' +
                    '<div class="dropdown-menu hidden absolute right-0 mt-1 w-32 rounded-xl bg-white dark:bg-slate-800 shadow-xl ring-1 ring-black/5 dark:ring-slate-700 py-1 z-30">' +
                        '<button class="btn-delete-item w-full text-left px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center gap-1.5 transition">' +
                            '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />' +
                            '</svg>' +
                            'Hapus Chat' +
                        '</button>' +
                    '</div>' +
                '</div>';

            item.onclick = (e) => {
                if (e.target.closest('.dropdown-container')) return;
                openConversation(c);
            };

            const trigger = item.querySelector('.dropdown-trigger');
            const menu = item.querySelector('.dropdown-menu');
            const container = item.querySelector('.dropdown-container');
            const btnDelete = item.querySelector('.btn-delete-item');

            trigger.onclick = (e) => {
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu').forEach(m => {
                    if (m !== menu) m.classList.add('hidden');
                });
                document.querySelectorAll('.dropdown-container').forEach(cnt => {
                    if (cnt !== container) cnt.classList.remove('opacity-100', 'is-open');
                });

                const isHidden = menu.classList.toggle('hidden');
                if (!isHidden) {
                    container.classList.add('opacity-100', 'is-open');
                } else {
                    container.classList.remove('opacity-100', 'is-open');
                }
            };

            btnDelete.onclick = (e) => {
                e.stopPropagation();
                menu.classList.add('hidden');
                container.classList.remove('opacity-100', 'is-open');
                deleteChat(c.id);
            };

            elQueue.appendChild(item);
        });
    }

    // ---------- Info panel ----------
    function roleLabel(r) {
        return { admin: 'Admin', siswa: 'Siswa', orang_tua: 'Orang Tua' }[r] || (r || '—');
    }
    function fillInfo(c) {
        infoEmpty.classList.add('hidden');
        infoActive.classList.remove('hidden');
        iAvatar.className = 'h-11 w-11 rounded-full flex items-center justify-center text-white font-semibold ' + avatarColor(c.user_name);
        iAvatar.textContent = initials(c.user_name);
        iName.textContent = c.user_name;
        iRole.textContent = roleLabel(c.user_role) + (c.user_class ? ' • Kelas ' + c.user_class : '');
        iEmail.textContent = c.user_email || '—';
        iCreated.textContent = fmtDate(c.created_at);
        const tutup = c.status === 'closed';
        iStatus.textContent = tutup ? 'Arsip' : 'Aktif';
        iStatus.className = 'text-[11px] rounded-full px-2.5 py-0.5 font-medium ' + (tutup ? 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300' : 'bg-emerald-100 text-emerald-700');
        iMode.textContent = c.mode === 'human' ? 'Manusia' : 'Bot';
        iCStatus.textContent = statusLabel(c.status);
        iUnread.textContent = c.unread_count || 0;
    }

    // ---------- Chat panel ----------
    async function openConversation(c) {
        root.classList.add('has-active-chat');
        activeId = c.id;
        handled.add(c.id);
        unread.delete(c.id);
        cursor = null; seen.clear(); elMsgs.innerHTML = ''; lastDayKey = null; readWatermark = null;
        elEmpty.classList.add('hidden');
        elActive.classList.remove('hidden'); elActive.classList.add('flex');
        elUser.textContent = c.user_name;
        elStatus.textContent = statusLabel(c.status);
        elAvatar.className = 'h-10 w-10 rounded-full flex items-center justify-center text-white font-semibold ' + avatarColor(c.user_name);
        elAvatar.textContent = initials(c.user_name);
        fillInfo(c);
        applyReadonly(c.status === 'closed', c.closed_at);
        renderQueue();

        // Percakapan tertutup = arsip read-only: jangan auto-assign.
        if (c.status === 'waiting') {
            await fetch(msgBase + '/' + c.id + '/assign', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            }).then(r => r.ok ? r.json() : null).then(d => {
                if (d) { elStatus.textContent = statusLabel(d.status); iCStatus.textContent = statusLabel(d.status); }
            });
        }
        await loadMessages(true);
        if (filter === 'closed') loadHistory(); else loadQueue();
    }

    /** Atur tampilan baca-saja (arsip) vs aktif: sembunyikan composer & tombol aksi saat tertutup. */
    function applyReadonly(isClosed, closedAt) {
        elComposer.classList.toggle('hidden', isClosed);
        btnBack.classList.toggle('hidden', isClosed);
        btnClose.classList.toggle('hidden', isClosed);
        elArchived.classList.toggle('hidden', !isClosed);
        elArchived.classList.toggle('flex', isClosed);
        if (isClosed) {
            elArchivedText.textContent = 'Percakapan telah ditutup'
                + (closedAt ? ' • ' + fmtDate(closedAt) : '')
                + ' — tersimpan sebagai histori/bukti (hanya baca).';
        }
    }

    async function loadMessages(isInitial = false) {
        if (!activeId) return;
        try {
            const url = new URL(msgBase + '/' + activeId + '/messages', window.location.origin);
            if (cursor) url.searchParams.set('after', cursor);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const wasBottom = atBottom();
            let newUserMsg = 0;
            (data.messages || []).forEach(m => {
                if (!seen.has(m.id) && m.sender === 'user') newUserMsg++;
                addMessage(m);
            });
            elStatus.textContent = statusLabel(data.status);
            iCStatus.textContent = statusLabel(data.status);
            iMode.textContent = data.mode === 'human' ? 'Manusia' : 'Bot';
            applyRead(data.read_watermark);
            if (wasBottom) scrollBottom();
            if (!isInitial && newUserMsg > 0 && messageNotif) ding();
        } catch (_) {}
    }

    function applyRead(watermark) {
        if (watermark && (!readWatermark || watermark > readWatermark)) readWatermark = watermark;
        if (!readWatermark) return;
        elMsgs.querySelectorAll('.tick').forEach(t => {
            if (t.dataset.createdAt && t.dataset.createdAt <= readWatermark) {
                t.classList.remove('text-white/40');
                t.classList.add('text-white');
            }
        });
    }

    function addMessage(m) {
        if (seen.has(m.id)) return;
        seen.add(m.id);
        if (m.created_at && (cursor === null || m.created_at >= cursor)) cursor = m.created_at;
        appendDayChipIfNeeded(m.created_at);
        const wrap = document.createElement('div');
        const isAdmin = m.sender === 'admin';
        const isRight = isAdmin || m.sender === 'bot';
        wrap.className = 'flex ' + (isRight ? 'justify-end' : 'justify-start');
        const b = document.createElement('div');
        b.className = 'max-w-[72%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap break-words shadow-sm ' + (
            isAdmin             ? 'bg-primary-600 text-white rounded-br-md'
          : m.sender === 'user' ? 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-100 border border-slate-100 dark:border-slate-700 rounded-bl-md'
          :                       'bg-primary-50 dark:bg-slate-800 text-primary-900 dark:text-slate-100 border border-primary-100 dark:border-slate-700 rounded-br-md'
        );
        const who = document.createElement('div');
        who.className = 'text-[10px] mb-0.5 ' + (isAdmin ? 'text-primary-100' : 'text-slate-400');
        who.textContent = isAdmin ? 'Kamu (admin)' : (m.sender === 'user' ? 'Pengguna' : 'Bot');
        const body = document.createElement('div');
        const isImage = m.attachment_is_image === true ? true
                      : (m.attachment_is_image === false ? false : (!!m.attachment_url && !m.attachment_name));
        if (isImage && m.attachment_url) {
            const a = document.createElement('a');
            a.href = m.attachment_url; a.target = '_blank'; a.rel = 'noopener'; a.className = 'block';
            const img = document.createElement('img');
            img.src = m.attachment_url; img.alt = 'gambar';
            img.className = 'rounded-xl max-w-full max-h-60 object-cover' + (m.body ? ' mb-1.5' : '');
            a.appendChild(img); body.appendChild(a);
        } else if (m.attachment_name || m.attachment_url) {
            // Kartu file/dokumen (PDF/Word/Excel/dll) yang dikirim pengguna.
            const card = document.createElement('a');
            card.href = m.attachment_url || '#'; card.target = '_blank'; card.rel = 'noopener';
            if (m.attachment_url) card.setAttribute('download', m.attachment_name || '');
            card.className = 'flex items-center gap-2 rounded-xl bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 ring-1 ring-slate-200 dark:ring-slate-700 px-2.5 py-2 hover:ring-primary-300 ' + (m.body ? 'mb-1.5' : '');
            const ic = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            ic.setAttribute('viewBox', '0 0 24 24'); ic.setAttribute('fill', 'none');
            ic.setAttribute('stroke', 'currentColor'); ic.setAttribute('stroke-width', '1.6');
            ic.setAttribute('class', 'h-7 w-7 shrink-0 text-primary-500');
            ic.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>';
            const mt = document.createElement('div'); mt.className = 'min-w-0';
            const nm = document.createElement('p'); nm.className = 'text-xs font-medium truncate'; nm.textContent = m.attachment_name || 'Lampiran';
            const sub = document.createElement('p'); sub.className = 'text-[10px] text-slate-400'; sub.textContent = 'Ketuk untuk unduh';
            mt.appendChild(nm); mt.appendChild(sub); card.appendChild(ic); card.appendChild(mt); body.appendChild(card);
        }
        if (m.body) { const t = document.createElement('div'); t.textContent = m.body; body.appendChild(t); }
        const meta = document.createElement('div');
        meta.className = 'flex items-center justify-end gap-1 mt-1';
        const time = document.createElement('span');
        time.className = 'text-[10px] ' + (isAdmin ? 'text-primary-200' : 'text-slate-400');
        time.textContent = fmtTime(m.created_at);
        meta.appendChild(time);
        if (isAdmin) {
            const tick = document.createElement('span');
            tick.className = 'tick text-[11px] leading-none ' + (m.is_read ? 'text-white' : 'text-white/40');
            tick.style.letterSpacing = '-3px';
            tick.style.paddingRight = '2px';
            tick.dataset.createdAt = m.created_at || '';
            tick.textContent = '✓✓';
            meta.appendChild(tick);
        }
        b.appendChild(who); b.appendChild(body); b.appendChild(meta);
        wrap.appendChild(b); elMsgs.appendChild(wrap);
    }

    async function sendReply() {
        const text = elInput.value.trim();
        if (!text || !activeId) return;
        elSend.disabled = true;
        try {
            const res = await fetch(msgBase + '/' + activeId + '/reply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ body: text }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            addMessage(data.message); scrollBottom();
            if (data.status) { elStatus.textContent = statusLabel(data.status); iCStatus.textContent = statusLabel(data.status); }
            elInput.value = ''; autoSize();
        } catch (_) {
            $.alert({ title: 'Gagal', content: 'Gagal mengirim balasan, coba lagi.', type: 'red' });
        } finally {
            elSend.disabled = false; elInput.focus();
        }
    }

    // ---------- Emoji + gambar (admin) ----------
    const A_EMOJIS = ['😀','😄','😁','😅','🙂','😉','😊','😍','😎','🤩','🤔','😴','🙏','👍','👌','👏',
                      '💪','🙌','🤝','👋','🔥','✨','⭐','🎉','❤️','💜','✅','❌','💯','📌','📷','🖼️',
                      '📝','📚','🎓','💰','🗓️','⏰','📞','📩','🏫','🚀','🎯','💡','😢','😮','😬','🤗'];
    A_EMOJIS.forEach(e => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'h-7 w-7 rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 text-base leading-none flex items-center justify-center';
        b.textContent = e;
        b.onmousedown = (ev) => { ev.preventDefault(); aInsertAtCursor(e); };
        aEmojiPop.appendChild(b);
    });
    function aInsertAtCursor(text) {
        const s = elInput.selectionStart ?? elInput.value.length;
        const en = elInput.selectionEnd ?? elInput.value.length;
        elInput.value = elInput.value.slice(0, s) + text + elInput.value.slice(en);
        const pos = s + text.length;
        elInput.selectionStart = elInput.selectionEnd = pos;
        elInput.focus(); autoSize();
    }
    aBtnEmoji.addEventListener('click', (e) => { e.stopPropagation(); aEmojiPop.classList.toggle('hidden'); });
    document.addEventListener('click', (e) => {
        if (!aEmojiPop.contains(e.target) && e.target !== aBtnEmoji && !aBtnEmoji.contains(e.target)) aEmojiPop.classList.add('hidden');
    });

    aBtnAttach.addEventListener('click', () => { if (activeId) aFileInput.click(); });
    aFileInput.addEventListener('change', async () => {
        const file = aFileInput.files && aFileInput.files[0];
        aFileInput.value = '';
        if (!file || !file.type.startsWith('image/')) return;
        try { aSetPending(await compressImage(file, 1280, 0.7)); } catch (_) { $.alert({ title: 'Gagal', content: 'Gagal memproses gambar.', type: 'red' }); }
    });
    aImgRemove.addEventListener('click', aClearPending);

    function aSetPending(blob) {
        aClearPending(); aClearPendingFile();
        aPendingBlob = blob;
        aPendingUrl = URL.createObjectURL(blob);
        aImgPreviewEl.src = aPendingUrl;
        aImgSize.textContent = (blob.size / 1024).toFixed(0) + ' KB (terkompres)';
        aImgPreview.classList.remove('hidden'); aImgPreview.classList.add('flex');
    }
    function aClearPending() {
        if (aPendingUrl) URL.revokeObjectURL(aPendingUrl);
        aPendingBlob = null; aPendingUrl = null;
        aImgPreview.classList.add('hidden'); aImgPreview.classList.remove('flex');
        aImgPreviewEl.src = '';
    }

    // ---------- Admin lampirkan file/dokumen (tanpa kompresi, dibatasi ukuran) ----------
    aBtnFile.addEventListener('click', () => { if (activeId) aFileInputDoc.click(); });
    aFileInputDoc.addEventListener('change', () => {
        const file = aFileInputDoc.files && aFileInputDoc.files[0];
        aFileInputDoc.value = '';
        if (!file) return;
        if (file.size > A_MAX_FILE_BYTES) { $.alert({ title: 'File Terlalu Besar', content: 'Ukuran file maksimal 5 MB.', type: 'orange' }); return; }
        aSetPendingFile(file);
    });
    aFileRemove.addEventListener('click', aClearPendingFile);

    function aSetPendingFile(file) {
        aClearPending(); aClearPendingFile();
        aPendingFile = file;
        aFileNameEl.textContent = file.name;
        const kb = file.size / 1024;
        aFileSizeEl.textContent = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb.toFixed(0) + ' KB';
        aFilePreview.classList.remove('hidden'); aFilePreview.classList.add('flex');
    }
    function aClearPendingFile() {
        aPendingFile = null;
        aFilePreview.classList.add('hidden'); aFilePreview.classList.remove('flex');
        aFileNameEl.textContent = '—'; aFileSizeEl.textContent = '—';
    }

    // Skala gambar ke sisi terpanjang `maxDim`, ekspor JPEG kualitas `quality`.
    function compressImage(file, maxDim, quality) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                let { width, height } = img;
                if (width > height && width > maxDim) { height = Math.round(height * maxDim / width); width = maxDim; }
                else if (height >= width && height > maxDim) { width = Math.round(width * maxDim / height); height = maxDim; }
                const canvas = document.createElement('canvas');
                canvas.width = width; canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, width, height);
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob(b => b ? resolve(b) : reject(new Error('toBlob gagal')), 'image/jpeg', quality);
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('muat gambar gagal')); };
            img.src = url;
        });
    }

    async function sendImage() {
        if (!aPendingBlob || !activeId) return;
        const caption = elInput.value.trim();
        const blob = aPendingBlob;
        aClearPending();
        elSend.disabled = true;
        try {
            const fd = new FormData();
            fd.append('image', blob, 'image.jpg');
            if (caption) fd.append('caption', caption);
            const res = await fetch(msgBase + '/' + activeId + '/reply-image', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            addMessage(data.message); scrollBottom();
            if (data.status) { elStatus.textContent = statusLabel(data.status); iCStatus.textContent = statusLabel(data.status); }
            elInput.value = ''; autoSize();
        } catch (_) {
            $.alert({ title: 'Gagal', content: 'Gagal mengirim gambar, coba lagi.', type: 'red' });
        } finally {
            elSend.disabled = false; elInput.focus();
        }
    }

    async function sendFile() {
        if (!aPendingFile || !activeId) return;
        const caption = elInput.value.trim();
        const file = aPendingFile;
        aClearPendingFile();
        elSend.disabled = true;
        try {
            const fd = new FormData();
            fd.append('file', file, file.name);
            if (caption) fd.append('caption', caption);
            const res = await fetch(msgBase + '/' + activeId + '/reply-file', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            addMessage(data.message); scrollBottom();
            if (data.status) { elStatus.textContent = statusLabel(data.status); iCStatus.textContent = statusLabel(data.status); }
            elInput.value = ''; autoSize();
        } catch (_) {
            $.alert({ title: 'Gagal', content: 'Gagal mengirim file, coba lagi.', type: 'red' });
        } finally {
            elSend.disabled = false; elInput.focus();
        }
    }

    function dispatchSend() {
        if (aPendingBlob) sendImage();
        else if (aPendingFile) sendFile();
        else sendReply();
    }

    async function backToBot() {
        if (!activeId) return;
        btnBack.disabled = true;
        try {
            const res = await fetch(msgBase + '/' + activeId + '/back-to-bot', {
                method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (res.ok) {
                const d = await res.json();
                elStatus.textContent = statusLabel(d.status);
                iCStatus.textContent = statusLabel(d.status);
                iMode.textContent = d.mode === 'human' ? 'Manusia' : 'Bot';
                await loadMessages(); loadQueue();
            }
        } finally { btnBack.disabled = false; }
    }

    function closeChat() {
        if (!activeId) return;
        $.confirm({
            title: 'Selesaikan percakapan?',
            content: 'Tutup percakapan ini? Semua pesan tetap tersimpan sebagai histori/bukti.',
            type: 'orange',
            buttons: {
                ya: { text: 'Ya, Selesaikan', btnClass: 'btn-orange', keys: ['enter'], action: async () => {
                    btnClose.disabled = true;
                    try {
                        const res = await fetch(msgBase + '/' + activeId + '/close', {
                            method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        if (res.ok) {
                            const d = await res.json();
                            // Tampilkan pesan penutup yang baru lalu alihkan panel ke mode arsip (read-only).
                            await loadMessages();
                            elStatus.textContent = statusLabel(d.status);
                            iCStatus.textContent = statusLabel(d.status);
                            applyReadonly(true, d.closed_at);
                            // Singkirkan dari antrian aktif & segarkan arsip.
                            allList = allList.filter(c => c.id !== activeId);
                            closedLoaded = false;
                            renderQueue(); updateCounts(lastWaiting || 0);
                            loadQueue(); loadHistory();
                        } else {
                            $.alert({ title: 'Gagal', content: 'Gagal menutup percakapan, coba lagi.', type: 'red' });
                        }
                    } finally { btnClose.disabled = false; }
                } },
                batal: { text: 'Batal' }
            }
        });
    }

    function deleteChat(idToDelete) {
        if (!idToDelete) return;
        $.confirm({
            title: 'Hapus percakapan permanen?',
            content: 'Hapus percakapan ini secara permanen? Semua pesan dan lampiran akan terhapus dan tidak dapat dikembalikan.',
            type: 'red',
            buttons: {
                hapus: { text: 'Ya, Hapus', btnClass: 'btn-red', keys: ['enter'], action: async () => {
                    try {
                        const res = await fetch(msgBase + '/' + idToDelete, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        if (res.ok) {
                            if (activeId === idToDelete) {
                                activeId = null;
                                root.classList.remove('has-active-chat');
                                elActive.classList.add('hidden');
                                elActive.classList.remove('flex');
                                elEmpty.classList.remove('hidden');

                                infoActive.classList.add('hidden');
                                infoEmpty.classList.remove('hidden');
                            }

                            allList = allList.filter(c => c.id !== idToDelete);
                            closedList = closedList.filter(c => c.id !== idToDelete);

                            renderQueue();
                            updateCounts(lastWaiting || 0);

                            if (filter === 'closed') loadHistory(); else loadQueue();
                        } else {
                            $.alert({ title: 'Gagal', content: 'Gagal menghapus percakapan, coba lagi.', type: 'red' });
                        }
                    } catch (_) {
                        $.alert({ title: 'Gagal', content: 'Gagal menghapus percakapan, coba lagi.', type: 'red' });
                    }
                } },
                batal: { text: 'Batal' }
            }
        });
    }

    // ---------- Settings ----------
    async function saveSettings() {
        notifEnabled = tglNotif.checked;
        soundEnabled = tglSound.checked;
        messageNotif = tglMsg.checked;
        try {
            await fetch(settingsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    notif_enabled: notifEnabled,
                    sound_enabled: soundEnabled,
                    message_notif_enabled: messageNotif,
                }),
            });
        } catch (_) {}
        loadQueue();
    }
    tglNotif.addEventListener('change', saveSettings);
    tglSound.addEventListener('change', saveSettings);
    tglMsg.addEventListener('change', saveSettings);

    btnSettings.addEventListener('click', (e) => { e.stopPropagation(); settingsMenu.classList.toggle('hidden'); });
    document.addEventListener('click', (e) => {
        if (!settingsMenu.contains(e.target) && e.target !== btnSettings) settingsMenu.classList.add('hidden');
    });

    // ---------- Avatar update ----------
    const avatarSelectUrl = "{{ route('chatbot.admin.settings.avatar') }}";
    document.querySelectorAll('.avatar-select-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const avatarType = btn.dataset.avatar;
            try {
                const res = await fetch(avatarSelectUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ avatar_type: avatarType }),
                });
                if (res.ok) {
                    const data = await res.json();
                    
                    // Update highlighting
                    document.querySelectorAll('.avatar-select-btn').forEach(b => {
                        b.classList.remove('border-primary-500', 'ring-2', 'ring-primary-300');
                        b.classList.add('border-transparent');
                    });
                    btn.classList.add('border-primary-500', 'ring-2', 'ring-primary-300');
                    btn.classList.remove('border-transparent');
                    
                    // Update brand logo in top bar
                    const container = document.getElementById('brand-avatar-container');
                    if (container) {
                        const img = document.getElementById('brand-avatar-img');
                        const fallback = document.getElementById('brand-avatar-fallback');
                        if (img) img.remove();
                        if (fallback) fallback.remove();
                        
                        if (data.avatar_type !== 'default') {
                            const newImg = document.createElement('img');
                            newImg.id = 'brand-avatar-img';
                            newImg.src = data.avatar_url;
                            newImg.className = 'h-9 w-9 rounded-xl object-cover shadow-sm bg-white';
                            newImg.alt = 'Avatar';
                            container.prepend(newImg);
                        } else {
                            const newFallback = document.createElement('div');
                            newFallback.id = 'brand-avatar-fallback';
                            newFallback.className = 'h-9 w-9 rounded-xl bg-gradient-to-br from-primary to-primary-700 flex items-center justify-center text-white font-black text-lg';
                            newFallback.textContent = 'A';
                            container.prepend(newFallback);
                        }
                    }
                } else {
                    $.alert({ title: 'Gagal', content: 'Gagal memperbarui maskot.', type: 'red' });
                }
            } catch (_) {
                $.alert({ title: 'Gagal', content: 'Terjadi kesalahan saat memperbarui maskot.', type: 'red' });
            }
        });
    });

    // ---------- Pill filter + search ----------
    navBtns.forEach(b => b.addEventListener('click', () => {
        filter = b.dataset.filter;
        navBtns.forEach(x => {
            x.classList.remove('bg-primary-600', 'text-white');
            x.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-500', 'dark:text-slate-300');
        });
        b.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-500', 'dark:text-slate-300');
        b.classList.add('bg-primary-600', 'text-white');
        renderQueue();
        // Muat arsip saat pertama kali membuka tab "Selesai".
        if (filter === 'closed' && !closedLoaded) loadHistory();
    }));
    elSearch.addEventListener('input', () => { search = elSearch.value.trim().toLowerCase(); renderQueue(); });

    // ---------- Resize & collapse ----------
    function clampQueueWidth(value) {
        const max = Math.max(320, Math.floor(root.clientWidth * 0.58));
        return Math.min(max, Math.max(260, value));
    }

    function applyQueueWidth(value, persist = false) {
        if (!queueSidebar) return;
        const width = clampQueueWidth(value);
        queueSidebar.style.width = width + 'px';
        queueSidebar.style.flexBasis = width + 'px';
        if (persist) localStorage.setItem('chat_inbox_queue_width', String(width));
    }

    function initQueueResize() {
        if (!queueSidebar || !queueResizer) return;
        applyQueueWidth(parseInt(localStorage.getItem('chat_inbox_queue_width') || '320', 10) || 320);
        queueResizer.addEventListener('dblclick', () => applyQueueWidth(320, true));
        queueResizer.addEventListener('pointerdown', (e) => {
            if (window.matchMedia('(max-width: 767.98px)').matches) return;
            e.preventDefault();
            const startX = e.clientX;
            const startW = queueSidebar.getBoundingClientRect().width;
            queueResizer.classList.add('is-dragging');
            const move = (ev) => applyQueueWidth(startW + ev.clientX - startX);
            const up = () => {
                localStorage.setItem('chat_inbox_queue_width', String(Math.round(queueSidebar.getBoundingClientRect().width)));
                queueResizer.classList.remove('is-dragging');
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
            };
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up, { once:true });
        });
    }

    function renderChatCollapse() {
        elActive.classList.toggle('chat-collapsed', chatCollapsed);
        if (btnToggleChatCollapse) {
            btnToggleChatCollapse.title = chatCollapsed ? 'Buka percakapan' : 'Ciutkan percakapan';
            btnToggleChatCollapse.innerHTML = chatCollapsed
                ? '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 9l7 7 7-7"/></svg>'
                : '<svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 15l-7-7-7 7"/></svg>';
        }
        if (!chatCollapsed) scrollBottom();
        if (window.lucide) window.lucide.createIcons();
    }

    function toggleChatCollapse() {
        chatCollapsed = !chatCollapsed;
        localStorage.setItem('chat_inbox_conversation_collapsed', chatCollapsed ? '1' : '0');
        renderChatCollapse();
    }

    // ---------- Helpers ----------
    function statusLabel(s) {
        return { waiting: 'Menunggu', assigned: 'Sedang ditangani', active: 'Kembali ke bot', closed: 'Ditutup' }[s] || s;
    }
    function atBottom() { return elMsgs.scrollHeight - elMsgs.scrollTop - elMsgs.clientHeight < 60; }
    function scrollBottom() { elMsgs.scrollTop = elMsgs.scrollHeight; }
    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
    function autoSize() { elInput.style.height = 'auto'; elInput.style.height = Math.min(elInput.scrollHeight, 112) + 'px'; }

    elInput.addEventListener('input', autoSize);
    elInput.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); dispatchSend(); } });
    elSend.addEventListener('click', dispatchSend);
    btnBack.addEventListener('click', backToBot);
    btnClose.addEventListener('click', closeChat);
    if (btnToggleChatCollapse) btnToggleChatCollapse.addEventListener('click', toggleChatCollapse);

    btnChatBack.addEventListener('click', () => {
        activeId = null;
        root.classList.remove('has-active-chat');
        elActive.classList.add('hidden');
        elActive.classList.remove('flex');
        elEmpty.classList.remove('hidden');

        infoActive.classList.add('hidden');
        infoEmpty.classList.remove('hidden');

        renderQueue();
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown-container')) {
            document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.add('hidden'));
            document.querySelectorAll('.dropdown-container').forEach(c => c.classList.remove('opacity-100', 'is-open'));
        }
    });

    // ---------- Manage Quick Questions Modal ----------
    btnQuickQuestions.addEventListener('click', () => {
        modalQuickQuestions.classList.remove('hidden');
    });

    const closeQuickModal = () => {
        modalQuickQuestions.classList.add('hidden');
    };
    btnCloseQuickModal.addEventListener('click', closeQuickModal);
    btnCancelQuick.addEventListener('click', closeQuickModal);

    // Remove question row
    quickQuestionsList.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remove-quick');
        if (btn) {
            btn.closest('.quick-question-row').remove();
        }
    });

    // Add question row (pertanyaan + jawaban)
    btnAddQuick.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'flex items-start gap-2 quick-question-row rounded-xl border border-slate-200 dark:border-slate-700 p-2.5';
        row.innerHTML = `
            <div class="flex-1 space-y-2">
                <input type="text" value="" class="quick-question-input w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="Pertanyaan (mis. Bagaimana cara absensi QR?)">
                <textarea rows="2" class="quick-answer-input w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-200 text-xs px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-y" placeholder="Jawaban bot (kosongkan bila ditangani sistem / handoff ke admin)"></textarea>
            </div>
            <button type="button" class="btn-remove-quick h-9 w-9 shrink-0 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 flex items-center justify-center transition">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        `;
        quickQuestionsList.appendChild(row);
        row.querySelector('input').focus();
    });

    // Save questions via fetch API
    const saveQuickQuestionsUrl = "{{ route('chatbot.admin.settings.quick-questions') }}";
    btnSaveQuick.addEventListener('click', async () => {
        btnSaveQuick.disabled = true;
        const rows = quickQuestionsList.querySelectorAll('.quick-question-row');
        const questions = Array.from(rows).map(row => ({
            q: row.querySelector('.quick-question-input').value.trim(),
            a: (row.querySelector('.quick-answer-input')?.value || '').trim(),
        })).filter(item => item.q !== '');

        try {
            const res = await fetch(saveQuickQuestionsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ questions: questions }),
            });
            if (res.ok) {
                closeQuickModal();
            } else {
                $.alert({ title: 'Gagal', content: 'Gagal menyimpan pertanyaan bantuan.', type: 'red' });
            }
        } catch (_) {
            $.alert({ title: 'Gagal', content: 'Terjadi kesalahan saat menyimpan.', type: 'red' });
        } finally {
            btnSaveQuick.disabled = false;
        }
    });

    // ---------- Polling ----------
    function tick() { loadQueue(); if (activeId) loadMessages(); }
    function start() { if (!pollTimer) pollTimer = setInterval(tick, pollEvery); }
    function stop()  { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') { tick(); start(); } else stop();
    });
    window.addEventListener('beforeunload', stop);

    // ---------- Init ----------
    initQueueResize();
    renderChatCollapse();
    loadQueue();
    if (document.visibilityState === 'visible') start();
})();
</script>
@endsection
