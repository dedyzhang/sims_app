@extends('layouts.app')
@section('title','Survei Pembelajaran')
@section('content')
<div class="max-w-5xl space-y-5">
<h1 class="page-title">Survei Pembelajaran</h1>
<p class="text-sm text-slate-600 dark:text-slate-400">Buat survei untuk kelas pilihan. Hasil dipakai untuk perbaikan pembelajaran; identitas siswa tidak ditampilkan dalam rekap.</p>

<div class="card p-5 space-y-2 text-sm">
    <h2 class="font-bold text-slate-800 dark:text-slate-100">Cara pakai</h2>
    <ol class="list-decimal space-y-1 pl-5 text-slate-600 dark:text-slate-300">
        <li>Surveyor yang sudah ditunjuk: <strong>Waka Humas</strong> dan <strong>Waka Sarpras</strong>. Ubah akses akun guru itu di Data Guru. Peran lain bisa ditambah di <a class="text-blue-700 dark:text-blue-400 underline" href="{{ route('setting.roles') }}">Hak Akses Peran (RBAC)</a>.</li>
        <li>Buat periode di bawah. Guru mapel diambil dari jadwal, wali kelas dari data kelas.</li>
        <li>Butir soal dapat disesuaikan (ditambah, diubah, atau dihapus) sesuai kebutuhan sekolah.</li>
        <li>Surveyor membuka periode ini, mencentang kelas yang dimulai sekarang, lalu menekan Mulai sesi.</li>
        <li>Surveyor menampilkan QR dan membacakan token 6 digit. Siswa memindai, masuk dengan akun SIMS, memasukkan token, mengisi formulir, lalu mengirim. Surveyor menekan Siap bila kelas selesai.</li>
        <li>Tutup periode setelah jadwal selesai. Rekap terbuka bila minimal lima siswa mengisi. Nama pengisi hanya terlihat oleh admin pembuat periode.</li>
    </ol>
</div>

@if(session('success'))
    <div class="card p-4 border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 text-sm flex items-center justify-between">
        <span>{{ session('success') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="card p-4 border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 text-sm">
        {{ $errors->first() }}
    </div>
@endif

<form method="post" action="{{ route('survei.store') }}" class="card p-5 space-y-5">
    @csrf
    <h2 class="font-bold text-lg text-slate-800 dark:text-slate-100 border-b border-slate-100 dark:border-slate-800 pb-2">Buat Periode Survei</h2>
    
    <label class="block">
        <span class="font-semibold text-xs text-slate-700 dark:text-slate-300">Nama Periode</span>
        <input class="form-input mt-1" name="judul" value="{{ old('judul') }}" placeholder="Survei Pengalaman Belajar Siswa · Semester Ganjil 2026/2027" required maxlength="150">
    </label>

    <div class="grid md:grid-cols-2 gap-3">
        <label>
            <span class="font-semibold text-xs text-slate-700 dark:text-slate-300">Waktu Mulai</span>
            <input class="form-input mt-1" type="datetime-local" name="mulai" value="{{ old('mulai') }}" required>
        </label>
        <label>
            <span class="font-semibold text-xs text-slate-700 dark:text-slate-300">Waktu Berakhir</span>
            <input class="form-input mt-1" type="datetime-local" name="selesai" value="{{ old('selesai') }}" required>
        </label>
    </div>

    <fieldset class="border border-slate-200 dark:border-slate-800 rounded-xl p-4">
        <legend class="font-semibold text-xs px-2 text-slate-700 dark:text-slate-300">Pilih Kelas</legend>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 max-h-48 overflow-y-auto pr-1">
            @foreach($kelas as $k)
                <label class="flex items-center gap-2 text-xs p-1.5 rounded hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer">
                    <input type="checkbox" name="kelas[]" value="{{ $k->uuid }}" @checked(in_array($k->uuid, old('kelas', []))) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="font-medium">{{ $k->nama_lengkap }}</span>
                </label>
            @endforeach
        </div>
        <p class="text-[11px] text-slate-500 mt-2">Guru dan mata pelajaran diambil dari Jadwal Pelajaran setiap rombel yang dipilih. Wali kelas diambil dari data kelas.</p>
    </fieldset>

    {{-- Kelola Butir Soal Survei Dinamis --}}
    <div class="border border-blue-200 dark:border-blue-900/50 bg-blue-50/20 dark:bg-blue-950/10 rounded-xl p-4 space-y-4"
         x-data="{
             activeTab: 'mapel',
             mapelItems: @js(old('pertanyaan_mapel', $pertanyaanMapelDefault)),
             waliItems: @js(old('pertanyaan_wali', $pertanyaanWaliDefault)),
             karakterItems: @js(old('opsi_karakter', $opsiKarakterDefault)),
             dibalikMapel: @js(old('dibalik_mapel', $dibalikMapelDefault)),
             dibalikWali: @js(old('dibalik_wali', $dibalikWaliDefault)),
             tambahButir(tab) {
                 if (tab === 'mapel') {
                     this.mapelItems.push('');
                 } else if (tab === 'wali') {
                     this.waliItems.push('');
                 } else {
                     this.karakterItems.push('');
                 }
             },
             hapusButir(tab, index) {
                 if (tab === 'mapel') {
                     if (this.mapelItems.length <= 1) {
                         alert('Minimal harus ada 1 butir pertanyaan.');
                         return;
                     }
                     this.mapelItems.splice(index, 1);
                     this.dibalikMapel = this.dibalikMapel.filter(i => i !== index).map(i => i > index ? i - 1 : i);
                 } else if (tab === 'wali') {
                     if (this.waliItems.length <= 1) {
                         alert('Minimal harus ada 1 butir pertanyaan.');
                         return;
                     }
                     this.waliItems.splice(index, 1);
                     this.dibalikWali = this.dibalikWali.filter(i => i !== index).map(i => i > index ? i - 1 : i);
                 } else {
                     if (this.karakterItems.length <= 1) {
                         alert('Minimal harus ada 1 pilihan karakter.');
                         return;
                     }
                     this.karakterItems.splice(index, 1);
                 }
             },
             toggleDibalik(tab, index) {
                 if (tab === 'mapel') {
                     const idx = this.dibalikMapel.indexOf(index);
                     if (idx !== -1) {
                         this.dibalikMapel.splice(idx, 1);
                     } else {
                         this.dibalikMapel.push(index);
                     }
                 } else {
                     const idx = this.dibalikWali.indexOf(index);
                     if (idx !== -1) {
                         this.dibalikWali.splice(idx, 1);
                     } else {
                         this.dibalikWali.push(index);
                     }
                 }
             },
             isDibalik(tab, index) {
                 return tab === 'mapel' ? this.dibalikMapel.includes(index) : this.dibalikWali.includes(index);
             },
             resetDefault(tab) {
                 if (confirm('Kembalikan butir pertanyaan ke default standar aplikasi?')) {
                     if (tab === 'mapel') {
                         this.mapelItems = @js(\App\Survei\Pertanyaan::defaultHardcoded('mapel'));
                         this.dibalikMapel = @js(\App\Survei\Pertanyaan::defaultDibalik('mapel'));
                     } else if (tab === 'wali') {
                         this.waliItems = @js(\App\Survei\Pertanyaan::defaultHardcoded('wali'));
                         this.dibalikWali = @js(\App\Survei\Pertanyaan::defaultDibalik('wali'));
                     } else {
                         this.karakterItems = @js(\App\Survei\Pertanyaan::defaultKarakter());
                     }
                 }
             }
         }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-blue-100 dark:border-blue-900/40 pb-3">
            <div>
                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span>Butir Pertanyaan & Pilihan Karakter Survei</span>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">Bisa Diedit / Ditambah</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Pertanyaan dan opsi dropdown karakter dapat diubah kata-katanya, ditambah baru, atau dihapus sesuai kebutuhan evaluasi.</p>
            </div>
            <div class="inline-flex rounded-lg p-1 bg-slate-200/80 dark:bg-slate-800 self-start sm:self-auto flex-wrap gap-1">
                <button type="button" @click="activeTab = 'mapel'"
                    :class="activeTab === 'mapel' ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                    class="px-3 py-1 text-xs font-semibold rounded-md transition">
                    Guru Mapel (<span x-text="mapelItems.length"></span>)
                </button>
                <button type="button" @click="activeTab = 'wali'"
                    :class="activeTab === 'wali' ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                    class="px-3 py-1 text-xs font-semibold rounded-md transition">
                    Wali Kelas (<span x-text="waliItems.length"></span>)
                </button>
                <button type="button" @click="activeTab = 'karakter'"
                    :class="activeTab === 'karakter' ? 'bg-white dark:bg-slate-700 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
                    class="px-3 py-1 text-xs font-semibold rounded-md transition">
                    Dropdown Karakter (<span x-text="karakterItems.length"></span>)
                </button>
            </div>
        </div>

        {{-- Tab Guru Mapel --}}
        <div x-show="activeTab === 'mapel'" class="space-y-2.5">
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-600 dark:text-slate-300">Daftar Pertanyaan Evaluasi Guru Mapel</span>
                <button type="button" @click="resetDefault('mapel')" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                    ↺ Reset ke Standar Mapel
                </button>
            </div>
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                <template x-for="(item, index) in mapelItems" :key="'mapel-' + index">
                    <div class="flex items-start gap-2 bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-800 dark:text-blue-200 font-bold text-xs flex items-center justify-center shrink-0 mt-1" x-text="index + 1"></span>
                        <div class="flex-1 space-y-1">
                            <input type="text" :name="'pertanyaan_mapel[' + index + ']'" x-model="mapelItems[index]" required
                                class="form-input text-xs w-full rounded-md border-slate-300 dark:border-slate-700"
                                placeholder="Tuliskan pernyataan evaluasi...">
                            <div class="flex items-center gap-2 text-[11px] text-slate-500">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" :name="'dibalik_mapel[]'" :value="index"
                                        :checked="isDibalik('mapel', index)"
                                        @change="toggleDibalik('mapel', index)"
                                        class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <span :class="isDibalik('mapel', index) ? 'text-rose-600 font-medium' : ''">Pernyataan negatif (skor dibalik)</span>
                                </label>
                            </div>
                        </div>
                        <button type="button" @click="hapusButir('mapel', index)"
                            title="Hapus butir ini"
                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded transition shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="tambahButir('mapel')"
                class="w-full py-2 px-3 border border-dashed border-blue-300 dark:border-blue-800 text-blue-700 dark:text-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-950/30 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                + Tambah Butir Soal Guru Mapel
            </button>
        </div>

        {{-- Tab Wali Kelas --}}
        <div x-show="activeTab === 'wali'" class="space-y-2.5" x-cloak>
            <div class="flex items-center justify-between text-xs">
                <span class="font-semibold text-slate-600 dark:text-slate-300">Daftar Pertanyaan Evaluasi Wali Kelas</span>
                <button type="button" @click="resetDefault('wali')" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                    ↺ Reset ke Standar Wali
                </button>
            </div>
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                <template x-for="(item, index) in waliItems" :key="'wali-' + index">
                    <div class="flex items-start gap-2 bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 font-bold text-xs flex items-center justify-center shrink-0 mt-1" x-text="index + 1"></span>
                        <div class="flex-1 space-y-1">
                            <input type="text" :name="'pertanyaan_wali[' + index + ']'" x-model="waliItems[index]" required
                                class="form-input text-xs w-full rounded-md border-slate-300 dark:border-slate-700"
                                placeholder="Tuliskan pernyataan evaluasi...">
                            <div class="flex items-center gap-2 text-[11px] text-slate-500">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="checkbox" :name="'dibalik_wali[]'" :value="index"
                                        :checked="isDibalik('wali', index)"
                                        @change="toggleDibalik('wali', index)"
                                        class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                                    <span :class="isDibalik('wali', index) ? 'text-rose-600 font-medium' : ''">Pernyataan negatif (skor dibalik)</span>
                                </label>
                            </div>
                        </div>
                        <button type="button" @click="hapusButir('wali', index)"
                            title="Hapus butir ini"
                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded transition shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="tambahButir('wali')"
                class="w-full py-2 px-3 border border-dashed border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50/50 dark:hover:bg-emerald-950/30 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                + Tambah Butir Soal Wali Kelas
            </button>
        </div>

        {{-- Tab Pilihan Karakter Guru --}}
        <div x-show="activeTab === 'karakter'" class="space-y-2.5" x-cloak>
            <div class="flex items-center justify-between text-xs">
                <div>
                    <span class="font-semibold text-slate-700 dark:text-slate-300">Pilihan Dropdown: "Menurut kamu, guru ini termasuk guru yang ..."</span>
                    <p class="text-[11px] text-slate-500">Opsi ini akan muncul di halaman terakhir setiap guru mapel dan wali kelas. Siswa dapat memilih satu kesan/karakter dari daftar ini.</p>
                </div>
                <button type="button" @click="resetDefault('karakter')" class="text-blue-600 dark:text-blue-400 hover:underline text-xs shrink-0">
                    ↺ Reset ke Standar
                </button>
            </div>
            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                <template x-for="(item, index) in karakterItems" :key="'karakter-' + index">
                    <div class="flex items-center gap-2 bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 shadow-sm">
                        <span class="w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 font-bold text-xs flex items-center justify-center shrink-0" x-text="index + 1"></span>
                        <input type="text" :name="'opsi_karakter[' + index + ']'" x-model="karakterItems[index]" required
                            class="form-input text-xs flex-1 rounded-md border-slate-300 dark:border-slate-700"
                            placeholder="Contoh: Sangat sabar dan ramah...">
                        <button type="button" @click="hapusButir('karakter', index)"
                            title="Hapus pilihan ini"
                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded transition shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </template>
            </div>
            <button type="button" @click="tambahButir('karakter')"
                class="w-full py-2 px-3 border border-dashed border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-400 hover:bg-amber-50/50 dark:hover:bg-amber-950/30 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                + Tambah Pilihan Karakter
            </button>
        </div>

        <div class="pt-2 border-t border-blue-100 dark:border-blue-900/40">
            <label class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                <input type="checkbox" name="simpan_default" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span>Simpan susunan soal ini juga sebagai <strong>template default sekolah</strong> untuk periode-periode selanjutnya.</span>
            </label>
        </div>
    </div>

    <button class="px-5 py-2.5 rounded-lg bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm shadow transition" type="submit">
        Buat Periode Survei
    </button>
</form>

{{-- Daftar Periode dengan Tombol Hapus & Detail --}}
<div class="card p-5 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
        <h2 class="font-bold text-base text-slate-800 dark:text-slate-100">Daftar Periode Survei</h2>
        <span class="text-xs text-slate-500">{{ $periodes->count() }} periode tercatat</span>
    </div>
    
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($periodes as $p)
            <div class="py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="space-y-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a class="text-blue-700 dark:text-blue-400 font-bold hover:underline text-sm truncate" href="{{ route('survei.periode', $p) }}">
                            {{ $p->judul }}
                        </a>
                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold {{ $p->ditutup ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' : ($p->terbuka() ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300') }}">
                            {{ $p->ditutup ? 'Ditutup' : ($p->terbuka() ? 'Terbuka' : 'Belum terbuka / berakhir') }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ $p->mulai->format('d/m/Y H:i') }} – {{ $p->selesai->format('d/m/Y H:i') }} · 
                        <span>{{ count($p->butir('mapel')) }} butir mapel · {{ count($p->butir('wali')) }} butir wali</span>
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a href="{{ route('survei.analisis', $p) }}" 
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-sky-50 hover:bg-sky-100 dark:bg-sky-950/40 dark:hover:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800 transition">
                        Hasil & Analisis
                    </a>
                    <a href="{{ route('survei.periode', $p) }}" 
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                        Buka / Kelola
                    </a>
                    <form method="post" action="{{ route('survei.destroy', $p) }}" 
                          onsubmit="return confirm('Apakah Anda yakin ingin menghapus periode survei “{{ $p->judul }}”?\n\nSemua data sasaran, sesi, dan seluruh jawaban siswa yang telah masuk akan DIHAPUS PERMANEN.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 transition">
                            Hapus
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500 py-3">Belum ada periode survei yang dibuat.</p>
        @endforelse
    </div>
</div>

</div>
@endsection
