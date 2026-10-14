@extends('layouts.app')
@section('title', 'Kop Surat & Tanda Tangan Rapor')

@php
    $img = function ($key, $default = null) use ($settings) {
        $v = $settings[$key] ?? null;
        if ($v && file_exists(storage_path('app/public/' . $v))) return asset('storage/' . $v);
        if ($default && file_exists(public_path($default))) return asset($default);
        return null;
    };
    $logoKiri = $img('kop_logo_kiri', 'img/tutwuri.png');
    $logoKanan = $img('kop_logo_kanan', 'img/maitreyawira_square.png');
    $backdrop = $img('kop_backdrop', 'img/logo.png');

    $ttdKepala = $img('ttd_kepala_sekolah', null)
        ?: ($kepalaGuru?->tanda_tangan && \Illuminate\Support\Facades\Storage::disk('public')->exists($kepalaGuru->tanda_tangan) ? \Illuminate\Support\Facades\Storage::disk('public')->url($kepalaGuru->tanda_tangan) : null);
    $capSekolah = $img('cap_sekolah', null);
    $ttdWalikelasDefault = $img('ttd_walikelas', null);
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('setting.index') }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">Kop Surat, Judul &amp; Tanda Tangan Rapor</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Atur logo, teks kepala surat, gambar latar (backdrop), judul laporan, serta tanda tangan &amp; cap sekolah untuk distribusi online rapor.</p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4"></i> {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('setting.kopRapor.save') }}" enctype="multipart/form-data" class="card p-6 space-y-6">
        @csrf

        {{-- Logo --}}
        <div>
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="image" class="w-[18px] h-[18px] text-primary"></i> Logo Kop</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                @foreach([['kop_logo_kiri','Logo Kiri',$logoKiri],['kop_logo_kanan','Logo Kanan',$logoKanan]] as [$field,$label,$cur])
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4">
                    <label class="form-label">{{ $label }}</label>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-16 h-16 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/40 flex items-center justify-center overflow-hidden">
                            @if($cur)<img src="{{ $cur }}" class="max-w-full max-h-full object-contain">@else<i data-lucide="image-off" class="w-5 h-5 text-slate-300"></i>@endif
                        </div>
                        <span class="text-xs text-slate-400">PNG/JPG/WEBP, maks 2MB</span>
                    </div>
                    <input type="file" name="{{ $field }}" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    @if(($settings[$field] ?? null))
                    <label class="flex items-center gap-2 mt-2 text-xs text-rose-500"><input type="checkbox" name="hapus_{{ $field }}" value="1" class="rounded"> Hapus logo ini (kembali ke default)</label>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Backdrop --}}
        <div>
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="layers" class="w-[18px] h-[18px] text-primary"></i> Backdrop (Gambar Latar)</h2>
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-24 h-24 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/40 flex items-center justify-center overflow-hidden">
                        @if($backdrop)<img src="{{ $backdrop }}" class="max-w-full max-h-full object-contain opacity-50">@else<i data-lucide="image-off" class="w-5 h-5 text-slate-300"></i>@endif
                    </div>
                    <span class="text-xs text-slate-400">Tampil samar di belakang tiap halaman. PNG transparan disarankan.</span>
                </div>
                <input type="file" name="kop_backdrop" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                @if(($settings['kop_backdrop'] ?? null))
                <label class="flex items-center gap-2 mt-2 text-xs text-rose-500"><input type="checkbox" name="hapus_kop_backdrop" value="1" class="rounded"> Hapus backdrop (kembali ke default)</label>
                @endif
            </div>
        </div>

        {{-- Teks kop --}}
        <div>
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-1 flex items-center gap-2"><i data-lucide="type" class="w-[18px] h-[18px] text-primary"></i> Teks Kepala Surat</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">Tulis nama sekolah &amp; alamat (bahasa apa saja). Gunakan toolbar untuk mengatur ukuran, tebal, warna, dan perataan. Kosongkan untuk memakai Identitas Sekolah otomatis.</p>
            <textarea id="kop_teks" name="kop_teks">{{ $settings['kop_teks'] ?? '' }}</textarea>
        </div>

        {{-- Judul Rapor --}}
        <div>
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-1 flex items-center gap-2"><i data-lucide="heading" class="w-[18px] h-[18px] text-primary"></i> Judul Rapor</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">Judul laporan yang dicetak di bawah kop surat pada lembar rapor semua siswa. Cukup ubah sekali di sini, seluruh rapor siswa di semua kelas otomatis berubah. Kosongkan untuk memakai teks bawaan.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Judul Utama</label>
                    <input type="text" name="judul_rapor" value="{{ old('judul_rapor', $settings['judul_rapor'] ?? '') }}" placeholder="PENCAPAIAN KOMPETENSI PESERTA DIDIK" class="form-input">
                    <p class="text-xs text-slate-400 mt-1">Default: <code>PENCAPAIAN KOMPETENSI PESERTA DIDIK</code></p>
                </div>
                <div>
                    <label class="form-label">Subjudul Penjabaran Nilai</label>
                    <input type="text" name="subjudul_penjabaran" value="{{ old('subjudul_penjabaran', $settings['subjudul_penjabaran'] ?? '') }}" placeholder="PENJABARAN NILAI" class="form-input">
                    <p class="text-xs text-slate-400 mt-1">Default: <code>PENJABARAN NILAI</code></p>
                </div>
            </div>
        </div>

        {{-- Tanda Tangan & Cap Rapor (Distribusi Online) --}}
        <div class="border-t border-slate-100 dark:border-slate-700 pt-6">
            <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-1 flex items-center gap-2"><i data-lucide="pen-tool" class="w-[18px] h-[18px] text-primary"></i> Tanda Tangan &amp; Cap Rapor (Distribusi Online)</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Unggah file gambar tanda tangan Kepala Sekolah, Cap/Stempel Sekolah, dan tanda tangan Wali Kelas untuk ditampilkan pada lembar rapor saat didistribusikan online atau dicetak ke PDF. Gunakan gambar berlatar transparan (format PNG/WEBP disarankan, maks 2MB).</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                {{-- TTD Kepala Sekolah --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <label class="form-label font-semibold mb-0">TTD Kepala Sekolah</label>
                                <p class="text-xs text-slate-400 mt-0.5 truncate max-w-[170px]" title="{{ $kepalaGuru?->nama ?: ($settings['kepala_sekolah'] ?? 'Belum diatur') }}">
                                    {{ $kepalaGuru?->nama ?: ($settings['kepala_sekolah'] ?? 'Belum diatur') }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Kepsek</span>
                        </div>

                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-24 h-16 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/40 flex items-center justify-center overflow-hidden p-1">
                                @if($ttdKepala)
                                    <img src="{{ $ttdKepala }}" class="max-w-full max-h-full object-contain" alt="TTD Kepala Sekolah">
                                @else
                                    <span class="text-xs text-slate-400 italic">Belum ada</span>
                                @endif
                            </div>
                            <span class="text-[11px] text-slate-400">PNG/JPG/WEBP, maks 2MB</span>
                        </div>

                        <input type="file" name="ttd_kepala_sekolah" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">

                        @if(!empty($settings['ttd_kepala_sekolah']) || !empty($kepalaGuru?->tanda_tangan))
                        <label class="flex items-center gap-2 mt-2 text-xs text-rose-500">
                            <input type="checkbox" name="hapus_ttd_kepala_sekolah" value="1" class="rounded"> Hapus tanda tangan
                        </label>
                        @endif
                    </div>
                </div>

                {{-- Cap / Stempel Sekolah --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <label class="form-label font-semibold mb-0">Cap / Stempel Sekolah</label>
                                <p class="text-xs text-slate-400 mt-0.5">Membubuhi TTD Kepala Sekolah</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-purple-50 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">Stempel</span>
                        </div>

                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-24 h-24 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/40 flex items-center justify-center overflow-hidden p-2">
                                @if($capSekolah)
                                    <img src="{{ $capSekolah }}" class="max-w-full max-h-full object-contain" alt="Cap Sekolah">
                                @else
                                    <span class="text-xs text-slate-400 italic">Belum ada</span>
                                @endif
                            </div>
                            <span class="text-[11px] text-slate-400">PNG transparan disarankan</span>
                        </div>

                        <input type="file" name="cap_sekolah" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">

                        @if(!empty($settings['cap_sekolah']))
                        <label class="flex items-center gap-2 mt-2 text-xs text-rose-500">
                            <input type="checkbox" name="hapus_cap_sekolah" value="1" class="rounded"> Hapus cap sekolah
                        </label>
                        @endif
                    </div>
                </div>

                {{-- TTD Walikelas Default / Bawaan --}}
                <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <label class="form-label font-semibold mb-0">TTD Wali Kelas (Bawaan)</label>
                                <p class="text-xs text-slate-400 mt-0.5">Default jika kelas belum isi khusus</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">Bawaan</span>
                        </div>

                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-24 h-16 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/40 flex items-center justify-center overflow-hidden p-1">
                                @if($ttdWalikelasDefault)
                                    <img src="{{ $ttdWalikelasDefault }}" class="max-w-full max-h-full object-contain" alt="TTD Walikelas Default">
                                @else
                                    <span class="text-xs text-slate-400 italic">Belum ada</span>
                                @endif
                            </div>
                            <span class="text-[11px] text-slate-400">PNG/JPG/WEBP, maks 2MB</span>
                        </div>

                        <input type="file" name="ttd_walikelas" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">

                        @if(!empty($settings['ttd_walikelas']))
                        <label class="flex items-center gap-2 mt-2 text-xs text-rose-500">
                            <input type="checkbox" name="hapus_ttd_walikelas" value="1" class="rounded"> Hapus tanda tangan bawaan
                        </label>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Pratinjau TTD & Cap Kepala Sekolah --}}
            @if($ttdKepala || $capSekolah)
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 block">Pratinjau TTD &amp; Cap Kepala Sekolah di Rapor:</span>
                    <span class="text-[11px] text-slate-400">Cap sekolah berukuran proporsional resmi dan menimpa bagian kiri tanda tangan kepala sekolah.</span>
                </div>
                <div class="h-28 px-8 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center relative overflow-hidden">
                    <div class="relative inline-flex items-center justify-center h-full min-w-[210px]">
                        @if($ttdKepala)
                            <img src="{{ $ttdKepala }}" class="relative z-[1] {{ $capSekolah ? 'ml-7' : '' }} max-h-16 max-w-[140px] object-contain" alt="TTD">
                        @endif
                        @if($capSekolah)
                            <img src="{{ $capSekolah }}" class="{{ $ttdKepala ? 'absolute left-[calc(50%-85px)] top-1/2 -translate-y-1/2' : 'relative' }} h-[100px] w-[100px] max-w-[100px] object-contain opacity-95 z-[2] mix-blend-multiply pointer-events-none" alt="Cap">
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- TTD Khusus per Wali Kelas / Kelas --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
                <div>
                    <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-1.5"><i data-lucide="users" class="w-4 h-4 text-primary"></i> Tanda Tangan Khusus per Kelas / Wali Kelas</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Unggah tanda tangan khusus per wali kelas masing-masing. Jika diisi, rapor kelas tersebut otomatis memakai tanda tangan khusus ini.</p>
                </div>

                @if(isset($kelasList) && $kelasList->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                                <th class="p-2.5 font-semibold">Kelas</th>
                                <th class="p-2.5 font-semibold">Wali Kelas</th>
                                <th class="p-2.5 font-semibold text-center w-28">Status TTD</th>
                                <th class="p-2.5 font-semibold">Unggah / Ganti Tanda Tangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($kelasList as $k)
                            @php
                                $wkGuru = $k->walikelas?->guru;
                                $ttdKhususPath = $settings['ttd_walikelas_' . $k->uuid] ?? null;
                                $ttdKhusus = null;
                                if ($wkGuru?->tanda_tangan && file_exists(storage_path('app/public/' . $wkGuru->tanda_tangan))) {
                                    $ttdKhusus = asset('storage/' . $wkGuru->tanda_tangan);
                                } elseif ($ttdKhususPath && file_exists(storage_path('app/public/' . $ttdKhususPath))) {
                                    $ttdKhusus = asset('storage/' . $ttdKhususPath);
                                }
                            @endphp
                            <tr>
                                <td class="p-2.5 font-bold text-slate-800 dark:text-slate-100 whitespace-nowrap">
                                    Kelas {{ $k->tingkat }}{{ $k->kelas }}
                                </td>
                                <td class="p-2.5 whitespace-nowrap">
                                    @if($wkGuru)
                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $wkGuru->nama }}</span>
                                        @if($wkGuru->nik)<span class="text-[11px] text-slate-400 block font-mono">NIK: {{ $wkGuru->nik }}</span>@endif
                                    @else
                                        <span class="text-amber-500 italic text-[11px]">Belum ditentukan</span>
                                    @endif
                                </td>
                                <td class="p-2.5 text-center">
                                    @if($ttdKhusus)
                                        <div class="inline-flex flex-col items-center">
                                            <div class="w-20 h-10 rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 flex items-center justify-center overflow-hidden p-0.5 mb-1">
                                                <img src="{{ $ttdKhusus }}" class="max-w-full max-h-full object-contain" alt="TTD {{ $k->tingkat }}{{ $k->kelas }}">
                                            </div>
                                            <span class="text-[10px] text-emerald-600 font-semibold">Khusus</span>
                                        </div>
                                    @elseif($ttdWalikelasDefault)
                                        <div class="inline-flex flex-col items-center opacity-70">
                                            <div class="w-16 h-8 rounded border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/30 flex items-center justify-center overflow-hidden p-0.5 mb-0.5">
                                                <img src="{{ $ttdWalikelasDefault }}" class="max-w-full max-h-full object-contain">
                                            </div>
                                            <span class="text-[10px] text-slate-400">Bawaan</span>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-slate-100 dark:bg-slate-700 text-slate-400">Kosong</span>
                                    @endif
                                </td>
                                <td class="p-2.5">
                                    <input type="file" name="ttd_kelas_{{ $k->uuid }}" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                                    @if($ttdKhusus)
                                    <label class="flex items-center gap-1.5 mt-1.5 text-[11px] text-rose-500">
                                        <input type="checkbox" name="hapus_ttd_kelas_{{ $k->uuid }}" value="1" class="rounded"> Hapus tanda tangan khusus
                                    </label>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-xs text-slate-400 italic">Belum ada data kelas yang terdaftar.</p>
                @endif
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Pengaturan Rapor</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    (function () {
        if (typeof tinymce === 'undefined') return;
        const dark = document.documentElement.classList.contains('dark');
        tinymce.init({
            selector: '#kop_teks',
            height: 340,
            menubar: 'edit insert format table',
            plugins: 'lists advlist link image table code charmap searchreplace visualblocks wordcount autolink',
            toolbar: 'undo redo | blocks fontfamily fontsizeinput | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table charmap | removeformat code',
            toolbar_mode: 'wrap',
            branding: false,
            promotion: false,
            skin: dark ? 'oxide-dark' : 'oxide',
            content_css: dark ? 'dark' : 'default',
            font_size_formats: '10px 11px 12px 13px 14px 16px 18px 20px 22px 24px 28px 32px 36px',
            // Tampilkan editor mendekati hasil cetak (kop rapor)
            content_style: 'body{font-family:"Times New Roman",Georgia,serif;text-align:center;color:#000;} p, h1, h2, h3, h4, h5, h6 { margin: 2px 0; line-height: 1.25; } .nm{font-size:24px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin:0;} .ad{font-size:13.5px;margin:1px 0 0;}'
        });
    })();
</script>
@endpush
