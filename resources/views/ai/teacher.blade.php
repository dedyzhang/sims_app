@extends('layouts.app')
@section('title', 'Asisten Guru')

@section('content')
<style>
    /* Generator Soal — hasil dokumen responsif di HP landscape/WebView */
    .ai-teacher-hasil .quiz-preview-scroll,
    .ai-teacher-hasil .ai-answer {
        -webkit-overflow-scrolling: touch;
        min-height: 0;
    }
    @media (orientation: landscape) and (max-height: 560px) and (max-width: 900px) {
        .ai-teacher-hasil {
            max-height: min(72vh, 640px);
            min-height: 0;
        }
        .ai-teacher-hasil .quiz-preview-scroll,
        .ai-teacher-hasil .ai-answer,
        .ai-teacher-hasil textarea.form-input {
            min-height: 0;
        }
    }
</style>
<div class="space-y-5 relative min-w-0 max-w-full" x-data="teacherAi()">

    {{-- Gate: wajib API key Gemini pribadi --}}
    <template x-if="needsApiKeySetup">
        <div class="fixed inset-0 z-[80] grid place-items-center bg-slate-900/55 backdrop-blur-sm p-4">
            <div class="w-full max-w-lg card p-6 space-y-4 shadow-xl" @click.stop>
                <div class="flex items-start gap-3">
                    <span class="grid place-items-center w-11 h-11 rounded-2xl bg-primary text-white shrink-0">
                        <i data-lucide="key-round" class="w-5 h-5"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="font-extrabold text-slate-800 dark:text-slate-100 text-lg">Hubungkan API key Gemini</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Generate di SIMS memakai API key akun Google Anda.
                            SIMS tidak membuat key otomatis — buat sekali di Google AI Studio, lalu tempel di sini.
                        </p>
                    </div>
                </div>
                <ol class="list-decimal pl-5 text-xs text-slate-600 dark:text-slate-300 space-y-1.5 leading-relaxed">
                    <li>Buka Google AI Studio → Create API key</li>
                    <li>Salin key, tempel di bawah, lalu simpan</li>
                </ol>
                <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer"
                   class="btn-primary w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold min-h-[48px]">
                    <i data-lucide="external-link" class="w-4 h-4"></i> Buka Google AI Studio
                </a>
                <div>
                    <label class="form-label">Tempel API key <span class="text-rose-500">*</span></label>
                    <input type="password" x-model="apiKeyInput" x-ref="apiKeyGateInput"
                           class="form-input font-mono text-sm" placeholder="AIza…" autocomplete="off"
                           @keydown.enter.prevent="saveGeminiApiKey()">
                </div>
                <p class="text-xs text-rose-500 font-semibold" x-show="apiKeyError" x-cloak x-text="apiKeyError"></p>
                <button type="button" @click="saveGeminiApiKey" :disabled="apiKeySaving || !(apiKeyInput || '').trim()"
                        class="btn-primary w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold min-h-[48px] disabled:opacity-40">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span x-text="apiKeySaving ? 'Memvalidasi & menyimpan…' : 'Simpan API key'"></span>
                </button>
            </div>
        </div>
    </template>

    <div class="space-y-5" :class="needsApiKeySetup ? 'pointer-events-none select-none opacity-40 blur-[1px]' : ''">
    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title flex items-center gap-2"><i data-lucide="sparkles" class="w-6 h-6 text-primary"></i> Asisten Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Nalar Guru, generator soal, RPM, ringkasan, dan umpan balik.</p>
        </div>
    </div>
    {{-- Pintasan Nalar Guru + key --}}
    @if($launcherAktif ?? true)
    <div class="card p-4 space-y-4" x-show="launcherAktif && !needsApiKeySetup" x-cloak>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-xl bg-primary/15 text-primary">
                        <i data-lucide="brain" class="w-4 h-4"></i>
                    </span>
                    Nalar Guru
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    Generate di SIMS memakai API key akun Google Anda.
                    <span x-show="external.has_gemini_api_key" x-cloak>
                        Key <span class="font-semibold font-mono" x-text="external.gemini_api_key_masked"></span>
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <button type="button" @click="selectTab('gemini')"
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold min-h-[44px]">
                    <i data-lucide="message-circle" class="w-4 h-4"></i> Buka Nalar Guru
                </button>
            </div>
        </div>

        <div class="grid gap-3 rounded-xl border border-primary/15 bg-primary/[0.04] dark:bg-slate-900/40 p-3">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="showReplaceKey = !showReplaceKey"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-bold text-slate-700 dark:text-slate-200 min-h-[40px] hover:border-primary transition">
                    <i data-lucide="key-round" class="w-4 h-4"></i> Ganti API key
                </button>
                <button type="button" @click="deleteGeminiApiKey" :disabled="apiKeySaving"
                        class="inline-flex items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 px-4 py-2 text-sm font-bold text-rose-600 min-h-[40px] hover:bg-rose-50 dark:hover:bg-rose-900/30 transition disabled:opacity-50">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Hapus key
                </button>
                <p class="text-[11px] text-primary font-semibold" x-show="externalSaved" x-cloak x-text="externalMessage"></p>
                <p class="text-[11px] text-rose-500 font-semibold" x-show="apiKeyError && !needsApiKeySetup" x-cloak x-text="apiKeyError"></p>
            </div>
            <div x-show="showReplaceKey" x-cloak class="space-y-2 pt-1 border-t border-primary/10">
                <label class="form-label">API key baru</label>
                <input type="password" x-model="apiKeyInput" class="form-input font-mono text-sm" placeholder="AIza…" autocomplete="off">
                <div class="flex flex-wrap gap-2">
                    <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i> AI Studio
                    </a>
                    <button type="button" @click="saveGeminiApiKey" :disabled="apiKeySaving || !(apiKeyInput || '').trim()"
                            class="btn-primary inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-bold disabled:opacity-40">
                        Simpan key
                    </button>
                </div>
                <p class="text-[11px] text-rose-500 font-semibold" x-show="apiKeyError" x-cloak x-text="apiKeyError"></p>
            </div>
        </div>
    </div>
    @endif

    {{-- Canva Pendidikan (belajar.id, gratis) --}}
    <div class="card p-4 space-y-3" x-show="!needsApiKeySetup && canva.feature_enabled" x-cloak>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-xl bg-sky-500/15 text-sky-600">
                        <i data-lucide="palette" class="w-4 h-4"></i>
                    </span>
                    Canva Pendidikan
                </h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    Hubungkan dengan akun <strong>belajar.id</strong> sekolah. Gratis, tanpa Canva Pro.
                    <span x-show="canva.connected" x-cloak>
                        Terhubung: <span class="font-semibold font-mono" x-text="canva.email_masked"></span>
                    </span>
                </p>
                <div class="mt-3 flex flex-col sm:flex-row gap-2" x-show="!canva.connected" x-cloak>
                    <input type="email" x-model="belajarIdInput" placeholder="nama@sekolah.belajar.id"
                           class="form-input text-sm font-mono flex-1 min-w-0"
                           :disabled="canvaBusy">
                    <button type="button" @click="saveBelajarId" :disabled="canvaBusy || !(belajarIdInput || '').trim()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold min-h-[44px] disabled:opacity-40">
                        Simpan email
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 mt-1" x-show="!canva.connected && canva.belajar_hint" x-cloak>
                    Siap hubungkan: <span class="font-mono font-semibold" x-text="canva.belajar_hint"></span>
                </p>
                <p class="text-[11px] text-rose-500 font-semibold mt-1" x-show="canvaError" x-cloak x-text="canvaError"></p>
                <p class="text-[11px] text-emerald-600 font-semibold mt-1" x-show="canvaMessage" x-cloak x-text="canvaMessage"></p>
            </div>
            <div class="flex flex-wrap gap-2 flex-shrink-0">
                <a href="{{ route('ai.teacher.presentasi.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-bold min-h-[44px] hover:border-primary">
                    <i data-lucide="presentation" class="w-4 h-4"></i> Studio Presentasi
                </a>
                <a x-show="!canva.connected" x-cloak href="{{ route('ai.teacher.canva.connect') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-sky-600 text-white px-4 py-2.5 text-sm font-bold min-h-[44px] hover:bg-sky-700"
                   :class="(!canva.configured || !canva.belajar_hint) && 'opacity-50 pointer-events-none'">
                    <i data-lucide="link" class="w-4 h-4"></i> Hubungkan Canva
                </a>
                <button x-show="canva.connected" x-cloak type="button" @click="disconnectCanva" :disabled="canvaBusy"
                        class="inline-flex items-center gap-2 rounded-xl border border-rose-200 dark:border-rose-800 px-4 py-2.5 text-sm font-bold text-rose-600 min-h-[44px] disabled:opacity-50">
                    <i data-lucide="unlink" class="w-4 h-4"></i> Putuskan
                </button>
            </div>
        </div>
        <p class="text-[11px] text-amber-700 dark:text-amber-300" x-show="!canva.configured" x-cloak>
            Admin belum mengisi <code>CANVA_CLIENT_ID</code> / <code>CANVA_CLIENT_SECRET</code> di server.
        </p>
    </div>

    {{-- Alur mengajar: soal → Arena / Presentasi / Canva --}}
    <div class="card p-4 space-y-3" x-show="!needsApiKeySetup" x-cloak>
        <div>
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                <span class="grid place-items-center w-8 h-8 rounded-xl bg-primary/10 text-primary">
                    <i data-lucide="route" class="w-4 h-4"></i>
                </span>
                Alur mengajar
            </h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Buat soal di Generator Soal, lalu lanjut ke Arena (kuis/misi), Studio Presentasi, atau Canva.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="tab = 'quiz'"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold min-h-[40px] hover:border-primary">
                <i data-lucide="file-question" class="w-3.5 h-3.5"></i> Generator Soal
            </button>
            @if($arenaBelajarAktif ?? false)
            <a href="{{ route('classroom.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold min-h-[40px] hover:border-primary">
                <i data-lucide="gamepad-2" class="w-3.5 h-3.5"></i> Arena Belajar
            </a>
            @endif
            <a href="{{ route('ai.teacher.presentasi.index') }}"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-xs font-bold min-h-[40px] hover:border-primary">
                <i data-lucide="presentation" class="w-3.5 h-3.5"></i> Studio Presentasi
            </a>
            <span x-show="canva.feature_enabled" x-cloak
                  class="inline-flex items-center gap-2 rounded-xl border border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-300 px-3 py-2 text-xs font-bold min-h-[40px]">
                <i data-lucide="palette" class="w-3.5 h-3.5"></i> Canva (panel di atas)
            </span>
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

    {{-- Nalar Guru (chat di dalam SIMS) --}}
    <div x-show="tab === 'gemini'" x-cloak class="card overflow-hidden flex flex-col shadow-sm" style="min-height:min(70vh,720px)">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-primary/15"
             style="background: linear-gradient(115deg, color-mix(in srgb, var(--cp) 12%, white) 0%, #fff 55%, color-mix(in srgb, var(--cps) 8%, white) 100%);">
            <div class="min-w-0">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="grid place-items-center w-9 h-9 rounded-xl bg-primary text-white shadow-sm">
                        <i data-lucide="brain" class="w-4 h-4"></i>
                    </span>
                    <span>
                        Nalar Guru
                        <span class="block text-[11px] font-medium text-slate-500 mt-0.5">Generate di SIMS · API key akun Anda</span>
                    </span>
                </h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="clearGeminiChat"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-primary/20 bg-white/80 dark:bg-slate-800 px-3 py-2 text-xs font-bold text-slate-600 dark:text-slate-300 hover:border-primary hover:text-primary transition">
                    <i data-lucide="eraser" class="w-3.5 h-3.5"></i> Reset chat
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-4 py-5 space-y-3 dark:bg-slate-950/40"
             x-ref="geminiScroll"
             style="background:
                radial-gradient(ellipse 80% 50% at 10% 0%, color-mix(in srgb, var(--cp) 10%, transparent), transparent 55%),
                radial-gradient(ellipse 60% 40% at 90% 100%, color-mix(in srgb, var(--cps) 12%, transparent), transparent 50%),
                color-mix(in srgb, var(--cp) 3%, #f8fafc);">
            <div x-show="geminiMessages.length === 0" class="h-full min-h-[260px] grid place-items-center text-center px-3">
                <div class="w-full max-w-lg">
                    <div class="mx-auto mb-4 grid place-items-center w-16 h-16 rounded-2xl bg-primary text-white shadow-md"
                         style="box-shadow: 0 10px 24px -12px color-mix(in srgb, var(--cp) 70%, transparent);">
                        <i data-lucide="brain" class="w-8 h-8"></i>
                    </div>
                    <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100 tracking-tight">Tanya Nalar Guru</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                        Minta soal, penjelasan materi, atau rubrik — generate langsung di SIMS dengan API key Anda.
                    </p>
                    <div class="mt-5 grid gap-2 text-left">
                        <template x-for="s in geminiSuggestions" :key="s">
                            <button type="button" @click="geminiInput = s; sendGeminiChat()"
                                    class="group flex items-start gap-3 rounded-xl border border-primary/15 bg-white/90 dark:bg-slate-900/80 px-3.5 py-3 text-left text-xs font-semibold text-slate-600 dark:text-slate-300 transition hover:border-primary hover:bg-primary/5 hover:text-primary">
                                <span class="mt-0.5 grid place-items-center w-7 h-7 shrink-0 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition">
                                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                </span>
                                <span class="leading-snug pt-1" x-text="s"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <template x-for="(m, i) in geminiMessages" :key="i">
                <div class="flex gap-2" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div x-show="m.role === 'assistant'" class="hidden sm:grid place-items-center w-8 h-8 shrink-0 rounded-xl bg-primary/15 text-primary mt-1">
                        <i data-lucide="brain" class="w-4 h-4"></i>
                    </div>
                    <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed"
                         :class="m.role === 'user'
                            ? 'max-w-[92%] sm:max-w-[80%] bg-primary text-white rounded-br-md shadow-sm'
                            : (m.previewHtml
                                ? 'w-full max-w-3xl bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 border border-primary/15 rounded-bl-md overflow-auto shadow-sm'
                                : 'max-w-[92%] sm:max-w-[80%] bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 border border-primary/15 rounded-bl-md shadow-sm')">
                        <div x-show="m.role === 'assistant' && m.previewHtml" x-cloak
                             class="min-w-0 max-w-full overflow-x-auto overflow-y-auto overscroll-contain"
                             x-html="m.previewHtml"></div>
                        <div x-show="m.role === 'assistant' && !m.previewHtml" class="ai-answer break-words whitespace-pre-wrap" x-text="m.text"></div>
                        <div x-show="m.role === 'user'" class="whitespace-pre-wrap" x-text="m.text"></div>
                        <div x-show="m.role === 'assistant'" class="mt-2.5 flex flex-wrap gap-2 border-t border-primary/10 pt-2">
                            <button type="button" @click="useGeminiAsQuizResult(m)"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-primary hover:bg-primary/10">
                                <i data-lucide="file-question" class="w-3.5 h-3.5"></i> Buka di Generator Soal
                            </button>
                            <button type="button" @click="result = m.text; exportQuiz('word')"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <i data-lucide="file-down" class="w-3.5 h-3.5"></i> Word
                            </button>
                            <button type="button" @click="result = m.text; exportQuiz('pdf')"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                <i data-lucide="file-type" class="w-3.5 h-3.5"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="geminiLoading" class="flex justify-start gap-2" x-cloak>
                <div class="hidden sm:grid place-items-center w-8 h-8 shrink-0 rounded-xl bg-primary/15 text-primary">
                    <i data-lucide="brain" class="w-4 h-4"></i>
                </div>
                <div class="rounded-2xl rounded-bl-md bg-white dark:bg-slate-900 border border-primary/15 px-4 py-3 text-sm text-slate-500 flex items-center gap-2 shadow-sm">
                    <i data-lucide="loader-circle" class="w-4 h-4 animate-spin text-primary"></i> Nalar Guru sedang menyusun…
                </div>
            </div>
            <p class="text-xs text-rose-500 font-semibold" x-show="geminiError" x-cloak x-text="geminiError"></p>
        </div>

        <div x-show="externalFlow && externalTool === 'chat'" x-cloak
             class="border-t border-primary/15 px-4 py-3 bg-primary/[0.04] space-y-2">
            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">
                Tempel jawaban Gemini
                <span class="font-medium text-slate-500">· pastikan akun Google Anda sudah login di Gemini web</span>
            </p>
            <p class="text-[11px] text-emerald-700 dark:text-emerald-300 font-semibold" x-show="promptCopied" x-cloak>Perintah sudah disalin · tempel di Gemini (Ctrl+V)</p>
            <textarea x-model="externalPaste" rows="5" class="form-input text-sm leading-relaxed"
                      placeholder="Tempel hasil dari Gemini web di sini…"></textarea>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="applyExternalResult()" :disabled="applyingExternal || !(externalPaste || '').trim()"
                        class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-bold disabled:opacity-40">
                    <i data-lucide="check" class="w-4 h-4"></i> Pakai hasil ini
                </button>
                <button type="button" @click="reopenExternalGemini()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-primary/20 px-3 py-2 text-xs font-bold text-primary">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Buka Gemini lagi
                </button>
            </div>
        </div>

        <form @submit.prevent="sendGeminiChat" class="border-t border-primary/15 p-3 bg-white/95 dark:bg-slate-900 backdrop-blur-sm">
            <div class="flex gap-2 items-end rounded-2xl border border-primary/20 bg-primary/[0.03] p-2 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition">
                <textarea x-model="geminiInput" rows="2" @keydown.enter.prevent="if (!$event.shiftKey) sendGeminiChat()"
                          class="flex-1 resize-none bg-transparent border-0 outline-none focus:ring-0 text-sm px-2 py-2 text-slate-700 dark:text-slate-100 placeholder:text-slate-400"
                          placeholder="Tulis pertanyaan untuk Nalar Guru… (Enter kirim, Shift+Enter baris baru)"></textarea>
                <button type="submit" :disabled="geminiLoading || !geminiInput.trim()"
                        class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-bold min-h-[48px] disabled:opacity-40 disabled:pointer-events-none">
                    <i data-lucide="send" class="w-4 h-4"></i> Kirim
                </button>
            </div>
        </form>
    </div>

    <div class="grid gap-5 min-w-0 xl:grid-cols-2 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(240px,0.55fr)]"
         x-show="isToolTab" x-cloak>
        {{-- Form --}}
        <div class="card p-5 min-w-0">
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
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 transition"
                       :class="quiz.soal_bergambar ? 'border-primary bg-primary/5' : 'border-slate-200 dark:border-slate-700'">
                    <input type="checkbox" x-model="quiz.soal_bergambar" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span>
                        <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Soal bergambar (Gemini Image)</span>
                        <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">AI menambahkan diagram/ilustrasi pada soal. Memakai kuota Gemini Image terpisah (maks. {{ (int) config('ai.image.max_per_quiz', 5) }} gambar/batch).</span>
                    </span>
                </label>
                <button type="button" @click="submit('quiz')" :disabled="loading || quiz.jenis_soal.length === 0 || (quiz.source === 'file' ? !quiz.file : quiz.topik.trim() === '')" class="btn-primary w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-40">
                    <i data-lucide="wand-2" class="w-4 h-4"></i> Buat Soal
                </button>
                <button type="button" @click="submitExternal('quiz')" :disabled="loading || quiz.jenis_soal.length === 0 || (quiz.source === 'file' ? !quiz.file : quiz.topik.trim() === '')"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-xs font-semibold text-slate-500 hover:border-primary hover:text-primary disabled:opacity-40">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Cadangan: buka Gemini web
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
                <button type="button" @click="submitExternal('learning')" :disabled="loading || (learning.source === 'file' ? !learning.file : learning.topik.trim() === '')"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-xs font-semibold text-slate-500 hover:border-primary hover:text-primary disabled:opacity-40">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Cadangan: buka Gemini web
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
        <div class="ai-teacher-hasil card p-4 sm:p-5 flex flex-col min-h-[300px] min-w-0 max-w-full overflow-x-clip">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-3 min-w-0">
                <h2 class="font-semibold text-slate-700 dark:text-slate-200 flex items-center gap-2 shrink-0"><i data-lucide="file-text" class="w-4 h-4"></i> Hasil</h2>
                <div x-show="result" x-cloak class="flex flex-wrap items-center gap-1.5 sm:justify-end min-w-0">
                    <button type="button" @click="toggleEdit()" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="editing ? 'check' : 'pencil'" class="w-4 h-4"></i><span x-text="editing ? 'Selesai' : 'Edit'"></span>
                    </button>
                    <button type="button" x-show="tab === 'quiz'" @click="exportQuiz('word')" :disabled="exportingWord" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingWord ? 'loader-circle' : 'file-down'" class="w-4 h-4" :class="exportingWord ? 'animate-spin' : ''"></i><span x-text="exportingWord ? 'Export...' : 'Word'"></span>
                    </button>
                    <button type="button" x-show="tab === 'quiz'" @click="exportQuiz('pdf')" :disabled="exportingPdf" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs text-slate-500 transition hover:bg-slate-100 hover:text-primary disabled:opacity-50 dark:text-slate-300 dark:hover:bg-slate-800">
                        <i :data-lucide="exportingPdf ? 'loader-circle' : 'file-type'" class="w-4 h-4" :class="exportingPdf ? 'animate-spin' : ''"></i><span x-text="exportingPdf ? 'Export...' : 'PDF'"></span>
                    </button>
                    <button type="button" x-show="tab === 'quiz' && arenaBelajarAktif && arenaClassrooms.length"
                            @click="openSendToArena()" :disabled="sendingArena"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-primary transition hover:bg-primary/10 disabled:opacity-50">
                        <i :data-lucide="sendingArena ? 'loader-circle' : 'gamepad-2'" class="w-4 h-4" :class="sendingArena ? 'animate-spin' : ''"></i>
                        <span x-text="sendingArena ? 'Mengirim…' : 'Kirim ke Arena'"></span>
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

            {{-- Loading: menyiapkan prompt eksternal --}}
            <div x-show="loading" x-cloak class="flex-1 grid place-items-center text-slate-400">
                <div class="text-center">
                    <i data-lucide="loader-circle" class="w-8 h-8 mx-auto animate-spin"></i>
                    <p class="text-sm mt-2">Asisten Guru sedang menyusun...</p>
                </div>
            </div>

            {{-- Panduan setelah prompt disalin & Gemini dibuka --}}
            <div x-show="externalFlow && !loading" x-cloak class="rounded-xl border border-primary/20 bg-primary/[0.04] px-4 py-3 text-sm space-y-2">
                <p class="font-bold text-slate-800 dark:text-slate-100">Langkah generate di Gemini web</p>
                <ol class="list-decimal pl-5 space-y-1 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    <li>Pastikan Anda sudah login di Gemini web dengan akun Google yang dipakai membuat API key</li>
                    <li>Tempel perintah di Gemini (<kbd class="px-1 rounded bg-slate-200 dark:bg-slate-700">Ctrl</kbd>+<kbd class="px-1 rounded bg-slate-200 dark:bg-slate-700">V</kbd>) lalu generate</li>
                    <li>Salin jawaban Gemini, tempel di bawah, lalu klik <span class="font-semibold">Pakai hasil ini</span></li>
                </ol>
                <p class="text-[11px] text-emerald-700 dark:text-emerald-300 font-semibold" x-show="promptCopied" x-cloak>Perintah sudah disalin ke clipboard.</p>
                <button type="button" @click="reopenExternalGemini()" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Buka Gemini lagi
                </button>
                <div class="pt-1 space-y-2">
                    <label class="form-label">Tempel jawaban dari Gemini</label>
                    <textarea x-model="externalPaste" rows="8" class="form-input text-sm leading-relaxed" placeholder="Tempel hasil generate dari Gemini di sini…"></textarea>
                    <button type="button" @click="applyExternalResult()" :disabled="applyingExternal || !(externalPaste || '').trim()"
                            class="btn-primary w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold disabled:opacity-40">
                        <i :data-lucide="applyingExternal ? 'loader-circle' : 'check'" class="w-4 h-4" :class="applyingExternal ? 'animate-spin' : ''"></i>
                        <span x-text="applyingExternal ? 'Menyimpan…' : 'Pakai hasil ini'"></span>
                    </button>
                </div>
            </div>

            {{-- Error --}}
            <div x-show="error && !loading" x-cloak class="rounded-xl bg-rose-50 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-800 px-4 py-3 text-sm" x-text="error"></div>

            {{-- Empty --}}
            <div x-show="!loading && !result && !error && !externalFlow" x-cloak class="flex-1 grid place-items-center text-slate-300 dark:text-slate-600">
                <div class="text-center">
                    <i data-lucide="sparkles" class="w-10 h-10 mx-auto opacity-40"></i>
                    <p class="text-sm mt-2">Hasil akan muncul di sini.</p>
                </div>
            </div>

            {{-- Result --}}
            <textarea x-show="result && !loading && editing" x-cloak x-model="result" rows="16" class="form-input flex-1 min-h-[260px] resize-y text-sm leading-relaxed"></textarea>

            {{-- Pratinjau dokumen berformat (soal / RPM): sama persis dengan hasil export --}}
            <div x-show="result && !loading && !editing && previewHtml" x-cloak
                 class="quiz-preview-scroll flex-1 min-w-0 max-w-full overflow-x-auto overflow-y-auto overscroll-contain"
                 x-html="previewHtml"></div>

            {{-- Teks biasa: tab lain, atau bila pratinjau gagal/konten tak berformat RPM --}}
            <div x-show="result && !loading && !editing && !previewHtml" x-cloak
                 class="ai-answer flex-1 min-w-0 max-w-full overflow-x-auto overflow-y-auto break-words text-sm text-slate-800 dark:text-slate-100"
                 x-html="renderAiMarkdown(result)"></div>
        </div>

        {{-- History generate: collapse + drag-resize supaya tidak mendominasi layar --}}
        <div class="card p-0 flex flex-col overflow-hidden xl:col-span-2 2xl:col-span-1"
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
    </div>{{-- /blur wrapper --}}

    {{-- Modal Arena di luar card Hasil agar fixed tidak ter-clip overflow --}}
    <div x-show="showArenaModal" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4" @keydown.escape.window="showArenaModal = false">
        <div class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 shadow-xl ring-1 ring-slate-200 dark:ring-slate-700 p-5 space-y-4" @click.outside="showArenaModal = false">
            <div>
                <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i data-lucide="gamepad-2" class="w-5 h-5 text-primary"></i>
                    Kirim ke Arena Belajar
                </h3>
                <p class="text-xs text-slate-500 mt-1">Pilih ruang kelas. Soal akan diimpor ke form buat kuis.</p>
            </div>
            <div>
                <label class="form-label">Ruang kelas</label>
                <select x-model="arenaClassroomId" class="form-input">
                    <option value="">— pilih —</option>
                    <template x-for="c in arenaClassrooms" :key="c.uuid">
                        <option :value="c.uuid" x-text="c.title"></option>
                    </template>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary px-3 py-2 rounded-xl text-sm" @click="showArenaModal = false">Batal</button>
                <button type="button" class="btn-primary px-3 py-2 rounded-xl text-sm font-semibold disabled:opacity-40"
                        :disabled="!arenaClassroomId || sendingArena" @click="sendToArena()">
                    Buka form Arena
                </button>
            </div>
        </div>
    </div>
</div>

@include('partials.ai-markdown')

<script>
    function teacherAi() {
        return {
            tab: @js(in_array(request('tab'), ['gemini', 'quiz', 'learning', 'summary', 'feedback'], true) ? request('tab') : 'gemini'),
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
            arenaBelajarAktif: @js((bool) ($arenaBelajarAktif ?? false)),
            arenaClassrooms: @js($arenaClassrooms ?? []),
            arenaClassroomId: '',
            showArenaModal: false,
            sendingArena: false,
            launcherAktif: @js((bool) ($launcherAktif ?? true)),
            needsApiKeySetup: @js((bool) ($needsApiKeySetup ?? true)),
            external: {
                has_gemini_api_key: @js((bool) ($externalAccounts['has_gemini_api_key'] ?? false)),
                gemini_api_key_masked: @js($externalAccounts['gemini_api_key_masked'] ?? null),
            },
            canva: @js($canvaStatus ?? [
                'configured' => false,
                'feature_enabled' => true,
                'connected' => false,
                'email_masked' => null,
                'display_name' => null,
                'allowed_email_suffix' => '.belajar.id',
                'belajar_hint' => null,
                'connected_at' => null,
            ]),
            canvaBusy: false,
            canvaError: '',
            canvaMessage: '',
            belajarIdInput: @js(($canvaStatus['belajar_hint'] ?? null) ?: ''),
            apiKeyInput: '',
            apiKeySaving: false,
            apiKeyError: '',
            showReplaceKey: false,
            externalSaved: false,
            externalMessage: '',
            externalFlow: false,
            externalTitle: '',
            externalTool: '',
            externalPaste: '',
            externalGeminiUrl: 'https://gemini.google.com/app',
            promptCopied: false,
            applyingExternal: false,
            tabs: [
                { key: 'gemini',   label: 'Nalar Guru',      icon: 'brain' },
                { key: 'quiz',     label: 'Generator Soal',  icon: 'file-question' },
                { key: 'learning', label: 'RPM Learning',    icon: 'clipboard-list' },
                { key: 'summary',  label: 'Perangkum Materi', icon: 'list-collapse' },
                { key: 'feedback', label: 'Draft Feedback',  icon: 'message-square-heart' },
            ],
            geminiMessages: [],
            geminiInput: '',
            geminiLoading: false,
            geminiError: '',
            geminiSuggestions: [
                'Buatkan 5 soal pilihan ganda fotosintesis tingkat sedang untuk kelas 7',
                'Buat 8 soal campuran PG dan isian tentang pecahan, mudah, kelas 5 SD',
                'Jelaskan cara membuat rubrik penilaian proyek singkat',
            ],
            get isToolTab() {
                return this.tab !== 'gemini';
            },
            quizTypeOptions: [
                { value: 'pg_kompleks', label: 'Pilihan Ganda Kompleks' },
                { value: 'pg', label: 'Pilihan Ganda' },
                { value: 'benar_salah', label: 'Benar/Salah' },
                { value: 'mencocokkan', label: 'Mencocokkan' },
                { value: 'isian', label: 'Isian' },
            ],
            quiz:     { topik: '', jumlah: 5, jenis_soal: ['pg'], tingkat: 'sedang', jenjang: '', source: 'ai', file: null, fileName: '', soal_bergambar: false },
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
                quizSendArena: '{{ route('ai.teacher.quiz.send-arena') }}',
                learningPreview: '{{ route('ai.teacher.learning.preview') }}',
                learningWord: '{{ route('ai.teacher.learning.export-word') }}',
                learningPdf: '{{ route('ai.teacher.learning.export-pdf') }}',
                geminiKey: '{{ route('ai.teacher.gemini-key') }}',
                canvaStatus: '{{ route('ai.teacher.canva.status') }}',
                canvaDisconnect: '{{ route('ai.teacher.canva.disconnect') }}',
                canvaBelajarId: '{{ route('ai.teacher.canva.belajar-id') }}',
                externalPrompt: '{{ route('ai.teacher.external-prompt') }}',
                externalResult: '{{ route('ai.teacher.external-result') }}',
                chat: '{{ route('ai.teacher.chat') }}',
            },

            init() {
                this.startQuotaPolling();
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) this.refreshQuota(true);
                });
                this.$nextTick(() => {
                    window.lucide && lucide.createIcons();
                    if (this.needsApiKeySetup && this.$refs.apiKeyGateInput) {
                        this.$refs.apiKeyGateInput.focus();
                    }
                });
            },

            clearGeminiChat() {
                this.geminiMessages = [];
                this.geminiError = '';
                this.geminiInput = '';
            },

            async sendGeminiChat() {
                const message = (this.geminiInput || '').trim();
                if (!message || this.geminiLoading) return;
                if (this.needsApiKeySetup) {
                    this.geminiError = 'Simpan API key Gemini terlebih dahulu.';
                    return;
                }
                this.geminiError = '';
                this.geminiMessages.push({ role: 'user', text: message });
                this.geminiInput = '';
                this.geminiLoading = true;
                this.$nextTick(() => {
                    window.lucide && lucide.createIcons();
                    const el = this.$refs.geminiScroll;
                    if (el) el.scrollTop = el.scrollHeight;
                });
                try {
                    const history = this.geminiMessages.slice(0, -1).map(m => ({ role: m.role, text: m.text }));
                    const r = await fetch(this.urls.chat, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ message, history }),
                    });
                    const d = await r.json().catch(() => ({}));
                    this.updateQuota(d.quota);
                    if (!r.ok || !d.ok) {
                        if (d.needs_api_key) this.needsApiKeySetup = true;
                        this.geminiError = d.message || 'Gagal mendapatkan jawaban Nalar Guru.';
                        this.geminiMessages.pop();
                        return;
                    }
                    const answer = d.answer || '';
                    const msg = { role: 'assistant', text: answer, previewHtml: '' };
                    this.geminiMessages.push(msg);
                    if (d.history) this.histories.unshift(d.history);
                    await this.attachQuizPreviewToMessage(msg);
                } catch (_) {
                    this.geminiError = 'Koneksi gagal. Coba lagi.';
                    this.geminiMessages.pop();
                } finally {
                    this.geminiLoading = false;
                    this.$nextTick(() => {
                        window.lucide && lucide.createIcons();
                        const el = this.$refs.geminiScroll;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            },

            async launchExternalGemini(d) {
                this.externalFlow = true;
                this.externalTitle = d.title || '';
                this.externalTool = d.tool || this.externalTool || '';
                this.externalGeminiUrl = d.gemini_url || 'https://gemini.google.com/app';
                this.externalPaste = '';
                this.promptCopied = false;
                this.error = '';
                this.result = '';
                this.previewHtml = '';
                try {
                    await navigator.clipboard.writeText(d.prompt || '');
                    this.promptCopied = true;
                } catch (_) {
                    this.promptCopied = false;
                    this.error = 'Gagal menyalin otomatis. Salin manual dari riwayat perintah bila perlu.';
                }
                window.open(this.externalGeminiUrl, '_blank', 'noopener,noreferrer');
            },

            reopenExternalGemini() {
                if (this.externalGeminiUrl) {
                    window.open(this.externalGeminiUrl, '_blank', 'noopener,noreferrer');
                }
            },

            async applyExternalResult() {
                const answer = (this.externalPaste || '').trim();
                if (!answer || this.applyingExternal) return;
                const tool = this.externalTool || this.tab || 'quiz';
                this.applyingExternal = true;
                this.error = '';
                try {
                    const r = await fetch(this.urls.externalResult, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            tool,
                            title: this.externalTitle || '',
                            answer,
                        }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.error = d.message || 'Gagal menyimpan hasil dari Gemini.';
                        this.geminiError = this.error;
                        return;
                    }
                    this.result = d.answer || answer;
                    this.externalFlow = false;
                    this.externalPaste = '';
                    this.editing = false;
                    if (d.history) this.addHistory(d.history);
                    if (tool === 'chat') {
                        // ganti pesan awaiting dengan jawaban asli
                        const idx = [...this.geminiMessages].map((m, i) => ({ m, i })).reverse().find(x => x.m.awaitingPaste)?.i;
                        if (idx !== undefined) {
                            this.geminiMessages[idx] = { role: 'assistant', text: this.result, previewHtml: '' };
                            await this.attachQuizPreviewToMessage(this.geminiMessages[idx]);
                        } else {
                            const msg = { role: 'assistant', text: this.result, previewHtml: '' };
                            this.geminiMessages.push(msg);
                            await this.attachQuizPreviewToMessage(msg);
                        }
                        this.tab = 'gemini';
                    } else {
                        this.tab = tool === 'learning' ? 'learning' : (tool === 'summary' ? 'summary' : (tool === 'feedback' ? 'feedback' : 'quiz'));
                        if (tool === 'learning' || tool === 'quiz') await this.refreshPreview();
                    }
                } catch (_) {
                    this.error = 'Gagal terhubung saat menyimpan hasil.';
                } finally {
                    this.applyingExternal = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            async attachQuizPreviewToMessage(msg) {
                if (!msg?.text || !this.urls.quizPreview) return;
                // Hanya pratinjau bila teks tampak seperti dokumen soal (kop / SOAL EVALUASI / kunci).
                if (!/SOAL EVALUASI|Kunci Jawaban|Bagian\s+[A-Z]\s*-/i.test(msg.text)) return;
                try {
                    const r = await fetch(this.urls.quizPreview, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ content: msg.text }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok && d.ok && d.html && d.parsed === true) {
                        msg.previewHtml = d.html;
                    }
                } catch (_) {
                    // biarkan teks polos
                }
            },

            useGeminiAsQuizResult(msg) {
                if (!msg?.text) return;
                this.result = msg.text;
                this.tab = 'quiz';
                this.editing = false;
                this.error = '';
                this.refreshPreview();
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            async saveGeminiApiKey() {
                if (this.apiKeySaving) return;
                const key = (this.apiKeyInput || '').trim();
                if (!key) {
                    this.apiKeyError = 'API key wajib diisi.';
                    return;
                }
                this.apiKeySaving = true;
                this.apiKeyError = '';
                try {
                    const r = await fetch(this.urls.geminiKey, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ gemini_api_key: key }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.apiKeyError = d.message || 'Gagal menyimpan API key.';
                        return;
                    }
                    this.external.has_gemini_api_key = true;
                    this.external.gemini_api_key_masked = d.accounts?.gemini_api_key_masked || null;
                    this.needsApiKeySetup = false;
                    this.apiKeyInput = '';
                    this.showReplaceKey = false;
                    this.externalMessage = d.message || 'API key disimpan.';
                    this.externalSaved = true;
                    setTimeout(() => { this.externalSaved = false; }, 3000);
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.apiKeyError = 'Gagal menyimpan. Coba lagi.';
                } finally {
                    this.apiKeySaving = false;
                }
            },

            async deleteGeminiApiKey() {
                if (this.apiKeySaving) return;
                if (!confirm('Hapus API key Gemini dari akun SIMS Anda?')) return;
                this.apiKeySaving = true;
                this.apiKeyError = '';
                try {
                    const r = await fetch(this.urls.geminiKey, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.apiKeyError = d.message || 'Gagal menghapus API key.';
                        return;
                    }
                    this.external.has_gemini_api_key = false;
                    this.external.gemini_api_key_masked = null;
                    this.needsApiKeySetup = true;
                    this.showReplaceKey = false;
                    this.externalMessage = d.message || 'API key dihapus.';
                    this.externalSaved = true;
                    setTimeout(() => { this.externalSaved = false; }, 3000);
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.apiKeyError = 'Gagal menghapus. Coba lagi.';
                } finally {
                    this.apiKeySaving = false;
                }
            },

            async saveBelajarId() {
                if (this.canvaBusy) return;
                this.canvaBusy = true;
                this.canvaError = '';
                this.canvaMessage = '';
                try {
                    const r = await fetch(this.urls.canvaBelajarId, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ canva_belajar_id: (this.belajarIdInput || '').trim() }),
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.canvaError = d.message || (d.errors?.canva_belajar_id?.[0]) || 'Email belajar.id ditolak.';
                        return;
                    }
                    this.canva = Object.assign({}, this.canva, d.canva || {});
                    this.belajarIdInput = this.canva.belajar_hint || this.belajarIdInput;
                    this.canvaMessage = d.message || 'Email belajar.id disimpan.';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.canvaError = 'Gagal menyimpan email.';
                } finally {
                    this.canvaBusy = false;
                }
            },

            async disconnectCanva() {
                if (this.canvaBusy) return;
                if (!confirm('Putuskan tautan Canva Pendidikan dari akun SIMS?')) return;
                this.canvaBusy = true;
                this.canvaError = '';
                this.canvaMessage = '';
                try {
                    const r = await fetch(this.urls.canvaDisconnect, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const d = await r.json().catch(() => ({}));
                    if (!r.ok || !d.ok) {
                        this.canvaError = d.message || 'Gagal memutus Canva.';
                        return;
                    }
                    this.canva = Object.assign({}, this.canva, d.canva || { connected: false, email_masked: null });
                    this.canvaMessage = d.message || 'Tautan Canva diputus.';
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                } catch (_) {
                    this.canvaError = 'Gagal terhubung.';
                } finally {
                    this.canvaBusy = false;
                }
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
                if (this.isToolTab) {
                    this.clearResult();
                    this.error = '';
                }
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
                    form.append('soal_bergambar', this.quiz.soal_bergambar ? '1' : '0');
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
                if (this.needsApiKeySetup) {
                    this.error = 'Simpan API key Gemini terlebih dahulu.';
                    return;
                }
                this.loading = true;
                this.result = '';
                this.error = '';
                this.copied = false;
                this.editing = false;
                this.externalFlow = false;
                this.promptCopied = false;
                try {
                    const payload = this.payloadFor(tool);
                    const r = await fetch(this.urls[tool], {
                        method: 'POST',
                        headers: payload.headers,
                        body: payload.body,
                    });
                    const d = await r.json().catch(() => ({}));
                    this.updateQuota(d.quota);
                    if (r.ok && d.ok) {
                        this.result = d.answer;
                        if (d.history) this.addHistory(d.history);
                        if (d.warning) this.error = d.warning;
                        if (tool === 'learning' || tool === 'quiz') await this.refreshPreview();
                        await this.refreshQuota(true);
                    } else if (r.status === 422) {
                        if (d.needs_api_key) this.needsApiKeySetup = true;
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

            async submitExternal(tool) {
                if (this.loading) return;
                this.loading = true;
                this.result = '';
                this.error = '';
                this.copied = false;
                this.editing = false;
                this.externalFlow = false;
                this.promptCopied = false;
                try {
                    const payload = this.payloadForExternal(tool);
                    const r = await fetch(this.urls.externalPrompt, {
                        method: 'POST',
                        headers: payload.headers,
                        body: payload.body,
                    });
                    const d = await r.json().catch(() => ({}));
                    if (r.ok && d.ok) {
                        await this.launchExternalGemini(d);
                        this.externalTool = tool;
                    } else if (r.status === 422) {
                        this.error = d.message || 'Periksa isian form: ' + Object.values(d.errors || {}).flat().join(' ');
                    } else {
                        this.error = d.message || 'Gagal menyiapkan perintah untuk Gemini web.';
                    }
                } catch (_) {
                    this.error = 'Gagal terhubung. Periksa koneksi lalu coba lagi.';
                } finally {
                    this.loading = false;
                    this.$nextTick(() => window.lucide && lucide.createIcons());
                }
            },

            payloadForExternal(tool) {
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                if (tool === 'summary' || tool === 'feedback') {
                    return {
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ tool, ...this[tool] }),
                    };
                }

                const form = new FormData();
                form.append('tool', tool);
                if (tool === 'learning') {
                    form.append('learning_tool', this.learning.tool || 'rpp');
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
                    form.append('soal_bergambar', this.quiz.soal_bergambar ? '1' : '0');
                    if (this.quiz.source === 'file' && this.quiz.file) form.append('file', this.quiz.file);
                }

                return {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: form,
                };
            },

            openSendToArena() {
                if (!this.result || this.tab !== 'quiz' || !this.arenaClassrooms.length) return;
                if (this.arenaClassrooms.length === 1) {
                    this.arenaClassroomId = this.arenaClassrooms[0].uuid;
                    this.sendToArena();
                    return;
                }
                this.arenaClassroomId = this.arenaClassroomId || this.arenaClassrooms[0].uuid;
                this.showArenaModal = true;
                this.$nextTick(() => window.lucide && lucide.createIcons());
            },

            sendToArena() {
                if (!this.result || !this.arenaClassroomId || this.sendingArena) return;
                this.sendingArena = true;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = this.urls.quizSendArena;
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = document.querySelector('meta[name="csrf-token"]').content;
                form.appendChild(csrf);
                const fields = {
                    classroom_id: this.arenaClassroomId,
                    raw_text: this.result,
                    title: this.quiz.topik ? ('Kuis: ' + this.quiz.topik) : 'Kuis dari Asisten Guru',
                };
                Object.entries(fields).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
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
                this.error = '';
                this.copied = false;
                this.editing = false;
                this.previewHtml = '';
                this.geminiError = '';

                if (item.type === 'gemini_chat') {
                    this.tab = 'gemini';
                    const prompt = (item.metadata && item.metadata.prompt) || item.title || '';
                    const answer = item.answer || '';
                    this.geminiMessages = [];
                    if (prompt) this.geminiMessages.push({ role: 'user', text: prompt });
                    if (answer) this.geminiMessages.push({ role: 'assistant', text: answer });
                    this.$nextTick(() => {
                        window.lucide && lucide.createIcons();
                        if (this.$refs.geminiScroll) this.$refs.geminiScroll.scrollTop = this.$refs.geminiScroll.scrollHeight;
                    });
                    return;
                }

                this.tab = learningTypes.includes(item.type) ? 'learning' : item.type;
                if (learningTypes.includes(item.type)) this.learning.tool = item.type;
                this.result = item.answer || '';
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
                this.externalFlow = false;
                this.externalPaste = '';
                this.promptCopied = false;
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
