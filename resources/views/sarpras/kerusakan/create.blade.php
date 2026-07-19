@extends('sarpras.layouts.app')
@section('title', 'Lapor Kerusakan')
@section('sarpras_title', 'Lapor Kerusakan')
@section('sarpras_subtitle', 'Laporkan fasilitas atau inventaris yang bermasalah. Sertakan foto agar Waka Sarpras bisa menindaklanjuti lebih cepat.')

@section('sarpras_actions')
    <a href="{{ route('sarpras.kerusakan.index') }}"
       class="sarpras-google-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
@endsection

@section('sarpras_body')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('sarpras.kerusakan.store') }}" enctype="multipart/form-data"
          class="card !rounded-[24px] p-5 sm:p-7 space-y-5">
        @csrf

        <div class="flex items-start gap-3 pb-1 border-b border-slate-100 dark:border-slate-700/60">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-950/40 text-rose-600 shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5"></i>
            </span>
            <div>
                <h2 class="text-base font-extrabold text-slate-800 dark:text-slate-100">Detail Laporan</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Isi objek, urgensi, deskripsi, lalu lampirkan foto.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1.5">Aset <span class="normal-case font-medium">(opsional)</span></label>
                <select name="aset_id" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a73e8]/40 focus:border-[#1a73e8]">
                    <option value="">— pilih aset —</option>
                    @foreach ($aset as $a)
                        <option value="{{ $a->id }}" @selected(old('aset_id')===$a->id)>{{ $a->kode }} — {{ $a->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1.5">Ruangan <span class="normal-case font-medium">(opsional)</span></label>
                <select name="ruangan_id" class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3.5 py-2.5 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a73e8]/40 focus:border-[#1a73e8]">
                    <option value="">— pilih ruangan —</option>
                    @foreach ($ruangan as $r)
                        <option value="{{ $r->id }}" @selected(old('ruangan_id', $ruanganTerpilih)===$r->id)>{{ $r->kode }} — {{ $r->nama }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1.5">Pilih minimal salah satu: aset atau ruangan.</p>
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-extrabold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1.5">Tingkat Urgensi</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach ([
                    'rendah' => ['Rendah', 'border-slate-200 text-slate-600 peer-checked:border-slate-500 peer-checked:bg-slate-50 peer-checked:text-slate-800'],
                    'sedang' => ['Sedang', 'border-amber-200 text-amber-700 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-checked:text-amber-800'],
                    'tinggi' => ['Tinggi', 'border-orange-200 text-orange-700 peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-800'],
                    'darurat' => ['Darurat', 'border-rose-200 text-rose-700 peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-800'],
                ] as $val => [$label, $cls])
                    <label class="relative cursor-pointer">
                        <input type="radio" name="urgensi" value="{{ $val }}" class="peer sr-only" @checked(old('urgensi', 'sedang') === $val) required>
                        <span class="flex items-center justify-center rounded-xl border-2 px-3 py-2.5 text-xs font-extrabold transition {{ $cls }}">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-[11px] font-extrabold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1.5">Deskripsi Kerusakan</label>
            <textarea name="deskripsi" rows="4" required placeholder="Contoh: AC kelas 7A tidak dingin, ada bunyi aneh sejak kemarin..."
                      class="w-full border border-slate-200 dark:border-slate-600 rounded-xl px-3.5 py-3 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-[#1a73e8]/40 focus:border-[#1a73e8] placeholder:text-slate-300">{{ old('deskripsi') }}</textarea>
        </div>

        <div class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-600 bg-[#f8fafd] dark:bg-slate-800/40 p-4">
            @include('sarpras.partials.foto-picker', [
                'name' => 'foto[]',
                'label' => 'Foto bagian yang rusak',
                'hint' => '1–4 foto, otomatis dikompres ≤2MB',
                'max' => 4,
                'live' => true,
            ])
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('sarpras.kerusakan.index') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-extrabold text-white bg-[#1a73e8] hover:bg-[#1557b0] shadow-[0_8px_18px_rgba(26,115,232,.28)] transition">
                <i data-lucide="send" class="w-4 h-4"></i>
                Kirim ke Waka Sarpras
            </button>
        </div>
    </form>
</div>
@endsection
