@extends('sarpras.layouts.app')
@section('title', 'Ajukan Pinjam Barang')
@section('sarpras_title', 'Ajukan Pinjam Barang')
@section('sarpras_subtitle', 'Satu pengajuan dapat memuat aset inventaris. Ruangan opsional jika ikut dipakai bersama barang.')

@section('sarpras_actions')
    <a href="{{ route('sarpras.peminjaman.index') }}" class="sarpras-google-btn inline-flex px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
@endsection

@section('sarpras_body')
<div class="max-w-2xl">
    <form method="POST" action="{{ route('sarpras.peminjaman.store') }}" class="card !rounded-[24px] p-5 sm:p-7 space-y-5">
        @csrf

        <div class="flex items-start gap-3 pb-1 border-b border-slate-100 dark:border-slate-700/60">
            <span class="grid place-items-center w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-950/40 text-[#1a73e8] shrink-0">
                <i data-lucide="hand-helping" class="w-5 h-5"></i>
            </span>
            <div>
                <h2 class="text-base font-extrabold text-slate-800 dark:text-slate-100">Detail Peminjaman</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Isi keperluan, waktu, lalu pilih aset dan/atau ruangan.</p>
            </div>
        </div>

        <div>
            <label class="sarpras-field-label">Keperluan</label>
            <textarea name="keperluan" rows="2" required class="sarpras-field" placeholder="Contoh: Presentasi kelas 8A — butuh proyektor">{{ old('keperluan') }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="sarpras-field-label">Mulai</label>
                <input name="mulai" type="datetime-local" required value="{{ old('mulai') }}" class="sarpras-field">
            </div>
            <div>
                <label class="sarpras-field-label">Selesai</label>
                <input name="selesai" type="datetime-local" required value="{{ old('selesai') }}" class="sarpras-field">
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-600 bg-[#f8fafd] dark:bg-slate-800/40 p-4 space-y-3">
            <div>
                <label class="sarpras-field-label">Ruangan <span class="normal-case font-medium tracking-normal">(opsional)</span></label>
                <select name="ruangan_id" class="sarpras-field">
                    <option value="">— tidak booking ruangan —</option>
                    @foreach ($ruangan as $r)
                        <option value="{{ $r->id }}" @selected(old('ruangan_id') === $r->id)>{{ $r->kode }} — {{ $r->nama }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400 mt-1.5">Bila dipilih, sistem menolak jika bentrok jadwal lain. Untuk booking ruang saja, pakai menu Booking Ruangan.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-600 p-4 space-y-3">
            <label class="sarpras-field-label">Aset <span class="normal-case font-medium tracking-normal">(opsional)</span></label>
            <div class="rounded-xl border border-slate-200 dark:border-slate-600 p-3 max-h-60 overflow-y-auto space-y-2.5 bg-white dark:bg-slate-900/40">
                @forelse($aset as $a)
                    <label class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="aset_id[]" value="{{ $a->id }}"
                               class="rounded border-slate-300 text-[#1a73e8] focus:ring-[#1a73e8]"
                               @checked(collect(old('aset_id', []))->contains($a->id))>
                        <span class="flex-1 text-sm text-slate-700 dark:text-slate-200">{{ $a->kode }} — {{ $a->nama }}</span>
                        <input type="number" name="qty[{{ $a->id }}]" value="{{ old('qty.' . $a->id, 1) }}" min="1"
                               class="w-16 border border-slate-200 dark:border-slate-600 rounded-lg px-2 py-1 text-sm text-center bg-white dark:bg-slate-800">
                    </label>
                @empty
                    <p class="text-sm text-slate-400 py-4 text-center">Belum ada aset aktif.</p>
                @endforelse
            </div>
            <p class="text-[11px] text-slate-400">Centang aset yang dipinjam beserta jumlahnya. Minimal pilih ruangan atau satu aset.</p>
        </div>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
            <a href="{{ route('sarpras.peminjaman.index') }}" class="sarpras-google-btn-ghost px-5 py-2.5 text-sm">Batal</a>
            <button type="submit" class="sarpras-google-btn-primary px-5 py-2.5 text-sm">
                <i data-lucide="send" class="w-4 h-4"></i> Ajukan Pinjam
            </button>
        </div>
    </form>
</div>
@endsection
