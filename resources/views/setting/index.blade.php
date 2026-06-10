@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ tab: 'sekolah' }">
    <div class="mb-6">
        <h1 class="page-title">Pengaturan Sistem</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi sekolah, semester, dan penilaian</p>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-2xl p-1.5 mb-6 flex-wrap">
        @foreach(['sekolah'=>['Identitas','building-2'],'semester'=>['Semester','calendar-days'],'penilaian'=>['Penilaian','calculator'],'absensi'=>['Absensi','clock']] as $key => [$label,$icon])
        <button @click="tab='{{ $key }}'"
                :class="tab==='{{ $key }}' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="seg flex-1 min-w-fit flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-semibold transition">
            <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Identitas --}}
    <div x-show="tab==='sekolah'" x-transition>
        <form method="POST" action="{{ route('setting.identitas') }}" class="card p-6 space-y-4">
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-2">Identitas Sekolah</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([
                    'nama_sekolah'=>'Nama Sekolah','npsn'=>'NPSN','kepala_sekolah'=>'Kepala Sekolah',
                    'nip_kepala'=>'NIP Kepala','kota'=>'Kota','provinsi'=>'Provinsi','telp_sekolah'=>'No. Telepon',
                ] as $key => $label)
                <div>
                    <label class="form-label">{{ $label }}</label>
                    <input type="text" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}" class="form-input">
                </div>
                @endforeach
                <div class="sm:col-span-2">
                    <label class="form-label">Alamat Sekolah</label>
                    <textarea name="alamat_sekolah" rows="2" class="form-input">{{ old('alamat_sekolah', $settings['alamat_sekolah'] ?? '') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Semester --}}
    <div x-show="tab==='semester'" x-transition class="space-y-4">
        <div class="card p-6 space-y-4">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Semester Aktif</h2>
            <form method="POST" action="{{ route('setting.semester') }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div class="flex-1 min-w-48">
                    <label class="form-label">Pilih Semester Aktif</label>
                    <select name="semester_id" class="form-select">
                        @foreach($semester as $s)
                        <option value="{{ $s->id }}" @selected($s->aktif)>Semester {{ $s->semester }} — {{ $s->tahun }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Set Aktif</button>
            </form>
        </div>
        <div class="card p-6 space-y-4">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Tambah Semester</h2>
            <form method="POST" action="{{ route('setting.semester.store') }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div>
                    <label class="form-label">Semester</label>
                    <select name="semester" class="form-select">
                        <option value="1">Ganjil (1)</option>
                        <option value="2">Genap (2)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tahun Ajaran</label>
                    <input type="text" name="tahun" placeholder="2024/2025" class="form-input">
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Tambah</button>
            </form>
        </div>
    </div>

    {{-- Penilaian --}}
    <div x-show="tab==='penilaian'" x-transition>
        <form method="POST" action="{{ route('setting.rumusRapor') }}" class="card p-6 space-y-4">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Rumus Nilai Rapor</h2>
                <p class="text-sm text-slate-400 mt-0.5">Total bobot harus = 100%</p>
            </div>
            <div class="grid grid-cols-3 gap-4">
                @foreach(['bobot_harian'=>'Harian (%)','bobot_pts'=>'PTS (%)','bobot_pas'=>'PAS (%)'] as $key => $label)
                <div>
                    <label class="form-label">{{ $label }}</label>
                    <input type="number" name="{{ $key }}" value="{{ old($key, $settings[$key] ?? '') }}" min="0" max="100" class="form-input text-center font-bold">
                </div>
                @endforeach
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Absensi --}}
    <div x-show="tab==='absensi'" x-transition class="space-y-4">
        <form method="POST" action="{{ route('setting.caraAbsensi') }}" class="card p-6 space-y-4">
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Cara Absensi Guru</h2>
            <div class="grid grid-cols-2 gap-3">
                @foreach(['manual'=>['Manual','Admin input kehadiran'],'barcode'=>['Barcode / QR','Scan QR Code']] as $val => [$lbl,$desc])
                <label class="cursor-pointer">
                    <input type="radio" name="cara_absensi" value="{{ $val }}" @checked(($settings['cara_absensi_guru'] ?? 'manual')===$val) class="sr-only peer">
                    <div class="border-2 rounded-xl p-4 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600">
                        <p class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ $lbl }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $desc }}</p>
                    </div>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>

        <div class="grid sm:grid-cols-2 gap-4">
            <form method="POST" action="{{ route('setting.poinTerlambat') }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Poin Terlambat</h2>
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="form-label">Poin dikurangi</label>
                        <input type="number" name="poin_terlambat" value="{{ $settings['poin_terlambat'] ?? 5 }}" class="form-input">
                    </div>
                    <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-bold">OK</button>
                </div>
            </form>
            <form method="POST" action="{{ route('setting.waktuTerlambat') }}" class="card p-6 space-y-3">
                @csrf
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Batas Terlambat</h2>
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="form-label">Jam terlambat</label>
                        <input type="time" name="waktu_terlambat" value="{{ $settings['waktu_terlambat'] ?? '07:30' }}" class="form-input">
                    </div>
                    <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-bold">OK</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
