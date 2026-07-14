@extends('layouts.app')
@section('title', $quiz ? 'Edit Kuis' : 'Buat Kuis')

@push('styles')
@include('arena-belajar.partials.game-styles')
<style>
.edu-builder .edu-header {
    background: linear-gradient(135deg, color-mix(in srgb, var(--cp) 12%, white) 0%, #fff 55%, color-mix(in srgb, var(--cps, var(--cp)) 8%, white) 100%);
    border: 1px solid color-mix(in srgb, var(--cp) 22%, transparent);
    border-radius: 1rem;
}
.dark .edu-builder .edu-header {
    background: linear-gradient(135deg, color-mix(in srgb, var(--cp) 18%, #0f172a), #0f172a);
    border-color: color-mix(in srgb, var(--cp) 30%, transparent);
}
.edu-builder .edu-section {
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    background: #fff;
}
.dark .edu-builder .edu-section {
    background: #0f172a;
    border-color: #334155;
}
.edu-builder .edu-section-title {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-weight: 800;
    font-size: .95rem;
    color: #0f172a;
}
.dark .edu-builder .edu-section-title { color: #f1f5f9; }
.edu-builder .edu-step {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: .5rem;
    display: grid;
    place-items: center;
    font-size: .75rem;
    font-weight: 800;
    color: #fff;
    background: var(--cp);
    flex-shrink: 0;
}
.edu-builder .edu-label {
    display: block;
    font-size: .8rem;
    font-weight: 700;
    color: #475569;
    margin-bottom: .35rem;
}
.dark .edu-builder .edu-label { color: #94a3b8; }
.edu-builder .edu-input {
    width: 100%;
    min-height: 2.75rem;
    padding: .65rem .9rem;
    border-radius: .75rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-size: .9rem;
}
.dark .edu-builder .edu-input {
    background: #1e293b;
    border-color: #475569;
    color: #f1f5f9;
}
.edu-builder .edu-input:focus {
    outline: none;
    border-color: var(--cp);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--cp) 18%, transparent);
}
.edu-builder .edu-check {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    min-height: 2.5rem;
    padding: .35rem .7rem;
    border-radius: .65rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: .8rem;
    font-weight: 600;
    color: #334155;
}
.dark .edu-builder .edu-check {
    background: #1e293b;
    border-color: #334155;
    color: #e2e8f0;
}
.edu-builder .edu-check input { accent-color: var(--cp); }
.edu-builder .edu-q {
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    overflow: hidden;
    background: #fff;
}
.dark .edu-builder .edu-q {
    background: #0f172a;
    border-color: #334155;
}
.edu-builder .edu-q-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .75rem 1rem;
    background: color-mix(in srgb, var(--cp) 8%, #f8fafc);
    border-bottom: 1px solid #e2e8f0;
}
.dark .edu-builder .edu-q-head {
    background: color-mix(in srgb, var(--cp) 12%, #1e293b);
    border-bottom-color: #334155;
}
.edu-builder .edu-type {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}
.edu-builder .edu-type button {
    padding: .45rem .7rem;
    border-radius: .55rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    font-size: .75rem;
    font-weight: 700;
    color: #475569;
}
.dark .edu-builder .edu-type button {
    background: #1e293b;
    border-color: #475569;
    color: #cbd5e1;
}
.edu-builder .edu-type button.is-on {
    background: var(--cp);
    border-color: var(--cp);
    color: #fff;
}
.edu-builder .edu-letter {
    width: 2rem;
    height: 2rem;
    border-radius: .5rem;
    border: 1px solid #cbd5e1;
    display: grid;
    place-items: center;
    font-size: .75rem;
    font-weight: 800;
    color: #64748b;
    background: #f8fafc;
    flex-shrink: 0;
    cursor: pointer;
}
.dark .edu-builder .edu-letter {
    background: #1e293b;
    border-color: #475569;
    color: #94a3b8;
}
.edu-builder .edu-letter.is-correct {
    background: color-mix(in srgb, var(--cp) 85%, #166534);
    border-color: transparent;
    color: #fff;
}
.edu-builder .edu-sticky {
    position: sticky;
    bottom: .75rem;
    z-index: 20;
    display: flex;
    gap: .6rem;
    padding: .65rem;
    border-radius: .9rem;
    background: rgba(255,255,255,.96);
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 24px rgba(15,23,42,.08);
    backdrop-filter: blur(8px);
}
.dark .edu-builder .edu-sticky {
    background: rgba(15,23,42,.96);
    border-color: #334155;
}
.edu-builder .edu-btn-primary {
    flex: 1.3;
    min-height: 2.9rem;
    border-radius: .75rem;
    border: none;
    background: var(--cp);
    color: #fff;
    font-weight: 800;
    font-size: .9rem;
}
.edu-builder .edu-btn-ghost {
    flex: 1;
    min-height: 2.9rem;
    border-radius: .75rem;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
    font-weight: 700;
    display: grid;
    place-items: center;
    text-decoration: none;
}
.dark .edu-builder .edu-btn-ghost {
    background: #1e293b;
    border-color: #475569;
    color: #e2e8f0;
}
</style>
@endpush

@php
    $initialQuestions = [];
    if ($quiz) {
        foreach ($quiz->questions as $q) {
            $initialQuestions[] = [
                'type' => $q->type,
                'question_text' => $q->question_text,
                'points' => $q->points,
                'explanation' => $q->explanation,
                'options' => $q->options->map(fn ($o) => [
                    'option_text' => $o->option_text,
                    'is_correct' => (bool) $o->is_correct,
                ])->values()->all() ?: [
                    ['option_text' => '', 'is_correct' => true],
                    ['option_text' => '', 'is_correct' => false],
                ],
                'meta' => [
                    'answers' => $q->meta['answers'] ?? [''],
                    'pairs' => $q->meta['pairs'] ?? [['left' => '', 'right' => ''], ['left' => '', 'right' => '']],
                ],
            ];
        }
    }
    if (!$initialQuestions) {
        $initialQuestions = [[
            'type' => 'mcq',
            'question_text' => '',
            'points' => 1,
            'explanation' => '',
            'options' => [
                ['option_text' => '', 'is_correct' => true],
                ['option_text' => '', 'is_correct' => false],
                ['option_text' => '', 'is_correct' => false],
                ['option_text' => '', 'is_correct' => false],
            ],
            'meta' => [
                'answers' => [''],
                'pairs' => [['left' => '', 'right' => ''], ['left' => '', 'right' => '']],
            ],
        ]];
    }
@endphp

@section('content')
<div class="space-y-5 max-w-3xl mx-auto edu-builder"
     x-data="arenaBuilder(@js($initialQuestions))"
     x-cloak>

    <div class="edu-header p-5 sm:p-6">
        <a href="{{ route('classroom.arena.index', $classroom) }}" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 inline-flex items-center gap-1 mb-2 font-medium">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Arena Belajar
        </a>
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide mb-1" style="color:var(--cp)">Pembelajaran interaktif</p>
                <h1 class="text-xl sm:text-2xl font-black text-slate-800 dark:text-slate-100">
                    {{ $quiz ? 'Edit Kuis' : 'Buat Kuis' }}
                </h1>
                <p class="text-sm text-slate-500 mt-1">{{ $classroom->title }} · Susun soal untuk siswa kelas ini</p>
            </div>
            <div class="text-sm font-semibold text-slate-500">
                <span x-text="questions.length"></span> soal
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm">
        <p class="font-bold mb-1">Periksa kembali isian berikut:</p>
        <ul class="list-disc pl-4 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST"
          action="{{ $quiz ? route('classroom.arena.update', [$classroom, $quiz]) : route('classroom.arena.store', $classroom) }}"
          class="space-y-5"
          @submit="prepareSubmit">
        @csrf

        {{-- Pengaturan --}}
        <div class="edu-section p-4 sm:p-5 space-y-4">
            <div class="edu-section-title">
                <span class="edu-step">1</span>
                Pengaturan kuis
            </div>

            <div>
                <label class="edu-label">Judul kuis</label>
                <input type="text" name="title" value="{{ old('title', $quiz->title ?? '') }}" required maxlength="200"
                       class="edu-input" placeholder="Contoh: Latihan Pecahan — Pertemuan 3">
            </div>
            <div>
                <label class="edu-label">Petunjuk untuk siswa (opsional)</label>
                <textarea name="instructions" rows="2" class="edu-input" placeholder="Tuliskan aturan atau materi yang diujikan…">{{ old('instructions', $quiz->instructions ?? '') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="edu-label">Mode penilaian</label>
                    <select name="scoring_mode" class="edu-input">
                        <option value="accuracy" @selected(old('scoring_mode', $quiz->scoring_mode ?? 'accuracy')==='accuracy')>Akurasi (disarankan untuk nilai rapor)</option>
                        <option value="competitive" @selected(old('scoring_mode', $quiz->scoring_mode ?? '')==='competitive')>Kompetitif (bonus kecepatan)</option>
                    </select>
                </div>
                <div>
                    <label class="edu-label">Nilai maksimum</label>
                    <input type="number" name="max_score" min="1" max="1000" value="{{ old('max_score', $quiz->max_score ?? 100) }}" class="edu-input">
                </div>
                <div>
                    <label class="edu-label">Waktu dibuka</label>
                    <input type="datetime-local" name="opens_at" value="{{ old('opens_at', isset($quiz->opens_at) ? $quiz->opens_at->format('Y-m-d\TH:i') : '') }}" class="edu-input">
                </div>
                <div>
                    <label class="edu-label">Batas pengumpulan</label>
                    <input type="datetime-local" name="due_at" value="{{ old('due_at', isset($quiz->due_at) ? $quiz->due_at->format('Y-m-d\TH:i') : '') }}" class="edu-input">
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <label class="edu-check"><input type="checkbox" name="instant_feedback" value="1" @checked(old('instant_feedback', $quiz->instant_feedback ?? true))> Umpan balik langsung</label>
                <label class="edu-check"><input type="checkbox" name="hide_scores" value="1" @checked(old('hide_scores', $quiz->hide_scores ?? false))> Sembunyikan skor</label>
                <label class="edu-check"><input type="checkbox" name="show_leaderboard" value="1" @checked(old('show_leaderboard', $quiz->show_leaderboard ?? false))> Tampilkan peringkat</label>
                <label class="edu-check"><input type="checkbox" name="publish_now" value="1" @checked(old('publish_now'))> Terbitkan sekarang</label>
                <input type="hidden" name="assign_self" value="1">
            </div>
        </div>

        {{-- Impor --}}
        <details class="edu-section p-4 sm:p-5 group">
            <summary class="cursor-pointer list-none edu-section-title">
                <span class="edu-step" style="background:color-mix(in srgb,var(--cp) 70%,#0f172a)">AI</span>
                Impor soal dari teks / AI Asisten SIMS
                <span class="ml-auto text-xs font-medium text-slate-400 group-open:hidden">buka</span>
            </summary>
            <div class="mt-3 space-y-3">
                <p class="text-xs text-slate-500">Tempel soal bernomor. Contoh: <code class="text-[11px] px-1 rounded bg-slate-100 dark:bg-slate-800">1. Ibu kota Indonesia? A. Bandung B. Jakarta *</code></p>
                <textarea x-model="importText" rows="4" class="edu-input" placeholder="Tempel teks soal di sini…"></textarea>
                <button type="button" @click="runImport" :disabled="importing"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold text-white min-h-[44px]" style="background:var(--cp)">
                    <i data-lucide="wand-2" class="w-4 h-4"></i>
                    <span x-text="importing ? 'Memproses…' : 'Impor ke daftar soal'"></span>
                </button>
                <p x-show="importMsg" class="text-sm font-semibold text-emerald-600" x-text="importMsg"></p>
            </div>
        </details>

        {{-- Soal --}}
        <div class="space-y-4">
            <div class="edu-section-title px-1">
                <span class="edu-step">2</span>
                Daftar soal
            </div>

            <template x-for="(q, qi) in questions" :key="qi">
                <div class="edu-q">
                    <div class="edu-q-head">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-8 h-8 rounded-lg grid place-items-center text-sm font-bold text-white shrink-0" style="background:var(--cp)" x-text="qi+1"></span>
                            <span class="font-bold text-slate-800 dark:text-slate-100 truncate" x-text="'Soal ' + (qi+1)"></span>
                        </div>
                        <button type="button" @click="removeQuestion(qi)" class="text-sm font-semibold text-rose-600 min-h-[40px] px-2" x-show="questions.length > 1">Hapus</button>
                    </div>

                    <div class="p-4 space-y-4">
                        <div>
                            <label class="edu-label">Pertanyaan</label>
                            <textarea x-model="q.question_text" :name="'questions['+qi+'][question_text]'" rows="2" required
                                      class="edu-input" placeholder="Tuliskan pertanyaan…"></textarea>
                        </div>

                        <div>
                            <label class="edu-label">Jenis soal</label>
                            <input type="hidden" :name="'questions['+qi+'][type]'" :value="q.type">
                            <div class="edu-type">
                                <button type="button" :class="q.type==='mcq' && 'is-on'" @click="q.type='mcq'; onTypeChange(qi)">Pilihan ganda</button>
                                <button type="button" :class="q.type==='true_false' && 'is-on'" @click="q.type='true_false'; onTypeChange(qi)">Benar / Salah</button>
                                <button type="button" :class="q.type==='short_answer' && 'is-on'" @click="q.type='short_answer'; onTypeChange(qi)">Isian singkat</button>
                                <button type="button" :class="q.type==='match' && 'is-on'" @click="q.type='match'; onTypeChange(qi)">Pasangkan</button>
                            </div>
                        </div>

                        <div class="w-28">
                            <label class="edu-label">Bobot</label>
                            <input type="number" min="1" max="100" x-model.number="q.points" :name="'questions['+qi+'][points]'" class="edu-input">
                        </div>

                        <div class="space-y-2" x-show="q.type === 'mcq' || q.type === 'true_false'">
                            <p class="edu-label mb-0">Pilihan jawaban — ketuk huruf untuk menandai kunci</p>
                            <template x-for="(opt, oi) in q.options" :key="oi">
                                <div class="flex items-center gap-2">
                                    <button type="button" class="edu-letter" :class="opt.is_correct && 'is-correct'"
                                            @click="setCorrect(qi, oi)"
                                            x-text="['A','B','C','D','E','F'][oi]"
                                            title="Tandai sebagai jawaban benar"></button>
                                    <input type="radio" class="sr-only" :name="'correct_'+qi" :checked="opt.is_correct">
                                    <input type="hidden" :name="'questions['+qi+'][options]['+oi+'][is_correct]'" :value="opt.is_correct ? 1 : 0">
                                    <input type="text" x-model="opt.option_text" :name="'questions['+qi+'][options]['+oi+'][option_text]'"
                                           :required="q.type === 'mcq' || q.type === 'true_false'"
                                           class="edu-input flex-1"
                                           :placeholder="'Pilihan ' + ['A','B','C','D','E','F'][oi]">
                                </div>
                            </template>
                        </div>

                        <div class="space-y-2" x-show="q.type === 'short_answer'" x-cloak>
                            <p class="edu-label mb-0">Kunci jawaban (boleh lebih dari satu)</p>
                            <template x-for="(ans, ai) in q.meta.answers" :key="ai">
                                <div class="flex gap-2">
                                    <input type="text" x-model="q.meta.answers[ai]" :name="'questions['+qi+'][meta][answers]['+ai+']'"
                                           class="edu-input flex-1" placeholder="Jawaban yang diterima">
                                    <button type="button" class="text-rose-600 text-sm font-semibold px-2" @click="q.meta.answers.splice(ai,1)" x-show="q.meta.answers.length > 1">Hapus</button>
                                </div>
                            </template>
                            <button type="button" class="text-sm font-semibold" style="color:var(--cp)" @click="q.meta.answers.push('')">+ Tambah kunci</button>
                        </div>

                        <div class="space-y-2" x-show="q.type === 'match'" x-cloak>
                            <p class="edu-label mb-0">Pasangan istilah dan definisi</p>
                            <template x-for="(pair, pi) in q.meta.pairs" :key="pi">
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" x-model="pair.left" :name="'questions['+qi+'][meta][pairs]['+pi+'][left]'"
                                           class="edu-input" placeholder="Istilah">
                                    <input type="text" x-model="pair.right" :name="'questions['+qi+'][meta][pairs]['+pi+'][right]'"
                                           class="edu-input" placeholder="Definisi">
                                </div>
                            </template>
                            <button type="button" class="text-sm font-semibold" style="color:var(--cp)" @click="q.meta.pairs.push({left:'',right:''})">+ Tambah pasangan</button>
                        </div>

                        <div>
                            <label class="edu-label">Pembahasan (opsional)</label>
                            <input type="text" x-model="q.explanation" :name="'questions['+qi+'][explanation]'"
                                   class="edu-input" placeholder="Penjelasan singkat setelah menjawab">
                        </div>
                    </div>
                </div>
            </template>

            <button type="button" @click="addQuestion"
                    class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold border border-dashed min-h-[48px]"
                    style="border-color:color-mix(in srgb,var(--cp) 45%,#94a3b8);color:var(--cp);background:color-mix(in srgb,var(--cp) 6%,transparent)">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah soal
            </button>
        </div>

        <div class="edu-sticky">
            <a href="{{ route('classroom.arena.index', $classroom) }}" class="edu-btn-ghost">Batal</a>
            <button type="submit" class="edu-btn-primary">Simpan kuis</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function arenaBuilder(initial) {
    return {
        questions: initial,
        importText: '',
        importing: false,
        importMsg: '',
        addQuestion() {
            this.questions.push({
                type: 'mcq',
                question_text: '',
                points: 1,
                explanation: '',
                options: [
                    { option_text: '', is_correct: true },
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false },
                    { option_text: '', is_correct: false },
                ],
                meta: {
                    answers: [''],
                    pairs: [{ left: '', right: '' }, { left: '', right: '' }],
                },
            });
            this.$nextTick(() => window.lucide && lucide.createIcons());
        },
        removeQuestion(i) {
            if (this.questions.length > 1) this.questions.splice(i, 1);
        },
        setCorrect(qi, oi) {
            this.questions[qi].options.forEach((o, idx) => o.is_correct = idx === oi);
        },
        onTypeChange(qi) {
            const q = this.questions[qi];
            if (!q.meta) q.meta = { answers: [''], pairs: [{ left: '', right: '' }, { left: '', right: '' }] };
            if (q.type === 'true_false') {
                q.options = [
                    { option_text: 'Benar', is_correct: true },
                    { option_text: 'Salah', is_correct: false },
                ];
            } else if (q.type === 'mcq') {
                if (q.options.length < 4) {
                    while (q.options.length < 4) q.options.push({ option_text: '', is_correct: false });
                    this.setCorrect(qi, 0);
                }
            } else if (q.type === 'short_answer') {
                if (!q.meta.answers?.length) q.meta.answers = [''];
            } else if (q.type === 'match') {
                if (!q.meta.pairs?.length) q.meta.pairs = [{ left: '', right: '' }, { left: '', right: '' }];
            }
        },
        prepareSubmit() {
            this.questions.forEach((q, qi) => {
                if (q.type === 'mcq' || q.type === 'true_false') {
                    this.setCorrect(qi, q.options.findIndex(o => o.is_correct));
                }
            });
        },
        async runImport() {
            if (!this.importText.trim()) return;
            this.importing = true;
            this.importMsg = '';
            try {
                const res = await fetch(@js(route('classroom.arena.import', $classroom)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ raw_text: this.importText }),
                });
                const data = await res.json();
                if (!data.ok || !data.questions.length) {
                    this.importMsg = 'Tidak ada soal yang dikenali. Periksa format teks.';
                } else {
                    this.questions = data.questions.map(q => ({
                        type: q.type,
                        question_text: q.question_text,
                        points: 1,
                        explanation: q.explanation || '',
                        options: q.options || [],
                        meta: {
                            answers: q.meta?.answers || [''],
                            pairs: q.meta?.pairs || [{ left: '', right: '' }, { left: '', right: '' }],
                        },
                    }));
                    this.importMsg = data.count + ' soal berhasil diimpor. Periksa kunci jawaban sebelum menyimpan.';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            } catch (e) {
                this.importMsg = 'Gagal mengimpor. Coba lagi.';
            } finally {
                this.importing = false;
            }
        },
    };
}
</script>
@endpush
