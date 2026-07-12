@extends('layouts.app')
@section('title', 'Kustomisasi Tampilan')

@push('styles')
<style>
    .theme-card { transition: all .2s cubic-bezier(.2,.8,.2,1); }
    .theme-card:hover { transform: translateY(-3px); }
    .theme-card.selected { box-shadow: 0 0 0 3px var(--cp), 0 10px 24px -8px color-mix(in srgb, var(--cp) 50%, transparent); }
    .swatch-ring { transition: all .15s; }
    .swatch-ring:hover { transform: scale(1.12); }
    .swatch-ring.on { box-shadow: 0 0 0 2.5px #fff, 0 0 0 5px var(--cp); }
    .seg { transition: all .18s; }
    .preview-shell { transition: background .25s, color .25s; }
    .save-bar { transition: all .3s cubic-bezier(.2,.8,.2,1); }
</style>
@endpush

@section('content')
<div x-data="prefStudio(@js($pref))" x-init="apply()" class="max-w-6xl mx-auto pb-24">

    {{-- Header --}}
    <div class="flex items-start justify-between flex-wrap gap-3 mb-6">
        <div>
            <h1 class="page-title flex items-center gap-2">
                <span class="grid place-items-center w-9 h-9 rounded-xl text-white" style="background:linear-gradient(135deg,var(--cp),var(--cps))"><i data-lucide="palette" class="w-5 h-5"></i></span>
                Studio Tampilan
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1.5">Atur warna, sidebar, dan tema. Perubahan tampil <span class="font-semibold text-slate-700 dark:text-slate-300">langsung</span> sebelum disimpan.</p>
        </div>
        <button @click="reset()" class="btn-ghost flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Reset Default
        </button>
    </div>

    <div class="grid lg:grid-cols-5 gap-6">

        {{-- ===== KIRI: Editor ===== --}}
        <div class="lg:col-span-3 space-y-5">

            {{-- Gaya Tampilan (Soft vs Analyzer) --}}
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="layout-template" class="w-[18px] h-[18px] text-primary"></i>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Gaya Tampilan</h2>
                </div>
                <p class="text-xs text-slate-400 mb-4">Pilih kerangka tampilan. Semua warna tema tetap berlaku di kedua gaya.</p>
                <div class="grid grid-cols-2 gap-3">
                    {{-- Soft --}}
                    <button type="button" @click="setStyleVal('soft')"
                            class="theme-card relative rounded-2xl p-3 border-2 text-left overflow-hidden transition"
                            :class="ui_style==='soft' ? 'border-primary' : 'border-slate-100 dark:border-slate-700'">
                        <div class="flex gap-1.5 h-16 rounded-xl overflow-hidden mb-2 border border-slate-100 dark:border-slate-700">
                            <div class="w-5" :style="'background:'+sidebar_bg"></div>
                            <div class="flex-1 p-1.5" style="background:color-mix(in srgb, var(--cp) 8%, white)">
                                <div class="rounded-lg h-full" :style="'background:'+primary_color+'22'"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200">Soft</p>
                            <i x-show="ui_style==='soft'" data-lucide="check-circle-2" class="w-4 h-4 text-primary"></i>
                        </div>
                        <p class="text-[11px] text-slate-400">Lembut, membulat, motif latar</p>
                    </button>
                    {{-- Corporate / Analyzer --}}
                    <button type="button" @click="setStyleVal('corporate')"
                            class="theme-card relative rounded-2xl p-3 border-2 text-left overflow-hidden transition"
                            :class="ui_style==='corporate' ? 'border-primary' : 'border-slate-100 dark:border-slate-700'">
                        <div class="flex gap-1.5 h-16 rounded-lg overflow-hidden mb-2 border border-slate-200" style="background:#f6f2ea">
                            <div class="w-5" :style="'background:color-mix(in srgb, '+primary_color+' 70%, #0a100d)'"></div>
                            <div class="flex-1 p-1.5 flex gap-1">
                                <div class="flex-1 bg-white rounded border border-slate-200"></div>
                                <div class="flex-1 bg-white rounded border border-slate-200"></div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200">Analyzer</p>
                            <i x-show="ui_style==='corporate'" data-lucide="check-circle-2" class="w-4 h-4 text-primary"></i>
                        </div>
                        <p class="text-[11px] text-slate-400">Profesional, sidebar gelap, tegas</p>
                    </button>
                </div>
            </div>


            {{-- Tema Dashboard --}}
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="monitor-cog" class="w-[18px] h-[18px] text-primary"></i>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Tema Dashboard</h2>
                </div>
                <p class="text-xs text-slate-400 mb-4">Pilih rasa tampilan khusus halaman dashboard.</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <button type="button" @click="setDashboardTheme('windows11')"
                            class="theme-card relative rounded-2xl p-3 border-2 text-left overflow-hidden transition"
                            :class="dashboard_theme==='windows11' ? 'border-primary' : 'border-slate-100 dark:border-slate-700'">
                        <div class="h-20 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-50 mb-3 p-2">
                            <div class="h-4 rounded-lg bg-white/90 border border-blue-100 mb-2 flex items-center justify-end px-1.5">
                                <span class="w-3 h-3 rounded bg-blue-500"></span>
                            </div>
                            <div class="grid grid-cols-3 gap-1.5">
                                <span class="h-10 rounded-lg bg-white border border-blue-100 shadow-sm"></span>
                                <span class="h-10 rounded-lg bg-sky-50 border border-sky-100 shadow-sm"></span>
                                <span class="h-10 rounded-lg bg-white border border-blue-100 shadow-sm"></span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200">Windows 11</p>
                            <i x-show="dashboard_theme==='windows11'" data-lucide="check-circle-2" class="w-4 h-4 text-primary"></i>
                        </div>
                        <p class="text-[11px] text-slate-400">Fluent, rapi, radius sedang</p>
                    </button>
                    <button type="button" @click="setDashboardTheme('macos')"
                            class="theme-card relative rounded-2xl p-3 border-2 text-left overflow-hidden transition"
                            :class="dashboard_theme==='macos' ? 'border-primary' : 'border-slate-100 dark:border-slate-700'">
                        <div class="h-20 rounded-[22px] overflow-hidden border border-slate-200 dark:border-slate-700 bg-gradient-to-br from-sky-50 via-white to-pink-50 mb-3 p-2">
                            <div class="flex gap-1 mb-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-400"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-400"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <span class="h-11 rounded-2xl bg-white/70 border border-white shadow-sm backdrop-blur"></span>
                                <span class="h-11 rounded-2xl bg-white/55 border border-white shadow-sm backdrop-blur"></span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[13px] font-bold text-slate-700 dark:text-slate-200">Mac OS</p>
                            <i x-show="dashboard_theme==='macos'" data-lucide="check-circle-2" class="w-4 h-4 text-primary"></i>
                        </div>
                        <p class="text-[11px] text-slate-400">Glass, halus, radius besar</p>
                    </button>
                </div>
            </div>
            {{-- Tema lengkap (motif + warna) --}}
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-1">
                    <i data-lucide="sparkles" class="w-[18px] h-[18px] text-primary"></i>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Tema</h2>
                </div>
                <p class="text-xs text-slate-400 mb-4">Pilih tema lengkap — motif latar, warna, sidebar, & kartu ikut berubah.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <template x-for="(t, i) in presets" :key="i">
                        <button type="button" @click="applyPreset(t)"
                                class="theme-card relative rounded-2xl p-3 border border-slate-100 dark:border-slate-700 text-left overflow-hidden"
                                :class="isPreset(t) && 'selected'"
                                :style="'background:'+t.sbg">
                            <div class="flex items-center justify-between mb-3">
                                <span class="grid place-items-center w-8 h-8 rounded-xl text-white" :style="'background:linear-gradient(135deg,'+t.cp+','+t.cps+')'">
                                    <i :data-lucide="t.icon" class="w-4 h-4"></i>
                                </span>
                                <i x-show="isPreset(t)" data-lucide="check-circle-2" class="w-4 h-4" :style="'color:'+t.cp"></i>
                            </div>
                            <div class="flex -space-x-1 mb-2">
                                <span class="w-4 h-4 rounded-full border-2" :style="'background:'+t.cp+';border-color:'+t.sbg"></span>
                                <span class="w-4 h-4 rounded-full border-2" :style="'background:'+t.cps+';border-color:'+t.sbg"></span>
                                <span class="w-4 h-4 rounded-full border-2" :style="'background:'+t.ca+';border-color:'+t.sbg"></span>
                            </div>
                            <p class="text-[12px] font-bold truncate" :style="'color:'+t.stx" x-text="t.name"></p>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Warna kustom --}}
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="droplet" class="w-[18px] h-[18px] text-primary"></i>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Warna Kustom</h2>
                </div>
                <div class="space-y-4">
                    @foreach([
                        ['primary_color','Warna Utama','Tombol, link, highlight'],
                        ['secondary_color','Warna Kedua','Gradien & aksen sekunder'],
                        ['accent_color','Warna Aksen','Badge & elemen kecil'],
                    ] as [$key, $label, $hint])
                    <div class="flex items-center gap-3">
                        <label class="relative w-12 h-12 rounded-xl overflow-hidden border-2 border-slate-200 dark:border-slate-600 cursor-pointer flex-shrink-0 shadow-sm">
                            <input type="color" x-model="{{ $key }}" @input="apply()" class="absolute -inset-2 w-[calc(100%+16px)] h-[calc(100%+16px)] cursor-pointer">
                        </label>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $label }}</p>
                            <p class="text-xs text-slate-400">{{ $hint }}</p>
                        </div>
                        <input type="text" x-model="{{ $key }}" @input="apply()" maxlength="7"
                               class="form-input !w-28 font-mono text-xs text-center uppercase">
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="panel-left" class="w-[18px] h-[18px] text-primary"></i>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Sidebar</h2>
                </div>
                <div class="grid sm:grid-cols-2 gap-5">
                    <div>
                        <p class="form-label">Warna Background</p>
                        <div class="flex items-center gap-2 mb-2.5">
                            <label class="relative w-9 h-9 rounded-lg overflow-hidden border border-slate-200 cursor-pointer flex-shrink-0">
                                <input type="color" x-model="sidebar_bg" @input="apply()" class="absolute -inset-2 w-[calc(100%+16px)] h-[calc(100%+16px)] cursor-pointer">
                            </label>
                            <input type="text" x-model="sidebar_bg" @input="apply()" maxlength="7" class="form-input font-mono text-xs uppercase">
                        </div>
                        <div class="flex gap-1.5 flex-wrap">
                            @foreach(['#0f172a','#111827','#1e1b4b','#0c4a6e','#14532d','#7f1d1d','#ffffff','#f8fafc'] as $c)
                            <button type="button" @click="sidebar_bg='{{ $c }}'; apply()" :class="sidebar_bg.toLowerCase()==='{{ $c }}' && 'on'"
                                    class="swatch-ring w-6 h-6 rounded-full border border-slate-200 dark:border-slate-600" style="background:{{ $c }}"></button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="form-label">Warna Teks</p>
                        <div class="flex items-center gap-2 mb-2.5">
                            <label class="relative w-9 h-9 rounded-lg overflow-hidden border border-slate-200 cursor-pointer flex-shrink-0">
                                <input type="color" x-model="sidebar_text" @input="apply()" class="absolute -inset-2 w-[calc(100%+16px)] h-[calc(100%+16px)] cursor-pointer">
                            </label>
                            <input type="text" x-model="sidebar_text" @input="apply()" maxlength="7" class="form-input font-mono text-xs uppercase">
                        </div>
                        <div class="flex gap-1.5 flex-wrap">
                            @foreach(['#e2e8f0','#f1f5f9','#ffffff','#cbd5e1','#fde68a','#1e293b','#0f172a'] as $c)
                            <button type="button" @click="sidebar_text='{{ $c }}'; apply()" :class="sidebar_text.toLowerCase()==='{{ $c }}' && 'on'"
                                    class="swatch-ring w-6 h-6 rounded-full border border-slate-200 dark:border-slate-600" style="background:{{ $c }}"></button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mode & Ukuran --}}
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="sliders-horizontal" class="w-[18px] h-[18px] text-primary"></i>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Mode &amp; Ukuran</h2>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="form-label">Mode Warna</p>
                        <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
                            @foreach(['light'=>['Terang','sun'],'dark'=>['Gelap','moon']] as $val => [$lbl,$ic])
                            <button type="button" @click="setMode('{{ $val }}')"
                                    class="seg flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-semibold"
                                    :class="theme_mode==='{{ $val }}' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'">
                                <i data-lucide="{{ $ic }}" class="w-4 h-4"></i> {{ $lbl }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <p class="form-label">Ukuran Teks</p>
                        <div class="grid grid-cols-3 gap-2 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
                            @foreach(['sm'=>'Kecil','md'=>'Normal','lg'=>'Besar'] as $val => $lbl)
                            <button type="button" @click="font_size='{{ $val }}'"
                                    class="seg py-2.5 rounded-lg text-sm font-semibold"
                                    :class="font_size==='{{ $val }}' ? 'bg-white dark:bg-slate-700 text-primary shadow-sm' : 'text-slate-500'">{{ $lbl }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== KANAN: Preview ===== --}}
        <div class="lg:col-span-2">
            <div class="sticky top-2 space-y-3">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4"></i> Pratinjau Langsung</p>
                <div class="card overflow-hidden shadow-soft">
                    {{-- mini app shell --}}
                    <div class="flex h-[380px] preview-shell">
                        {{-- mini sidebar --}}
                        <div class="w-[90px] flex flex-col p-2.5 gap-1.5 flex-shrink-0" :style="'background:'+sidebar_bg+';color:'+sidebar_text">
                            <div class="flex items-center gap-1.5 mb-2 px-1">
                                <div class="w-6 h-6 rounded-lg grid place-items-center flex-shrink-0" :style="'background:linear-gradient(135deg,'+primary_color+','+secondary_color+')'">
                                    <span class="text-white text-[10px] font-black">E</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg" :style="'background:color-mix(in srgb,'+primary_color+' 22%,transparent)'">
                                <span class="w-2 h-2 rounded-full" :style="'background:'+primary_color"></span>
                                <span class="h-1.5 rounded-full flex-1" :style="'background:'+sidebar_text"></span>
                            </div>
                            <template x-for="n in 4">
                                <div class="flex items-center gap-1.5 px-2 py-1.5">
                                    <span class="w-2 h-2 rounded-full opacity-40" :style="'background:'+sidebar_text"></span>
                                    <span class="h-1.5 rounded-full flex-1 opacity-30" :style="'background:'+sidebar_text"></span>
                                </div>
                            </template>
                        </div>
                        {{-- mini main --}}
                        <div class="flex-1 flex flex-col" :class="theme_mode==='dark' ? 'bg-slate-900' : 'bg-slate-50'">
                            <div class="h-9 flex items-center justify-between px-3 border-b" :class="theme_mode==='dark' ? 'bg-slate-800 border-slate-700' : 'bg-white border-slate-100'">
                                <div class="h-1.5 w-14 rounded-full" :class="theme_mode==='dark' ? 'bg-slate-600' : 'bg-slate-200'"></div>
                                <div class="w-5 h-5 rounded-md" :style="'background:linear-gradient(135deg,'+primary_color+','+secondary_color+')'"></div>
                            </div>
                            <div class="p-3 space-y-2.5 flex-1">
                                <div class="rounded-xl p-2.5" :style="'background:linear-gradient(120deg,'+primary_color+','+secondary_color+' 70%,'+accent_color+')'">
                                    <div class="h-1.5 w-16 rounded-full bg-white/70 mb-1.5"></div>
                                    <div class="h-1 w-24 rounded-full bg-white/40"></div>
                                </div>
                                <div class="grid grid-cols-3 gap-1.5">
                                    <template x-for="c in [primary_color, secondary_color, accent_color]">
                                        <div class="rounded-lg p-1.5" :class="theme_mode==='dark' ? 'bg-slate-800' : 'bg-white'">
                                            <div class="w-4 h-4 rounded-md mb-1" :style="'background:color-mix(in srgb,'+c+' 18%,transparent)'"><div class="w-full h-full rounded-md scale-50 origin-top-left" :style="'background:'+c"></div></div>
                                            <div class="h-1 rounded-full" :style="'background:'+c"></div>
                                        </div>
                                    </template>
                                </div>
                                <div class="rounded-xl p-2 space-y-1.5" :class="theme_mode==='dark' ? 'bg-slate-800' : 'bg-white'">
                                    <template x-for="r in 3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-3 h-3 rounded-full" :style="'background:color-mix(in srgb,'+accent_color+' 30%,transparent)'"></span>
                                            <span class="h-1 rounded-full flex-1" :class="theme_mode==='dark' ? 'bg-slate-600' : 'bg-slate-200'"></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex gap-1.5">
                                    <div class="flex-1 h-6 rounded-lg grid place-items-center" :style="'background:'+primary_color"><div class="h-1 w-8 rounded-full bg-white/80"></div></div>
                                    <div class="h-6 w-12 rounded-lg border" :class="theme_mode==='dark' ? 'border-slate-600' : 'border-slate-200'"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-400 text-center">Seluruh aplikasi ikut berubah saat kamu menggeser warna 👆</p>
            </div>
        </div>
    </div>

    {{-- Floating Save Bar --}}
    <div x-show="dirty" x-transition.opacity
         class="save-bar fixed bottom-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 card !rounded-2xl px-4 py-3 shadow-2xl border border-slate-200 dark:border-slate-700">
        <span class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-300">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> Ada perubahan belum disimpan
        </span>
        <button @click="revert()" class="px-3 py-2 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
        <button @click="save()" :disabled="saving" class="btn-accent flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-bold">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="saving"></i>
            <i data-lucide="check" class="w-4 h-4" x-show="!saving"></i>
            <span x-text="saving ? 'Menyimpan...' : 'Simpan Tema'"></span >
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
function prefStudio(data) {
    const init = {
        primary_color:   data.primary_color   || '#7ba088',
        secondary_color: data.secondary_color || '#9db89f',
        accent_color:    data.accent_color    || '#e5996c',
        sidebar_bg:      data.sidebar_bg      || '#fceadb',
        sidebar_text:    data.sidebar_text    || '#57534e',
        sidebar_style:   data.sidebar_style   || 'default',
        theme_mode:      data.theme_mode      || 'light',
        motif:           data.motif           || 'botanical',
        ui_style:        data.ui_style        || 'soft',
        dashboard_theme: data.dashboard_theme || 'windows11',
        font_size:       data.font_size       || 'md',
        compact_mode:    !!data.compact_mode,
    };
    return {
        ...init,
        _saved: JSON.stringify(init),
        saving: false,
        presets: [
            { name:'Botanical', icon:'flower-2',    motif:'botanical', cp:'#7ba088', cps:'#9db89f', ca:'#e5996c', sbg:'#fceadb', stx:'#57534e' },
            { name:'Awan Biru', icon:'school',    motif:'minimal',   cp:'#2563eb', cps:'#3b82f6', ca:'#f59e0b', sbg:'#ffffff', stx:'#475569' },
            { name:'Lautan',    icon:'waves',        motif:'ocean',     cp:'#2f8fb3', cps:'#56b4cf', ca:'#f2a65a', sbg:'#e6f4f8', stx:'#2c4a54' },
            { name:'Hutan',     icon:'trees',        motif:'forest',    cp:'#4a7c59', cps:'#6b9b6f', ca:'#c99a4b', sbg:'#e8f0e3', stx:'#3a4a36' },
            { name:'Senja',     icon:'sunset',       motif:'sunset',    cp:'#e07a5f', cps:'#f2a25c', ca:'#c75d8f', sbg:'#fdeee2', stx:'#6b4a3f' },
            { name:'Robot',     icon:'bot',          motif:'robot',     cp:'#3b6fe0', cps:'#5b8def', ca:'#22d3ee', sbg:'#161d2e', stx:'#c7d2e0' },
            { name:'Galaksi',   icon:'rocket',       motif:'space',     cp:'#7c6cf0', cps:'#a78bfa', ca:'#f0a3c8', sbg:'#1a1830', stx:'#d8d4f0' },
            { name:'Warna-warni', icon:'sparkles',    motif:'rainbow',   cp:'#4285f4', cps:'#34a853', ca:'#fbbc05', sbg:'#ffffff', stx:'#475569' },
            { name:'Minimalis', icon:'minus',        motif:'minimal',   cp:'#5b7a99', cps:'#7d97b0', ca:'#e2a04a', sbg:'#eef1f4', stx:'#3a4654' },
            { name:'Samudera Malam', icon:'anchor', motif:'nightocean', cp:'#132039', cps:'#203254', ca:'#e8a04e', sbg:'#132039', stx:'#cbd5e1' },
            { name:'Zamrud Pro', icon:'gem',          motif:'minimal',   cp:'#0f5132', cps:'#198754', ca:'#0dcaf0', sbg:'#08331e', stx:'#d1e7dd' },
            { name:'Kopi Karamel', icon:'coffee',      motif:'minimal',   cp:'#3d2314', cps:'#5a3a22', ca:'#e67e22', sbg:'#2c180c', stx:'#e8d8ce' },
            { name:'Arang Pro', icon:'briefcase',    motif:'minimal',   cp:'#212529', cps:'#343a40', ca:'#0d6efd', sbg:'#15191d', stx:'#e2e8f0' },
        ],

        get dirty() { return JSON.stringify(this._state()) !== this._saved; },
        _state() {
            return { primary_color:this.primary_color, secondary_color:this.secondary_color, accent_color:this.accent_color,
                     sidebar_bg:this.sidebar_bg, sidebar_text:this.sidebar_text, sidebar_style:this.sidebar_style,
                     theme_mode:this.theme_mode, motif:this.motif, ui_style:this.ui_style, dashboard_theme:this.dashboard_theme, font_size:this.font_size, compact_mode:this.compact_mode };
        },
        setStyleVal(s) { this.ui_style = s; if (window.setStyle) window.setStyle(s); },
        setDashboardTheme(t) { this.dashboard_theme = t; if (window.setDashboardTheme) window.setDashboardTheme(t); },

        apply() {
            const r = document.documentElement;
            r.style.setProperty('--cp', this.primary_color);
            r.style.setProperty('--cps', this.secondary_color);
            r.style.setProperty('--ca', this.accent_color);
            r.style.setProperty('--sbg', this.sidebar_bg);
            r.style.setProperty('--stx', this.sidebar_text);
            const fm = { sm:['11px','13px','15px'], md:['12px','14px','16px'], lg:['13px','15px','17px'] }[this.font_size];
            r.style.setProperty('--fsm', fm[0]); r.style.setProperty('--fmd', fm[1]); r.style.setProperty('--flg', fm[2]);
        },
        setMode(m) {
            this.theme_mode = m;
            document.documentElement.classList.toggle('dark', m === 'dark');
            localStorage.setItem('theme_mode', m);
        },
        applyPreset(t) {
            this.primary_color=t.cp; this.secondary_color=t.cps; this.accent_color=t.ca;
            this.sidebar_bg=t.sbg; this.sidebar_text=t.stx; this.motif=t.motif;
            this.apply();
            if (window.setMotif) window.setMotif(t.motif);
            this.$nextTick(()=>lucide.createIcons());
        },
        isPreset(t) {
            return this.motif===t.motif
                && this.primary_color.toLowerCase()===t.cp.toLowerCase()
                && this.sidebar_bg.toLowerCase()===t.sbg.toLowerCase();
        },

        revert() {
            Object.assign(this, JSON.parse(this._saved));
            this.apply();
            this.setMode(this.theme_mode);
            if (window.setMotif) window.setMotif(this.motif);
            if (window.setStyle) window.setStyle(this.ui_style);
            if (window.setDashboardTheme) window.setDashboardTheme(this.dashboard_theme);
            this.$nextTick(()=>lucide.createIcons());
        },

        async save() {
            this.saving = true;
            try {
                const res = await fetch('{{ route('profile.preference.update') }}', {
                    method:'POST',
                    headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':$('meta[name=csrf-token]').attr('content'), 'X-HTTP-Method-Override':'PUT', Accept:'application/json' },
                    body: JSON.stringify({ _method:'PUT', ...this._state() })
                });
                const d = await res.json();
                if (res.ok) { this._saved = JSON.stringify(this._state()); showToast(d.message || 'Tema disimpan!'); }
                else { showToast(Object.values(d.errors||{})[0]?.[0] || 'Gagal menyimpan', 'error'); }
            } catch { showToast('Gagal menghubungi server', 'error'); }
            this.saving = false;
        },

        reset() {
            $.confirm({ title:'Reset ke default?', content:'Semua kustomisasi warna akan dikembalikan ke bawaan.', type:'orange',
                buttons:{ ya:{ text:'Ya, Reset', btnClass:'btn-blue', action:()=> window.location='{{ route('profile.preference.reset') }}' }, batal:{text:'Batal'} } });
        },

        init() { this.apply(); this.$nextTick(()=>lucide.createIcons()); }
    }
}
</script>
@endpush
