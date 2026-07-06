@extends('layouts.app')
@section('title', 'Materi & TP')

@section('content')
@include('nilai._tabs')

<div class="space-y-4 mt-5" x-data="{ showDuplicateModal: false }">

    @include('nilai._terkunci')

    {{-- Tambah materi --}}
    @if(!$terkunci && empty($readOnly))
    <form method="POST" action="{{ route('nilai.materi.store', $ngajar->uuid) }}" class="card p-4 flex flex-wrap items-end gap-3">
        @csrf
        <div class="flex-1 min-w-48">
            <label class="form-label">Materi / Bab baru</label>
            <input type="text" name="nama" required placeholder="mis. Bilangan Bulat" class="form-input">
        </div>
        <button type="submit" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Materi
        </button>
    </form>
    @endif

    @if(!$otherNgajars->isEmpty() && !$materi->isEmpty() && !$terkunci && empty($readOnly))
    {{-- Trigger button panel --}}
    <div class="flex justify-between items-center bg-white/40 dark:bg-slate-800/20 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800 backdrop-blur-md">
        <div class="flex items-center gap-2 text-slate-500">
            <i data-lucide="copy" class="w-4 h-4 text-violet-600"></i>
            <span class="text-xs font-semibold">Materi Paralel & TP</span>
        </div>
        <button type="button" @click="showDuplicateModal = true" class="flex items-center gap-1.5 px-3.5 py-2 bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 hover:bg-violet-200 dark:hover:bg-violet-900/50 rounded-xl text-xs font-bold transition shadow-sm">
            <i data-lucide="copy" class="w-3.5 h-3.5"></i> Duplikat ke Kelas Lain
        </button>
    </div>

    {{-- Modal Duplikasi --}}
    <div x-show="showDuplicateModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-sm transition-opacity" @click="showDuplicateModal = false" x-show="showDuplicateModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        {{-- Dialog --}}
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl transition-all w-full max-w-lg p-6 space-y-4" x-show="showDuplicateModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <i data-lucide="copy" class="w-5 h-5 text-violet-600"></i>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Duplikat ke Kelas Lain</h3>
                    </div>
                    <button type="button" @click="showDuplicateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                {{-- Form --}}
                <form method="POST" action="{{ route('nilai.materi.duplicate', $ngajar->uuid) }}" class="space-y-4">
                    @csrf
                    
                    {{-- Pilih Materi --}}
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">1. Pilih Materi yang akan Diduplikasi:</p>
                        <div class="flex flex-col gap-2 pl-1 max-h-40 overflow-y-auto pr-2">
                            @foreach($materi as $m)
                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer">
                                    <input type="checkbox" name="materi_ids[]" value="{{ $m->uuid }}" checked class="rounded text-violet-600 focus:ring-violet-500">
                                    <span>{{ $m->nama }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Pilih Kelas Tujuan --}}
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide">2. Pilih Kelas Tujuan:</p>
                        <div class="flex flex-wrap gap-2 pl-1">
                            @foreach($otherNgajars as $on)
                                <label class="inline-flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                                    <input type="checkbox" name="target_ngajar_ids[]" value="{{ $on->uuid }}" class="rounded text-violet-600 focus:ring-violet-500">
                                    <span>Kelas {{ $on->kelas?->tingkat }}{{ $on->kelas?->kelas }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end gap-2 border-t border-slate-100 dark:border-slate-700 pt-3">
                        <button type="button" @click="showDuplicateModal = false" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                            Batal
                        </button>
                        <button type="submit" class="btn-primary flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold shadow-sm bg-violet-600 hover:bg-violet-700 text-white">
                            <i data-lucide="copy" class="w-4 h-4"></i> Duplikat Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @forelse($materi as $m)
    <div class="card overflow-hidden {{ $m->aktif ? '' : 'opacity-60' }}">
        {{-- Header materi --}}
        <div class="flex items-center gap-2 px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40">
            <i data-lucide="book-open" class="w-4 h-4 text-primary flex-shrink-0"></i>
            @if($terkunci)
                <span class="font-bold text-slate-700 dark:text-slate-200 flex-1 min-w-0 truncate py-1">{{ $m->nama }}</span>
                @unless($m->aktif)<span class="badge bg-slate-200 text-slate-500 dark:bg-slate-700">nonaktif</span>@endunless
            @else
                <form method="POST" action="{{ route('nilai.materi.update', $m->uuid) }}" class="flex items-center gap-2 flex-1 min-w-0">
                    @csrf @method('PUT')
                    <input type="hidden" name="aktif" value="{{ $m->aktif ? 1 : 0 }}">
                    <input type="text" name="nama" value="{{ $m->nama }}" class="font-bold text-slate-700 dark:text-slate-200 bg-transparent border-0 border-b border-transparent hover:border-slate-300 focus:border-primary outline-none flex-1 min-w-0 py-1">
                    <button type="submit" class="text-xs text-primary hover:underline font-semibold flex-shrink-0">Simpan</button>
                </form>
                @unless($m->aktif)<span class="badge bg-slate-200 text-slate-500 dark:bg-slate-700">nonaktif</span>@endunless
                <form method="POST" action="{{ route('nilai.materi.toggle', $m->uuid) }}">
                    @csrf
                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="{{ $m->aktif ? 'Nonaktifkan (tak dihitung rapor)' : 'Aktifkan' }}">
                        <i data-lucide="{{ $m->aktif ? 'eye' : 'eye-off' }}" class="w-4 h-4"></i>
                    </button>
                </form>
                <form method="POST" action="{{ route('nilai.materi.destroy', $m->uuid) }}" onsubmit="return confirmDelete(this)">
                    @csrf @method('DELETE')
                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition" title="Hapus materi"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </form>
            @endif
        </div>

        {{-- Daftar TP --}}
        <div class="p-4 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">
                    Tujuan Pembelajaran
                    <span class="normal-case text-slate-400">({{ $m->tujuan->count() }}@if(($tpMax ?? 0) > 0)/{{ $tpMax }}@endif)</span>
                </p>
                @if(($tpMin ?? 0) > 0 || ($tpMax ?? 0) > 0)
                <span class="text-[10px] text-slate-400">@if(($tpMin ?? 0) > 0)min {{ $tpMin }}@endif @if(($tpMax ?? 0) > 0) · maks {{ $tpMax }}@endif</span>
                @endif
            </div>
            @forelse($m->tujuan as $i => $t)
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400 w-5 text-center flex-shrink-0">{{ $i + 1 }}</span>
                @if($terkunci)
                <span class="flex-1 min-w-0 text-sm text-slate-600 dark:text-slate-300 py-1.5">{{ $t->tupe }}</span>
                @else
                <form method="POST" action="{{ route('nilai.tupe.update', $t->uuid) }}" class="flex items-center gap-2 flex-1 min-w-0">
                    @csrf @method('PUT')
                    <input type="text" name="tupe" value="{{ $t->tupe }}" class="form-input flex-1 min-w-0 py-1.5 text-sm">
                    <button type="submit" class="text-xs text-primary hover:underline font-semibold flex-shrink-0">Simpan</button>
                </form>
                <form method="POST" action="{{ route('nilai.tupe.destroy', $t->uuid) }}" onsubmit="return confirmDelete(this)">
                    @csrf @method('DELETE')
                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition flex-shrink-0"><i data-lucide="x" class="w-4 h-4"></i></button>
                </form>
                @endif
            </div>
            @empty
            <p class="text-sm text-slate-400 italic">Belum ada TP.</p>
            @endforelse

            @if(!$terkunci)
                @if(($tpMax ?? 0) > 0 && $m->tujuan->count() >= $tpMax)
                <p class="text-xs text-amber-600 flex items-center gap-1.5 pt-1"><i data-lucide="info" class="w-3.5 h-3.5"></i> Sudah mencapai maksimal {{ $tpMax }} TP.</p>
                @else
                <form method="POST" action="{{ route('nilai.tupe.store', $m->uuid) }}" class="flex items-center gap-2 pt-1">
                    @csrf
                    <span class="w-5 flex-shrink-0"></span>
                    <input type="text" name="tupe" required placeholder="Tambah tujuan pembelajaran…" class="form-input flex-1 min-w-0 py-1.5 text-sm">
                    <button type="submit" class="flex items-center gap-1 text-sm font-semibold text-primary hover:underline flex-shrink-0"><i data-lucide="plus" class="w-4 h-4"></i> TP</button>
                </form>
                @endif
            @endif
        </div>
    </div>
    @empty
    <div class="card p-12 text-center text-slate-400">
        <i data-lucide="book-plus" class="w-12 h-12 mx-auto mb-3 opacity-30"></i>
        <p class="font-medium">Belum ada materi.</p>
        <p class="text-sm mt-1">Tambahkan materi di atas, lalu isi Tujuan Pembelajarannya.</p>
    </div>
    @endforelse
</div>
@endsection
