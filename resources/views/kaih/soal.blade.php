@extends('layouts.app')
@section('title', 'Soal 7 KAIH')

@section('content')
<div class="space-y-5 max-w-3xl">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="page-title">Kelola Soal 7 KAIH</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Ubah teks pertanyaan &amp; opsi jawaban, atau tambah pertanyaan baru. Bobot tiap opsi 1 (rendah) &mdash; 4 (tinggi).</p>
        </div>
    </div>

    {{-- Master switch: nyalakan/matikan seluruh fitur 7 KAIH --}}
    <form method="POST" action="{{ route('kaih.toggle-aktif') }}" class="card p-5" x-data="{ on: {{ $aktif ? 'true' : 'false' }} }">
        @csrf
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="power" class="w-4 h-4 text-primary"></i> Wajibkan Isi 7 KAIH Sebelum Absen</h2>
                <p class="text-xs text-slate-400 mt-1 leading-relaxed">Kalau aktif, siswa wajib isi kuesioner 7 KAIH tiap pagi (QR maupun scan wajah) sebelum bisa absen. Kalau dinonaktifkan, siswa bisa langsung absen tanpa isi kuesioner — data &amp; rekap yang sudah ada tetap tersimpan.</p>
                <p class="text-xs mt-2 font-semibold" :class="on ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'" x-text="on ? '● Aktif' : '○ Nonaktif'"></p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                <input type="checkbox" name="aktif" value="1" class="sr-only peer" x-model="on" @change="$el.form.submit()">
                <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
            </label>
        </div>
    </form>

    @foreach($pertanyaans as $p)
    <div class="card p-5 space-y-4">
        <form method="POST" action="{{ route('kaih.soal.update', $p) }}" class="space-y-3">
            @csrf @method('PUT')
            <div class="flex items-center justify-between gap-2">
                <span class="badge bg-primary/10 text-primary font-bold">#{{ $p->urutan }}</span>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-xs text-slate-500">
                        <input type="checkbox" name="aktif" value="1" @checked($p->aktif) class="rounded border-slate-300 text-primary focus:ring-primary">
                        Aktif
                    </label>
                </div>
            </div>
            <div>
                <label class="form-label">Nama Kebiasaan</label>
                <input type="text" name="kebiasaan" value="{{ $p->kebiasaan }}" required maxlength="100" class="form-input">
            </div>
            <div>
                <label class="form-label">Teks Pertanyaan</label>
                <textarea name="pertanyaan" required rows="2" class="form-input">{{ $p->pertanyaan }}</textarea>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition flex items-center gap-1.5">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i> Simpan Pertanyaan
                </button>
            </div>
        </form>

        @if($p->jawaban_detail_count > 0)
        <div class="px-4 py-2.5 rounded-xl text-xs bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 flex items-start gap-2">
            <i data-lucide="lock" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
            <span>Sudah dijawab {{ $p->jawaban_detail_count }}x oleh siswa &mdash; tidak bisa dihapus (data historis terjaga). Nonaktifkan saja lewat centang "Aktif" di atas kalau tidak mau dipakai lagi.</span>
        </div>
        @else
        <form method="POST" action="{{ route('kaih.soal.destroy', $p) }}" onsubmit="return confirmDelete(this)">
            @csrf @method('DELETE')
            <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold border border-rose-200 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition flex items-center gap-1.5">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Pertanyaan Ini
            </button>
        </form>
        @endif

        <div class="border-t border-slate-100 dark:border-slate-700 pt-4 space-y-2">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Opsi Jawaban</p>
            @foreach($p->opsi as $o)
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('kaih.opsi.update', $o) }}" class="flex items-center gap-2 flex-1">
                    @csrf @method('PUT')
                    <input type="text" name="label" value="{{ $o->label }}" required maxlength="150" class="form-input flex-1 !py-1.5 text-sm">
                    <select name="bobot" class="form-select !w-20 !py-1.5 text-sm">
                        @foreach([1,2,3,4] as $b)
                        <option value="{{ $b }}" @selected($o->bobot == $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-700 flex-shrink-0" title="Simpan"><i data-lucide="check" class="w-4 h-4"></i></button>
                </form>
                <form method="POST" action="{{ route('kaih.opsi.destroy', $o) }}" onsubmit="return confirmDelete(this)">
                    @csrf @method('DELETE')
                    <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/30 flex-shrink-0" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </form>
            </div>
            @endforeach

            <form method="POST" action="{{ route('kaih.opsi.store', $p) }}" class="flex items-center gap-2 pt-1">
                @csrf
                <input type="text" name="label" placeholder="Tambah opsi baru..." required maxlength="150" class="form-input flex-1 !py-1.5 text-sm">
                <select name="bobot" class="form-select !w-20 !py-1.5 text-sm">
                    @foreach([1,2,3,4] as $b)
                    <option value="{{ $b }}">{{ $b }}</option>
                    @endforeach
                </select>
                <button type="submit" class="grid place-items-center w-8 h-8 rounded-lg text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 flex-shrink-0" title="Tambah"><i data-lucide="plus" class="w-4 h-4"></i></button>
            </form>
        </div>
    </div>
    @endforeach

    {{-- Tambah pertanyaan baru --}}
    <div class="card p-5 space-y-3 border-dashed">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2"><i data-lucide="plus-circle" class="w-[18px] h-[18px] text-primary"></i> Tambah Pertanyaan Baru</h2>
        <form method="POST" action="{{ route('kaih.soal.store') }}" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">Nama Kebiasaan</label>
                <input type="text" name="kebiasaan" required maxlength="100" placeholder="mis. Menjaga Kebersihan" class="form-input">
            </div>
            <div>
                <label class="form-label">Teks Pertanyaan</label>
                <textarea name="pertanyaan" required rows="2" placeholder="mis. Apakah kamu mandi & berpakaian rapi pagi ini?" class="form-input"></textarea>
            </div>
            <p class="text-xs text-slate-400">2 opsi contoh ("Ya / Baik" bobot 4, "Tidak / Kurang" bobot 1) akan otomatis dibuat &mdash; sesuaikan atau tambah lagi setelah tersimpan.</p>
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Pertanyaan
            </button>
        </form>
    </div>
</div>
@endsection
