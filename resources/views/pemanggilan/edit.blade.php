@extends('layouts.app')
@section('title', 'Ubah Pemanggilan — '.($panggilan->siswa?->nama ?? ''))

@section('content')
<div class="max-w-2xl mx-auto space-y-4">
    <a href="{{ route('pemanggilan.show', $panggilan) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
    <div>
        <h1 class="page-title">Ubah Catatan Pemanggilan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {{ $panggilan->siswa?->nama ?? '-' }} &bull; Kelas {{ $panggilan->siswa?->kelas ? $panggilan->siswa->kelas->tingkat.$panggilan->siswa->kelas->kelas : '-' }}
        </p>
    </div>

    <form method="POST" action="{{ route('pemanggilan.update', $panggilan) }}" enctype="multipart/form-data" class="card p-6 space-y-4">
        @csrf @method('PUT')
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Tanggal <span class="text-rose-500">*</span></label>
                <input type="date" name="tanggal" value="{{ old('tanggal', $panggilan->tanggal->toDateString()) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Yang Dipanggil <span class="text-rose-500">*</span></label>
                <select name="dipanggil" class="form-select" required>
                    @foreach(\App\Models\Pemanggilan::DIPANGGIL as $val => $label)
                    <option value="{{ $val }}" @selected(old('dipanggil', $panggilan->dipanggil)===$val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Perihal <span class="text-rose-500">*</span></label>
            <input type="text" name="perihal" value="{{ old('perihal', $panggilan->perihal) }}" maxlength="150" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Catatan Permasalahan <span class="text-rose-500">*</span></label>
            <textarea name="permasalahan" rows="4" class="form-input" required>{{ old('permasalahan', $panggilan->permasalahan) }}</textarea>
        </div>
        <div>
            <label class="form-label">Hasil Pertemuan <span class="text-slate-400 font-normal">(boleh diisi belakangan)</span></label>
            <textarea name="hasil" rows="4" class="form-input">{{ old('hasil', $panggilan->hasil) }}</textarea>
        </div>

        @if($panggilan->dokumentasi->isNotEmpty())
        <div>
            <label class="form-label">Dokumentasi Tersimpan</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($panggilan->dokumentasi as $d)
                <div class="relative group aspect-square rounded-xl overflow-hidden border border-slate-200 dark:border-slate-600">
                    @if($d->isImage())
                    <img src="{{ $d->url }}" class="w-full h-full object-cover" alt="{{ $d->original_name }}">
                    @else
                    <a href="{{ $d->url }}" target="_blank" class="w-full h-full flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-primary">
                        <i data-lucide="file-text" class="w-6 h-6"></i>
                        <span class="text-[10px] truncate px-1">{{ $d->original_name }}</span>
                    </a>
                    @endif
                    <form method="POST" action="{{ route('pemanggilan.dokumentasi.destroy', [$panggilan, $d]) }}" onsubmit="return confirmDelete(this)" class="absolute top-1.5 right-1.5">
                        @csrf @method('DELETE')
                        <button class="w-7 h-7 rounded-full bg-rose-500/90 text-white grid place-items-center opacity-0 group-hover:opacity-100 transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div>
            <label class="form-label">Tambah Dokumentasi <span class="text-slate-400 font-normal">(opsional)</span></label>
            @include('classroom.partials.upload', [
                'label' => 'Seret & lepas foto/bukti pertemuan, atau klik untuk pilih',
                'acceptLabel' => 'Gambar (JPG/PNG/WEBP/HEIC) atau PDF',
                'acceptAttr' => 'image/*,application/pdf',
                'maxMb' => 5,
            ])
            @error('files')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
            @error('files.*')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
        </button>
    </form>
</div>
@endsection
