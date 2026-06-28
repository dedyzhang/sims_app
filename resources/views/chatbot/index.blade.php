@extends('layouts.chatbot')

@section('title', 'Asisten Sekolah')

@section('content')
<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
<div class="h-screen flex flex-col bg-white dark:bg-slate-900">
    <div class="flex-1 min-h-0 flex flex-col"
         id="chat-widget"
         data-conversation-id="{{ $conversation->id }}"
         data-mode="{{ $conversation->mode }}"
         data-status="{{ $conversation->status }}"
         data-poll-interval="{{ $pollInterval }}"
         data-send-url="{{ route('chatbot.send') }}"
         data-upload-url="{{ route('chatbot.upload') }}"
         data-upload-file-url="{{ route('chatbot.upload-file') }}"
         data-poll-url="{{ route('chatbot.poll') }}"
         data-request-human-url="{{ url('/chatbot/'.$conversation->id.'/request-human') }}"
         data-back-to-bot-url="{{ url('/chatbot/'.$conversation->id.'/back-to-bot') }}"
         data-read-watermark="{{ $readWatermark }}">

        {{-- Header --}}
        <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-gradient-to-r from-primary to-primary-700">
            @if($avatarType !== 'default')
                <img src="{{ asset('images/chatbot/avatar_' . $avatarType . '.png') }}" class="h-9 w-9 rounded-full object-cover shadow-sm bg-white" alt="Avatar">
            @else
                <div class="h-9 w-9 rounded-full bg-white/20 flex items-center justify-center text-white text-lg">🎓</div>
            @endif
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold leading-tight text-white">Asisten Sekolah</p>
                <p class="text-xs text-white/80" id="mode-badge"></p>
            </div>
            {{-- Tombol tutup: kirim pesan ke parent (floating panel) bila di-embed dalam iframe. --}}
            <button type="button" onclick="parent.postMessage('chatfab:close','*')"
                    class="h-7 w-7 rounded-lg text-white/80 hover:text-white hover:bg-white/15 flex items-center justify-center transition" title="Tutup">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mode action bar (Phase 6 mengisi tombol di sini) --}}
        <div id="action-bar" class="px-4 py-2 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hidden"></div>

        {{-- Messages --}}
        <div id="messages" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-slate-50/40 dark:bg-slate-900"></div>

        {{-- Typing indicator --}}
        <div id="typing" class="px-4 pb-1 text-xs text-slate-400 hidden">Bot sedang mengetik…</div>

        {{-- Network error --}}
        <div id="net-error" class="px-4 pb-1 text-xs text-red-500 hidden">Gagal mengirim, coba lagi.</div>

        {{-- Composer (TANPA <form> — kirim via fetch) --}}
        <div class="p-3 border-t border-slate-100 dark:border-slate-700 relative">
            {{-- Pratinjau gambar terpilih --}}
            <div id="img-preview" class="hidden mb-2 flex items-center gap-2">
                <div class="relative">
                    <img id="img-preview-el" alt="pratinjau" class="h-16 w-16 object-cover rounded-xl border border-slate-200">
                    <button id="img-remove" type="button"
                            class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-slate-700 text-white text-[11px] leading-none flex items-center justify-center hover:bg-slate-900">✕</button>
                </div>
                <span id="img-size" class="text-[11px] text-slate-400"></span>
            </div>

            {{-- Pratinjau file/dokumen terpilih --}}
            <div id="file-preview" class="hidden mb-2 items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2">
                <svg class="h-7 w-7 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                </svg>
                <div class="min-w-0 flex-1">
                    <p id="file-name" class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">—</p>
                    <p id="file-size" class="text-[11px] text-slate-400">—</p>
                </div>
                <button id="file-remove" type="button"
                        class="h-6 w-6 shrink-0 rounded-full bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-200 text-[11px] leading-none flex items-center justify-center hover:bg-slate-300 dark:hover:bg-slate-500">✕</button>
            </div>

            {{-- Quick suggestion chips (akses cepat saat percakapan sudah berjalan;
                 saat kosong, pertanyaan tampil prominent di welcome screen) --}}
            <div id="quick-chips" class="hidden gap-2 overflow-x-auto no-scrollbar pb-2 px-1 whitespace-nowrap">
                @foreach($quickQuestions as $q)
                    <button type="button" class="btn-quick-chip shrink-0 px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition" data-question="{{ $q }}">
                        {{ $q }}
                    </button>
                @endforeach
            </div>

            <div class="rounded-2xl bg-slate-100 dark:bg-slate-800 px-3 pt-2 pb-1.5">
                <textarea id="composer" rows="1" placeholder="Tulis pesan…"
                          class="w-full resize-none border-0 p-0 text-sm bg-transparent text-slate-800 dark:text-slate-100 focus:ring-0 focus:outline-none placeholder:text-slate-400 dark:placeholder:text-slate-500 max-h-28"></textarea>
                <div class="flex items-center gap-1 mt-1">
                    <button id="btn-attach" type="button" title="Kirim gambar"
                            class="h-8 w-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-primary-600 flex items-center justify-center transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 15l-5-5L5 21M3 16.5V7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <circle cx="8.5" cy="9.5" r="1.5"/>
                        </svg>
                    </button>
                    <button id="btn-file" type="button" title="Lampirkan file (PDF/Word/Excel)"
                            class="h-8 w-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-primary-600 flex items-center justify-center transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05l-9.19 9.19a5 5 0 01-7.07-7.07l9.19-9.19a3.5 3.5 0 014.95 4.95l-9.2 9.19a1.5 1.5 0 01-2.12-2.12l8.49-8.49"/>
                        </svg>
                    </button>
                    <button id="btn-emoji" type="button" title="Emoji"
                            class="h-8 w-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary-600 flex items-center justify-center transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                            <circle cx="12" cy="12" r="9"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 10h.01M15 10h.01M8.5 14.5a4 4 0 007 0"/>
                        </svg>
                    </button>
                    <button id="send-btn" type="button" title="Kirim"
                            class="ml-auto h-9 w-9 rounded-full bg-primary-600 text-white flex items-center justify-center hover:bg-primary-700 disabled:opacity-50 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </button>
                </div>
            </div>

            <input id="file-input" type="file" accept="image/*" class="hidden">
            <input id="file-input-doc" type="file"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                   class="hidden">

            {{-- Emoji picker --}}
            <div id="emoji-pop" class="hidden absolute left-3 w-64 max-w-[calc(100%-1.5rem)] bottom-[88px] z-20 bg-white dark:bg-slate-800 rounded-2xl shadow-xl ring-1 ring-slate-100 dark:ring-slate-700 p-2 max-h-44 overflow-y-auto grid grid-cols-8 gap-0.5"></div>

            <p class="mt-1 text-[11px] text-slate-400">Tekan Enter untuk kirim, Shift+Enter untuk baris baru.</p>
        </div>
    </div>
</div>

<script id="initial-messages" type="application/json">@json($messages)</script>
<script>
(() => {
    const widget   = document.getElementById('chat-widget');
    const csrf     = document.querySelector('meta[name="csrf-token"]').content;
    const elMsgs   = document.getElementById('messages');
    const elTyping = document.getElementById('typing');
    const elError  = document.getElementById('net-error');
    const elBadge  = document.getElementById('mode-badge');
    const elActions= document.getElementById('action-bar');
    const composer = document.getElementById('composer');
    const sendBtn  = document.getElementById('send-btn');
    const btnAttach = document.getElementById('btn-attach');
    const btnEmoji  = document.getElementById('btn-emoji');
    const emojiPop  = document.getElementById('emoji-pop');
    const fileInput = document.getElementById('file-input');
    const btnFile   = document.getElementById('btn-file');
    const fileInputDoc = document.getElementById('file-input-doc');
    const imgPreview   = document.getElementById('img-preview');
    const imgPreviewEl = document.getElementById('img-preview-el');
    const imgRemove    = document.getElementById('img-remove');
    const imgSize      = document.getElementById('img-size');
    const filePreview  = document.getElementById('file-preview');
    const fileNameEl   = document.getElementById('file-name');
    const fileSizeEl   = document.getElementById('file-size');
    const fileRemove   = document.getElementById('file-remove');

    const conversationId = widget.dataset.conversationId;
    const sendUrl   = widget.dataset.sendUrl;
    const uploadUrl = widget.dataset.uploadUrl;
    const uploadFileUrl = widget.dataset.uploadFileUrl;
    const pollUrl   = widget.dataset.pollUrl;
    const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB
    const pollEvery = parseInt(widget.dataset.pollInterval, 10) || 5000;

    let mode   = widget.dataset.mode;
    let status = widget.dataset.status;
    const seen = new Set();   // dedup per message id
    let cursor = null;        // created_at terakhir yang diketahui
    let readWatermark = widget.dataset.readWatermark || null; // batas "sudah dibaca" pesan user
    let pollTimer = null;
    let pendingBlob = null;   // gambar terkompres siap kirim
    let pendingUrl  = null;   // object URL untuk pratinjau lokal
    let pendingFile = null;   // file/dokumen mentah siap kirim (tanpa kompresi)

    // ---------- Audio notifikasi pesan masuk (Web Audio API) ----------
    let audioCtx = null;
    function primeAudio() {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
        } catch (_) {}
    }
    // Browser memblokir audio sampai user berinteraksi; prime saat klik/ketik.
    document.addEventListener('click', primeAudio);
    document.addEventListener('keydown', primeAudio);
    function beep() {
        try {
            primeAudio();
            if (!audioCtx) return;
            const t = audioCtx.currentTime;
            const o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.connect(g); g.connect(audioCtx.destination);
            o.type = 'sine'; o.frequency.value = 660;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.2, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.3);
            o.start(t); o.stop(t + 0.32);
        } catch (_) {}
    }

    // ---------- Rendering ----------
    function atBottom() {
        return elMsgs.scrollHeight - elMsgs.scrollTop - elMsgs.clientHeight < 60;
    }
    function scrollToBottom() { elMsgs.scrollTop = elMsgs.scrollHeight; }
    // Escape teks untuk disisipkan via innerHTML (mis. pertanyaan cepat di welcome screen).
    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    // ---------- Waktu & pemisah hari ----------
    const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
                   'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    let lastDayKey = null;

    function fmtTime(iso) {
        const d = iso ? new Date(iso) : new Date();
        return String(d.getHours()).padStart(2, '0') + '.' + String(d.getMinutes()).padStart(2, '0');
    }
    function dayLabel(d) {
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const md = new Date(d); md.setHours(0, 0, 0, 0);
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
        wrap.className = 'flex justify-center my-2';
        const chip = document.createElement('span');
        chip.className = 'text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300 rounded-full px-2.5 py-0.5';
        chip.textContent = dayLabel(d);
        wrap.appendChild(chip);
        elMsgs.appendChild(wrap);
    }

    function bubble(msg) {
        const wrap = document.createElement('div');
        const isUser = msg.sender === 'user';
        if (msg.id) wrap.dataset.mid = msg.id;
        wrap.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');

        const b = document.createElement('div');
        b.className = 'max-w-[80%] rounded-2xl px-3 py-2 text-sm whitespace-pre-wrap break-words shadow-sm ' + (
            isUser        ? 'bg-primary-600 text-white rounded-br-sm'
          : msg.sender === 'admin' ? 'bg-primary-50 dark:bg-slate-800 text-primary-900 dark:text-slate-100 border border-primary-200 dark:border-slate-700 rounded-bl-sm'
          :                 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-bl-sm'
        );
        // Tentukan jenis lampiran: gambar (tampilkan pratinjau) vs file/dokumen (kartu unduh).
        let isImage;
        if (msg.attachment_is_image === true) isImage = true;
        else if (msg.attachment_is_image === false) isImage = false;
        else isImage = !!msg.attachment_url && !msg.attachment_name; // fallback bubble optimistik gambar

        if (isImage && msg.attachment_url) {
            const a = document.createElement('a');
            a.href = msg.attachment_url; a.target = '_blank'; a.rel = 'noopener';
            a.className = 'block';
            const img = document.createElement('img');
            img.src = msg.attachment_url;
            img.alt = 'gambar';
            img.className = 'rounded-xl max-w-full max-h-60 object-cover' + (msg.body ? ' mb-1.5' : '');
            a.appendChild(img); b.appendChild(a);
        } else if (msg.attachment_name || msg.attachment_url) {
            // Kartu file/dokumen (PDF/Word/Excel/dll).
            const card = document.createElement(msg.attachment_url ? 'a' : 'div');
            if (msg.attachment_url) {
                card.href = msg.attachment_url; card.target = '_blank'; card.rel = 'noopener';
                card.setAttribute('download', msg.attachment_name || '');
            }
            card.className = 'flex items-center gap-2 rounded-xl bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 ring-1 ring-slate-200 dark:ring-slate-700 px-2.5 py-2 ' +
                             (msg.body ? 'mb-1.5 ' : '') + (msg.attachment_url ? 'hover:ring-primary-300' : 'opacity-80');
            const ic = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            ic.setAttribute('viewBox', '0 0 24 24'); ic.setAttribute('fill', 'none');
            ic.setAttribute('stroke', 'currentColor'); ic.setAttribute('stroke-width', '1.6');
            ic.setAttribute('class', 'h-7 w-7 shrink-0 text-primary-500');
            ic.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>';
            const meta = document.createElement('div'); meta.className = 'min-w-0';
            const nm = document.createElement('p'); nm.className = 'text-xs font-medium truncate';
            nm.textContent = msg.attachment_name || 'Lampiran';
            const sub = document.createElement('p'); sub.className = 'text-[10px] text-slate-400';
            sub.textContent = msg.attachment_url ? 'Ketuk untuk unduh' : 'Mengunggah…';
            meta.appendChild(nm); meta.appendChild(sub);
            card.appendChild(ic); card.appendChild(meta);
            b.appendChild(card);
        }
        if (msg.body) {
            const body = document.createElement('div');
            body.textContent = msg.body;
            b.appendChild(body);
        }

        if (msg.sender === 'admin') {
            const tag = document.createElement('div');
            tag.className = 'text-[10px] font-medium text-primary-500 mb-0.5';
            tag.textContent = 'Admin sekolah';
            b.prepend(tag);
        }

        const meta = document.createElement('div');
        meta.className = 'flex items-center justify-end gap-1 mt-1';
        const time = document.createElement('span');
        time.className = 'text-[10px] ' + (isUser ? 'text-primary-200' : 'text-slate-400');
        time.textContent = fmtTime(msg.created_at);
        meta.appendChild(time);
        if (isUser) {
            // Centang: pudar = terkirim/belum dibaca, putih terang = sudah dibaca.
            const tick = document.createElement('span');
            tick.className = 'tick text-[11px] leading-none ' + (msg.is_read ? 'text-white' : 'text-white/50');
            tick.style.letterSpacing = '-3px';
            tick.style.paddingRight = '2px';
            tick.dataset.createdAt = msg.created_at || '';
            tick.textContent = '✓✓';
            meta.appendChild(tick);
        }
        b.appendChild(meta);

        wrap.appendChild(b);
        return wrap;
    }

    // Ubah centang pesan user menjadi biru sampai batas "sudah dibaca".
    function applyRead(watermark) {
        if (watermark && (!readWatermark || watermark > readWatermark)) readWatermark = watermark;
        if (!readWatermark) return;
        elMsgs.querySelectorAll('.tick').forEach(t => {
            if (t.dataset.createdAt && t.dataset.createdAt <= readWatermark) {
                t.classList.remove('text-white/50');
                t.classList.add('text-white');
            }
        });
    }

    const quickQuestions = @json($quickQuestions);
    const userName = "{{ auth()->user()?->siswa?->nama ?? auth()->user()?->guru?->nama ?? auth()->user()?->username }}";

    function renderWelcomeScreen() {
        elMsgs.innerHTML = '';
        // Welcome screen sudah menampilkan pertanyaan; sembunyikan chip row agar tidak dobel.
        const chips = document.getElementById('quick-chips');
        if (chips) { chips.classList.add('hidden'); chips.classList.remove('flex'); }

        const welcomeWrap = document.createElement('div');
        welcomeWrap.id = 'welcome-screen';
        welcomeWrap.className = 'flex flex-col items-center justify-center py-6 px-4 text-center my-auto w-full';
        
        const avatarWrap = document.createElement('div');
        avatarWrap.className = 'mb-3';
        @if($avatarType !== 'default')
            avatarWrap.innerHTML = `<img src="{{ asset('images/chatbot/avatar_' . $avatarType . '.png') }}" class="h-14 w-14 rounded-full object-cover shadow-sm mx-auto bg-white border border-slate-100" alt="Avatar">`;
        @else
            avatarWrap.innerHTML = `<div class="h-14 w-14 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 text-2xl mx-auto shadow-sm">🎓</div>`;
        @endif
        
        const title = document.createElement('h2');
        title.className = 'text-sm font-bold text-slate-700 dark:text-slate-200';
        title.textContent = `Halo, ${userName} 👋`;
        
        const subtitle = document.createElement('p');
        subtitle.className = 'text-xs text-slate-400 mt-0.5 mb-4';
        subtitle.textContent = 'Ada yang bisa saya bantu?';
        
        welcomeWrap.appendChild(avatarWrap);
        welcomeWrap.appendChild(title);
        welcomeWrap.appendChild(subtitle);
        
        const questionsContainer = document.createElement('div');
        questionsContainer.className = 'w-full max-w-xs space-y-2 text-left';
        
        quickQuestions.forEach(q => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full flex items-center justify-between text-left px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-700/50 transition shadow-sm group';
            btn.innerHTML = `
                <span class="flex-1 pr-2 truncate">${escapeHtml(q)}</span>
                <span class="text-slate-400 group-hover:text-primary transition shrink-0">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </span>
            `;
            btn.onclick = () => {
                composer.value = q;
                dispatchSend();
            };
            questionsContainer.appendChild(btn);
        });
        
        welcomeWrap.appendChild(questionsContainer);
        elMsgs.appendChild(welcomeWrap);
    }

    function addMessage(msg, keepScroll = false) {
        if (!msg || seen.has(msg.id)) return;
        
        const welcome = document.getElementById('welcome-screen');
        if (welcome) welcome.remove();
        // Percakapan sudah berjalan → munculkan chip row untuk akses cepat.
        const chips = document.getElementById('quick-chips');
        if (chips && chips.classList.contains('hidden')) { chips.classList.remove('hidden'); chips.classList.add('flex'); }

        seen.add(msg.id);
        const wasBottom = atBottom();
        appendDayChipIfNeeded(msg.created_at);
        elMsgs.appendChild(bubble(msg));
        if (msg.created_at && (cursor === null || msg.created_at >= cursor)) cursor = msg.created_at;
        if (!keepScroll || wasBottom) scrollToBottom();
    }

    // ---------- Mode UI ----------
    function renderModeUI() {
        widget.dataset.mode = mode;
        widget.dataset.status = status;
        if (mode === 'human' && status === 'waiting') {
            elBadge.textContent = '⏳ Menunggu admin…';
            elBadge.className = 'text-xs text-amber-100';
        } else if (mode === 'human') {
            elBadge.textContent = '👤 Terhubung ke Admin';
            elBadge.className = 'text-xs text-white/90';
        } else {
            elBadge.textContent = '🤖 Asisten Otomatis';
            elBadge.className = 'text-xs text-white/80';
        }
        renderActions();
    }
    const requestHumanUrl = widget.dataset.requestHumanUrl;
    const backToBotUrl     = widget.dataset.backToBotUrl;

    function renderActions() {
        elActions.innerHTML = '';
        if (mode === 'bot') {
            elActions.classList.remove('hidden');
            const btn = document.createElement('button');
            btn.className = 'w-full rounded-lg border border-primary-300 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-slate-700 transition';
            btn.textContent = '👤 Hubungkan ke Admin';
            btn.onclick = () => handoff(requestHumanUrl, btn);
            elActions.appendChild(btn);
        } else {
            elActions.classList.remove('hidden');
            const note = document.createElement('p');
            note.className = 'text-[11px] text-slate-500 mb-1';
            note.textContent = status === 'waiting'
                ? 'Permintaanmu sedang menunggu admin sekolah.'
                : 'Kamu sedang berbicara dengan admin sekolah.';
            const btn = document.createElement('button');
            btn.className = 'w-full rounded-lg border border-primary-300 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-slate-700 transition';
            btn.textContent = '🤖 Kembali ke Bot';
            btn.onclick = () => handoff(backToBotUrl, btn);
            elActions.appendChild(note);
            elActions.appendChild(btn);
        }
    }

    async function handoff(url, btn) {
        btn.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            mode = data.mode; status = data.status;
            renderModeUI();
            poll(); // ambil pesan sistem segera (Lapis B)
        } catch (e) {
            elError.classList.remove('hidden');
            btn.disabled = false;
        }
    }

    // ---------- Send (Lapis A) ----------
    async function send() {
        const text = composer.value.trim();
        if (!text) return;

        elError.classList.add('hidden');
        sendBtn.disabled = true;

        // Optimistic user bubble (id sementara, akan didedup oleh id asli dari server).
        const tempId = 'temp-' + Date.now();
        addMessage({ id: tempId, sender: 'user', body: text, created_at: null });
        composer.value = '';
        autoSize();
        elTyping.classList.toggle('hidden', mode !== 'bot'); // hanya bot yang "mengetik"

        try {
            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ message: text }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            mode = data.mode; status = data.status;
            // Ganti bubble sementara dengan id asli.
            seen.delete(tempId);
            const temp = elMsgs.lastElementChild;
            if (data.user_message) {
                if (temp) temp.remove();
                seen.delete(tempId);
                addMessage(data.user_message);
            }
            if (data.bot_message) addMessage(data.bot_message);
            renderModeUI();
        } catch (e) {
            elError.classList.remove('hidden');
            // Kembalikan teks user supaya bisa coba lagi (tanpa hapus bubble optimistic).
            composer.value = text;
            autoSize();
        } finally {
            elTyping.classList.add('hidden');
            sendBtn.disabled = false;
            composer.focus();
        }
    }

    // ---------- Poll (Lapis B) ----------
    async function poll() {
        if (document.visibilityState !== 'visible') return;
        try {
            const url = new URL(pollUrl, window.location.origin);
            url.searchParams.set('conversation_id', conversationId);
            if (cursor) url.searchParams.set('after', cursor);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const wasBottom = atBottom();
            let incoming = 0;
            (data.messages || []).forEach(m => {
                if (!seen.has(m.id) && m.sender !== 'user') incoming++;
                addMessage(m, true);
            });
            if (incoming > 0) beep(); // bunyi saat ada pesan masuk (mis. balasan admin)
            applyRead(data.read_watermark);
            if (data.mode !== mode || data.status !== status) {
                mode = data.mode; status = data.status; renderModeUI();
            }
            if (wasBottom) scrollToBottom();
        } catch (_) { /* diam: poll berikutnya coba lagi */ }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(poll, pollEvery);
    }
    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    // ---------- Lifecycle ----------
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') { poll(); startPolling(); }
        else stopPolling();
    });
    window.addEventListener('beforeunload', stopPolling); // cegah memory leak

    // ---------- Emoji picker ----------
    const EMOJIS = ['😀','😄','😁','😅','😂','🙂','😉','😊','😍','😘','😎','🤩','🤔','😴','😇','🥳',
                    '👍','👎','👌','🙏','👏','💪','🙌','🤝','👋','✌️','🔥','✨','⭐','🎉','❤️','💜',
                    '😢','😭','😡','😱','😅','😬','🤗','🥺','😋','🤤','😴','🤒','🤧','💯','✅','❌',
                    '📌','📎','📷','🖼️','📝','📚','🎓','💰','🗓️','⏰','📞','📩','🏫','🚀','🎯','💡'];
    EMOJIS.forEach(e => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'h-7 w-7 rounded-md hover:bg-slate-100 dark:hover:bg-slate-700 text-base leading-none flex items-center justify-center';
        b.textContent = e;
        b.onmousedown = (ev) => { ev.preventDefault(); insertAtCursor(e); };
        emojiPop.appendChild(b);
    });
    function insertAtCursor(text) {
        const s = composer.selectionStart ?? composer.value.length;
        const en = composer.selectionEnd ?? composer.value.length;
        composer.value = composer.value.slice(0, s) + text + composer.value.slice(en);
        const pos = s + text.length;
        composer.selectionStart = composer.selectionEnd = pos;
        composer.focus(); autoSize();
    }
    btnEmoji.addEventListener('click', (e) => { e.stopPropagation(); emojiPop.classList.toggle('hidden'); });
    document.addEventListener('click', (e) => {
        if (!emojiPop.contains(e.target) && e.target !== btnEmoji && !btnEmoji.contains(e.target)) emojiPop.classList.add('hidden');
    });

    // ---------- Upload gambar (kompres di browser) ----------
    btnAttach.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async () => {
        const file = fileInput.files && fileInput.files[0];
        fileInput.value = ''; // reset agar file sama bisa dipilih ulang
        if (!file || !file.type.startsWith('image/')) return;
        try {
            const blob = await compressImage(file, 1280, 0.7);
            setPending(blob);
        } catch (_) {
            elError.textContent = 'Gagal memproses gambar.';
            elError.classList.remove('hidden');
        }
    });
    imgRemove.addEventListener('click', clearPending);

    function setPending(blob) {
        clearPending(); clearPendingFile();
        pendingBlob = blob;
        pendingUrl = URL.createObjectURL(blob);
        imgPreviewEl.src = pendingUrl;
        imgSize.textContent = (blob.size / 1024).toFixed(0) + ' KB (terkompres)';
        imgPreview.classList.remove('hidden');
        imgPreview.classList.add('flex');
    }
    function clearPending() {
        if (pendingUrl) URL.revokeObjectURL(pendingUrl);
        pendingBlob = null; pendingUrl = null;
        imgPreview.classList.add('hidden');
        imgPreview.classList.remove('flex');
        imgPreviewEl.src = '';
    }

    // ---------- Lampirkan file/dokumen (PDF/Word/Excel — tanpa kompresi, dibatasi ukuran) ----------
    btnFile.addEventListener('click', () => fileInputDoc.click());
    fileInputDoc.addEventListener('change', () => {
        const file = fileInputDoc.files && fileInputDoc.files[0];
        fileInputDoc.value = '';
        if (!file) return;
        if (file.size > MAX_FILE_BYTES) {
            elError.textContent = 'File terlalu besar (maks 5 MB).';
            elError.classList.remove('hidden');
            return;
        }
        elError.classList.add('hidden');
        setPendingFile(file);
    });
    fileRemove.addEventListener('click', clearPendingFile);

    function setPendingFile(file) {
        clearPending(); clearPendingFile();
        pendingFile = file;
        fileNameEl.textContent = file.name;
        const kb = file.size / 1024;
        fileSizeEl.textContent = kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb.toFixed(0) + ' KB';
        filePreview.classList.remove('hidden');
        filePreview.classList.add('flex');
    }
    function clearPendingFile() {
        pendingFile = null;
        filePreview.classList.add('hidden');
        filePreview.classList.remove('flex');
        fileNameEl.textContent = '—'; fileSizeEl.textContent = '—';
    }

    // Skala gambar ke sisi terpanjang `maxDim`, ekspor JPEG dengan kualitas `quality`.
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
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, width, height); // alas putih untuk PNG transparan
                ctx.drawImage(img, 0, 0, width, height);
                canvas.toBlob(b => b ? resolve(b) : reject(new Error('toBlob gagal')), 'image/jpeg', quality);
            };
            img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('muat gambar gagal')); };
            img.src = url;
        });
    }

    async function sendImage() {
        if (!pendingBlob) return;
        const caption = composer.value.trim();
        const blob = pendingBlob, localUrl = pendingUrl;
        pendingUrl = null; // jangan di-revoke; dipakai bubble optimistik
        clearPending();
        elError.classList.add('hidden');
        sendBtn.disabled = true;

        const tempId = 'temp-' + Date.now();
        addMessage({ id: tempId, sender: 'user', body: caption, attachment_url: localUrl, created_at: null });
        composer.value = ''; autoSize();
        elTyping.classList.toggle('hidden', mode !== 'bot');

        try {
            const fd = new FormData();
            fd.append('image', blob, 'image.jpg');
            if (caption) fd.append('caption', caption);
            const res = await fetch(uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            mode = data.mode; status = data.status;
            seen.delete(tempId);
            const temp = elMsgs.querySelector('[data-mid="' + tempId + '"]');
            if (data.user_message) { if (temp) temp.remove(); addMessage(data.user_message); }
            if (data.bot_message) addMessage(data.bot_message);
            renderModeUI();
        } catch (e) {
            elError.textContent = 'Gagal mengirim gambar, coba lagi.';
            elError.classList.remove('hidden');
        } finally {
            elTyping.classList.add('hidden');
            sendBtn.disabled = false;
            composer.focus();
        }
    }

    async function sendFile() {
        if (!pendingFile) return;
        const caption = composer.value.trim();
        const file = pendingFile;
        clearPendingFile();
        elError.classList.add('hidden');
        sendBtn.disabled = true;

        const tempId = 'temp-' + Date.now();
        addMessage({ id: tempId, sender: 'user', body: caption, attachment_name: file.name, attachment_is_image: false, created_at: null });
        composer.value = ''; autoSize();
        elTyping.classList.toggle('hidden', mode !== 'bot');

        try {
            const fd = new FormData();
            fd.append('file', file, file.name);
            if (caption) fd.append('caption', caption);
            const res = await fetch(uploadFileUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: fd,
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            mode = data.mode; status = data.status;
            seen.delete(tempId);
            const temp = elMsgs.querySelector('[data-mid="' + tempId + '"]');
            if (data.user_message) { if (temp) temp.remove(); addMessage(data.user_message); }
            if (data.bot_message) addMessage(data.bot_message);
            renderModeUI();
        } catch (e) {
            elError.textContent = 'Gagal mengirim file, coba lagi.';
            elError.classList.remove('hidden');
        } finally {
            elTyping.classList.add('hidden');
            sendBtn.disabled = false;
            composer.focus();
        }
    }

    function dispatchSend() {
        if (pendingBlob) sendImage();
        else if (pendingFile) sendFile();
        else send();
    }

    // ---------- Composer UX ----------
    function autoSize() {
        composer.style.height = 'auto';
        composer.style.height = Math.min(composer.scrollHeight, 112) + 'px';
    }
    composer.addEventListener('input', autoSize);
    composer.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); dispatchSend(); }
    });
    sendBtn.addEventListener('click', dispatchSend);

    // ---------- Quick Suggestions Chips ----------
    document.querySelectorAll('.btn-quick-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            composer.value = btn.dataset.question;
            dispatchSend();
        });
    });

    // Expose untuk Phase 6.
    window.ChatbotWidget = { addMessage, renderModeUI, get mode() { return mode; }, set mode(v) { mode = v; },
                             get status() { return status; }, set status(v) { status = v; }, csrf, widget };

    // ---------- Init ----------
    const initialMsgs = JSON.parse(document.getElementById('initial-messages').textContent || '[]');
    if (initialMsgs.length === 0) {
        renderWelcomeScreen();
    } else {
        initialMsgs.forEach(m => addMessage(m));
    }
    applyRead(readWatermark);
    renderModeUI();
    scrollToBottom();
    if (document.visibilityState === 'visible') startPolling();
})();
</script>
@endsection
