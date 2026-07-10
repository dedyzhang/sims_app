@extends('sarpras.layouts.app')
@section('title', 'Pengajuan Pengadaan')
@section('sarpras_title', 'Pengajuan Pengadaan Barang')
@section('sarpras_subtitle', 'Ajukan kebutuhan aset baru lengkap dengan item barang, kuantitas, estimasi harga, kategori, dan supplier bila tersedia.')

@section('sarpras_actions')
    <a href="{{ route('sarpras.pengadaan.index') }}" class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-800">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
@endsection

@section('sarpras_body')
<div class="max-w-5xl card p-5 sm:p-6">
    <form method="POST" action="{{ route('sarpras.pengadaan.store') }}" class="space-y-5 text-sm">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="lg:col-span-2 min-w-0">
                <label class="form-label">Judul Pengadaan</label>
                <input name="judul" required value="{{ old('judul') }}" class="form-input" placeholder="Contoh: Pengadaan proyektor ruang kelas">
                @error('judul')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="lg:col-span-2 min-w-0">
                <label class="form-label">Deskripsi</label>
                <textarea name="deskripsi" rows="3" class="form-input" placeholder="Jelaskan alasan kebutuhan dan prioritas pengadaan.">{{ old('deskripsi') }}</textarea>
                @error('deskripsi')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border border-slate-100 dark:border-slate-700 rounded-2xl p-4 space-y-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Item Barang</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 break-words">Tambahkan semua barang yang diajukan. Estimasi harga memakai angka rupiah penuh tanpa titik.</p>
                </div>
                <button type="button" onclick="tambahItem()" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                    <i data-lucide="plus" class="w-4 h-4"></i> Tambah Item
                </button>
            </div>

            <div id="items" class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 item-row">
                    <div class="md:col-span-4 min-w-0">
                        <label class="form-label">Nama Barang</label>
                        <input name="item[0][nama_barang]" placeholder="Nama barang" required class="form-input">
                    </div>
                    <div class="md:col-span-2 min-w-0">
                        <label class="form-label">Qty</label>
                        <input name="item[0][qty]" type="number" min="1" value="1" placeholder="Qty" required class="form-input">
                    </div>
                    <div class="md:col-span-2 min-w-0">
                        <label class="form-label">Satuan</label>
                        <input name="item[0][satuan]" placeholder="Unit" value="unit" class="form-input">
                    </div>
                    <div class="md:col-span-2 min-w-0">
                        <label class="form-label">Harga</label>
                        <input name="item[0][estimasi_harga]" type="number" min="0" placeholder="Rp" required class="form-input">
                    </div>
                    <div class="md:col-span-2 min-w-0">
                        <label class="form-label">Kategori</label>
                        <select name="item[0][kategori_id]" class="form-select">
                            <option value="">Pilih</option>
                            @foreach ($kategori as $k)<option value="{{ $k->id }}">{{ $k->nama }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>
            @error('item')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-2 pt-1 flex-wrap">
            <button class="btn-primary rounded-xl px-5 py-2.5 text-sm font-bold">Ajukan Pengadaan</button>
            <a href="{{ route('sarpras.pengadaan.index') }}" class="px-5 py-2.5 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">Batal</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
let idx = 1;
const kategoriOpts = `@foreach ($kategori as $k)<option value="{{ $k->id }}">{{ $k->nama }}</option>@endforeach`;
function tambahItem() {
    const wrap = document.getElementById('items');
    const row = document.createElement('div');
    row.className = 'grid grid-cols-1 md:grid-cols-12 gap-3 item-row border-t border-slate-100 dark:border-slate-700 pt-3';
    row.innerHTML =
        `<div class="md:col-span-4 min-w-0"><label class="form-label">Nama Barang</label><input name="item[${idx}][nama_barang]" placeholder="Nama barang" required class="form-input"></div>`+
        `<div class="md:col-span-2 min-w-0"><label class="form-label">Qty</label><input name="item[${idx}][qty]" type="number" min="1" value="1" required class="form-input"></div>`+
        `<div class="md:col-span-2 min-w-0"><label class="form-label">Satuan</label><input name="item[${idx}][satuan]" value="unit" class="form-input"></div>`+
        `<div class="md:col-span-2 min-w-0"><label class="form-label">Harga</label><input name="item[${idx}][estimasi_harga]" type="number" min="0" placeholder="Rp" required class="form-input"></div>`+
        `<div class="md:col-span-2 min-w-0"><label class="form-label">Kategori</label><select name="item[${idx}][kategori_id]" class="form-select"><option value="">Pilih</option>${kategoriOpts}</select></div>`;
    wrap.appendChild(row);
    idx++;
    if (window.lucide) window.lucide.createIcons();
}
</script>
@endpush
@endsection