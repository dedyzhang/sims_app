@extends('layouts.app')
@section('title', $material->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <nav class="text-xs text-slate-400 flex items-center gap-1 flex-wrap">
        <a href="{{ route('classroom.show', $classroom) }}" class="hover:text-primary">{{ $classroom->title }}</a>
        <span>/</span><span>Materi</span>
    </nav>

    @unless($kioskMode)
    <a href="{{ route('classroom.show', $classroom) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke kelas</a>
    @endunless

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    @if($gateLocked)
    <x-lock-gate :title="$material->title" :unlock-url="route('classroom.material.unlock', [$material, 'class' => $classroom->uuid])" />
    @else
    @php $teacherLock = $material->is_locked && $canManage; @endphp
    <div @if($teacherLock) x-data="{ ctab:'materi' }" @endif>

        @if($teacherLock)
        {{-- Banner kunci + tab navigasi --}}
        <div class="card p-4 border border-amber-300 dark:border-amber-700 flex items-center gap-2 text-sm flex-wrap">
            <i data-lucide="lock" class="w-4 h-4 text-amber-500"></i>
            <span class="font-semibold text-slate-700 dark:text-slate-200">Materi Terkunci</span>
            <span class="text-slate-400">· Token siswa:</span>
            <span class="font-mono font-bold text-lg tracking-widest px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{{ $material->access_token }}</span>
            <form method="POST" action="{{ route('classroom.material.togglelock', $material) }}" class="ml-auto" onsubmit="return confirmAction(this, 'Buka kunci materi ini? Siswa bisa mengakses tanpa token.', 'orange')">@csrf
                <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-700 dark:hover:bg-rose-900/30"><i data-lucide="unlock" class="w-3.5 h-3.5"></i> Buka Kunci</button>
            </form>
        </div>
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-700 mt-4">
            <button @click="ctab='materi'" :style="ctab==='materi' ? 'color:var(--cp);border-color:var(--cp)' : ''" :class="ctab==='materi' ? '' : 'text-slate-500'" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px" style="border-color:transparent"><i data-lucide="book-open" class="w-4 h-4 inline"></i> Materi</button>
            <button @click="ctab='pemantauan'" :style="ctab==='pemantauan' ? 'color:var(--cp);border-color:var(--cp)' : ''" :class="ctab==='pemantauan' ? '' : 'text-slate-500'" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px flex items-center gap-1.5" style="border-color:transparent"><i data-lucide="monitor-dot" class="w-4 h-4"></i> Pemantauan Siswa <span id="mon-tab-out" class="hidden text-[10px] bg-rose-500 text-white rounded-full px-1.5"></span></button>
        </div>
        @endif

        {{-- TAB MATERI --}}
        <div @if($teacherLock) x-show="ctab==='materi'" @endif class="space-y-4 @if($teacherLock) mt-4 @endif">

            {{-- Toggle kunci di halaman (guru, saat belum terkunci) --}}
            @if($canManage && !$material->is_locked)
            <div class="card p-3 flex items-center justify-between gap-3 flex-wrap">
                <span class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-2"><i data-lucide="unlock" class="w-4 h-4"></i> Materi tidak terkunci</span>
                <form method="POST" action="{{ route('classroom.material.togglelock', $material) }}" class="flex items-center gap-2">@csrf
                    <input type="text" name="access_token" maxlength="16" value="{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)) }}" class="form-input font-mono uppercase tracking-widest w-24 text-center text-sm" title="Token untuk siswa">
                    <button class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold text-white" style="background:#f59e0b"><i data-lucide="lock" class="w-4 h-4"></i> Kunci Materi</button>
                </form>
            </div>
            @endif

            @if($kioskMode)
            <x-lock-kiosk :exit-url="route('classroom.material.lockexit', $material)" :back-url="route('classroom.show', $classroom)">
                @include('classroom.partials.material_content', ['material' => $material, 'canManage' => false])
            </x-lock-kiosk>
            @else
            <div class="card p-5">
                @include('classroom.partials.material_content', ['material' => $material, 'canManage' => $canManage])
            </div>
            @unless($kioskMode)
            @include('classroom.partials.comments', ['comments' => $comments, 'storeUrl' => route('classroom.material.comment', $material), 'canManage' => $canManage, 'commentable' => $material, 'type' => 'material'])
            @endunless
            @endif
        </div>

        {{-- TAB PEMANTAUAN --}}
        @if($teacherLock)
        <div x-show="ctab==='pemantauan'" x-cloak class="mt-4">
            <x-lock-monitor :events-url="route('classroom.material.lockevents', [$material, 'class' => $classroom->uuid])" />
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
