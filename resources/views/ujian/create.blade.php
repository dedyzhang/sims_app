@extends('layouts.app')
@section('title', 'Buat Ujian')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">Buat Ujian</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            @if($bolehAturKelas)
                Isi info dasar dan tetapkan kelasnya — soal disusun di langkah berikutnya.
            @else
                Isi info dasar ujiannya — kelas & soal disusun di langkah berikutnya.
            @endif
        </p>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('ujian.store') }}" class="card p-6 space-y-4"
          x-data="{
              target: '{{ $bolehAturKelas ? old('target_nilai', 'pts') : 'sumatif' }}',
              pelajaran: '{{ old('id_pelajaran') }}',
              kelasMap: {{ Js::from($kelasByPelajaran) }},
              get kelasTersedia() { return this.kelasMap[this.pelajaran] || []; },
          }">
        @csrf

        <div>
            <label class="form-label">Judul Ujian <span class="text-rose-500">*</span></label>
            <input type="text" name="judul" value="{{ old('judul') }}" placeholder="Contoh: PTS Ganjil - Matematika Kelas 7" required class="form-input">
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Jenis Ujian</label>
                @if($bolehAturKelas)
                <select name="jenis" class="form-select">
                    @foreach(['harian'=>'Ulangan Harian','pts'=>'PTS','pas'=>'PAS','uas'=>'UAS'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('jenis')===$val)>{{ $lbl }}</option>
                    @endforeach
                </select>
                @else
                <input type="hidden" name="jenis" value="harian">
                <p class="form-input bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400">Ulangan Harian</p>
                @endif
            </div>
            <div>
                <label class="form-label">Durasi (menit) <span class="text-rose-500">*</span></label>
                <input type="number" name="durasi_menit" value="{{ old('durasi_menit', 90) }}" min="5" max="600" required class="form-input">
            </div>
        </div>

        @if($bolehAturKelas)
        <div>
            <label class="form-label mb-2">Nilai Otomatis Masuk Ke</label>
            <div class="grid grid-cols-3 gap-2">
                <label class="cursor-pointer">
                    <input type="radio" name="target_nilai" value="pts" x-model="target" @checked(old('target_nilai','pts')==='pts') class="hidden peer">
                    <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600">
                        <p class="font-bold text-sm">PTS</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="target_nilai" value="pas" x-model="target" @checked(old('target_nilai')==='pas') class="hidden peer">
                    <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600">
                        <p class="font-bold text-sm">PAS</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="target_nilai" value="sumatif" x-model="target" @checked(old('target_nilai')==='sumatif') class="hidden peer">
                    <div class="border-2 rounded-xl p-3 text-center transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600">
                        <p class="font-bold text-sm">Sumatif</p>
                    </div>
                </label>
            </div>
            <p class="text-xs text-slate-400 mt-1.5">PTS/PAS: ujian bisa lintas banyak kelas sekaligus (koordinasi satu tingkat). Sumatif (ulangan harian): otomatis dibatasi ke <b>satu kelas</b> karena terikat ke satu Materi penugasan mengajar.</p>
        </div>
        @else
        <input type="hidden" name="target_nilai" value="sumatif">
        <p class="text-xs text-slate-400 -mt-1">Nilai ujian ini otomatis masuk sebagai nilai <b>Sumatif</b> dari Materi yang dipilih di bawah. Ujian formal PTS/PAS/UAS diatur lewat Paket Ujian oleh admin/pengelola.</p>
        @endif

        <div x-show="target !== 'sumatif'" x-cloak>
            <label class="form-label">Mata Pelajaran <span class="text-rose-500">*</span></label>
            <select name="id_pelajaran" x-model="pelajaran" class="form-select">
                <option value="">Pilih mata pelajaran</option>
                @foreach($ngajars->unique('id_pelajaran') as $n)
                    <option value="{{ $n->id_pelajaran }}" @selected(old('id_pelajaran')===$n->id_pelajaran)>{{ $n->pelajaran?->nama }}</option>
                @endforeach
            </select>
        </div>

        @if($bolehAturKelas)
        <div x-show="target !== 'sumatif'" x-cloak>
            <label class="form-label">Kelas <span class="text-rose-500">*</span></label>
            <select name="id_kelas[]" multiple class="form-select h-32" :disabled="kelasTersedia.length === 0">
                <template x-for="k in kelasTersedia" :key="k.uuid">
                    <option :value="k.uuid" x-text="k.label"></option>
                </template>
            </select>
            <p class="text-xs text-slate-400 mt-1" x-show="!pelajaran">Pilih mata pelajaran dulu.</p>
            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1" x-show="pelajaran && kelasTersedia.length === 0">Belum ada kelas yang diajar mata pelajaran ini (cek data Ngajar).</p>
            <p class="text-xs text-slate-400 mt-1" x-show="kelasTersedia.length > 0">Hanya kelas yang benar-benar diajar mata pelajaran ini (via data Ngajar) yang muncul di sini. Bisa pilih lebih dari satu — kelas satu tingkat otomatis berbagi token masuk.</p>
        </div>
        @else
        <div x-show="target !== 'sumatif'" x-cloak class="rounded-xl bg-slate-50 dark:bg-slate-800 p-3 text-xs text-slate-500 dark:text-slate-400">
            Kelas & token masuk ditetapkan oleh admin/pengelola setelah ujian ini dibuat — cukup susun soalnya dulu.
        </div>
        @endif

        <div x-show="target === 'sumatif'" x-cloak>
            <label class="form-label">Materi (Penugasan Mengajar) <span class="text-rose-500">*</span></label>
            <select name="id_materi" class="form-select">
                <option value="">Pilih materi</option>
                @foreach($ngajars as $n)
                    @foreach($materiByNgajar->get($n->uuid, []) as $m)
                        <option value="{{ $m->uuid }}" @selected(old('id_materi')===$m->uuid)>{{ $n->pelajaran?->nama }} — {{ $n->kelas?->tingkat }}{{ $n->kelas?->kelas }} — {{ $m->nama }}</option>
                    @endforeach
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">Materi belum tersedia? Tambahkan dulu lewat menu Penilaian → pilih penugasan mengajar → tambah Materi.</p>
        </div>

        <div>
            <label class="form-label">Instruksi untuk Siswa (opsional)</label>
            <textarea name="instruksi" rows="3" class="form-input" placeholder="Bacalah soal dengan teliti. Ujian ini terkunci fullscreen...">{{ old('instruksi') }}</textarea>
        </div>

        <div class="grid sm:grid-cols-3 gap-3 pt-1">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="acak_soal" value="1" @checked(old('acak_soal', true)) class="rounded text-primary focus:ring-primary"> Acak urutan soal
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="acak_opsi" value="1" @checked(old('acak_opsi', true)) class="rounded text-primary focus:ring-primary"> Acak urutan opsi
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="tampilkan_pembahasan" value="1" @checked(old('tampilkan_pembahasan')) class="rounded text-primary focus:ring-primary"> Tampilkan pembahasan
            </label>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan & Lanjut Susun Soal</button>
            <a href="{{ route('ujian.index') }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">Batal</a>
        </div>
    </form>
</div>
@endsection
