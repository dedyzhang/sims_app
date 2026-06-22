@extends('layouts.app')
@section('title', $assignment->title)

@php $me = auth()->user(); @endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-4">
    <nav class="text-xs text-slate-400 flex items-center gap-1 flex-wrap">
        <a href="{{ route('classroom.show', $classroom) }}" class="hover:text-primary">{{ $classroom->title }}</a>
        <span>/</span><span>Latihan &amp; Tugas</span>
    </nav>

    @unless($kioskMode)
    <a href="{{ route('classroom.show', $classroom) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali ke kelas</a>
    @endunless

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm"><ul class="list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($gateLocked)
    {{-- Siswa: gerbang token --}}
    <x-lock-gate :title="$assignment->title" :unlock-url="route('classroom.assignment.unlock', [$assignment, 'class' => $classroom->uuid])" />

    @elseif($kioskMode)
    {{-- Siswa: mode layar penuh terkunci (instruksi + pengumpulan) --}}
    <x-lock-kiosk :exit-url="route('classroom.assignment.lockexit', $assignment)" :back-url="route('classroom.show', $classroom)">
        @include('classroom.partials.assignment_detail', ['assignment' => $assignment, 'canManage' => false])
        <div class="mt-4">@include('classroom.partials.assignment_submission', ['assignment' => $assignment, 'mySubmission' => $mySubmission])</div>
    </x-lock-kiosk>

    @else
    @php $teacherLock = $assignment->is_locked && $canManage; @endphp
    <div @if($teacherLock) x-data="{ ctab:'tugas' }" @endif>

        @if($teacherLock)
        <div class="card p-4 border border-amber-300 dark:border-amber-700 flex items-center gap-2 text-sm flex-wrap">
            <i data-lucide="lock" class="w-4 h-4 text-amber-500"></i>
            <span class="font-semibold text-slate-700 dark:text-slate-200">Tugas Terkunci</span>
            <span class="text-slate-400">· Token siswa:</span>
            <span class="font-mono font-bold text-lg tracking-widest px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">{{ $assignment->access_token }}</span>
            <form method="POST" action="{{ route('classroom.assignment.togglelock', $assignment) }}" class="ml-auto" onsubmit="return confirmAction(this, 'Buka kunci tugas ini? Siswa bisa mengakses tanpa token.', 'orange')">@csrf
                <button class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-700 dark:hover:bg-rose-900/30"><i data-lucide="unlock" class="w-3.5 h-3.5"></i> Buka Kunci</button>
            </form>
        </div>
        <div class="flex gap-1 border-b border-slate-200 dark:border-slate-700 mt-4">
            <button @click="ctab='tugas'" :style="ctab==='tugas' ? 'color:var(--cp);border-color:var(--cp)' : ''" :class="ctab==='tugas' ? '' : 'text-slate-500'" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px" style="border-color:transparent"><i data-lucide="clipboard-list" class="w-4 h-4 inline"></i> Tugas</button>
            <button @click="ctab='pemantauan'" :style="ctab==='pemantauan' ? 'color:var(--cp);border-color:var(--cp)' : ''" :class="ctab==='pemantauan' ? '' : 'text-slate-500'" class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px flex items-center gap-1.5" style="border-color:transparent"><i data-lucide="monitor-dot" class="w-4 h-4"></i> Pemantauan Siswa <span id="mon-tab-out" class="hidden text-[10px] bg-rose-500 text-white rounded-full px-1.5"></span></button>
        </div>
        @endif

        {{-- TAB TUGAS --}}
        <div @if($teacherLock) x-show="ctab==='tugas'" @endif class="space-y-4 @if($teacherLock) mt-4 @endif">

            {{-- Toggle kunci di halaman (guru, saat belum terkunci) --}}
            @if($canManage && !$assignment->is_locked)
            <div class="card p-3 flex items-center justify-between gap-3 flex-wrap">
                <span class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-2"><i data-lucide="unlock" class="w-4 h-4"></i> Tugas tidak terkunci</span>
                <form method="POST" action="{{ route('classroom.assignment.togglelock', $assignment) }}" class="flex items-center gap-2">@csrf
                    <input type="text" name="access_token" maxlength="16" value="{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(4)) }}" class="form-input font-mono uppercase tracking-widest w-24 text-center text-sm" title="Token untuk siswa">
                    <button class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold text-white" style="background:#f59e0b"><i data-lucide="lock" class="w-4 h-4"></i> Kunci Tugas</button>
                </form>
            </div>
            @endif

            {{-- Detail tugas --}}
            <div class="card p-5">@include('classroom.partials.assignment_detail', ['assignment' => $assignment, 'canManage' => $canManage])</div>

            {{-- Penilaian & Kehadiran (Guru/Admin Only) --}}
            @if($canManage)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="award" class="w-5 h-5 text-emerald-500"></i><span>Nilai Sudah Dikoreksi</span></h3>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-300 font-semibold">{{ $gradedSubmissions->count() }} Siswa</span>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 pr-1">
                        @forelse($gradedSubmissions as $s)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $s->student?->displayName() }}</p><p class="text-[10px] text-slate-400">Dikoreksi {{ $s->graded_at?->locale('id')->diffForHumans() }}</p></div>
                            <div class="text-right"><span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $s->score }}</span><span class="text-[10px] text-slate-400">/ {{ $assignment->max_score }}</span></div>
                        </div>
                        @empty<div class="py-6 text-center text-slate-400 text-xs italic">Belum ada nilai yang dikoreksi.</div>@endforelse
                    </div>
                </div>
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="user-x" class="w-5 h-5 text-rose-500"></i><span>Belum Mengerjakan</span></h3>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-300 font-semibold">{{ $unsubmittedStudents->count() }} Siswa</span>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-700 pr-1">
                        @forelse($unsubmittedStudents as $student)
                        <div class="py-3 flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-[10px] font-bold text-slate-500">{{ strtoupper(substr($student->nama, 0, 1)) }}</div>
                            <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $student->nama }}</p><p class="text-[10px] text-slate-400">NIS: {{ $student->nis ?? '-' }}</p></div>
                        </div>
                        @empty<div class="py-6 text-center text-emerald-600 text-xs italic font-medium">✨ Semua siswa sudah mengumpulkan/dikoreksi!</div>@endforelse
                    </div>
                </div>
            </div>

            @if($gradedSubmissions->isNotEmpty())
            <div class="card p-5">
                <div class="flex items-center gap-2 mb-2"><i data-lucide="send" class="w-5 h-5 text-primary" style="color:var(--cp)"></i><h3 class="font-bold text-slate-800 dark:text-slate-100">Transfer Nilai ke Buku Nilai</h3></div>
                <p class="text-xs text-slate-400 mb-4">Pindahkan nilai tugas yang sudah dikoreksi di ruang kelas ini ke Buku Nilai Akademik (Formatif/Sumatif).</p>
                @if($materiList->isEmpty())
                <div class="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 p-4 text-xs space-y-2">
                    <p class="font-bold text-sm">Materi / Tujuan Pembelajaran (TP) belum dibuat!</p>
                    <p>Anda belum membuat data Materi atau Tujuan Pembelajaran (TP) pada Buku Nilai untuk mata pelajaran dan kelas ini.</p>
                    @if($ngajarUuid)<div class="pt-1"><a href="{{ route('nilai.materi', $ngajarUuid) }}" class="inline-flex items-center gap-1 font-bold text-primary hover:underline"><span>Buat Materi & TP Sekarang</span><i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a></div>@endif
                </div>
                @else
                <form method="POST" action="{{ route('classroom.assignment.transfer', $assignment) }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-data="{ type: 'formatif' }">
                        <div>
                            <label class="form-label text-xs font-semibold">Pilih Jenis Nilai</label>
                            <select name="type" x-model="type" class="form-select text-sm rounded-xl"><option value="formatif">Nilai Formatif (per Tujuan Pembelajaran)</option><option value="sumatif">Nilai Sumatif (per Materi)</option></select>
                        </div>
                        <div x-show="type === 'formatif'">
                            <label class="form-label text-xs font-semibold">Pilih Tujuan Pembelajaran (TP)</label>
                            <select name="id_tupe" class="form-select text-sm rounded-xl">@foreach($materiList as $m)<optgroup label="Materi: {{ $m->nama }}">@foreach($m->tujuan as $tp)<option value="{{ $tp->uuid }}">TP {{ $tp->urutan }} - {{ \Illuminate\Support\Str::limit($tp->tupe, 50) }}</option>@endforeach</optgroup>@endforeach</select>
                        </div>
                        <div x-show="type === 'sumatif'" style="display:none">
                            <label class="form-label text-xs font-semibold">Pilih Materi</label>
                            <select name="id_materi" class="form-select text-sm rounded-xl">@foreach($materiList as $m)<option value="{{ $m->uuid }}">{{ $m->nama }}</option>@endforeach</select>
                        </div>
                    </div>
                    <div class="flex justify-end pt-2"><button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90 shadow btn-primary" style="background:var(--cp)">Transfer Sekarang</button></div>
                </form>
                @endif
            </div>
            @endif
            @endif

            {{-- Pengumpulan siswa --}}
            @if($me->access==='siswa' && $assignment->status==='published')
            @include('classroom.partials.assignment_submission', ['assignment' => $assignment, 'mySubmission' => $mySubmission])
            @endif

            {{-- Diskusi --}}
            @include('classroom.partials.comments', ['comments' => $comments, 'storeUrl' => route('classroom.assignment.comment', $assignment), 'canManage' => $canManage, 'commentable' => $assignment, 'type' => 'assignment'])
        </div>

        {{-- TAB PEMANTAUAN --}}
        @if($teacherLock)
        <div x-show="ctab==='pemantauan'" x-cloak class="mt-4">
            <x-lock-monitor :events-url="route('classroom.assignment.lockevents', [$assignment, 'class' => $classroom->uuid])" />
        </div>
        @endif
    </div>
    @endif
</div>
@endsection
