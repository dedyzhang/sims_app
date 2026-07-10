@extends('layouts.app')
@section('title', 'Asisten AI Guru')

@section('content')
<div class="space-y-5" x-data="teacherAi()">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title flex items-center gap-2"><i data-lucide="sparkles" class="w-6 h-6 text-primary"></i> Asisten AI Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Percepat menyusun soal, merangkum materi, dan menulis umpan balik siswa.</p>
        </div>
    </div>

    {{-- Tab --}}
    <div class="flex flex-wrap gap-2">
        <template x-for="t in tabs" :key="t.key">
            <button type="button" @click="selectTab(t.key)"
                    :class="tab === t.key ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition">
                <i :data-lucide="t.icon" class="w-4 h-4"></i><span x-text="t.label"></span>
            </button>
        </template>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Form --}}
        <div class="card p-5">
            {{-- Generator Soal --}}
            <div x-show="tab === 'quiz'" class="space-y-4">
                <div>
                    <label class="form-label">Topik / Fokus Materi <span class="text-rose-500" x-show="!quiz.file" x-cloak>*</span></label>
                    <input type="text" x-model="quiz.topik" placeholder="mis. Fotosintesis, Perang Diponegoro, Pecahan..." class="form-input">
                    <p class="text-[11px] text-slate-400 mt-1">Boleh dikosongkan jika soal dibuat langsung dari file.</p>
                </div>

                <div>
                    <label class="form-label">File Materi <span class="text-slate-400 font-normal">(opsional)</span></label>
                    <label class="flex min-h-[104px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                        <input x-ref="quizFile" type="file" class="sr-only" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="setQuizFile($event)">
                        <i data-lucide="upload-cloud" class="w-7 h-7 text-slate-400"></i>
                        <span class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="quiz.fileName || 'Unggah PDF atau Word'"></span>
                        <span class="mt-1 text-[11px] text-slate-400">PDF, DOC, DOCX maks. 10 MB</span>
                    </label>
                    <div x-show="quiz.file" x-cloak class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <span class="truncate" x-text="quiz.fileName"></span>
                        <button type="button" @click="clearQuizFile()" class="inline-flex items-center gap-1 text-rose-600 hover:text-rose-700 dark:text-rose-300">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Jumlah Soal <span class="text-rose-500">*</span></label>
                        <input type="number" x-model.number="quiz.jumlah" min="1" max="20" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Jenjang (opsional)</label>
                        <input type="text" x-model="quiz.jenjang" placeholder="mis. Kelas 5 SD" class="form-input">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Jenis Soal <span class="text-rose-500">*</span></label>
                        <select x-model="quiz.jenis" class="form-input">
                            <option value="pg">Pilihan Ganda</option>
                            <option value="esai">Esai / Uraian</option>
                            <option value="campuran">Campuran</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Tingkat Kesulitan <span class="text-rose-500">*</span></label>
                        <select x-model="quiz.tingkat" class="form-input">
                            <option value="mudah">Mudah</option>
                            <option value="sedang">Sedang</option>
                            <option value="sulit">Sulit</option>
                        </select>
                    </div>
                </div>
                <button type="button" @click="submit('quiz')" :disabled="loading || (!quiz.topik.trim() && !quiz.file)" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="wand-2" class="w-4 h-4"></i> Buat Soal
                </button>
            </div>

            {{-- Perangkum Materi --}}
            <div x-show="tab === 'summary'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Materi <span class="text-rose-500">*</span></label>
                    <textarea x-model="summary.materi" rows="12" placeholder="Tempel materi panjang di sini..." class="form-input resize-y"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Maks. {{ number_format(config('ai.max_input_chars')) }} karakter.</p>
                </div>
                <button type="button" @click="submit('summary')" :disabled="loading || summary.materi.trim() === ''" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="list-collapse" class="w-4 h-4"></i> Rangkum
                </button>
            </div>

            {{-- Draft Feedback --}}
            <div x-show="tab === 'feedback'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Nama Siswa (opsional)</label>
                    <input type="text" x-model="feedback.nama" placeholder="mis. Andi" class="form-input">
                </div>
                <div>
                    <label class="form-label">Konteks / Jawaban Siswa <span class="text-rose-500">*</span></label>
                    <textarea x-model="feedback.konteks" rows="9" placeholder="mis. Jawaban ujian, sikap belajar, atau hal yang ingin dikomentari..." class="form-input resize-y"></textarea>
                </div>
                <button type="button" @click="submit('feedback')" :disabled="loading || feedback.konteks.trim() === ''" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="message-square-heart" class="w-4 h-4"></i> Susun Draf
                </button>
            </div>
        </div>

        {{-- Hasil --}}
        <div class="card p-5 flex flex-col min-h-[300px]">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> Hasil</h2>
                <div x-show="result" x-cloak class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" @click="toggleEdit()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="editing ? 'check' : 'pencil'" class="w-4 h-4"></i><span x-text="editing ? 'Selesai' : 'Edit'"></span>
                    </button>
                    <button type="button" x-show="tab === 'quiz'" @click="exportWord()" :disabled="exportingWord" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingWord ? 'loader-circle' : 'file-down'" class="w-4 h-4" :class="exportingWord ? 'animate-spin' : ''"></i><span x-text="exportingWord ? 'Export...' : 'Word'"></span>
                    </button>
                    <button type="button" @click="copy()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="copied ? 'check' : 'copy'" class="w-4 h-4"></i><span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                    </button>
                    <button type="button" @click="clearResult()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 dark:text-rose-300 dark:hover:bg-rose-900/30">
                        <i data-lucide="trash-2" class="w-4 h-4"></i><span>Hapus</span>
                    </button>
                </div>
            </div>

            {{-- Loading --}}
            <div x-show="loading" x-cloak class="flex-1 grid place-items-center text-slate-400">
                <div class="text-center">
                    <i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin"></i>
                    <p class="text-sm mt-2">AsistenAI sedang menyusun...</p>
                </div>
            </div>

            {{-- Error --}}
            <div x-show="error && !loading" x-cloak class="rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800 px-4 py-3 text-sm" x-text="error"></div>

            {{-- Empty --}}
            <div x-show="!loading && !result && !error" x-cloak class="flex-1 grid place-items-center text-slate-300 dark:text-slate-600">
                <div class="text-center">
                    <i data-lucide="sparkles" class="w-10 h-10 mx-auto opacity-40"></i>
                    <p class="text-sm mt-2">Hasil akan muncul di sini.</p>
                </div>
            </div>

            {{-- Result --}}
            <textarea x-show="result && !loading && editing" x-cloak x-model="result" rows="16" class="form-input flex-1 min-h-[260px] resize-y text-sm leading-relaxed"></textarea>
            <div x-show="result && !loading && !editing" x-cloak class="ai-answer flex-1 overflow-auto break-words text-sm text-slate-800 dark:text-slate-100" x-html="renderAiMarkdown(result)"></div>
        </div>
    </div>
</div>

@include('partials.ai-markdown')

<script>
    function teacherAi() {
        return {
            tab: 'quiz',
            loading: false,
            exportingWord: false,
            result: '',
            error: '',
            copied: false,
            editing: false,
            tabs: [
                { key: 'quiz',     label: 'Generator Soal',  icon: 'file-question' },
                { key: 'summary',  label: 'Perangkum Materi', icon: 'list-collapse' },
                { key: 'feedback', label: 'Draft Feedback',   icon: 'message-square-heart' },
            ],
            quiz:     { topik: '', jumlah: 5, jenis: 'pg', tingkat: 'sedang', jenjang: '', file: null, fileName: '' },
            summary:  { materi: '' },
            feedback: { nama: '', konteks: '' },
            urls: {
                quiz:     '{{ route('ai.teacher.quiz') }}',
                summary:  '{{ route('ai.teacher.summary') }}',
                feedback: '{{ route('ai.teacher.feedback') }}',
                quizWord: '{{ route('ai.teacher.quiz.export-word') }}',
            },

            selectTab(key) {
                this.tab = key;
                this.clearResult();
                this.error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            setQuizFile(event) {
                const file = event.target.files[0] || null;
                this.quiz.file = file;
                this.quiz.fileName = file ? file.name : '';
                this.error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearQuizFile() {
                this.quiz.file = null;
                this.quiz.fileName = '';
                if (this.$refs.quizFile) this.$refs.quizFile.value = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            payloadFor(tool) {
                if (tool !== 'quiz') {
                    return {
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify(this[tool]),
                    };
                }

                const form = new FormData();
                form.append('topik', this.quiz.topik || '');
                form.append('jumlah', this.quiz.jumlah || 1);
                form.append('jenis', this.quiz.jenis);
                form.append('tingkat', this.quiz.tingkat);
                form.append('jenjang', this.quiz.jenjang || '');
                if (this.quiz.file) form.append('file', this.quiz.file);

                return {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: form,
                };
            },

            async submit(tool) {
                if (this.loading) return;
                this.loading = true;
                this.result = '';
                this.error = '';
                this.copied = false;
                this.editing = false;
                try {
                    const payload = this.payloadFor(tool);
                    const r = await fetch(this.urls[tool], {
                        method: 'POST',
                        headers: payload.headers,
                        body: payload.body,
                    });
                    const d = await r.json();
                    if (r.ok && d.ok) {
                        this.result = d.answer;
                    } else if (r.status === 422) {
                        this.error = d.message || 'Periksa isian form: ' + Object.values(d.errors || {}).flat().join(' ');
                    } else {
                        this.error = d.message || 'Terjadi kesalahan. Coba lagi.';
                    }
                } catch (_) {
                    this.error = 'Gagal terhubung. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.loading = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            async exportWord() {
                if (!this.result || this.exportingWord) return;
                this.exportingWord = true;
                this.error = '';
                try {
                    const r = await fetch(this.urls.quizWord, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({
                            title: this.quiz.topik ? 'Soal - ' + this.quiz.topik : 'Soal dari Asisten AI',
                            content: this.result,
                        }),
                    });

                    if (!r.ok) {
                        const d = await r.json().catch(() => ({}));
                        this.error = d.message || 'Export Word gagal. Coba lagi.';
                        return;
                    }

                    const blob = await r.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = this.quiz.topik ? 'soal-' + this.quiz.topik.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.docx' : 'soal-asisten-ai.docx';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                } catch (_) {
                    this.error = 'Export Word gagal. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.exportingWord = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },
            toggleEdit() {
                this.editing = !this.editing;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearResult() {
                this.result = '';
                this.copied = false;
                this.editing = false;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            copy() {
                navigator.clipboard.writeText(this.result).then(() => {
                    this.copied = true;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                    setTimeout(() => { this.copied = false; this.$nextTick(() => window.lucide && lucide.createIcons()); }, 2000);
                });
            },
        }
    }
</script>
@endsection
