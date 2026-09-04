@extends('layouts.app')
@section('title', $grup->nama)

@section('content')
{{-- Pesan awal disuntikkan sebagai JSON inline (bukan HTML server-side + append JS)
     supaya markup bubble hanya ditulis SEKALI. Tidak ada flash layar kosong karena
     datanya sudah ada di dokumen — Alpine merender tanpa menunggu request apa pun. --}}
<div class="flex flex-col h-[calc(100dvh-11rem)] min-h-[400px]"
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
        adaRiwayatLama: {{ $adaRiwayatLama ? 'true' : 'false' }},
        olderCursor: @js($olderCursor),
        olderUrl: @js(route('grup.pesan.lama', $grup->uuid)),
        membersUrl: @js(route('grup.members', $grup->uuid)),
     })"
     x-init="init()">

    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="px-5 py-3 flex items-center gap-4 flex-shrink-0 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border-b border-slate-200/50 dark:border-slate-800/50 z-20 relative shadow-sm">
        <a href="{{ route('grup.index') }}" class="p-2 -ml-2 rounded-full hover:bg-slate-200/50 dark:hover:bg-slate-800/50 transition text-slate-700 dark:text-slate-300">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>

        <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-white shadow-md bg-gradient-to-br from-indigo-500 to-purple-500">
            <i data-lucide="{{ $grup->isPaguyuban() ? 'users-round' : 'graduation-cap' }}" class="w-5 h-5"></i>
        </div>

        <div class="flex-1 min-w-0 cursor-pointer group" @click="showMembersModal = true; fetchMembers()">
            <h1 class="font-extrabold text-[16px] text-slate-800 dark:text-slate-100 truncate tracking-tight">{{ $grup->nama }}</h1>
            <p class="text-[13px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                <span class="group-hover:text-indigo-500 transition font-medium">
                    {{ $jumlahAnggota }} anggota
                </span>
                <template x-if="mode === 'pengumuman'">
                    <span class="text-amber-500 font-semibold ml-1"> &middot; Pengumuman</span>
                </template>
                @if($grup->isArsip())
                    <span class="ml-1 text-slate-400"> &middot; Diarsipkan</span>
                @endif
            </p>
        </div>
    </div>

    {{-- ── Daftar pesan ───────────────────────────────────────────────────── --}}
    <div x-ref="scroll" @scroll="cekPosisi()"
         class="flex-1 overflow-y-auto py-4 space-y-3 px-3 sm:px-5 bg-slate-50 dark:bg-slate-900/50 relative">

        <div x-show="adaRiwayatLama" class="flex justify-center mb-3">
            <button type="button" @click="muatLebihLama()" :disabled="memuatRiwayat"
                    class="px-4 py-2 rounded-full text-xs font-semibold text-indigo-600 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 disabled:opacity-50">
                <span x-text="memuatRiwayat ? 'Memuat riwayat...' : 'Muat pesan sebelumnya'"></span>
            </button>
        </div>

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
                    <div class="flex justify-center my-4 relative">
                        <span class="text-[11px] font-medium px-4 py-1.5 rounded-full bg-white/60 dark:bg-slate-800/60 backdrop-blur-md text-slate-500 dark:text-slate-400 shadow-sm border border-slate-200/50 dark:border-slate-700/50"
                              x-text="labelTanggal(m.tanggal)"></span>
                    </div>
                </template>

                <div class="group flex items-center gap-1" :class="m.user_id === meId ? 'justify-end' : 'justify-start'">
                    {{-- Aksi: hover di desktop, tetap tersedia di layar sentuh. --}}
                    <template x-if="!m.dihapus && m.user_id === meId">
                        <div class="flex items-center gap-0.5 flex-shrink-0 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
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

                    <div class="max-w-[85%] sm:max-w-[75%] rounded-3xl px-4 py-2.5 relative shadow-sm"
                         :class="m.dihapus
                            ? 'bg-slate-100 dark:bg-slate-800/60 text-slate-400 italic'
                            : (m.user_id === meId
                                ? 'bg-gradient-to-br from-indigo-500 to-purple-500 text-white rounded-br-sm shadow-indigo-500/20'
                                : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 rounded-bl-sm border border-slate-100 dark:border-slate-700/50')">

                        {{-- Nama pengirim (hanya untuk pesan orang lain) --}}
                        <template x-if="m.user_id !== meId && !m.dihapus">
                            <p class="text-[12px] font-bold mb-1" style="color: var(--cp)" x-text="m.nama"></p>
                        </template>

                        {{-- Kutipan balasan --}}
                        <template x-if="m.reply_snippet">
                            <div class="mb-2 p-2 rounded-xl bg-black/5 dark:bg-black/20 border-l-4" style="border-color: var(--cp)">
                                <p class="text-[11px] font-semibold" style="color: var(--cp)" x-text="m.reply_nama"></p>
                                <p class="text-[12px] truncate opacity-90 mt-0.5" x-text="m.reply_snippet"></p>
                            </div>
                        </template>

                        {{-- Lampiran foto --}}
                        <template x-if="m.lampiran && m.lampiran.tipe === 'image'">
                            <a :href="m.lampiran.url" target="_blank" rel="noopener" class="block mb-1 -mx-2 mt-1">
                                <img :src="m.lampiran.url" loading="lazy" class="rounded-2xl max-h-64 w-auto max-w-full object-cover">
                            </a>
                        </template>

                        {{-- Lampiran berkas --}}
                        <template x-if="m.lampiran && m.lampiran.tipe !== 'image'">
                            <a :href="m.lampiran.url" target="_blank" rel="noopener"
                               class="flex items-center gap-3 mb-2 p-2.5 rounded-2xl bg-black/5 dark:bg-black/20 hover:bg-black/10 transition-colors">
                                <div class="w-10 h-10 rounded-full bg-white/20 dark:bg-white/10 flex items-center justify-center flex-shrink-0">
                                    <i data-lucide="file-text" class="w-5 h-5"></i>
                                </div>
                                <span class="text-[13px] font-medium truncate" x-text="m.lampiran.nama"></span>
                            </a>
                        </template>

                        <div class="flex items-end justify-between gap-4 flex-wrap">
                            <template x-if="m.body">
                                <p class="text-[15px] whitespace-pre-wrap break-words leading-relaxed" style="min-width: 0;" x-text="m.body"></p>
                            </template>
                            
                            <div class="flex items-center gap-1 flex-shrink-0 ml-auto mt-1">
                                <p class="text-[10px] font-medium"
                                   :class="m.user_id === meId && !m.dihapus ? 'text-white/80' : 'text-slate-400'"
                                   x-text="m.jam"></p>
                                <template x-if="m.user_id === meId && !m.dihapus">
                                    <i data-lucide="check-check" class="w-3.5 h-3.5" :class="m.dilihat ? 'text-blue-300' : 'text-white/60'"></i>
                                </template>
                            </div>
                        </div>
                    </div>

                    <template x-if="!m.dihapus && m.user_id !== meId">
                        <div class="flex items-center gap-0.5 flex-shrink-0 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
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
    <div class="bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl px-4 py-3 flex-shrink-0 z-20 relative border-t border-slate-200/50 dark:border-slate-800/50">
        {{-- Mode pengumuman: siswa/ortu tak boleh menulis bebas, tapi tetap boleh
             membalas pesan staf (lihat GrupChatPolicy::reply) — composer tetap
             tampil, hanya terkunci sampai mereka menekan "Balas" di sebuah pesan. --}}
        <template x-if="bolehKirim || bolehBalasPengumuman">
            <div>
                {{-- Pratinjau balasan --}}
                <template x-if="replying">
                    <div class="flex items-start gap-3 mb-3 pl-4 pr-3 py-3 rounded-2xl bg-indigo-50/50 dark:bg-indigo-900/20 border-l-4 border-indigo-500 backdrop-blur-sm">
                        <div class="flex-1 min-w-0">
                            <p class="text-[12px] font-bold text-indigo-600 dark:text-indigo-400" x-text="'Membalas ' + replying.nama"></p>
                            <p class="text-[13px] text-slate-600 dark:text-slate-300 truncate mt-1" x-text="replying.snippet"></p>
                        </div>
                        <button type="button" @click="batalBalas()" class="p-1.5 rounded-full hover:bg-slate-200/50 dark:hover:bg-slate-700/50 flex-shrink-0 transition">
                            <i data-lucide="x" class="w-4 h-4 text-slate-500"></i>
                        </button>
                    </div>
                </template>

                <template x-if="!bolehKirim && !replying">
                    <p class="text-[12px] text-amber-600 dark:text-amber-400 mb-2 px-3 text-center bg-amber-50 dark:bg-amber-900/20 py-2 rounded-xl border border-amber-100/50 dark:border-amber-800/50 font-medium">
                        Hanya wali kelas yang dapat mengirim pesan baru. Tekan "Balas" pada pesan wali kelas untuk menanggapi.
                    </p>
                </template>

                <form @submit.prevent="kirim()" class="flex items-end gap-3">
                    <input type="file" x-ref="fileInput" class="hidden" @change="pilihLampiran($event)"
                           accept="image/jpeg,image/png,image/webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                    <button type="button" @click="$refs.fileInput.click()" :disabled="mengirim || !bolehTulisSekarang()" title="Lampirkan berkas"
                            class="w-11 h-11 rounded-full flex items-center justify-center text-slate-500 bg-slate-100/50 dark:bg-slate-800/50 hover:bg-slate-200 dark:hover:bg-slate-700 hover:text-indigo-500 dark:hover:text-indigo-400 flex-shrink-0 disabled:opacity-40 transition-colors">
                        <i data-lucide="paperclip" class="w-5 h-5"></i>
                    </button>
                    <textarea x-ref="input" x-model="draft" rows="1" maxlength="{{ \App\Services\GrupChatMessenger::MAX_BODY }}"
                              @input="autoGrow()" @keydown.enter.exact.prevent="kirim()"
                              :disabled="!bolehTulisSekarang()"
                              :placeholder="bolehTulisSekarang() ? 'Ketik pesan...' : 'Pilih pesan untuk dibalas…'"
                              class="form-input flex-1 resize-none max-h-32 py-3 px-5 rounded-3xl bg-slate-100/80 dark:bg-slate-800/80 border-transparent shadow-inner text-[15px] disabled:opacity-60 focus:ring-2 focus:ring-indigo-500/30 placeholder-slate-400 dark:text-slate-300 transition-all"></textarea>
                    <button type="submit" :disabled="mengirim || !draft.trim() || !bolehTulisSekarang()"
                            class="w-11 h-11 rounded-full flex items-center justify-center text-white bg-gradient-to-br from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 flex-shrink-0 disabled:opacity-40 transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                        <i data-lucide="send" class="w-5 h-5 translate-x-px translate-y-px"></i>
                    </button>
                </form>
            </div>
        </template>

        <template x-if="!bolehKirim && !bolehBalasPengumuman">
            <p class="text-xs text-center text-slate-500 dark:text-slate-400 py-2" x-text="alasanTakBisaKirim()"></p>
        </template>
    </div>

    {{-- Modal Anggota --}}
    <div x-show="showMembersModal" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="showMembersModal = false">
         
         <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm flex flex-col max-h-[80vh]"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
              x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
              @click.away="showMembersModal = false">
              
              <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
                  <h3 class="font-bold text-slate-800 dark:text-slate-100">Daftar Anggota</h3>
                  <button @click="showMembersModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition">
                      <i data-lucide="x" class="w-5 h-5"></i>
                  </button>
              </div>

              <div class="px-5 py-3 bg-amber-50 dark:bg-amber-900/30 border-b border-amber-100 dark:border-amber-800/50 text-[11px] text-amber-800 dark:text-amber-300">
                  <div class="flex items-start gap-2">
                      <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                      <p>
                          @php
                              $target = $grup->isPaguyuban() ? 'orang tua' : 'siswa';
                          @endphp
                          @if(in_array($member?->peran, ['walikelas', 'admin', 'guru']))
                              Anggota grup terhubung secara otomatis dengan Data Induk Akademik. Jika ada {{ $target }} yang salah kelas, <strong>Bapak/Ibu Walikelas memiliki hak akses penuh</strong> untuk menambah atau memindahkan {{ $target }} tersebut secara langsung melalui menu Kelola Siswa. Data di grup ini akan otomatis menyesuaikan.
                          @else
                              Anggota grup terhubung secara otomatis dengan Data Induk Akademik. Jika Anda merasa tergabung di kelas yang salah, <strong>mohon segera laporkan kepada Walikelas Anda</strong> agar dapat dilakukan penyesuaian data.
                          @endif
                      </p>
                  </div>
              </div>
              
              <div class="p-5 flex-1 overflow-y-auto">
                  <template x-if="loadingMembers">
                      <div class="py-8 flex flex-col items-center justify-center text-slate-400">
                          <i data-lucide="loader-2" class="w-6 h-6 animate-spin mb-2"></i>
                          <span class="text-sm">Memuat anggota...</span>
                      </div>
                  </template>
                  
                  <template x-if="!loadingMembers && membersList.length > 0">
                      <div class="space-y-3">
                          <template x-for="m in membersList" :key="m.id">
                              <div class="flex items-center gap-3">
                                  <div class="relative w-11 h-11 flex-shrink-0">
                                       <div @click="m.private_chat_url && (window.location.href = m.private_chat_url)"
                                            :class="m.private_chat_url ? 'cursor-pointer hover:ring-2 hover:ring-indigo-400' : ''"
                                            :title="m.private_chat_url ? 'Buka chat privat' : ''"
                                            class="w-full h-full rounded-full flex items-center justify-center text-white font-extrabold text-[15px] shadow-sm"
                                           :class="{'bg-gradient-to-br from-indigo-500 to-purple-500': ['admin','guru','walikelas'].includes(m.peran), 'bg-gradient-to-br from-amber-400 to-orange-500': m.peran === 'orangtua', 'bg-gradient-to-br from-emerald-400 to-teal-500': m.peran === 'siswa'}"
                                           x-text="(m.nama || '?').charAt(0).toUpperCase()"></div>
                                      <template x-if="m.is_online">
                                          <div class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse shadow-[0_0_8px_rgba(16,185,129,0.8)]"></div>
                                      </template>
                                  </div>
                                   <div class="min-w-0">
                                       <template x-if="m.private_chat_url">
                                           <a :href="m.private_chat_url" class="text-sm font-semibold text-indigo-600 dark:text-indigo-300 truncate hover:underline block" x-text="m.nama" title="Buka chat privat"></a>
                                       </template>
                                       <template x-if="!m.private_chat_url">
                                           <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="m.nama"></p>
                                       </template>
                                       <p class="text-[11px] text-slate-500 capitalize" x-text="m.peran"></p>
                                       <p class="text-[10px] flex items-center gap-1"
                                          :class="m.presence === 'online' ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                                           <span x-text="m.last_seen || 'Tidak aktif'"></span>
                                       </p>
                                   </div>
                              </div>
                          </template>
                      </div>
                  </template>
              </div>
         </div>
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
        
        showMembersModal: false,
        membersList: [],
        loadingMembers: false,
        memuatRiwayat: false,
        async fetchMembers() {
            this.loadingMembers = true;
            try {
                const res = await fetch(this.membersUrl);
                if (res.ok) {
                    this.membersList = await res.json();
                }
            } catch (e) {
                // diamkan
            } finally {
                this.loadingMembers = false;
            }
        },

        init() {
            this.serap(this.awal, false);
            this.$nextTick(() => { this.keBawah(); window.lucide?.createIcons(); });
            this.arm();
        },

        // ── Polling ────────────────────────────────────────────────────────
        arm() {
            if (this.timer) clearInterval(this.timer);
            this.timer = window.simsPollInterval(() => this.poll(), this.pollMs, 'pesan_grup'); // bisa dimatikan lewat Performa Server
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
            // Pakai cursor hasil batch, bukan last_seq global. Jika backlog > 200,
            // request berikutnya harus mengambil batch lanjutan.
            if (data.next_after > this.cursor) this.cursor = data.next_after;
            // Walikelas bisa mengubah mode / mengarsipkan saat halaman ini terbuka.
            this.mode = data.mode;
            this.status = data.status;
            this.bolehKirim = data.boleh_kirim;
            this.bolehModerasi = data.boleh_moderasi;
            this.bolehBalasPengumuman = data.boleh_balas_pengumuman;
            this.retune();
        },
        async muatLebihLama() {
            if (this.memuatRiwayat || !this.adaRiwayatLama || !this.olderCursor) return;
            this.memuatRiwayat = true;
            const scroll = this.$refs.scroll;
            const tinggiSebelum = scroll.scrollHeight;
            try {
                const res = await fetch(`${this.olderUrl}?before=${this.olderCursor}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                const baru = (data.messages || []).filter(m => !this.seen.has(m.uuid));
                for (const m of baru) this.seen.add(m.uuid);
                this.messages = [...baru, ...this.messages];
                this.olderCursor = data.next_before;
                this.adaRiwayatLama = data.has_more;
                this.$nextTick(() => {
                    scroll.scrollTop += scroll.scrollHeight - tinggiSebelum;
                    window.lucide?.createIcons();
                });
            } catch (_) {
                // Pengguna dapat mencoba lagi tanpa kehilangan posisi percakapan.
            } finally {
                this.memuatRiwayat = false;
            }
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
            if (this.mode === 'pengumuman') return 'Mode pengumuman — hanya wali kelas yang dapat menulis.';
            return 'Anda tidak memiliki izin menulis di grup ini.';
        },
    };
}
</script>
@endpush
@endsection
