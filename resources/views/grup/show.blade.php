@extends('layouts.app')
@section('title', $grup->nama)

@section('content')
{{-- Pesan awal disuntikkan sebagai JSON inline (bukan HTML server-side + append JS)
     supaya markup bubble hanya ditulis SEKALI. Tidak ada flash layar kosong karena
     datanya sudah ada di dokumen — Alpine merender tanpa menunggu request apa pun. --}}
<div class="flex flex-col h-[calc(100vh-11rem)] min-h-[400px]"
     x-data="grupChat({
        pollUrl: @js(route('grup.poll', $grup->uuid)),
        kirimUrl: @js(route('grup.pesan', $grup->uuid)),
        lampiranUrl: @js(route('grup.lampiran', $grup->uuid)),
        hapusUrlTemplate: @js(route('grup.pesan.hapus', [$grup->uuid, '__PESAN__'])),
        meId: @js(auth()->id()),
        awal: @js($pesan),
        lastSeq: {{ $lastSeq }},
        mode: @js($grup->mode),
        status: @js($grup->status),
        bolehKirim: {{ $bolehKirim ? 'true' : 'false' }},
        bolehModerasi: {{ $bolehModerasi ? 'true' : 'false' }},
        bolehBalasPengumuman: {{ $bolehBalasPengumuman ? 'true' : 'false' }},
        adaRiwayatTerpotong: {{ $batasSeq > 0 ? 'true' : 'false' }},
     })"
     x-init="init()">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="card px-4 py-3 flex items-center gap-3 flex-shrink-0">
        <a href="{{ route('grup.index') }}" class="p-1.5 -ml-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition">
            <i data-lucide="arrow-left" class="w-5 h-5 text-slate-500"></i>
        </a>

        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-white"
             style="background: {{ $grup->isPaguyuban() ? '#0d9488' : 'var(--cp)' }}">
            <i data-lucide="{{ $grup->isPaguyuban() ? 'users-round' : 'graduation-cap' }}" class="w-5 h-5"></i>
        </div>

        <div class="flex-1 min-w-0">
            <h1 class="font-bold text-sm text-slate-800 dark:text-slate-100 truncate">{{ $grup->nama }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $jumlahAnggota }} anggota
                <template x-if="mode === 'pengumuman'">
                    <span class="text-amber-600 dark:text-amber-400 font-semibold"> &middot; Mode pengumuman</span>
                </template>
                @if($grup->isArsip())
                    <span class="text-slate-400"> &middot; Diarsipkan</span>
                @endif
            </p>
        </div>
    </div>

    {{-- ── Daftar pesan ───────────────────────────────────────────────────── --}}
    <div x-ref="scroll" @scroll="cekPosisi()"
         class="flex-1 overflow-y-auto py-4 space-y-1.5 px-1">

        <template x-if="adaRiwayatTerpotong">
            <p class="text-center text-[11px] text-slate-400 px-6 py-2">
                Riwayat sebelum Anda bergabung tidak ditampilkan.
            </p>
        </template>

        <template x-if="!messages.length">
            <div class="text-center py-10">
                <i data-lucide="message-circle" class="w-9 h-9 mx-auto text-slate-300 dark:text-slate-600"></i>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Belum ada pesan.</p>
            </div>
        </template>

        <template x-for="(m, i) in messages" :key="m.uuid">
            <div>
                {{-- Separator tanggal --}}
                <template x-if="i === 0 || messages[i-1].tanggal !== m.tanggal">
                    <div class="flex justify-center my-3">
                        <span class="text-[11px] px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400"
                              x-text="labelTanggal(m.tanggal)"></span>
                    </div>
                </template>

                <div class="group flex items-center gap-1" :class="m.user_id === meId ? 'justify-end' : 'justify-start'">
                    {{-- Aksi: balas & hapus — muncul saat hover, mengapit gelembung dari sisi luar --}}
                    <template x-if="!m.dihapus && m.user_id === meId">
                        <div class="hidden group-hover:flex items-center gap-0.5 flex-shrink-0">
                            <template x-if="bolehBalas(m)">
                                <button @click="mulaiBalas(m)" title="Balas" class="p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                                    <i data-lucide="reply" class="w-3.5 h-3.5"></i>
                                </button>
                            </template>
                            <template x-if="m.user_id === meId || bolehModerasi">
                                <button @click="hapusPesan(m)" title="Hapus pesan" class="p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </template>
                        </div>
                    </template>

                    <div class="max-w-[80%] sm:max-w-[70%] rounded-2xl px-3.5 py-2"
                         :class="m.dihapus
                            ? 'bg-slate-100 dark:bg-slate-700/60 text-slate-400 italic'
                            : (m.user_id === meId
                                ? 'text-white rounded-br-md'
                                : 'bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100 rounded-bl-md border border-slate-100 dark:border-slate-600')"
                         :style="m.user_id === meId && !m.dihapus ? 'background: var(--cp)' : ''">

                        {{-- Nama pengirim (hanya untuk pesan orang lain) --}}
                        <template x-if="m.user_id !== meId && !m.dihapus">
                            <p class="text-[11px] font-bold mb-0.5" style="color: var(--cp)" x-text="m.nama"></p>
                        </template>

                        {{-- Kutipan balasan --}}
                        <template x-if="m.reply_snippet">
                            <div class="mb-1.5 pl-2 border-l-2 border-current/40 opacity-80">
                                <p class="text-[11px] font-semibold" x-text="m.reply_nama"></p>
                                <p class="text-[11px] truncate" x-text="m.reply_snippet"></p>
                            </div>
                        </template>

                        {{-- Lampiran foto --}}
                        <template x-if="m.lampiran && m.lampiran.tipe === 'image'">
                            <a :href="m.lampiran.url" target="_blank" rel="noopener" class="block mb-1.5 -mx-0.5">
                                <img :src="m.lampiran.url" loading="lazy" class="rounded-lg max-h-56 w-auto max-w-full object-cover">
                            </a>
                        </template>

                        {{-- Lampiran berkas --}}
                        <template x-if="m.lampiran && m.lampiran.tipe !== 'image'">
                            <a :href="m.lampiran.url" target="_blank" rel="noopener"
                               class="flex items-center gap-2 mb-1.5 p-2 rounded-lg bg-black/5 dark:bg-white/10">
                                <i data-lucide="file-text" class="w-5 h-5 flex-shrink-0"></i>
                                <span class="text-xs truncate" x-text="m.lampiran.nama"></span>
                            </a>
                        </template>

                        <template x-if="m.body">
                            <p class="text-sm whitespace-pre-wrap break-words" x-text="m.body"></p>
                        </template>

                        <p class="text-[10px] mt-0.5 text-right"
                           :class="m.user_id === meId && !m.dihapus ? 'text-white/70' : 'text-slate-400'"
                           x-text="m.jam"></p>
                    </div>

                    <template x-if="!m.dihapus && m.user_id !== meId">
                        <div class="hidden group-hover:flex items-center gap-0.5 flex-shrink-0">
                            <template x-if="bolehBalas(m)">
                                <button @click="mulaiBalas(m)" title="Balas" class="p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                                    <i data-lucide="reply" class="w-3.5 h-3.5"></i>
                                </button>
                            </template>
                            <template x-if="bolehModerasi">
                                <button @click="hapusPesan(m)" title="Hapus pesan" class="p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- ── Composer ───────────────────────────────────────────────────────── --}}
    <div class="card px-3 py-2.5 flex-shrink-0">
        {{-- Mode pengumuman: siswa/ortu tak boleh menulis bebas, tapi tetap boleh
             membalas pesan staf (lihat GrupChatPolicy::reply) — composer tetap
             tampil, hanya terkunci sampai mereka menekan "Balas" di sebuah pesan. --}}
        <template x-if="bolehKirim || bolehBalasPengumuman">
            <div>
                {{-- Pratinjau balasan --}}
                <template x-if="replying">
                    <div class="flex items-start gap-2 mb-2 pl-2.5 pr-1.5 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-700/60 border-l-2" style="border-color: var(--cp)">
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-semibold" style="color: var(--cp)" x-text="'Membalas ' + replying.nama"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate" x-text="replying.snippet"></p>
                        </div>
                        <button type="button" @click="batalBalas()" class="p-1 rounded-full hover:bg-slate-200 dark:hover:bg-slate-600 flex-shrink-0">
                            <i data-lucide="x" class="w-3.5 h-3.5 text-slate-500"></i>
                        </button>
                    </div>
                </template>

                <template x-if="!bolehKirim && !replying">
                    <p class="text-[11px] text-amber-600 dark:text-amber-400 mb-1.5 px-1">
                        Mode pengumuman — tekan "Balas" pada pesan guru/staf untuk menanggapi.
                    </p>
                </template>

                <form @submit.prevent="kirim()" class="flex items-end gap-2">
                    <input type="file" x-ref="fileInput" class="hidden" @change="pilihLampiran($event)"
                           accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                    <button type="button" @click="$refs.fileInput.click()" :disabled="mengirim || !bolehTulisSekarang()" title="Lampirkan berkas"
                            class="w-9 h-9 rounded-full flex items-center justify-center text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 flex-shrink-0 disabled:opacity-40 transition">
                        <i data-lucide="paperclip" class="w-4 h-4"></i>
                    </button>
                    <textarea x-ref="input" x-model="draft" rows="1" maxlength="{{ \App\Services\GrupChatMessenger::MAX_BODY }}"
                              @input="autoGrow()" @keydown.enter.exact.prevent="kirim()"
                              :disabled="!bolehTulisSekarang()"
                              :placeholder="bolehTulisSekarang() ? 'Tulis pesan…' : 'Balas pesan guru/staf untuk menulis…'"
                              class="form-input flex-1 resize-none max-h-32 py-2 disabled:opacity-60"></textarea>
                    <button type="submit" :disabled="mengirim || !draft.trim() || !bolehTulisSekarang()"
                            class="w-10 h-10 rounded-full flex items-center justify-center text-white flex-shrink-0 disabled:opacity-40 transition"
                            style="background: var(--cp)">
                        <i data-lucide="send" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </template>

        <template x-if="!bolehKirim && !bolehBalasPengumuman">
            <p class="text-xs text-center text-slate-500 dark:text-slate-400 py-2" x-text="alasanTakBisaKirim()"></p>
        </template>
    </div>
</div>

@push('scripts')
<script>
function grupChat(cfg) {
    return {
        ...cfg,
        messages: [],
        seen: new Set(),
        cursor: cfg.lastSeq,
        draft: '',
        mengirim: false,
        replying: null,
        diBawah: true,
        pollSeq: 0,          // buang respons yang datang tidak berurutan
        pollMs: 4000,
        timer: null,
        lastActivity: Date.now(),

        init() {
            this.serap(this.awal, false);
            this.$nextTick(() => { this.keBawah(); window.lucide?.createIcons(); });
            this.arm();
        },

        // ── Polling ────────────────────────────────────────────────────────
        arm() {
            if (this.timer) clearInterval(this.timer);
            this.timer = setInterval(() => window.simsWhenVisible(() => this.poll()), this.pollMs);
        },
        backoff(ms) {
            if (this.timer) clearInterval(this.timer);
            this.timer = setTimeout(() => this.arm(), ms);
        },
        async poll() {
            const seq = ++this.pollSeq;
            let res;
            try {
                res = await fetch(`${this.pollUrl}?after=${this.cursor}`, { headers: { Accept: 'application/json' } });
            } catch (_) { return; }
            if (seq !== this.pollSeq) return;                  // respons kadaluarsa
            if (res.status === 429) return this.backoff(15000);
            if (res.status === 403) { this.bolehKirim = false; if (this.timer) clearInterval(this.timer); return; }
            if (!res.ok) return;

            let data;
            try { data = await res.json(); } catch (_) { return; }
            if (seq !== this.pollSeq) return;

            this.serap(data.messages, true);
            if (data.last_seq > this.cursor) this.cursor = data.last_seq;
            // Walikelas bisa mengubah mode / mengarsipkan saat halaman ini terbuka.
            this.mode = data.mode;
            this.status = data.status;
            this.bolehKirim = data.boleh_kirim;
            this.bolehModerasi = data.boleh_moderasi;
            this.bolehBalasPengumuman = data.boleh_balas_pengumuman;
            this.retune();
        },
        // Turunkan frekuensi saat percakapan sepi — memangkas beban server ~4x.
        retune() {
            const target = (Date.now() - this.lastActivity > 120000) ? 15000 : 4000;
            if (target !== this.pollMs) { this.pollMs = target; this.arm(); }
        },

        // ── Data ───────────────────────────────────────────────────────────
        serap(list, bunyikan) {
            let baru = false;
            for (const m of (list || [])) {
                if (this.seen.has(m.uuid)) continue;           // jaring pengaman dedup
                this.seen.add(m.uuid);
                this.messages.push(m);
                if (m.user_id !== this.meId) baru = true;
            }
            if (!baru) return;
            this.lastActivity = Date.now();
            if (bunyikan) this.beep();
            this.$nextTick(() => { if (this.diBawah) this.keBawah(); window.lucide?.createIcons(); });
        },

        async kirim() {
            const body = this.draft.trim();
            if (!body || this.mengirim) return;
            this.mengirim = true;
            try {
                const res = await fetch(this.kirimUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({ body, reply_to_id: this.replying?.uuid ?? null }),
                });
                if (!res.ok) { this.mengirim = false; return; }
                const data = await res.json();
                this.draft = '';
                this.replying = null;
                this.$refs.input.style.height = 'auto';
                this.diBawah = true;
                this.serap([data.message], false);
                if (data.last_seq > this.cursor) this.cursor = data.last_seq;
                this.lastActivity = Date.now();
                this.retune();
            } catch (_) { /* diamkan: user bisa tekan kirim lagi */ }
            this.mengirim = false;
        },

        // Cermin GrupChatPolicy::reply(): siapa pun yang boleh kirim bebas boleh
        // balas pesan mana pun; di mode pengumuman, non-staf hanya boleh balas
        // pesan staf (peran walikelas/guru/admin) — lihat GrupChat::PERAN_STAF.
        bolehBalas(m) {
            if (this.bolehKirim) return true;
            return this.bolehBalasPengumuman && ['walikelas', 'guru', 'admin'].includes(m.peran);
        },
        // Boleh menulis di composer SEKARANG: bebas kalau bolehKirim, atau sedang
        // aktif membalas pesan staf di mode pengumuman.
        bolehTulisSekarang() {
            return this.bolehKirim || (this.bolehBalasPengumuman && !!this.replying);
        },
        mulaiBalas(m) {
            if (m.dihapus || !this.bolehBalas(m)) return;
            const snippet = m.body || (m.lampiran ? (m.lampiran.tipe === 'image' ? '📷 Foto' : '📎 Berkas') : '');
            this.replying = { uuid: m.uuid, nama: m.nama, snippet };
            this.$refs.input?.focus();
        },
        batalBalas() {
            this.replying = null;
        },

        async pilihLampiran(event) {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file || this.mengirim) return;
            this.mengirim = true;

            const fd = new FormData();
            fd.append('file', file);
            if (this.draft.trim()) fd.append('body', this.draft.trim());
            if (this.replying?.uuid) fd.append('reply_to_id', this.replying.uuid);

            try {
                const res = await fetch(this.lampiranUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: fd,
                });
                if (!res.ok) { this.mengirim = false; alert('Gagal mengirim lampiran. Periksa ukuran/jenis berkas.'); return; }
                const data = await res.json();
                this.draft = '';
                this.replying = null;
                this.$refs.input.style.height = 'auto';
                this.diBawah = true;
                this.serap([data.message], false);
                if (data.last_seq > this.cursor) this.cursor = data.last_seq;
                this.lastActivity = Date.now();
                this.retune();
            } catch (_) { alert('Gagal mengirim lampiran.'); }
            this.mengirim = false;
        },

        async hapusPesan(m) {
            if (m.dihapus || !confirm('Hapus pesan ini?')) return;
            try {
                const res = await fetch(this.hapusUrlTemplate.replace('__PESAN__', m.uuid), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                });
                if (!res.ok) return;
                const data = await res.json();
                const idx = this.messages.findIndex(x => x.uuid === m.uuid);
                if (idx !== -1) this.messages[idx] = data.message;
            } catch (_) { /* diamkan */ }
        },

        // ── UI ─────────────────────────────────────────────────────────────
        autoGrow() {
            const el = this.$refs.input;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 128) + 'px';
        },
        cekPosisi() {
            const el = this.$refs.scroll;
            this.diBawah = (el.scrollHeight - el.scrollTop - el.clientHeight) < 80;
        },
        keBawah() {
            const el = this.$refs.scroll;
            if (el) el.scrollTop = el.scrollHeight;
        },
        beep() {
            if (document.hidden) return;
            try {
                const a = new Audio('{{ asset('sounds/notif-sims.wav') }}');
                a.volume = 0.4;
                a.play().catch(() => {});
            } catch (_) {}
        },
        labelTanggal(t) {
            const hari = new Date(t + 'T00:00:00');
            const kini = new Date(); kini.setHours(0, 0, 0, 0);
            const beda = Math.round((kini - hari) / 86400000);
            if (beda === 0) return 'Hari ini';
            if (beda === 1) return 'Kemarin';
            return hari.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        },
        alasanTakBisaKirim() {
            if (this.status === 'arsip') return 'Grup ini diarsipkan — hanya bisa dibaca.';
            if (this.mode === 'pengumuman') return 'Mode pengumuman — hanya guru yang dapat menulis.';
            return 'Anda tidak memiliki izin menulis di grup ini.';
        },
    };
}
</script>
@endpush
@endsection
