@extends('layouts.app')
@section('title', isset($topic) ? 'Sunting Topik' : 'Buat Topik')

@section('content')
@php $editing = isset($topic); @endphp
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ $editing ? route('forum.show', $topic) : route('forum.index') }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">{{ $editing ? 'Sunting Topik' : 'Buat Topik Diskusi' }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Mulai diskusi baru di forum kelas/mapel.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm">
        <ul class="list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $editing ? route('forum.update', $topic) : route('forum.store') }}" class="card p-6 space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif

        <div>
            <label class="form-label">Judul</label>
            <input type="text" name="title" value="{{ old('title', $topic->title ?? '') }}" required maxlength="160" class="form-input" placeholder="Judul topik diskusi">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Kategori</label>
                <select name="category" class="form-select">
                    @foreach($categories as $k => $label)
                    @if($k === 'pengumuman' && !$canAnnounce) @continue @endif
                    <option value="{{ $k }}" @selected(old('category', $topic->category ?? 'umum')===$k)>{{ $label }}</option>
                    @endforeach
                </select>
                @unless($canAnnounce)<p class="text-[11px] text-slate-400 mt-1">Kategori "Pengumuman" hanya untuk yang berizin.</p>@endunless
            </div>
            <div>
                <label class="form-label">Audiens</label>
                <select name="audience" class="form-select">
                    @foreach($audiences as $k => $label)
                    <option value="{{ $k }}" @selected(old('audience', $topic->audience ?? 'siswa_guru')===$k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Kelas <span class="text-slate-400 font-normal">(opsional)</span></label>
                <select name="id_kelas" class="form-select">
                    <option value="">— Tanpa kelas (umum) —</option>
                    @foreach($kelasList as $k)
                    <option value="{{ $k->uuid }}" @selected(old('id_kelas', $topic->id_kelas ?? '')===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Mata Pelajaran <span class="text-slate-400 font-normal">(opsional)</span></label>
                <select name="id_pelajaran" class="form-select">
                    <option value="">— Tanpa mapel —</option>
                    @foreach($pelajaranList as $p)
                    <option value="{{ $p->uuid }}" @selected(old('id_pelajaran', $topic->id_pelajaran ?? '')===$p->uuid)>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="form-label">Isi</label>
            <textarea name="body" rows="6" required class="form-input" placeholder="Tulis isi diskusi…">{{ old('body', $topic->body ?? '') }}</textarea>
            <p class="text-[11px] text-slate-400 mt-1">Teks biasa (tanpa HTML) demi keamanan. Baris baru tetap dipertahankan.</p>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $editing ? route('forum.show', $topic) : route('forum.index') }}" class="px-5 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</a>
            <button class="px-6 py-2.5 rounded-xl text-sm font-bold text-white" style="background:var(--cp)">{{ $editing ? 'Simpan Perubahan' : 'Buat Topik' }}</button>
        </div>
    </form>
</div>
@endsection
