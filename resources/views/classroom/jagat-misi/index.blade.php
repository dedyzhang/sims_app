@extends('layouts.app')
@section('title', 'Arena Belajar — Misi — ' . $classroom->title)

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 max-w-4xl mx-auto arena-stage">
    <div class="rounded-2xl p-5 sm:p-7 text-white relative overflow-hidden"
         style="background:radial-gradient(ellipse 70% 50% at 10% 0%,color-mix(in srgb,var(--cp) 50%,transparent),transparent 60%),linear-gradient(155deg,#0c1a24,#152836 55%,#0f2430)">
        <div class="relative z-[1]">
            <a href="{{ route('classroom.arena.index', ['classroom' => $classroom, 'mode' => 'misi']) }}"
               class="arena-hud-back !bg-white/15 !border-white/20 !text-white !shadow-none hover:!bg-white/25 mb-3 inline-flex">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span>Lobby Arena</span>
            </a>
            <h1 class="text-2xl sm:text-3xl font-black tracking-tight">Misi</h1>
            <p class="text-sm text-slate-300 mt-1.5">Misi edukatif interaktif untuk kelas ini.</p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    @if($canManage && $availableMissions->isNotEmpty())
    <div class="card p-4 sm:p-5">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3">Tugaskan misi ke kelas</h2>
        <form method="POST" action="{{ route('classroom.jagat.assign', $classroom) }}" class="space-y-3">
            @csrf
            <select name="mission_id" required class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-3 text-sm min-h-[44px]">
                <option value="">— Pilih misi —</option>
                @foreach($availableMissions as $m)
                <option value="{{ $m->uuid }}">{{ $m->title }} ({{ $m->subject }})</option>
                @endforeach
            </select>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-500">Buka mulai (opsional)</label>
                    <input type="datetime-local" name="opens_at" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm mt-1">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Batas waktu (opsional)</label>
                    <input type="datetime-local" name="due_at" class="w-full rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm mt-1">
                </div>
            </div>
            <button type="submit" class="px-4 py-3 rounded-xl text-sm font-bold text-white min-h-[48px]" style="background:var(--cp)">Tugaskan</button>
        </form>
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @forelse($assignments as $a)
        @php $mission = $a->mission; @endphp
        @if(!$mission) @continue @endif
        <a href="{{ route('classroom.jagat.show', [$classroom, $mission]) }}" class="card p-4 flex gap-3 hover:border-primary transition">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 text-white font-black text-lg"
                 style="background:linear-gradient(145deg,#7c3aed,color-mix(in srgb,#7c3aed 55%,#1a0f2e))">
                <i data-lucide="compass" class="w-6 h-6"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 flex-wrap mb-1">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300">
                        {{ $a->isOpen() ? 'Aktif' : 'Tertutup' }}
                    </span>
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500">{{ $mission->mechanicLabel() }}</span>
                </div>
                <h3 class="font-black text-slate-800 dark:text-slate-100 truncate text-base">{{ $mission->title }}</h3>
                <p class="text-xs text-slate-500 mt-1">
                    {{ $mission->subject }} · {{ $mission->duration_minutes }} menit
                    @if($a->due_at) · Batas {{ $a->due_at->locale('id')->translatedFormat('d M') }}@endif
                    @if(auth()->user()->access === 'siswa')
                        @php $att = $myAttempts[$a->uuid] ?? null; @endphp
                        · @if($att && $att->status === 'completed')<span class="text-emerald-600 font-semibold">Selesai {{ $att->score }}%</span>
                        @elseif($att)<span class="text-sky-600">Berjalan</span>
                        @else<span class="text-amber-600">Belum dimulai</span>@endif
                    @endif
                </p>
            </div>
            <div class="self-center text-slate-300"><i data-lucide="chevron-right" class="w-5 h-5"></i></div>
        </a>
        @empty
        <div class="sm:col-span-2 card p-10 text-center text-slate-400">
            <i data-lucide="compass" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p>Belum ada misi ditugaskan ke kelas ini.</p>
            @if($canManage)
            <p class="text-sm mt-1">Pilih misi siap main dari katalog, lalu tugaskan ke kelas. <a href="{{ route('jagat-misi.builder.index') }}" class="text-primary font-semibold">Kelola katalog</a>.</p>
            @endif
        </div>
        @endforelse
    </div>
</div>
@endsection
