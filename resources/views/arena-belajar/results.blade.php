@extends('layouts.app')
@section('title', 'Hasil — '.$quiz->title)

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&display=swap" rel="stylesheet">
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="arena-stage arena-rx arena-rx-detail space-y-5 max-w-4xl mx-auto">
    <div class="arena-rx-detail-hero p-5 sm:p-7 relative">
        <div class="arena-rx-detail-hero-grid" aria-hidden="true"></div>
        <div class="relative z-[1] flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
            <div>
                <a href="{{ route('classroom.arena.show', [$classroom, $quiz]) }}" class="arena-hud-back !bg-white/15 !border-white/20 !text-white !shadow-none mb-3 inline-flex">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i> Experience
                </a>
                <p class="arena-lobby-kicker m-0 text-amber-200">Host monitor</p>
                <h1 class="m-0 text-2xl sm:text-3xl font-black tracking-tight" style="font-family:'Fredoka',sans-serif">{{ $quiz->title }}</h1>
                <p class="m-0 mt-1 text-sm font-semibold text-slate-300">
                    {{ $doneCount }}/{{ $memberCount }} siswa selesai
                    ({{ $memberCount > 0 ? round(($doneCount / $memberCount) * 100) : 0 }}%)
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="arena-rx-flag arena-rx-flag-ok">{{ $doneCount }} selesai</span>
                <span class="arena-rx-flag">{{ $quiz->questions->count() }} soal</span>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-900/40 border-2 border-emerald-300 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm font-bold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-2xl bg-rose-50 dark:bg-rose-900/40 border-2 border-rose-300 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm font-bold">{{ session('error') }}</div>
    @endif

    <div class="arena-rx-detail-panel !p-0 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b-2 border-slate-100 dark:border-slate-700">
                    <th class="p-3 font-black uppercase text-[11px] tracking-wide">Siswa</th>
                    <th class="p-3 font-black uppercase text-[11px] tracking-wide">Status</th>
                    <th class="p-3 font-black uppercase text-[11px] tracking-wide">Skor</th>
                    <th class="p-3 font-black uppercase text-[11px] tracking-wide">Benar</th>
                    <th class="p-3 font-black uppercase text-[11px] tracking-wide">Keluar fokus</th>
                    <th class="p-3 font-black uppercase text-[11px] tracking-wide">Dikumpulkan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attempts as $a)
                @php $exitCount = (int) (($focusExitCounts[$a->student_id] ?? 0)); @endphp
                <tr class="border-b border-slate-50 dark:border-slate-800">
                    <td class="p-3 font-bold text-slate-800 dark:text-slate-100">{{ $a->student?->displayName() ?? '—' }}</td>
                    <td class="p-3 capitalize text-slate-500 font-semibold">{{ $a->status === 'graded' ? 'dinilai' : ($a->status === 'submitted' ? 'dikumpulkan' : 'berjalan') }}</td>
                    <td class="p-3 font-black text-teal-600">{{ $a->isSubmitted() ? $a->total_score : '—' }}</td>
                    <td class="p-3 text-slate-500 font-semibold">{{ $a->isSubmitted() ? $a->correct_count.'/'.$quiz->questions->count() : '—' }}</td>
                    <td class="p-3 font-black {{ $exitCount > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                        {{ $exitCount > 0 ? $exitCount.'×' : '—' }}
                    </td>
                    <td class="p-3 text-slate-400 font-semibold">{{ $a->submitted_at?->locale('id')->translatedFormat('d M H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="p-10 text-center text-slate-400 font-bold">Belum ada attempt siswa.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="arena-rx-detail-panel">
        <h2 class="font-black text-slate-800 dark:text-slate-100 mb-3 m-0">Akurasi per soal</h2>
        <div class="space-y-3">
            @foreach($questionStats as $i => $stat)
            <div>
                <div class="flex justify-between text-sm gap-2">
                    <p class="text-slate-700 dark:text-slate-200 truncate font-semibold m-0">{{ $i+1 }}. {{ \Illuminate\Support\Str::limit($stat['question']->question_text, 60) }}</p>
                    <span class="text-slate-500 flex-shrink-0 font-black">{{ $stat['accuracy'] !== null ? $stat['accuracy'].'%' : '—' }}</span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden border border-slate-200/60 dark:border-slate-600">
                    <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-lime-400" style="width:{{ $stat['accuracy'] ?? 0 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="arena-rx-detail-panel space-y-3">
        <h2 class="font-black text-slate-800 dark:text-slate-100 m-0">Transfer ke buku nilai</h2>
        <p class="text-xs text-slate-500 font-semibold m-0">Siswa tanpa attempt mendapat nilai 0. Dibatalkan jika rapor sudah dikunci.</p>
        <form method="POST" action="{{ route('classroom.arena.transfer', [$classroom, $quiz]) }}" class="space-y-3"
              x-data="{ type: 'formatif' }">
            @csrf
            <div class="flex gap-4 text-sm font-bold">
                <label class="inline-flex items-center gap-2 min-h-[44px]"><input type="radio" name="type" value="formatif" x-model="type" checked> Formatif (TP)</label>
                <label class="inline-flex items-center gap-2 min-h-[44px]"><input type="radio" name="type" value="sumatif" x-model="type"> Sumatif (Materi)</label>
            </div>
            <div x-show="type==='formatif'">
                <select name="id_tupe" class="w-full rounded-2xl border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[44px] font-semibold">
                    <option value="">— Pilih Tujuan Pembelajaran —</option>
                    @foreach($tupeList as $tp)
                    <option value="{{ $tp->uuid }}">TP {{ $tp->urutan }}: {{ \Illuminate\Support\Str::limit($tp->tupe, 60) }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="type==='sumatif'" x-cloak>
                <select name="id_materi" class="w-full rounded-2xl border-2 border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[44px] font-semibold">
                    <option value="">— Pilih Materi —</option>
                    @foreach($materiList as $m)
                    <option value="{{ $m->uuid }}">{{ $m->nama }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="arena-rx-cta-big solo !w-auto"
                    onclick="return confirm('Transfer nilai ke buku rapor sekarang?')">Transfer nilai</button>
        </form>
    </div>
</div>
@endsection
