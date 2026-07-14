@extends('layouts.app')
@section('title', $pengumuman->exists ? 'Sunting Pengumuman' : 'Buat Pengumuman')

@section('content')
@php $editing = $pengumuman->exists; @endphp
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ $editing ? route('pengumuman.show', $pengumuman) : route('pengumuman.index') }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">{{ $editing ? 'Sunting Pengumuman' : 'Buat Pengumuman' }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $editing ? 'Perubahan tidak mengirim ulang notifikasi.' : 'Saat diterbitkan, notifikasi & push dikirim ke peran sasaran.' }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-700 text-rose-700 dark:text-rose-300 px-4 py-3 text-sm">
        <ul class="list-disc ml-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ $editing ? route('pengumuman.update', $pengumuman) : route('pengumuman.store') }}" class="card p-6 space-y-5">
        @csrf
        @if($editing) @method('PUT') @endif

        <div>
            <label class="form-label">Judul</label>
            <input type="text" name="judul" value="{{ old('judul', $pengumuman->judul) }}" required maxlength="150" class="form-input" placeholder="mis. Libur Semester Ganjil">
        </div>

        <div>
            <label class="form-label">Isi Pengumuman</label>
            <textarea name="isi" rows="8" required maxlength="20000" class="form-input" placeholder="Tulis isi pengumuman…">{{ old('isi', $pengumuman->isi) }}</textarea>
            <p class="text-[11px] text-slate-400 mt-1">Teks biasa (tanpa HTML). Baris baru dipertahankan.</p>
        </div>

        <div>
            @php $selected = old('target_roles', $pengumuman->target_roles ?? []); @endphp
            <label class="form-label">Peran Sasaran</label>
            <p class="text-[11px] text-slate-400 mb-2">Kosongkan semua = kirim ke <strong>semua peran</strong>. Centang <strong>Orang Tua</strong> agar push FCM &amp; bell notifikasi sampai ke akun orang tua.</p>
            <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mb-2">Notifikasi kehadiran anak (scan wajah/QR) otomatis terkirim ke orang tua — terpisah dari pengumuman ini.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($targetRoles as $key => $label)
                <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm">
                    <input type="checkbox" name="target_roles[]" value="{{ $key }}" @checked(in_array($key, (array) $selected, true)) class="rounded border-slate-300 text-primary" style="accent-color:var(--cp)">
                    <span class="text-slate-700 dark:text-slate-200">{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ $editing ? route('pengumuman.show', $pengumuman) : route('pengumuman.index') }}" class="px-5 py-2.5 rounded-xl text-sm font-medium border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">Batal</a>
            <button class="px-6 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg" style="background:var(--cp)">{{ $editing ? 'Simpan Perubahan' : 'Terbitkan & Kirim' }}</button>
        </div>
    </form>
</div>
@endsection
