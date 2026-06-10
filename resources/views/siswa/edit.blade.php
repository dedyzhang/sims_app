@extends('layouts.app')
@section('title', 'Edit Siswa')

@section('content')
@php $breadcrumbs = [['label'=>'Data Siswa','url'=>route('siswa.index')], ['label'=>$siswa->nama,'url'=>route('siswa.show',$siswa->uuid)], ['label'=>'Edit','url'=>'#']]; @endphp

<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('siswa.show', $siswa->uuid) }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Edit Siswa</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $siswa->nama }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('siswa.update', $siswa->uuid) }}" class="space-y-5">
        @csrf @method('PUT')

        <div class="card p-6">
            <div class="flex items-center gap-2 mb-5 pb-4 border-b border-slate-100 dark:border-slate-700">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-primary-50 text-primary"><i data-lucide="user-round" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Identitas Siswa</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="form-label">Nama <span class="text-rose-500">*</span></label>
                    <input type="text" name="nama" value="{{ old('nama', $siswa->nama) }}" required class="form-input">
                </div>
                <div>
                    <label class="form-label">NIS <span class="text-rose-500">*</span></label>
                    <input type="text" name="nis" value="{{ old('nis', $siswa->nis) }}" maxlength="30" class="form-input font-mono">
                    <p class="text-xs text-slate-400 mt-1.5">Harus unik. Mengubah NIS juga memperbarui login siswa.</p>
                </div>
                <div>
                    <label class="form-label">NISN</label>
                    <input type="text" name="nisn" value="{{ old('nisn', $siswa->nisn) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Kelas</label>
                    <select name="id_kelas" class="form-select">
                        <option value="">Belum ditentukan</option>
                        @foreach($kelas as $k)
                        <option value="{{ $k->uuid }}" @selected(old('id_kelas',$siswa->id_kelas)===$k->uuid)>Kelas {{ $k->tingkat }}{{ $k->kelas }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jk" class="form-select">
                        <option value="L" @selected(old('jk',$siswa->jk)==='L')>Laki-laki</option>
                        <option value="P" @selected(old('jk',$siswa->jk)==='P')>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Agama</label>
                    <select name="agama" class="form-select">
                        <option value="">— Pilih —</option>
                        @foreach(['Islam','Kristen Protestan','Katolik','Hindu','Buddha','Konghucu'] as $ag)
                        <option value="{{ $ag }}" @selected(old('agama',$siswa->agama)===$ag)>{{ $ag }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir',$siswa->tempat_lahir) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir',$siswa->tanggal_lahir) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_handphone" value="{{ old('no_handphone',$siswa->no_handphone) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Sekolah Asal</label>
                    <input type="text" name="sekolah_asal" value="{{ old('sekolah_asal',$siswa->sekolah_asal) }}" class="form-input">
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" rows="2" class="form-input">{{ old('alamat',$siswa->alamat) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex items-center gap-2 mb-5 pb-4 border-b border-slate-100 dark:border-slate-700">
                <span class="grid place-items-center w-8 h-8 rounded-lg bg-primary-50 text-primary"><i data-lucide="users-round" class="w-4 h-4"></i></span>
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Data Orang Tua</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Nama Ayah</label>
                    <input type="text" name="nama_ayah" value="{{ old('nama_ayah',$siswa->nama_ayah) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">No. HP Ayah</label>
                    <input type="text" name="no_telp_ayah" value="{{ old('no_telp_ayah',$siswa->no_telp_ayah) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">Nama Ibu</label>
                    <input type="text" name="nama_ibu" value="{{ old('nama_ibu',$siswa->nama_ibu) }}" class="form-input">
                </div>
                <div>
                    <label class="form-label">No. HP Ibu</label>
                    <input type="text" name="no_telp_ibu" value="{{ old('no_telp_ibu',$siswa->no_telp_ibu) }}" class="form-input">
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Perubahan
            </button>
            <a href="{{ route('siswa.show', $siswa->uuid) }}" class="px-6 py-3 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800 transition">Batal</a>
        </div>
    </form>
</div>
@endsection
