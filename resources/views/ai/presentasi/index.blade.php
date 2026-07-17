@extends('layouts.app')
@section('title', 'Studio Presentasi')

@section('content')
<div class="space-y-5 max-w-5xl mx-auto" x-data="{ showCreate: {{ $errors->any() ? 'true' : 'false' }} }">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
        <div>
            <a href="{{ route('ai.teacher.index', ['tab' => 'gemini']) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-primary mb-2">
                <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i> Asisten Guru
            </a>
            <h1 class="page-title flex items-center gap-2">
                <i data-lucide="presentation" class="w-6 h-6 text-primary"></i>
                Studio Presentasi
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Susun slide di SIMS, atau hubungkan Canva Pendidikan (belajar.id, gratis) untuk desain visual.
            </p>
        </div>
        <button type="button" @click="showCreate = !showCreate"
                class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-4 py-2.5 text-sm font-bold min-h-[44px]">
            <i data-lucide="plus" class="w-4 h-4"></i> Presentasi baru
        </button>
    </div>

    <div class="card p-5 space-y-4" x-show="showCreate" x-cloak>
        <h2 class="font-bold text-slate-800 dark:text-slate-100">Buat presentasi</h2>
        <form method="POST" action="{{ route('ai.teacher.presentasi.store') }}" class="space-y-4">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Judul <span class="text-rose-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="form-input" placeholder="mis. Presentasi Fotosintesis Kelas 7">
                    @error('title')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label">Mapel / topik</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" class="form-input" placeholder="mis. IPA">
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', 'draft') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Outline slide</label>
                    <textarea name="outline" rows="6" class="form-input font-mono text-sm" placeholder="1. Judul & tujuan&#10;2. Materi inti&#10;3. Contoh&#10;4. Latihan&#10;5. Penutup">{{ old('outline') }}</textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Setiap baris bernomor jadi satu slide. Tempel outline di sini lalu buka studio.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold inline-flex items-center gap-2">
                    <i data-lucide="arrow-right" class="w-4 h-4"></i> Buka studio
                </button>
                <button type="button" @click="showCreate = false" class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600">Batal</button>
            </div>
        </form>
    </div>

    <div class="grid gap-3">
        @forelse($items as $item)
        <a href="{{ route('ai.teacher.presentasi.show', $item) }}"
           class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3 hover:border-primary/40 transition border border-transparent">
            <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                <i data-lucide="presentation" class="w-5 h-5"></i>
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $item->title }}</p>
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ $item->subject ?: 'Tanpa mapel' }}
                    · {{ $item->statusLabel() }}
                    · {{ count($item->resolvedSlides()) }} slide
                    · {{ $item->updated_at?->diffForHumans() }}
                </p>
            </div>
            <span class="inline-flex items-center gap-1 text-xs font-bold text-primary">
                Buka studio <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            </span>
        </a>
        @empty
        <div class="card p-10 text-center">
            <div class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-primary/10 text-primary mb-3">
                <i data-lucide="presentation" class="w-7 h-7"></i>
            </div>
            <p class="font-bold text-slate-700 dark:text-slate-200">Belum ada presentasi</p>
            <p class="text-sm text-slate-500 mt-1 max-w-md mx-auto">Buat presentasi baru dari judul dan outline lewat tombol di bawah.</p>
            <button type="button" @click="showCreate = true" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary text-white px-4 py-2.5 text-sm font-bold">
                <i data-lucide="plus" class="w-4 h-4"></i> Presentasi baru
            </button>
        </div>
        @endforelse
    </div>
</div>
@endsection
