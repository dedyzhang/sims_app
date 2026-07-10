@extends('layouts.app')
@section('title', 'Kirim Masukan')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Kirim Masukan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Jelaskan yang Anda alami supaya tim sekolah bisa menindaklanjuti dengan tepat.</p>
        </div>
        <a href="{{ route('feedback.index') }}" class="btn-ghost inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Riwayat
        </a>
    </div>

    <form method="POST" action="{{ route('feedback.store') }}" class="card p-5 md:p-6 space-y-5">
        @csrf
        <input type="hidden" name="context_url" value="{{ old('context_url', $contextUrl) }}">

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label for="category" class="form-label">Kategori</label>
                <select id="category" name="category" class="form-select" required>
                    @foreach($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="rating" class="form-label">Rating pengalaman</label>
                <select id="rating" name="rating" class="form-select">
                    <option value="">Tidak memberi rating</option>
                    @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected((string) old('rating') === (string) $i)>{{ $i }} / 5</option>
                    @endfor
                </select>
            </div>
        </div>

        <div>
            <label for="subject" class="form-label">Judul singkat</label>
            <input id="subject" name="subject" value="{{ old('subject') }}" class="form-input" maxlength="160" required placeholder="Contoh: Tidak bisa melihat jadwal hari ini">
        </div>

        <div>
            <label for="message" class="form-label">Detail masukan</label>
            <textarea id="message" name="message" rows="7" class="form-input" required placeholder="Tulis langkah yang dilakukan, halaman yang dibuka, dan apa yang Anda harapkan.">{{ old('message') }}</textarea>
            <p class="text-xs text-slate-400 mt-2">Halaman asal otomatis ikut tersimpan agar admin tahu konteksnya.</p>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold">
                <i data-lucide="send" class="w-4 h-4"></i>
                Kirim Masukan
            </button>
        </div>
    </form>
</div>
@endsection
