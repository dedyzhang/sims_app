@extends('layouts.app')
@section('title', 'Asisten Guru')

@section('content')
<div class="space-y-5" x-data="teacherAi()">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title flex items-center gap-2"><i data-lucide="sparkles" class="w-6 h-6 text-primary"></i> Asisten Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Percepat menyusun soal, RPM Learning, ringkasan materi, dan umpan balik siswa.</p>
        </div>
    </div>
    {{-- Generate quota --}}
    <div class="card p-4" x-show="quota" x-cloak>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <i data-lucide="gauge" class="w-4 h-4 text-primary"></i>
                    Generate Kuota
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                          :class="quota.live ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="quota.live && quota.key_alive !== false ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                        <span x-text="quota.live ? 'Live' : 'Estimasi'"></span>
                    </span>
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-show="quota.provider !== 'ninerouter' && quota.status && quota.status !== 'ok'" x-text="quota.message"></p>
                <div class="mt-3 flex flex-wrap items-end gap-3">
                    <div class="text-2xl font-extrabold text-slate-800 dark:text-slate-100" x-text="quota.remaining_label || 'Asisten Guru'"></div>
                    <div class="pb-1 text-xs font-medium text-slate-400" x-show="quota.provider !== 'ninerouter' && quota.remaining_percent !== null && quota.status === 'ok'" x-text="quota.remaining_percent + '% tersisa'"></div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
                    <span x-show="quota.key_alive === true" class="text-emerald-600 dark:text-emerald-400 font-semibold">Key aktif</span>
                    <span x-show="quota.key_alive === false" class="text-rose-600 dark:text-rose-400 font-semibold">Key bermasalah</span>
                    <span x-show="quota.updated_at_human" x-text="'Update ' + quota.updated_at_human"></span>
                    <button type="button" class="font-semibold text-primary hover:underline" @click="refreshQuota(true)" :disabled="quotaLoading">Segarkan</button>
                </div>
            </div>
            <div class="w-full lg:w-72">
                <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" x-show="quota.remaining_percent !== null && quota.status === 'ok'">
                    <div class="h-full rounded-full bg-primary transition-all" :style="'width: ' + quota.remaining_percent + '%'"></div>
                </div>
                <div class="mt-2 h-3 rounded-full bg-slate-100 dark:bg-slate-800" x-show="quota.remaining_percent === null || quota.status !== 'ok'"></div>
            </div>
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

    <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(240px,0.55fr)]">
        {{-- Form --}}
        <div class="card p-5">
            {{-- Generator Soal --}}
            <div x-show="tab === 'quiz'" class="space-y-4">
                <div>
                    <label class="form-label">Topik / Fokus Materi <span class="text-rose-500" x-show="quiz.source === 'ai'" x-cloak>*</span></label>
                    <input type="text" x-model="quiz.topik" placeholder="mis. Fotosintesis, Perang Diponegoro, Pecahan..." class="form-input">
                    <p class="text-[11px] text-slate-400 mt-1">Jika upload materi, topik boleh dipakai sebagai fokus soal.</p>
                </div>

                <div>
                    <label class="form-label">Sumber Materi <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button type="button" @click="quiz.source = 'ai'"
                                :class="quiz.source === 'ai' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-3 py-2 text-xs font-semibold transition">Generate dari topik</button>
                        <button type="button" @click="quiz.source = 'file'"
                                :class="quiz.source === 'file' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-3 py-2 text-xs font-semibold transition">Upload materi</button>
                    </div>
                </div>

                <div x-show="quiz.source === 'file'" x-cloak>
                    <label class="form-label">File Materi Soal <span class="text-rose-500">*</span></label>
                    <label class="flex min-h-[104px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                        <input x-ref="quizFile" type="file" class="sr-only" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="setQuizFile($event)">
                        <i data-lucide="upload-cloud" class="w-7 h-7 text-slate-400"></i>
                        <span class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="quiz.fileName || 'Unggah PDF atau Word'"></span>
                        <span class="mt-1 text-[11px] text-slate-400">AI menyusun soal berdasarkan isi file agar tidak melenceng. Maks. 10 MB.</span>
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
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <div>
                        <label class="form-label">Jenis Soal <span class="text-rose-500">*</span></label>
                        <div class="grid gap-2 rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <template x-for="option in quizTypeOptions" :key="option.value">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition"
                                       :class="quiz.jenis_soal.includes(option.value) ? 'border-primary bg-primary/5 text-primary' : 'border-slate-200 text-slate-600 hover:border-primary/50 dark:border-slate-700 dark:text-slate-300'">
                                    <input type="checkbox" :value="option.value" x-model="quiz.jenis_soal" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    <span x-text="option.label"></span>
                                </label>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-rose-500" x-show="quiz.jenis_soal.length === 0" x-cloak>Pilih minimal satu jenis soal.</p>
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
                <button type="button" @click="submit('quiz')" :disabled="loading || quiz.jenis_soal.length === 0 || (quiz.source === 'file' ? !quiz.file : quiz.topik.trim() === '')" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="wand-2" class="w-4 h-4"></i> Buat Soal
                </button>
            </div>

            {{-- RPM Learning --}}
            <div x-show="tab === 'learning'" class="space-y-4" x-cloak>
                <div>
                    <label class="form-label">Topik / Judul RPM <span class="text-rose-500" x-show="learning.source === 'ai'" x-cloak>*</span></label>
                    <input type="text" x-model="learning.topik" placeholder="mis. Ekosistem, Persamaan Linear, Teks Prosedur..." class="form-input">
                    <p class="text-[11px] text-slate-400 mt-1">Jika upload materi, topik boleh dipakai sebagai fokus/judul RPM.</p>
                </div>
                <div>
                    <label class="form-label">Sumber Materi <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                        <button type="button" @click="learning.source = 'ai'"
                                :class="learning.source === 'ai' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-3 py-2 text-xs font-semibold transition">Generate dari topik</button>
                        <button type="button" @click="learning.source = 'file'"
                                :class="learning.source === 'file' ? 'bg-white text-primary shadow-sm dark:bg-slate-900' : 'text-slate-500 dark:text-slate-300'"
                                class="rounded-lg px-3 py-2 text-xs font-semibold transition">Upload materi</button>
                    </div>
                </div>
                <div x-show="learning.source === 'file'" x-cloak>
                    <label class="form-label">File Materi RPM <span class="text-rose-500">*</span></label>
                    <label class="flex min-h-[104px] cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                        <input x-ref="learningFile" type="file" class="sr-only" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" @change="setLearningFile($event)">
                        <i data-lucide="upload-cloud" class="w-7 h-7 text-slate-400"></i>
                        <span class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="learning.fileName || 'Unggah PDF atau Word'"></span>
                        <span class="mt-1 text-[11px] text-slate-400">AI akan menyusun RPM berdasarkan isi file agar tidak melenceng. Maks. 10 MB.</span>
                    </label>
                    <div x-show="learning.file" x-cloak class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-slate-100 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                        <span class="truncate" x-text="learning.fileName"></span>
                        <button type="button" @click="clearLearningFile()" class="inline-flex items-center gap-1 text-rose-600 hover:text-rose-700 dark:text-rose-300">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Mata Pelajaran</label>
                        <input type="text" x-model="learning.mapel" placeholder="mis. IPAS" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Jenjang / Kelas</label>
                        <input type="text" x-model="learning.jenjang" placeholder="mis. Kelas 5 SD" class="form-input">
                    </div>
                </div>
                <div>
                    <label class="form-label">Alokasi Waktu</label>
                    <input type="text" x-model="learning.durasi" placeholder="mis. 2 x 40 menit" class="form-input">
                </div>
                <button type="button" @click="submit('learning')" :disabled="loading || (learning.source === 'file' ? !learning.file : learning.topik.trim() === '')" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="clipboard-list" class="w-4 h-4"></i> Buat RPM Learning
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
                    <button type="button" x-show="tab === 'quiz'" @click="exportQuiz('word')" :disabled="exportingWord" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingWord ? 'loader-circle' : 'file-down'" class="w-4 h-4" :class="exportingWord ? 'animate-spin' : ''"></i><span x-text="exportingWord ? 'Export...' : 'Word'"></span>
                    </button>
                    <button type="button" x-show="tab === 'quiz'" @click="exportQuiz('pdf')" :disabled="exportingPdf" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingPdf ? 'loader-circle' : 'file-type'" class="w-4 h-4" :class="exportingPdf ? 'animate-spin' : ''"></i><span x-text="exportingPdf ? 'Export...' : 'PDF'"></span>
                    </button>
                    <button type="button" x-show="tab === 'learning'" @click="exportLearning('word')" :disabled="exportingWord" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingWord ? 'loader-circle' : 'file-down'" class="w-4 h-4" :class="exportingWord ? 'animate-spin' : ''"></i><span x-text="exportingWord ? 'Export...' : 'Word'"></span>
                    </button>
                    <button type="button" x-show="tab === 'learning'" @click="exportLearning('pdf')" :disabled="exportingPdf" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingPdf ? 'loader-circle' : 'file-type'" class="w-4 h-4" :class="exportingPdf ? 'animate-spin' : ''"></i><span x-text="exportingPdf ? 'Export...' : 'PDF'"></span>
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
                    <p class="text-sm mt-2">Asisten Guru sedang menyusun...</p>
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

            {{-- Pratinjau dokumen berformat (soal / RPM): sama persis dengan hasil export --}}
            <div x-show="result && !loading && !editing && previewHtml" x-cloak class="flex-1 overflow-auto" x-html="previewHtml"></div>

            {{-- Teks biasa: tab lain, atau bila pratinjau gagal/konten tak berformat RPM --}}
            <div x-show="result && !loading && !editing && !previewHtml" x-cloak class="ai-answer flex-1 overflow-auto break-words text-sm text-slate-800 dark:text-slate-100" x-html="renderAiMarkdown(result)"></div>
        </div>

        {{-- History generate: collapse + drag-resize supaya tidak mendominasi layar --}}
        <div class="card p-0 flex flex-col overflow-hidden lg:col-span-2 xl:col-span-1"
             x-data="{
                collapsed: localStorage.getItem('ai.teacher.historyCollapsed') === '1',
                height: Number(localStorage.getItem('ai.teacher.historyHeight') || 220),
                dragging: false,
                toggle() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('ai.teacher.historyCollapsed', this.collapsed ? '1' : '0');
                },
                startResize(e) {
                    if (this.collapsed) return;
                    this.dragging = true;
                    const startY = e.clientY;
                    const startH = this.height;
                    const onMove = (ev) => {
                        this.height = Math.min(520, Math.max(140, startH + (ev.clientY - startY)));
                    };
                    const onUp = () => {
                        this.dragging = false;
                        localStorage.setItem('ai.teacher.historyHeight', String(this.height));
                        window.removeEventListener('pointermove', onMove);
                        window.removeEventListener('pointerup', onUp);
                    };
                    window.addEventListener('pointermove', onMove);
                    window.addEventListener('pointerup', onUp);
                }
             }">
            <button type="button" @click="toggle()"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 text-sm">
                    <i data-lucide="history" class="w-4 h-4"></i> History Generate
                    <span class="text-[11px] font-medium text-slate-400" x-text="histories.length ? '(' + histories.length + ')' : ''"></span>
                </h2>
                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform" :class="collapsed ? '' : 'rotate-180'"></i>
            </button>

            <div x-show="!collapsed" x-cloak class="flex flex-col border-t border-slate-100 dark:border-slate-800"
                 :style="'height:' + height + 'px'">
                <div x-show="histories.length === 0" class="flex-1 grid place-items-center text-slate-300 dark:text-slate-600 px-4">
                    <p class="text-xs text-center">Belum ada history.</p>
                </div>

                <div x-show="histories.length > 0" class="flex-1 space-y-1.5 overflow-auto px-3 py-2">
                    <template x-for="item in histories" :key="item.uuid">
                        <div class="rounded-lg border border-slate-200 bg-white transition hover:border-primary hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:border-primary/70">
                            <div class="flex items-start gap-1 p-2">
                                <button type="button" @click="openHistory(item)" class="min-w-0 flex-1 text-left">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="inline-flex items-center rounded-full bg-primary-50 px-1.5 py-0.5 text-[10px] font-semibold text-primary" x-text="item.type_label"></span>
                                        <span class="shrink-0 text-[10px] text-slate-400" x-text="item.created_at_human || ''"></span>
                                    </div>
                                    <div class="mt-1 line-clamp-1 text-xs font-semibold text-slate-700 dark:text-slate-100" x-text="item.title"></div>
                                    <p class="mt-0.5 line-clamp-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400" x-text="item.excerpt"></p>
                                </button>
                                <button type="button" @click="deleteHistory(item)" :disabled="deletingHistory === item.uuid"
                                        :title="'Hapus history: ' + item.title"
                                        class="shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:opacity-50 dark:hover:bg-rose-900/30 dark:hover:text-rose-300">
                                    <i :data-lucide="deletingHistory === item.uuid ? 'loader-circle' : 'trash-2'" class="w-3.5 h-3.5" :class="deletingHistory === item.uuid ? 'animate-spin' : ''"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div role="separator" aria-orientation="horizontal" title="Geser untuk ubah tinggi"
                     @pointerdown.prevent="startResize($event)"
                     class="h-2 cursor-row-resize border-t border-slate-100 bg-slate-50 hover:bg-primary/10 dark:border-slate-800 dark:bg-slate-900/40"
                     :class="dragging ? 'bg-primary/20' : ''">
                    <div class="mx-auto mt-0.5 h-0.5 w-8 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                </div>
            </div>
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
            exportingPdf: false,
            result: '',
            error: '',
            copied: false,
            editing: false,
            previewHtml: '',      // dokumen berformat: soal (tab quiz) atau RPM (tab learning)
            previewLoading: false,
            deletingHistory: '',  // uuid item history yang sedang dihapus
            histories: @js($histories ?? []),
            quota: @js($quotaUsage ?? null),
            quotaLoading: false,
            quotaTimer: null,
            tabs: [
                { key: 'quiz',     label: 'Generator Soal',  icon: 'file-question' },
                { key: 'learning', label: 'RPM Learning', icon: 'clipboard-list' },
                { key: 'summary',  label: 'Perangkum Materi', icon: 'list-collapse' },
                { key: 'feedback', label: 'Draft Feedback',   icon: 'message-square-heart' },
            ],
            quizTypeOptions: [
                { value: 'pg_kompleks', label: 'Pilihan Ganda Kompleks' },
                { value: 'pg', label: 'Pilihan Ganda' },
                { value: 'benar_salah', label: 'Benar/Salah' },
                { value: 'mencocokkan', label: 'Mencocokkan' },
                { value: 'isian', label: 'Isian' },
            ],
            quiz:     { topik: '', jumlah: 5, jenis_soal: ['pg'], tingkat: 'sedang', jenjang: '', source: 'ai', file: null, fileName: '' },
            learning: { tool: 'rpp', topik: '', mapel: '', jenjang: '', durasi: '', source: 'ai', file: null, fileName: '' },
            summary:  { materi: '' },
            feedback: { nama: '', konteks: '' },
            urls: {
                quiz:     '{{ route('ai.teacher.quiz') }}',
                learning: '{{ route('ai.teacher.learning') }}',
                summary:  '{{ route('ai.teacher.summary') }}',
                feedback: '{{ route('ai.teacher.feedback') }}',
                quota:    '{{ route('ai.teacher.quota') }}',
                historyBase: '{{ url('ai/teacher/history') }}',
                quizPreview: '{{ route('ai.teacher.quiz.preview') }}',
                quizWord: '{{ route('ai.teacher.quiz.export-word') }}',
                quizPdf: '{{ route('ai.teacher.quiz.export-pdf') }}',
                learningPreview: '{{ route('ai.teacher.learning.preview') }}',
                learningWord: '{{ route('ai.teacher.learning.export-word') }}',
                learningPdf: '{{ route('ai.teacher.learning.export-pdf') }}',
            },

            init() {
                this.startQuotaPolling();
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) this.refreshQuota(true);
                });
            },

            startQuotaPolling() {
                if (this.quotaTimer) clearInterval(this.quotaTimer);
                this.quotaTimer = setInterval(() => this.refreshQuota(false), 10000);
            },

            async refreshQuota(fresh = false) {
                if (this.quotaLoading) return;
                this.quotaLoading = true;
                try {
                    const url = this.urls.quota + (fresh ? '?fresh=1' : '');
                    const r = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const d = await r.json();
                    if (r.ok && d.quota) this.updateQuota(d.quota);
                } catch (_) {
                    // diam: polling gagal tidak mengganggu form
                } finally {
                    this.quotaLoading = false;
                }
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

            setLearningFile(event) {
                const file = event.target.files[0] || null;
                this.learning.file = file;
                this.learning.fileName = file ? file.name : '';
                this.error = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearLearningFile() {
                this.learning.file = null;
                this.learning.fileName = '';
                if (this.$refs.learningFile) this.$refs.learningFile.value = '';
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            payloadFor(tool) {
                if (tool === 'summary' || tool === 'feedback') {
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
                if (tool === 'learning') {
                    form.append('tool', this.learning.tool);
                    form.append('topik', this.learning.topik || '');
                    form.append('mapel', this.learning.mapel || '');
                    form.append('jenjang', this.learning.jenjang || '');
                    form.append('durasi', this.learning.durasi || '');
                    if (this.learning.source === 'file' && this.learning.file) form.append('file', this.learning.file);
                } else {
                    form.append('topik', this.quiz.topik || '');
                    form.append('jumlah', this.quiz.jumlah || 1);
                    this.quiz.jenis_soal.forEach((jenis) => form.append('jenis_soal[]', jenis));
                    form.append('tingkat', this.quiz.tingkat);
                    form.append('jenjang', this.quiz.jenjang || '');
                    if (this.quiz.source === 'file' && this.quiz.file) form.append('file', this.quiz.file);
                }

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
                    this.updateQuota(d.quota);
                    if (r.ok && d.ok) {
                        this.result = d.answer;
                        if (d.history) this.addHistory(d.history);
                        if (tool === 'learning' || tool === 'quiz') await this.refreshPreview();
                        await this.refreshQuota(true);
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

            async exportQuiz(format) {
                if (!this.result) return;
                const isPdf = format === 'pdf';
                if ((isPdf && this.exportingPdf) || (!isPdf && this.exportingWord)) return;
                if (isPdf) this.exportingPdf = true; else this.exportingWord = true;
                this.error = '';
                try {
                    const title = this.quiz.topik ? 'Soal - ' + this.quiz.topik : 'Soal dari Asisten Guru';
                    const r = await fetch(isPdf ? this.urls.quizPdf : this.urls.quizWord, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': isPdf ? 'application/pdf,application/json' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ title, content: this.result }),
                    });

                    if (!r.ok) {
                        const d = await r.json().catch(() => ({}));
                        this.error = d.message || 'Export gagal. Coba lagi.';
                        return;
                    }

                    const blob = await r.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = this.slugify(title || 'soal-asisten-ai') + (isPdf ? '.pdf' : '.docx');
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                } catch (_) {
                    this.error = 'Export gagal. Periksa koneksi lalu coba lagi.';
                } finally {
                    if (isPdf) this.exportingPdf = false; else this.exportingWord = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },
            /**
             * Ambil pratinjau dokumen berformat dari server (parser + template yang sama
             * dengan export), jadi tampilan di layar persis seperti hasil unduhannya.
             * Berlaku untuk tab yang punya dokumen berformat: soal dan RPM Learning.
             */
            async refreshPreview() {
                const url = { quiz: this.urls.quizPreview, learning: this.urls.learningPreview }[this.tab];
                if (!url || !this.result) {
                    this.previewHtml = '';
                    return;
                }
                this.previewLoading = true;
                try {
                    const body = this.tab === 'learning'
                        ? { tool: this.learning.tool, content: this.result }
                        : { content: this.result };
                    const r = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify(body),
                    });
                    const d = await r.json();
                    // Gagal pratinjau bukan kegagalan fatal: teks hasil tetap tampil apa adanya.
                    this.previewHtml = (r.ok && d.ok) ? d.html : '';
                } catch (_) {
                    this.previewHtml = '';
                } finally {
                    this.previewLoading = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },
            updateQuota(quota) {
                if (quota) this.quota = quota;
            },

            addHistory(item) {
                this.histories = [item, ...this.histories.filter((history) => history.uuid !== item.uuid)].slice(0, 20);
            },

            async deleteHistory(item) {
                if (this.deletingHistory) return;
                if (!confirm('Hapus history "' + item.title + '"? Hasil yang sudah diunduh tidak ikut terhapus.')) return;

                this.deletingHistory = item.uuid;
                try {
                    const r = await fetch(this.urls.historyBase + '/' + item.uuid, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                    });
                    if (!r.ok) {
                        this.error = 'Gagal menghapus history. Coba lagi.';
                        return;
                    }
                    this.histories = this.histories.filter((history) => history.uuid !== item.uuid);
                } catch (_) {
                    this.error = 'Gagal menghapus history. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.deletingHistory = '';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            openHistory(item) {
                const learningTypes = ['rpp'];
                this.tab = learningTypes.includes(item.type) ? 'learning' : item.type;
                if (learningTypes.includes(item.type)) this.learning.tool = item.type;
                this.result = item.answer || '';
                this.error = '';
                this.copied = false;
                this.editing = false;
                this.previewHtml = '';
                if (this.tab === 'learning' || this.tab === 'quiz') this.refreshPreview();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            learningToolLabel() {
                return 'RPM Learning';
            },
            async exportLearning(format) {
                if (!this.result) return;
                const isPdf = format === 'pdf';
                if ((isPdf && this.exportingPdf) || (!isPdf && this.exportingWord)) return;
                if (isPdf) this.exportingPdf = true; else this.exportingWord = true;
                this.error = '';
                try {
                    const toolLabel = this.learningToolLabel();
                    const title = this.learning.topik ? toolLabel + ' - ' + this.learning.topik : toolLabel;
                    const r = await fetch(isPdf ? this.urls.learningPdf : this.urls.learningWord, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': isPdf ? 'application/pdf,application/json' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({
                            tool: this.learning.tool,
                            title,
                            content: this.result,
                        }),
                    });

                    if (!r.ok) {
                        const d = await r.json().catch(() => ({}));
                        this.error = d.message || 'Export gagal. Coba lagi.';
                        return;
                    }

                    const blob = await r.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = this.slugify(title || 'perangkat-ajar-learning') + (isPdf ? '.pdf' : '.docx');
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                } catch (_) {
                    this.error = 'Export gagal. Periksa koneksi lalu coba lagi.';
                } finally {
                    if (isPdf) this.exportingPdf = false; else this.exportingWord = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            slugify(value) {
                return (value || 'dokumen')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '') || 'dokumen';
            },
            toggleEdit() {
                this.editing = !this.editing;
                // Keluar dari mode edit: susun ulang pratinjau agar ikut suntingan guru.
                if (!this.editing) this.refreshPreview();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            clearResult() {
                this.result = '';
                this.previewHtml = '';
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
