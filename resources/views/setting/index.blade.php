@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ tab: '{{ request('tab', 'fitur') }}' }">
    <div class="mb-6">
        <h1 class="page-title">Pengaturan Sistem</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Konfigurasi sekolah, semester, penilaian, dan fitur aktif</p>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 rounded-2xl p-1.5 mb-6 flex-wrap">
        @foreach(['fitur'=>['Fitur','toggle-left'],'sekolah'=>['Identitas','building-2'],'semester'=>['Semester','calendar-days'],'penilaian'=>['Penilaian','calculator'],'absensi'=>['Absensi','clock'],'disiplin'=>['Kedisiplinan','shield-alert'],'sosmed'=>['Media Sosial','share-2'],'integrasi'=>['Integrasi','plug'],'aplikasi'=>['Aplikasi','smartphone']] as $key => [$label,$icon])
        <button @click="tab='{{ $key }}'"
                :class="tab==='{{ $key }}' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary' : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="seg flex-1 min-w-fit flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-semibold transition">
            <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- Fitur On/Off --}}
    <div x-show="tab==='fitur'" x-transition>
        <form method="POST" action="{{ route('setting.fitur') }}" class="card p-6 space-y-5">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="toggle-left" class="w-4 h-4 text-primary"></i> Fitur Aktif</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Geser untuk matikan fitur yang tidak dipakai satuan pendidikan. Bisa dihidupkan kembali kapan saja. Fitur yang dimatikan hilang dari menu dan tidak bisa diakses.</p>
            </div>

            <div class="space-y-2.5">
                @foreach($modulFitur as $kode => $meta)
                @php $on = ($settings[\App\Support\ModulAktif::settingKey($kode)] ?? '1') === '1'; @endphp
                <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3"
                     x-data="{ on: {{ $on ? 'true' : 'false' }} }">
                    <div class="min-w-0 flex items-start gap-3">
                        <div class="mt-0.5 w-9 h-9 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 grid place-items-center flex-shrink-0">
                            <i data-lucide="{{ $meta['ikon'] }}" class="w-4 h-4 text-primary"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $meta['label'] }}</p>
                            <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">{{ $meta['deskripsi'] }}</p>
                            <p class="text-xs mt-1.5 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                        <input type="checkbox" name="{{ $kode }}" value="1" class="hidden peer" x-model="on">
                        <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                    </label>
                </div>
                @endforeach
            </div>

            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Fitur</button>
        </form>
    </div>

    {{-- Identitas --}}
    <div x-show="tab==='sekolah'" x-transition>
        <form method="POST" action="{{ route('setting.identitas') }}" enctype="multipart/form-data" class="card p-6 space-y-4">
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

            <div class="border-t border-slate-100 dark:border-slate-700 pt-4 mt-2">
                <label class="form-label font-bold text-slate-700 dark:text-slate-300">Logo / Ikon Sekolah</label>
                <p class="text-xs text-slate-400 mb-3">Pilih file gambar (PNG, JPG, JPEG, SVG) untuk mengganti logo di sidebar dan shortcut-icon tab browser.</p>
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                        @php
                            $logoPath = $settings['sekolah_logo'] ?? null;
                            $hasLogo = $logoPath && file_exists(storage_path('app/public/' . $logoPath));
                        @endphp
                        @if($hasLogo)
                            <img src="{{ asset('storage/' . $logoPath) }}" class="w-full h-full object-cover" id="logo-preview">
                        @else
                            <div class="w-10 h-10 rounded-xl grid place-items-center bg-gradient-to-br from-primary to-primary-700 text-white" id="logo-preview-placeholder">
                                <svg viewBox="0 0 24 24" fill="none" class="w-6 h-6 text-white" stroke="currentColor" stroke-width="2.2"><path d="M12 3L1 9l11 6 9-4.91V17M1 9v7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="flex-1">
                        <input type="file" name="sekolah_logo" accept="image/*" class="text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100 cursor-pointer">
                        @if($hasLogo)
                            <div class="mt-1.5">
                                <label class="inline-flex items-center gap-1.5 text-xs text-rose-600 cursor-pointer">
                                    <input type="checkbox" name="hapus_logo" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"> Hapus Logo (kembali ke default)
                                </label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Media Sosial --}}
    <div x-show="tab==='sosmed'" x-transition>
        <form method="POST" action="{{ route('setting.mediaSosial') }}" class="card p-6 space-y-5"
              x-data="{ master: {{ ($settings['sosmed_aktif'] ?? '1')=='1' ? 'true' : 'false' }} }">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="share-2" class="w-4 h-4 text-primary"></i> Media Sosial Sekolah</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Cukup tempel tautan — platform otomatis aktif dan ikonnya muncul di dashboard semua pengguna. Kosongkan tautan (atau matikan toggle) untuk menyembunyikan. <span class="font-semibold text-amber-600 dark:text-amber-400">Ikon tidak akan tampil bila tautan kosong.</span></p>
            </div>

            {{-- Master toggle: tampilkan di dashboard --}}
            <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tampilkan di Dashboard</p>
                    <p class="text-xs mt-1 font-semibold" :class="master ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="master ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="sosmed_aktif" value="1" class="hidden peer" x-model="master">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            {{-- Daftar platform --}}
            <div class="space-y-3" :class="master ? '' : 'opacity-50 pointer-events-none'">
                @foreach(config('sosmed') as $key => $meta)
                @php $urlVal = old('sosmed_'.$key.'_url', $settings['sosmed_'.$key.'_url'] ?? ''); @endphp
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2.5"
                     x-data="{ url: @js($urlVal), on: {{ ($settings['sosmed_'.$key.'_on'] ?? '0')=='1' ? 'true' : 'false' }} }">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-primary/10 text-primary flex-shrink-0">
                        @include('partials.sosmed-icon', ['key' => $key, 'cls' => 'w-4 h-4'])
                    </span>
                    <div class="flex-1 min-w-0">
                        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $meta['label'] }}</label>
                        <input type="text" name="sosmed_{{ $key }}_url" x-model="url"
                               @input="if (url.trim() !== '') on = true"
                               placeholder="{{ $meta['ph'] }}" class="form-input mt-0.5 py-1.5 text-sm">
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0" title="Tampilkan di dashboard">
                        <input type="checkbox" name="sosmed_{{ $key }}_on" value="1" class="hidden peer" x-model="on">
                        <div class="relative w-9 h-5 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition peer-checked:after:translate-x-4"></div>
                    </label>
                </div>
                @endforeach
            </div>

            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Unduh Aplikasi (APK Android + Installer Windows) --}}
    <div x-show="tab==='aplikasi'" x-transition>
        <form method="POST" action="{{ route('setting.appDownload') }}" enctype="multipart/form-data" class="card p-6 space-y-5"
              x-data="{ on: {{ ($settings['app_download_aktif'] ?? '0')=='1' ? 'true' : 'false' }} }">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="smartphone" class="w-4 h-4 text-primary"></i> Unduh Aplikasi</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Unggah aplikasi Android (.apk) dan/atau Installer Windows (.exe/.msi). Bila diaktifkan, menu <b>“Unduh Aplikasi”</b> muncul di sidebar untuk semua pengguna. File disimpan privat & hanya bisa diunduh pengguna yang login.</p>
            </div>

            {{-- Master toggle --}}
            <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Aktifkan Menu Unduh Aplikasi</p>
                    <p class="text-xs mt-1 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="app_download_aktif" value="1" class="hidden peer" x-model="on">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            @php
                $apkPath = $settings['app_apk_path'] ?? null;
                $apkOk   = $apkPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($apkPath);
                $winPath = $settings['app_windows_path'] ?? null;
                $winOk   = $winPath && \Illuminate\Support\Facades\Storage::disk('local')->exists($winPath);
                $fmt = fn ($b) => $b >= 1048576 ? round($b/1048576, 1).' MB' : round($b/1024).' KB';
            @endphp

            {{-- APK Android --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-emerald-500/10 text-emerald-600 flex-shrink-0"><i data-lucide="smartphone" class="w-4 h-4"></i></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Aplikasi Android (.apk)</p>
                        @if($apkOk)
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 truncate">✓ {{ $settings['app_apk_name'] ?? basename($apkPath) }} ({{ $fmt(\Illuminate\Support\Facades\Storage::disk('local')->size($apkPath)) }})</p>
                        @else
                            <p class="text-xs text-slate-400">Belum ada file diunggah.</p>
                        @endif
                    </div>
                </div>
                <input type="file" name="app_apk" accept=".apk" class="text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100 cursor-pointer">
                @error('app_apk')<p class="text-xs text-rose-500">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-40">
                        <label class="form-label text-xs">Label Versi (opsional)</label>
                        <input type="text" name="app_apk_version" value="{{ old('app_apk_version', $settings['app_apk_version'] ?? '') }}" placeholder="mis. v1.2.0" class="form-input py-1.5 text-sm">
                    </div>
                    @if($apkOk)
                    <label class="inline-flex items-center gap-1.5 text-xs text-rose-600 cursor-pointer mt-4">
                        <input type="checkbox" name="hapus_app_apk" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"> Hapus APK
                    </label>
                    @endif
                </div>
            </div>

            {{-- Installer Windows --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-sky-500/10 text-sky-600 flex-shrink-0"><i data-lucide="monitor" class="w-4 h-4"></i></span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Installer Windows (.exe / .msi)</p>
                        @if($winOk)
                            <p class="text-xs text-sky-600 dark:text-sky-400 truncate">✓ {{ $settings['app_windows_name'] ?? basename($winPath) }} ({{ $fmt(\Illuminate\Support\Facades\Storage::disk('local')->size($winPath)) }})</p>
                        @else
                            <p class="text-xs text-slate-400">Belum ada file diunggah.</p>
                        @endif
                    </div>
                </div>
                <input type="file" name="app_windows" accept=".exe,.msi" class="text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100 cursor-pointer">
                @error('app_windows')<p class="text-xs text-rose-500">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-40">
                        <label class="form-label text-xs">Label Versi (opsional)</label>
                        <input type="text" name="app_windows_version" value="{{ old('app_windows_version', $settings['app_windows_version'] ?? '') }}" placeholder="mis. v1.2.0" class="form-input py-1.5 text-sm">
                    </div>
                    @if($winOk)
                    <label class="inline-flex items-center gap-1.5 text-xs text-rose-600 cursor-pointer mt-4">
                        <input type="checkbox" name="hapus_app_windows" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"> Hapus Installer
                    </label>
                    @endif
                </div>
            </div>

            <p class="text-xs text-amber-600 dark:text-amber-400 leading-relaxed"><i data-lucide="triangle-alert" class="w-3.5 h-3.5 inline -mt-0.5"></i> Batas unggah server saat ini <b>{{ ini_get('upload_max_filesize') }}</b>. Bila APK/installer lebih besar, minta admin server menaikkan <code>upload_max_filesize</code> &amp; <code>post_max_size</code> di php.ini.</p>

            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>
    </div>

    {{-- Integrasi Nalar Guru --}}
    <div x-show="tab==='integrasi'" x-transition>
        <form method="POST" action="{{ route('setting.integrasi') }}" class="card p-6 space-y-5"
              x-data="{
                  on: {{ ($settings['tp_launcher_aktif'] ?? '1')=='1' ? 'true' : 'false' }},
                  canvaOn: {{ ($settings['canva_connect_aktif'] ?? '1')=='1' ? 'true' : 'false' }}
              }">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="plug" class="w-4 h-4 text-primary"></i> Integrasi Asisten Guru</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Launcher Nalar Guru dan Canva Pendidikan (gratis via belajar.id).</p>
            </div>

            <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tampilkan launcher di Asisten Guru</p>
                    <p class="text-xs text-slate-400 mt-0.5">Tombol cepat ke Nalar Guru di Asisten Guru.</p>
                    <p class="text-xs mt-1.5 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="tp_launcher_aktif" value="1" class="hidden peer" x-model="on">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <div class="flex items-start justify-between gap-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Canva Connect (belajar.id)</p>
                    <p class="text-xs text-slate-400 mt-0.5">Izinkan guru menghubungkan Canva Pendidikan gratis. Hanya email belajar.id.</p>
                    <p class="text-xs mt-1.5 font-semibold" :class="canvaOn ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="canvaOn ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="canva_connect_aktif" value="1" class="sr-only peer" x-model="canvaOn">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>

            <div>
                <label class="form-label">Domain email Canva yang diizinkan</label>
                <input type="text" name="canva_allowed_email_suffix" value="{{ $settings['canva_allowed_email_suffix'] ?? '.belajar.id' }}"
                       class="form-input font-mono text-sm" placeholder=".belajar.id">
                <p class="text-[11px] text-slate-400 mt-1">Wajib berakhiran <code>.belajar.id</code>. Contoh: <code>.belajar.id</code> atau <code>@smpn1.belajar.id</code>. Domain lain ditolak.</p>
            </div>

            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Integrasi
            </button>
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
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Rumus Perhitungan Nilai Rapor</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Pilih metode perhitungan nilai rapor akhir siswa secara universal di satu tempat saja.</p>
            </div>
            <div class="space-y-1.5">
                <label class="form-label">Metode Perhitungan Rapor</label>
                <select name="rumus_rapor" class="form-select">
                    @foreach(\App\Support\Penilaian::RUMUS as $key => $label)
                        <option value="{{ $key }}" @selected(($settings['rumus_rapor'] ?? 'bagi4') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Rumus Rapor</button>
        </form>

        {{-- Wali kelas boleh lihat nilai mapel lain di kelasnya --}}
        <form method="POST" action="{{ route('setting.walikelasLihatNilai') }}" class="card p-6"
              x-data="{ on: {{ ($settings['walikelas_lihat_nilai'] ?? '0')=='1' ? 'true' : 'false' }} }">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4 text-sky-500"></i> Wali Kelas Lihat Nilai Mapel Lain</h2>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">Jika aktif, wali kelas bisa <b>melihat (tanpa mengubah)</b> nilai Formatif, Sumatif, dan PAS semua mata pelajaran di kelasnya — bukan hanya mapel yang ia ajar sendiri.</p>
                    <p class="text-xs mt-2 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="walikelas_lihat_nilai" value="1" class="hidden peer" x-model="on" @change="$el.form.submit()">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>
        </form>

        <a href="{{ route('setting.penjabaran') }}" class="card p-6 flex items-center justify-between gap-3 hover:border-primary transition">
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="list-tree" class="w-[18px] h-[18px] text-primary"></i> Nilai Penjabaran</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Atur mata pelajaran yang punya nilai penjabaran &amp; komponen nilainya (mis. B. Inggris: Listening, Speaking, Reading, Writing).</p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
        </a>

        <a href="{{ route('setting.kopRapor') }}" class="card p-6 flex items-center justify-between gap-3 hover:border-primary transition">
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="stamp" class="w-[18px] h-[18px] text-primary"></i> Kop Surat Rapor</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Upload logo (kiri &amp; kanan), ubah teks kepala surat, dan ganti gambar latar (backdrop) pada cetak rapor.</p>
            </div>
            <i data-lucide="chevron-right" class="w-5 h-5 text-slate-400 flex-shrink-0"></i>
        </a>


        <form method="POST" action="{{ route('setting.tpRange') }}" class="card p-6 space-y-4">
            @csrf
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="list-checks" class="w-[18px] h-[18px] text-primary"></i> Batas Tujuan Pembelajaran per Materi</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tentukan jumlah minimal &amp; maksimal TP yang boleh ditambahkan guru di tiap materi. Isi <b>0</b> untuk tanpa batas.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div class="space-y-1.5">
                    <label class="form-label">Minimal TP</label>
                    <input type="number" name="tp_min" min="0" max="50" value="{{ $settings['tp_min'] ?? 0 }}" class="form-input w-28">
                </div>
                <div class="space-y-1.5">
                    <label class="form-label">Maksimal TP</label>
                    <input type="number" name="tp_max" min="0" max="50" value="{{ $settings['tp_max'] ?? 0 }}" class="form-input w-28">
                </div>
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Batas TP</button>
        </form>
    </div>

    {{-- Absensi --}}
    <div x-show="tab==='absensi'" x-transition class="space-y-4">
        <form method="POST" action="{{ route('setting.caraAbsensi') }}" class="card p-6 space-y-4">
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Cara Absensi (Guru &amp; Siswa)</h2>
            <p class="text-xs text-slate-400 -mt-1">Pilih metode absen mandiri. Metode yang tidak dipilih <span class="font-semibold">dikunci</span> untuk guru &amp; siswa. Admin tetap bisa <span class="font-semibold">mengoreksi manual</span> kapan saja.</p>
            @php $caraNow = in_array($settings['cara_absensi_guru'] ?? 'wajah', ['wajah','barcode']) ? ($settings['cara_absensi_guru'] ?? 'wajah') : 'wajah'; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach(['wajah'=>['Scan Wajah','Pengenalan wajah (kiosk)','scan-face'],'barcode'=>['Barcode / QR','Scan QR Code','qr-code']] as $val => [$lbl,$desc,$icon])
                <label class="cursor-pointer">
                    <input type="radio" name="cara_absensi" value="{{ $val }}" @checked($caraNow===$val) class="hidden peer">
                    <div class="border-2 rounded-xl p-4 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600 h-full">
                        <i data-lucide="{{ $icon }}" class="w-5 h-5 text-slate-400 peer-checked:text-primary mb-1.5"></i>
                        <p class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ $lbl }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $desc }}</p>
                    </div>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </form>

        {{-- Link Kiosk Absensi --}}
        <div class="card p-6 space-y-3" x-data="{ copied:false, url:'{{ $settings['kiosk_token'] ?? '' ? url('/kiosk-absensi/'.$settings['kiosk_token']) : '' }}',
            copy(){ navigator.clipboard.writeText(this.url); this.copied=true; setTimeout(()=>this.copied=false,1800); } }">
            <div>
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="monitor-smartphone" class="w-4 h-4 text-primary"></i> Link Kiosk Absensi</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Link rahasia tanpa perlu login — buka langsung Scan Wajah/QR sesuai metode aktif di atas. Jadikan shortcut di komputer meja piket supaya guru bisa langsung absen sendiri.</p>
            </div>
            @if($settings['kiosk_token'] ?? null)
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" readonly :value="url" onclick="this.select()" class="form-input flex-1 min-w-64 font-mono text-xs">
                <button type="button" @click="copy()" class="flex items-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i data-lucide="copy" class="w-4 h-4" x-show="!copied"></i>
                    <i data-lucide="check" class="w-4 h-4 text-emerald-500" x-show="copied" x-cloak></i>
                    <span x-text="copied ? 'Tersalin' : 'Salin Link'"></span>
                </button>
            </div>
            @endif
            <form method="POST" action="{{ route('setting.kioskToken.regenerate') }}" onsubmit="return confirmAction(this, '{{ ($settings['kiosk_token'] ?? null) ? 'Buat ulang link kiosk? Link lama tidak akan berlaku lagi.' : 'Buat link kiosk absensi?' }}', 'orange')">
                @csrf
                <button class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold border border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:hover:bg-amber-900/30">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> {{ ($settings['kiosk_token'] ?? null) ? 'Buat Ulang Link' : 'Buat Link Kiosk' }}
                </button>
            </form>
        </div>

        {{-- Wajib isi agenda sebelum absen pulang --}}
        <form method="POST" action="{{ route('setting.agendaWajibPulang') }}" class="card p-6"
              x-data="{ on: {{ ($settings['agenda_wajib_pulang'] ?? '1')=='1' ? 'true' : 'false' }} }">
            @csrf
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="clipboard-pen-line" class="w-4 h-4 text-amber-500"></i> Wajib Isi Agenda Sebelum Pulang</h2>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">Jika aktif, guru tidak dapat absen pulang (scan wajah maupun QR) sebelum seluruh agenda mengajar hari itu diisi.</p>
                    <p class="text-xs mt-2 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                    <input type="checkbox" name="agenda_wajib_pulang" value="1" class="hidden peer" x-model="on" @change="$el.form.submit()">
                    <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                </label>
            </div>
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
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Batas Jam Terlambat</h2>
                <p class="text-xs text-slate-400 -mt-1">Hadir setelah jam ini dihitung <span class="font-semibold text-rose-500">Terlambat</span>.</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Siswa</label>
                        <input type="time" name="waktu_terlambat" value="{{ $settings['waktu_terlambat'] ?? '07:30' }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Guru</label>
                        <input type="time" name="waktu_terlambat_guru" value="{{ $settings['waktu_terlambat_guru'] ?? ($settings['waktu_terlambat'] ?? '07:30') }}" class="form-input">
                    </div>
                </div>
                <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-bold w-full">Simpan</button>
            </form>
        </div>

        <div class="card p-6" x-data="qrLokasi({ lat:@js($settings['sekolah_lat'] ?? ''), lng:@js($settings['sekolah_lng'] ?? '') })" x-init="init()">
            <div class="flex items-center gap-3 mb-5 pb-5 border-b border-slate-100 dark:border-slate-700">
                <div class="w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary shrink-0"><span x-ignore><i data-lucide="map-pin" class="w-5 h-5"></i></span></div>
                <div>
                    <h2 class="font-bold text-slate-800 dark:text-slate-100">Lokasi & Absen QR</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Tentukan titik sekolah (lintang/bujur) & jarak maksimal absen siswa/guru.</p>
                </div>
                <div class="flex items-center gap-3 ml-auto">
                    <a href="{{ route('qr.absensi') }}" class="text-xs text-primary font-semibold flex items-center gap-1"><span x-ignore><i data-lucide="qr-code" class="w-3.5 h-3.5"></i></span> Lihat QR</a>
                    <a href="{{ route('qr.cetak') }}" target="_blank" class="text-xs text-primary font-semibold flex items-center gap-1"><span x-ignore><i data-lucide="printer" class="w-3.5 h-3.5"></i></span> Cetak QR</a>
                </div>
            </div>
            <form method="POST" action="{{ route('setting.lokasiQr') }}" class="space-y-4">
                @csrf
                <div id="setMap" x-ignore style="height:240px" class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 z-0"></div>
                <p class="text-xs text-slate-400">Klik di peta untuk menetapkan titik sekolah, atau gunakan tombol lokasi.</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <div>
                        <label class="form-label">Latitude</label>
                        <input type="text" name="sekolah_lat" x-model="lat" class="form-input font-mono" placeholder="-0.917">
                    </div>
                    <div>
                        <label class="form-label">Longitude</label>
                        <input type="text" name="sekolah_lng" x-model="lng" class="form-input font-mono" placeholder="104.46">
                    </div>
                    <div>
                        <label class="form-label">Radius (meter)</label>
                        <input type="number" name="absen_radius" value="{{ $settings['absen_radius'] ?? 100 }}" min="10" max="5000" class="form-input">
                    </div>
                </div>

                {{-- Mode QR: harian (otomatis berganti) atau tetap (satu QR permanen, cocok utk dicetak & ditempel) --}}
                <div>
                    <label class="form-label">Mode QR Absensi</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="qr_absensi_mode" value="harian" @checked(($settings['qr_absensi_mode'] ?? 'harian') == 'harian') class="hidden peer">
                            <div class="border-2 rounded-xl p-3.5 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600 h-full">
                                <p class="font-bold text-sm text-slate-700 dark:text-slate-200 flex items-center gap-1.5"><span x-ignore><i data-lucide="refresh-cw" class="w-4 h-4 text-slate-400"></i></span> Ganti Setiap Hari</p>
                                <p class="text-xs text-slate-400 mt-1">QR otomatis berubah tiap hari — lebih aman dari QR lama yang difoto/disebarluaskan. Cetak/tampilkan ulang tiap pagi.</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="qr_absensi_mode" value="tetap" @checked(($settings['qr_absensi_mode'] ?? 'harian') == 'tetap') class="hidden peer">
                            <div class="border-2 rounded-xl p-3.5 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600 h-full">
                                <p class="font-bold text-sm text-slate-700 dark:text-slate-200 flex items-center gap-1.5"><span x-ignore><i data-lucide="pin" class="w-4 h-4 text-slate-400"></i></span> Satu QR Tetap</p>
                                <p class="text-xs text-slate-400 mt-1">QR sama setiap hari — cetak sekali, tempel permanen. Buat ulang manual kalau dicurigai bocor.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between flex-wrap gap-3">
                    <button type="button" @click="useMyLocation()" class="text-sm font-semibold text-primary flex items-center gap-1.5"><span x-ignore><i data-lucide="locate-fixed" class="w-4 h-4"></i></span> Gunakan lokasi saya sekarang</button>
                    <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
                        <input type="checkbox" name="qr_absensi_aktif" value="1" @checked(($settings['qr_absensi_aktif'] ?? '1')=='1') class="accent-[color:var(--cp)] w-4 h-4"> Aktifkan Absen QR
                    </label>
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><span x-ignore><i data-lucide="save" class="w-4 h-4"></i></span> Simpan Lokasi</button>
            </form>

            @if(($settings['qr_absensi_mode'] ?? 'harian') === 'tetap')
            <form method="POST" action="{{ route('setting.qrTokenTetap.regenerate') }}" onsubmit="return confirmAction(this, 'Buat ulang token QR tetap? QR lama yang sudah ditempel tidak akan berlaku lagi — Anda perlu mencetak & menempel QR baru.', 'orange')" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
                @csrf
                <button class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold border border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-700 dark:hover:bg-amber-900/30">
                    <span x-ignore><i data-lucide="refresh-cw" class="w-4 h-4"></i></span> Buat Ulang QR Tetap
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Kedisiplinan: sistem poin & aturan --}}
    <div x-show="tab==='disiplin'" x-transition class="space-y-4" x-data="{ jenisAturan: @js($settings['jenis_aturan'] ?? 'p3') }">
        <form method="POST" action="{{ route('setting.jenisAturan') }}" class="card p-6 space-y-4">
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="shield-alert" class="w-[18px] h-[18px] text-primary"></i> Sistem Aturan Kedisiplinan</h2>
            <p class="text-xs text-slate-400 -mt-2">Pilih satu sistem pencatatan kedisiplinan siswa yang aktif dipakai sekolah.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="jenis_aturan" value="p3" x-model="jenisAturan" class="hidden peer">
                    <div class="border-2 rounded-xl p-4 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600 h-full">
                        <i data-lucide="award" class="w-5 h-5 text-slate-400 peer-checked:text-primary mb-1.5"></i>
                        <p class="font-bold text-sm text-slate-700 dark:text-slate-200">P3 (Rekomendasi)</p>
                        <p class="text-xs text-slate-400 mt-0.5">Pelanggaran, Prestasi &amp; Partisipasi — tiga kategori akumulatif per semester, ada cetak laporan.</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="jenis_aturan" value="poin" x-model="jenisAturan" class="hidden peer">
                    <div class="border-2 rounded-xl p-4 transition peer-checked:border-primary peer-checked:bg-primary-50 border-slate-200 dark:border-slate-600 h-full">
                        <i data-lucide="gauge" class="w-5 h-5 text-slate-400 peer-checked:text-primary mb-1.5"></i>
                        <p class="font-bold text-sm text-slate-700 dark:text-slate-200">Poin/Aturan</p>
                        <p class="text-xs text-slate-400 mt-0.5">Ledger poin dari basis 100 — berkurang tiap pelanggaran, ada label Peringatan 1/2/3 otomatis.</p>
                    </div>
                </label>
            </div>
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Sistem</button>
        </form>

        <form method="POST" action="{{ route('setting.poinTerlambatAturan') }}" class="card p-6 space-y-3" x-show="jenisAturan==='poin'" x-cloak x-transition>
            @csrf
            <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="alarm-clock-minus" class="w-[18px] h-[18px] text-amber-500"></i> Aturan Poin Keterlambatan</h2>
            <p class="text-xs text-slate-400 -mt-1">Saat siswa absen melewati batas jam terlambat, aturan ini otomatis tercatat sebagai poin dikurangi (khusus sistem Poin/Aturan).</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-48">
                    <label class="form-label">Aturan</label>
                    <select name="poin_terlambat_aturan" class="form-select">
                        <option value="">— Tidak diaktifkan —</option>
                        @foreach($aturans->where('jenis', 'kurang') as $a)
                        <option value="{{ $a->uuid }}" @selected(($settings['poin_terlambat_aturan'] ?? '')===$a->uuid)>{{ $a->kode }} — {{ \Illuminate\Support\Str::limit($a->aturan, 40) }} ({{ $a->poin }} poin)</option>
                        @endforeach
                    </select>
                    @if($aturans->where('jenis', 'kurang')->isEmpty())
                    <p class="text-xs text-amber-500 mt-1">Belum ada aturan berjenis "Kurang". Tambahkan dulu di menu Poin/Aturan.</p>
                    @endif
                </div>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

@push('styles')<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function qrLokasi(cfg){
    return {
        lat: cfg.lat || '', lng: cfg.lng || '', map:null, marker:null,
        init(){
            const has = this.lat && this.lng;
            const start = has ? [parseFloat(this.lat), parseFloat(this.lng)] : [-0.9177, 104.4602]; // default Tanjungpinang
            this.$nextTick(()=>{
                if(!document.getElementById('setMap')) return;
                this.map = L.map('setMap').setView(start, has?16:12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19, attribution:'&copy; OpenStreetMap' }).addTo(this.map);
                if(has) this.place(start[0], start[1], false);
                this.map.on('click', e=> this.place(e.latlng.lat, e.latlng.lng));
                // refresh ukuran saat container mendapat lebar penuh (anti peta cuma sebagian)
                try { new ResizeObserver(()=> this.map && this.map.invalidateSize()).observe(document.getElementById('setMap')); } catch(e){}
                [100,400,900,1500].forEach(t=> setTimeout(()=> this.map && this.map.invalidateSize(), t));
            });
        },
        place(la, ln, recenter=true){
            this.lat = (+la).toFixed(6); this.lng = (+ln).toFixed(6);
            if(this.marker) this.map.removeLayer(this.marker);
            this.marker = L.marker([la,ln]).addTo(this.map);
            if(recenter) this.map.setView([la,ln], 16);
        },
        useMyLocation(){
            if(!navigator.geolocation){ showToast('Perangkat ini tidak mendukung deteksi lokasi. Coba buka lewat HP atau browser lain.','error'); return; }
            // Konteks tidak aman (http) = penyebab umum "izin ditolak" walau izin sudah aktif.
            if(typeof window.isSecureContext !== 'undefined' && !window.isSecureContext){
                showToast('Lokasi hanya bisa dibaca lewat alamat aman (https://). Buka halaman ini memakai https, bukan http.','error'); return;
            }
            navigator.geolocation.getCurrentPosition(p=> this.place(p.coords.latitude, p.coords.longitude),
                err=>{
                    const msg = err && err.code===1 ? 'Izin lokasi ditolak. Klik ikon gembok di address bar browser, aktifkan Lokasi, lalu coba lagi.'
                        : err && err.code===2 ? 'Lokasi belum ditemukan. Pastikan GPS/Layanan Lokasi menyala, lalu coba lagi.'
                        : err && err.code===3 ? 'Membaca lokasi terlalu lama. Coba lagi di tempat yang lebih terbuka.'
                        : 'Lokasi gagal dibaca. Silakan coba lagi.';
                    showToast(msg,'error');
                }, { enableHighAccuracy:true });
        }
    }
}
</script>
@endpush
@endsection
