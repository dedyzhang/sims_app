@extends('layouts.app')
@section('title', 'Catat Pemanggilan Ortu/Siswa')

@section('content')
<div class="max-w-2xl mx-auto space-y-4" x-data="panggilanForm(@js(route('pemanggilan.cari-siswa')))" x-init="init()">
    <a href="{{ auth()->user()->canAccess('manage_disiplin') ? route('pemanggilan.index') : route('pemanggilan.riwayat') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-primary">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali
    </a>
    <div>
        <h1 class="page-title">Catat Pemanggilan Ortu/Siswa</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Rekam permasalahan, hasil pertemuan, dan dokumentasi (opsional).</p>
    </div>

    <form method="POST" action="{{ route('pemanggilan.store') }}" enctype="multipart/form-data" class="card p-6 space-y-4">
        @csrf
        <div>
            <label class="form-label">Siswa <span class="text-rose-500">*</span></label>
            <select name="id_siswa" x-ref="siswaSelect" class="form-select" required></select>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Tanggal <span class="text-rose-500">*</span></label>
                <input type="date" name="tanggal" value="{{ old('tanggal', now()->toDateString()) }}" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Yang Dipanggil <span class="text-rose-500">*</span></label>
                <select name="dipanggil" class="form-select" required>
                    <option value="">— Pilih —</option>
                    @foreach(\App\Models\Pemanggilan::DIPANGGIL as $val => $label)
                    <option value="{{ $val }}" @selected(old('dipanggil')===$val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Perihal <span class="text-rose-500">*</span></label>
            <input type="text" name="perihal" value="{{ old('perihal') }}" maxlength="150" placeholder="mis. Sering terlambat masuk kelas" class="form-input" required>
        </div>
        <div>
            <label class="form-label">Catatan Permasalahan <span class="text-rose-500">*</span></label>
            <textarea name="permasalahan" rows="4" placeholder="Jelaskan permasalahan yang dibahas..." class="form-input" required>{{ old('permasalahan') }}</textarea>
        </div>
        <div>
            <label class="form-label">Hasil Pertemuan <span class="text-slate-400 font-normal">(boleh diisi belakangan)</span></label>
            <textarea name="hasil" rows="4" placeholder="Kesepakatan/tindak lanjut hasil pertemuan..." class="form-input">{{ old('hasil') }}</textarea>
        </div>
        <div>
            <label class="form-label">Dokumentasi <span class="text-slate-400 font-normal">(opsional)</span></label>
            @include('classroom.partials.upload', [
                'label' => 'Seret & lepas foto/bukti pertemuan, atau klik untuk pilih',
                'acceptLabel' => 'Gambar (JPG/PNG/WEBP/HEIC) atau PDF',
                'acceptAttr' => 'image/*,application/pdf',
                'maxMb' => 5,
            ])
            @error('files')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
            @error('files.*')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="save" class="w-4 h-4"></i> Simpan Catatan
        </button>
    </form>
</div>
@endsection

@push('scripts')
<script>
function panggilanForm(searchUrl) {
    return {
        ts: null,
        init() {
            this.ts = new TomSelect(this.$refs.siswaSelect, {
                valueField: 'uuid',
                labelField: 'nama',
                searchField: ['nama', 'nis'],
                placeholder: 'Ketik nama atau NIS siswa...',
                options: [],
                create: false,
                shouldLoad: () => true,
                render: {
                    option: (d, esc) => `<div><span class="font-semibold">${esc(d.nama)}</span> <span class="text-xs text-slate-400">NIS ${esc(d.nis)} &bull; ${esc(d.kelas)}</span></div>`,
                    item: (d, esc) => `<div>${esc(d.nama)} <span class="text-xs opacity-70">(${esc(d.kelas)})</span></div>`,
                },
                load: (query, callback) => {
                    fetch(searchUrl + '?q=' + encodeURIComponent(query))
                        .then(res => res.json())
                        .then(data => callback(data.siswas || []))
                        .catch(() => callback());
                },
            });
        }
    };
}
</script>
@endpush
