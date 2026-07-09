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
            <button type="button" @click="tab = t.key; result = ''; error = ''"
                    :class="tab === t.key ? 'bg-primary text-white border-primary' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'"
                    class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition">
                <i :data-lucide="t.icon" class="w-4 h-4"></i><span x-text="t.label"></span>
            </button>
        </template>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- ─── Form ─── --}}
        <div class="card p-5">
            {{-- Generator Soal --}}
            <div x-show="tab === 'quiz'" class="space-y-4">
                <div>
                    <label class="form-label">Topik / Materi <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="quiz.topik" placeholder="mis. Fotosintesis, Perang Diponegoro, Pecahan…" class="form-input">
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
                <button type="button" @click="submit('quiz')" :disabled="loading || quiz.topik.trim() === ''" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="wand-2" class="w-4 h-4"></i> Buat Soal
                </button>
            </div>

            {{-- Perangkum Materi --}}
            <div x-show="tab === 'summary'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Materi <span class="text-rose-500">*</span></label>
                    <textarea x-model="summary.materi" rows="12" placeholder="Tempel materi panjang di sini…" class="form-input resize-y"></textarea>
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
                    <textarea x-model="feedback.konteks" rows="9" placeholder="mis. Jawaban ujian, sikap belajar, atau hal yang ingin dikomentari…" class="form-input resize-y"></textarea>
                </div>
                <button type="button" @click="submit('feedback')" :disabled="loading || feedback.konteks.trim() === ''" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="message-square-heart" class="w-4 h-4"></i> Susun Draf
                </button>
            </div>
        </div>

        {{-- ─── Hasil ─── --}}
        <div class="card p-5 flex flex-col min-h-[300px]">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> Hasil</h2>
                <button type="button" @click="copy()" x-show="result" x-cloak class="flex items-center gap-1.5 text-xs text-slate-500 hover:text-primary transition">
                    <i :data-lucide="copied ? 'check' : 'copy'" class="w-4 h-4"></i><span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                </button>
            </div>

            {{-- Loading --}}
            <div x-show="loading" x-cloak class="flex-1 grid place-items-center text-slate-400">
                <div class="text-center">
                    <i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin"></i>
                    <p class="text-sm mt-2">AsistenAI sedang menyusun…</p>
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
            <div x-show="result && !loading" x-cloak class="flex-1 overflow-auto whitespace-pre-wrap break-words text-sm text-slate-800 dark:text-slate-100 leading-relaxed" x-text="result"></div>
        </div>
    </div>
</div>

<script>
    function teacherAi() {
        return {
            tab: 'quiz',
            loading: false,
            result: '',
            error: '',
            copied: false,
            tabs: [
                { key: 'quiz',     label: 'Generator Soal',  icon: 'file-question' },
                { key: 'summary',  label: 'Perangkum Materi', icon: 'list-collapse' },
                { key: 'feedback', label: 'Draft Feedback',   icon: 'message-square-heart' },
            ],
            quiz:     { topik: '', jumlah: 5, jenis: 'pg', tingkat: 'sedang', jenjang: '' },
            summary:  { materi: '' },
            feedback: { nama: '', konteks: '' },
            urls: {
                quiz:     '{{ route('ai.teacher.quiz') }}',
                summary:  '{{ route('ai.teacher.summary') }}',
                feedback: '{{ route('ai.teacher.feedback') }}',
            },

            async submit(tool) {
                if (this.loading) return;
                this.loading = true;
                this.result = '';
                this.error = '';
                try {
                    const r = await fetch(this.urls[tool], {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                        body: JSON.stringify(this[tool]),
                    });
                    const d = await r.json();
                    if (r.ok && d.ok) {
                        this.result = d.answer;
                    } else if (r.status === 422) {
                        this.error = 'Periksa isian form: ' + Object.values(d.errors || {}).flat().join(' ');
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
