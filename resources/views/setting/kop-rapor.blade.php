@extends('layouts.app')
@section('title', 'Kop Surat Rapor')

@php
    $img = function ($key, $default) use ($settings) {
        $v = $settings[$key] ?? null;
        if ($v && file_exists(storage_path('app/public/' . $v))) return asset('storage/' . $v);
        if (file_exists(public_path($default))) return asset($default);
        return null;
    };
    $logoKiri = $img('kop_logo_kiri', 'img/tutwuri.png');
    $logoKanan = $img('kop_logo_kanan', 'img/maitreyawira_square.png');
    $backdrop = $img('kop_backdrop', 'img/logo.png');
@endphp

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('setting.index') }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">Kop Surat Rapor</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Atur logo, teks kepala surat, dan gambar latar (backdrop) pada halaman cetak rapor.</p>
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

        <div class="flex justify-end pt-2">
            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan Kop Surat</button>
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
            content_style: 'body{font-family:"Times New Roman",Georgia,serif;text-align:center;color:#000;} p{margin:1px 0;} .nm{font-size:24px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin:0;} .ad{font-size:13.5px;margin:1px 0 0;}'
        });
    })();
</script>
@endpush
