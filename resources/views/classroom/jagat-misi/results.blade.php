@extends('layouts.app')
@section('title', 'Hasil — ' . $mission->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 max-w-4xl mx-auto arena-stage">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('classroom.jagat.show', [$classroom, $mission]) }}" class="arena-hud-back mb-3">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span class="truncate max-w-[14rem] sm:max-w-xs">{{ $mission->title }}</span>
            </a>
            <h1 class="text-xl font-black text-slate-800 dark:text-slate-100">Monitor hasil misi</h1>
            <p class="text-sm text-slate-500">{{ $doneCount }}/{{ $memberCount }} siswa selesai
                ({{ $memberCount > 0 ? round(($doneCount / $memberCount) * 100) : 0 }}%)</p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="p-3 font-semibold">Siswa</th>
                    <th class="p-3 font-semibold">Status</th>
                    <th class="p-3 font-semibold">Skor</th>
                    <th class="p-3 font-semibold">Durasi</th>
                    <th class="p-3 font-semibold">Selesai</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attempts as $a)
                <tr class="border-b border-slate-50 dark:border-slate-800">
                    <td class="p-3 font-medium text-slate-800 dark:text-slate-100">{{ $a->user?->displayName() ?? '—' }}</td>
                    <td class="p-3 capitalize text-slate-500">{{ str_replace('_', ' ', $a->status) }}</td>
                    <td class="p-3 font-bold" style="color:var(--cp)">{{ $a->score }}%</td>
                    <td class="p-3 text-slate-500">{{ $a->duration_seconds ? gmdate('i:s', $a->duration_seconds) : '—' }}</td>
                    <td class="p-3 text-slate-400">{{ $a->completed_at?->locale('id')->translatedFormat('d M H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-8 text-center text-slate-400">Belum ada attempt siswa yang selesai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card p-4 sm:p-5 space-y-3">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">Transfer ke buku nilai</h2>
        <p class="text-xs text-slate-500">Siswa tanpa attempt mendapat nilai 0. Dibatalkan jika rapor sudah dikunci.</p>
        <form method="POST" action="{{ route('classroom.jagat.transfer', [$classroom, $mission]) }}" class="space-y-3"
              x-data="{ type: 'formatif' }">
            @csrf
            <div class="flex gap-4 text-sm">
                <label class="inline-flex items-center gap-2 min-h-[44px]"><input type="radio" name="type" value="formatif" x-model="type" checked> Formatif (TP)</label>
                <label class="inline-flex items-center gap-2 min-h-[44px]"><input type="radio" name="type" value="sumatif" x-model="type"> Sumatif (Materi)</label>
            </div>
            <div x-show="type==='formatif'">
                <select name="id_tupe" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[44px]">
                    <option value="">— Pilih Tujuan Pembelajaran —</option>
                    @foreach($tupeList as $tp)
                    <option value="{{ $tp->uuid }}">TP {{ $tp->urutan }}: {{ \Illuminate\Support\Str::limit($tp->tupe, 60) }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="type==='sumatif'" x-cloak>
                <select name="id_materi" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[44px]">
                    <option value="">— Pilih Materi —</option>
                    @foreach($materiList as $m)
                    <option value="{{ $m->uuid }}">{{ $m->nama }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)"
                    onclick="return confirm('Transfer nilai ke buku rapor sekarang?')">Transfer nilai</button>
        </form>
    </div>
</div>
@endsection
