@extends('layouts.app')
@section('title', 'Pengaturan — ' . $ujian->judul)

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Pengaturan
        </nav>
        <h1 class="page-title">Pengaturan Ujian</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Ubah info dasar ujian. Susun soal & kelas ada di halaman tersendiri.</p>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($ujian->isClosed())
    <div class="card p-4 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border-l-4 !border-l-amber-500">
        Ujian ini sudah ditutup — pengaturan tidak bisa diubah lagi.
    </div>
    @else

    <form method="POST" action="{{ route('ujian.update', $ujian) }}" class="card p-6 space-y-4">
        @csrf

        <div>
            <label class="form-label">Judul Ujian <span class="text-rose-500">*</span></label>
            <input type="text" name="judul" value="{{ old('judul', $ujian->judul) }}" required class="form-input">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Jenis Ujian</label>
                <select name="jenis" class="form-select">
                    @foreach(['harian'=>'Ulangan Harian','pts'=>'PTS','pas'=>'PAS','uas'=>'UAS'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('jenis', $ujian->jenis)===$val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Durasi (menit) <span class="text-rose-500">*</span></label>
                <input type="number" name="durasi_menit" value="{{ old('durasi_menit', $ujian->durasi_menit) }}" min="5" max="600" required class="form-input">
            </div>
        </div>

        <div>
            <label class="form-label">Mata Pelajaran</label>
            @if($ujian->status === 'draft' && !$ujian->butuhSatuKelas())
            <select name="id_pelajaran" class="form-select">
                @foreach($pelajaranPilihan as $p)
                    <option value="{{ $p->uuid }}" @selected(old('id_pelajaran', $ujian->id_pelajaran)===$p->uuid)>{{ $p->nama }}</option>
                @endforeach
            </select>
            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Mengganti mata pelajaran akan melepas kelas & token yang sudah ditetapkan (kelas lama belum tentu diajar mapel baru) — tetapkan ulang kelasnya setelah ini.</p>
            @else
            <p class="form-input bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400">{{ $ujian->pelajaran?->nama ?? '—' }}</p>
            <p class="text-xs text-slate-400 mt-1">
                @if($ujian->butuhSatuKelas())
                    Ujian target Sumatif — mata pelajaran mengikuti Materi penugasan mengajar, tidak bisa diubah di sini.
                @else
                    Mata pelajaran cuma bisa diubah selama ujian masih draf.
                @endif
            </p>
            @endif
        </div>

        <div>
            <label class="form-label">Instruksi untuk Siswa (opsional)</label>
            <textarea name="instruksi" rows="3" class="form-input">{{ old('instruksi', $ujian->instruksi) }}</textarea>
        </div>

        <div class="grid sm:grid-cols-3 gap-3 pt-1">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="acak_soal" value="1" @checked(old('acak_soal', $ujian->acak_soal)) class="rounded text-primary focus:ring-primary"> Acak urutan soal
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="acak_opsi" value="1" @checked(old('acak_opsi', $ujian->acak_opsi)) class="rounded text-primary focus:ring-primary"> Acak urutan opsi
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="tampilkan_pembahasan" value="1" @checked(old('tampilkan_pembahasan', $ujian->tampilkan_pembahasan)) class="rounded text-primary focus:ring-primary"> Tampilkan pembahasan
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan</button>
            <a href="{{ route('ujian.show', $ujian) }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Batal</a>
        </div>
    </form>
    @endif

    @unless($ujian->isPublished())
    <div class="card p-6 border-l-4 !border-l-rose-500 space-y-2">
        <h2 class="font-bold text-rose-700 dark:text-rose-400">Hapus Ujian</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">Menghapus ujian ini beserta seluruh soal, kelas, dan token yang sudah ditetapkan. Tindakan ini tidak bisa dibatalkan.</p>
        <form method="POST" action="{{ route('ujian.destroy', $ujian) }}" onsubmit="return confirmAction(this, 'Hapus ujian &quot;{{ $ujian->judul }}&quot; beserta seluruh soalnya? Tindakan ini tidak bisa dibatalkan.', 'red')">
            @csrf @method('DELETE')
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-bold bg-rose-600 text-white hover:bg-rose-700">Hapus Ujian</button>
        </form>
    </div>
    @endunless
</div>
@endsection
