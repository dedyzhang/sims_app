@extends('layouts.app')
@section('title', 'Kelola katalog misi — Arena Belajar')

@push('styles')
@include('arena-belajar.partials.game-styles')
@endpush

@section('content')
<div class="space-y-5 arena-stage">
    <a href="{{ route('jagat-misi.index') }}" class="arena-hud-back">
        <i data-lucide="chevron-left" class="w-4 h-4"></i>
        <span>Katalog Misi</span>
    </a>
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-3">
        <div>
            <p class="arena-eyebrow" style="color:var(--arena-teal)">Arena Belajar</p>
            <h1 class="text-2xl font-black text-slate-800 dark:text-slate-100">Kelola katalog misi</h1>
            <p class="text-sm text-slate-500 mt-1 max-w-xl">
                Tugaskan misi yang <strong>sudah siap dimainkan</strong> ke ruang kelas.
                Editor langkah permainan belum tersedia — jangan terbitkan misi kosong.
            </p>
        </div>
        <a href="{{ route('jagat-misi.index') }}" class="arena-cta inline-flex">
            <i data-lucide="compass" class="w-4 h-4"></i> Buka katalog
        </a>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 p-3 text-sm text-emerald-700 dark:text-emerald-300 font-semibold">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 p-3 text-sm text-rose-700 dark:text-rose-300 font-semibold">{{ session('error') }}</div>
    @endif

    <div class="rounded-2xl border-2 border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-900/20 p-4 text-sm text-amber-900 dark:text-amber-100">
        <p class="font-black mb-1">Cara pakai yang disarankan</p>
        <ol class="list-decimal pl-4 space-y-1 text-amber-800/90 dark:text-amber-100/90">
            <li>Pilih misi berlabel <strong>Siap main</strong> dari daftar / katalog.</li>
            <li>Buka Ruang Kelas → Arena Belajar → tab Misi → <strong>Tugaskan misi</strong>.</li>
            <li>Untuk kuis cepat &amp; live: buat di <strong>Kuis Arena</strong> (bisa impor dari Asisten Guru).</li>
        </ol>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-200/80">
            Metadata misi (judul/mapel) masih bisa diedit. Membuat misi baru tanpa langkah = belum bisa dimainkan siswa.
        </p>
    </div>

    <div class="flex flex-wrap gap-2 text-xs font-bold">
        <span class="arena-pill arena-pill-sd">SD</span>
        <span class="arena-pill arena-pill-smp">SMP</span>
        <span class="arena-pill arena-pill-sma">SMA/SMK</span>
        <span class="arena-pill arena-pill-umum">Umum</span>
    </div>

    <div class="grid gap-3">
        @forelse($missions as $m)
        @php
            $jenjangKey = $m->jenjangKey();
            $jenjangLabel = $m->jenjangLabel();
            $playable = $m->isPlayable();
        @endphp
        <a href="{{ route('jagat-misi.builder.edit', $m) }}"
           class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900 hover:shadow-md transition flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                    <span class="arena-pill arena-pill-jenjang arena-pill-{{ $jenjangKey }}">{{ $jenjangLabel }}</span>
                    <span class="arena-pill">{{ $m->status }}</span>
                    @if($playable)
                    <span class="arena-pill arena-pill-teal">Siap main · {{ $m->steps_count }} langkah</span>
                    @else
                    <span class="arena-pill" style="background:#fef3c7;color:#92400e">Metadata saja</span>
                    @endif
                    @if($m->isTren())
                    <span class="arena-pill arena-pill-tren">Tren</span>
                    @endif
                </div>
                <p class="font-bold text-slate-800 dark:text-slate-100">{{ $m->title }}</p>
                <p class="text-xs text-slate-500 mt-0.5">
                    {{ $m->subject }}
                    · {{ $m->mechanicLabel() }}
                    @if($m->grade_level)
                    · {{ $m->grade_level }}
                    @endif
                    · {{ $m->duration_minutes }} menit
                </p>
            </div>
            <span class="text-xs font-bold shrink-0" style="color:var(--arena-teal)">Edit metadata →</span>
        </a>
        @empty
        <div class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-700 p-10 text-center">
            <p class="font-bold text-slate-700 dark:text-slate-200">Belum ada misi di daftar Anda</p>
            <p class="text-sm text-slate-500 mt-1">Jalankan seeder katalog atau buka misi bersama guru lain.</p>
            <a href="{{ route('jagat-misi.index') }}" class="arena-cta mt-4 inline-flex">Buka katalog</a>
        </div>
        @endforelse
    </div>

    <details class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900">
        <summary class="cursor-pointer font-bold text-sm text-slate-700 dark:text-slate-200">Opsi lanjutan: buat metadata misi baru</summary>
        <p class="text-xs text-slate-500 mt-2 leading-relaxed">
            Form ini hanya menyimpan judul/mapel/mekanik. Tanpa langkah permainan, misi <strong>tidak bisa diterbitkan</strong> untuk siswa.
            Gunakan hanya jika Anda menyiapkan konten lewat jalur admin/seeder.
        </p>
        <a href="{{ route('jagat-misi.builder.create') }}" class="inline-flex mt-3 text-sm font-bold text-primary">Buat metadata misi →</a>
    </details>

    @if(isset($bankItems) && $bankItems->isNotEmpty())
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-slate-50 dark:bg-slate-900/50">
        <h2 class="font-black text-slate-800 dark:text-slate-100 text-sm">Cuplikan tersimpan ({{ $bankItems->count() }})</h2>
        <p class="text-xs text-slate-500 mt-1 mb-3">Bank item untuk editor langkah (masih terbatas). Bisa dipakai saat step editor tersedia.</p>
        <ul class="space-y-2">
            @foreach($bankItems->take(8) as $item)
            <li class="text-sm text-slate-700 dark:text-slate-200">
                <span class="font-semibold">{{ $item->title }}</span>
                <span class="text-xs text-slate-400">· {{ $item->type }}</span>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endsection
