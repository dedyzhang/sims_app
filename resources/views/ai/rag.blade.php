@extends('layouts.app')
@section('title', 'Dokumen AI (RAG)')

@section('content')
<div class="space-y-5" x-data="ragAi()">

    {{-- Header --}}
    <div>
        <h1 class="page-title flex items-center gap-2"><i data-lucide="file-search" class="w-6 h-6 text-primary"></i> Dokumen AI</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Unggah dokumen sekolah (peraturan/materi), lalu tanya-jawab berbasis isinya dengan sumber kutipan.</p>
    </div>

    {{-- Unggah --}}
    <div class="card p-5">
        <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 mb-3"><i data-lucide="upload" class="w-4 h-4"></i> Unggah Dokumen</h2>
        <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <div class="space-y-3">
                <div>
                    <label class="form-label">Judul (opsional)</label>
                    <input type="text" x-model="title" placeholder="mis. Tata Tertib Siswa 2026" class="form-input">
                </div>
                <div>
                    <label class="form-label">Berkas (PDF / TXT, maks 10 MB) <span class="text-rose-500">*</span></label>
                    <input type="file" x-ref="file" accept=".pdf,.txt" class="form-input">
                </div>
            </div>
            <button type="button" @click="upload()" :disabled="uploading" class="btn-primary flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40 h-fit">
                <i :data-lucide="uploading ? 'loader-circle' : 'upload'" :class="uploading && 'animate-spin'" class="w-4 h-4"></i>
                <span x-text="uploading ? 'Memproses…' : 'Unggah & Proses'"></span>
            </button>
        </div>
        <p x-show="uploadMsg" x-cloak class="mt-3 text-sm text-emerald-600 dark:text-emerald-400" x-text="uploadMsg"></p>
        <p x-show="uploadErr" x-cloak class="mt-3 text-sm text-rose-600 dark:text-rose-400" x-text="uploadErr"></p>
        <p class="mt-2 text-[11px] text-slate-400">Catatan: PDF hasil scan (gambar) tidak bisa diekstrak teksnya.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Daftar dokumen --}}
        <div class="card p-5">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 mb-3"><i data-lucide="folder" class="w-4 h-4"></i> Dokumen ({{ $documents->count() }})</h2>

            @forelse($documents as $doc)
                <div class="flex items-center gap-3 rounded-xl ring-1 ring-slate-200 dark:ring-slate-700 px-3 py-2.5 mb-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-slate-400 shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ $doc->title }}</p>
                        <p class="text-[11px] text-slate-400">
                            @if($doc->status === 'processed')
                                {{ $doc->chunk_count }} potongan · {{ $doc->created_at?->diffForHumans() }}
                            @elseif($doc->status === 'failed')
                                <span class="text-rose-500">Gagal: {{ Str::limit($doc->error, 60) }}</span>
                            @else
                                Menunggu diproses
                            @endif
                        </p>
                    </div>
                    @php
                        $badge = match($doc->status) {
                            'processed' => ['Siap', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
                            'failed'    => ['Gagal', 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'],
                            default     => ['Pending', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
                        };
                    @endphp
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full {{ $badge[1] }}">{{ $badge[0] }}</span>
                    <button type="button" @click="remove('{{ $doc->uuid }}')" title="Hapus" class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition shrink-0">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            @empty
                <p class="text-center text-sm text-slate-400 py-8">Belum ada dokumen. Unggah dokumen pertama di atas.</p>
            @endforelse
        </div>

        {{-- Tanya-jawab --}}
        <div class="card p-5 flex flex-col">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 mb-3"><i data-lucide="messages-square" class="w-4 h-4"></i> Tanya Dokumen</h2>

            <div class="flex items-end gap-2">
                <textarea x-model="question" rows="2" @keydown.enter.prevent="if(!$event.shiftKey) ask()" placeholder="mis. Apa sanksi terlambat masuk sekolah?" class="form-input resize-none flex-1"></textarea>
                <button type="button" @click="ask()" :disabled="asking || question.trim() === ''" class="btn-primary grid h-10 w-10 shrink-0 place-items-center rounded-xl disabled:opacity-40">
                    <i :data-lucide="asking ? 'loader-circle' : 'send'" :class="asking && 'animate-spin'" class="w-5 h-5"></i>
                </button>
            </div>

            <div x-show="askErr" x-cloak class="mt-3 rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800 px-4 py-3 text-sm" x-text="askErr"></div>

            <template x-if="answer">
                <div class="mt-4 space-y-3">
                    <div class="ai-answer break-words text-sm text-slate-800 dark:text-slate-100" x-html="renderAiMarkdown(answer)"></div>
                    <template x-if="sources.length">
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                            <p class="text-[11px] font-semibold text-slate-500 mb-1.5 flex items-center gap-1"><i data-lucide="quote" class="w-3.5 h-3.5"></i> Sumber:</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="s in sources" :key="s.title">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-slate-700 px-2.5 py-1 text-[11px] text-slate-600 dark:text-slate-300">
                                        <i data-lucide="file-text" class="w-3 h-3"></i><span x-text="s.title"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <div x-show="!answer && !asking && !askErr" x-cloak class="flex-1 grid place-items-center text-slate-300 dark:text-slate-600 py-8">
                <div class="text-center">
                    <i data-lucide="file-search" class="w-10 h-10 mx-auto opacity-40"></i>
                    <p class="text-sm mt-2">Jawaban hanya diambil dari isi dokumen.</p>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.ai-markdown')

<script>
    function ragAi() {
        return {
            title: '', uploading: false, uploadMsg: '', uploadErr: '',
            question: '', asking: false, answer: '', sources: [], askErr: '',
            csrf() { return document.querySelector('meta[name="csrf-token"]').getAttribute('content'); },

            async upload() {
                const f = this.$refs.file.files[0];
                this.uploadMsg = ''; this.uploadErr = '';
                if (!f) { this.uploadErr = 'Pilih berkas terlebih dahulu.'; return; }
                this.uploading = true;
                const fd = new FormData();
                fd.append('file', f);
                if (this.title) fd.append('title', this.title);
                try {
                    const r = await fetch('{{ route('ai.rag.store') }}', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: fd,
                    });
                    const d = await r.json();
                    if (r.ok && d.ok) { this.uploadMsg = d.message; setTimeout(() => window.location.reload(), 800); }
                    else { this.uploadErr = d.message || (d.errors ? Object.values(d.errors).flat().join(' ') : 'Gagal mengunggah.'); }
                } catch (_) { this.uploadErr = 'Gagal terhubung.'; }
                finally { this.uploading = false; this.$nextTick(() => window.lucide && lucide.createIcons()); }
            },

            async remove(id) {
                try {
                    const r = await fetch('{{ url('ai/rag') }}/' + id, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                    });
                    if (r.ok) window.location.reload();
                } catch (_) {}
            },

            async ask() {
                const q = this.question.trim();
                if (q === '' || this.asking) return;
                this.asking = true; this.answer = ''; this.sources = []; this.askErr = '';
                try {
                    const r = await fetch('{{ route('ai.rag.ask') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() },
                        body: JSON.stringify({ question: q }),
                    });
                    const d = await r.json();
                    if (r.ok && d.ok) { this.answer = d.answer; this.sources = d.sources || []; }
                    else { this.askErr = d.message || 'Terjadi kesalahan.'; }
                } catch (_) { this.askErr = 'Gagal terhubung.'; }
                finally { this.asking = false; this.$nextTick(() => window.lucide && lucide.createIcons()); }
            },
        }
    }
</script>
@endsection
