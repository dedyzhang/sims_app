@extends('layouts.app')
@section('title', 'Pengaturan Bank Pembayaran')

@section('content')
@php
    $initBanks = collect($banks)->map(fn($b) => [
        'nama' => $b['nama'] ?? '',
        'atas_nama' => $b['atas_nama'] ?? '',
        'nomor' => $b['nomor'] ?? '',
        'warna' => $b['warna'] ?? '#64748b',
        'langkah' => implode("\n", $b['langkah'] ?? []),
        'aktif' => (bool)($b['aktif'] ?? false),
    ])->values();
@endphp
<div x-data="bankStudio(@js($initBanks))" class="space-y-5 max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.index') }}" class="hover:underline">Keuangan</a> / Bank</nav>
            <h1 class="page-title">Bank & Metode Pembayaran</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Atur rekening tujuan & langkah pembayaran yang dilihat orang tua/siswa.</p>
        </div>
        <button @click="addBank()" type="button" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-primary/40 text-primary hover:bg-primary/5">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Bank
        </button>
    </div>

    <div class="card p-4 border-l-4 border-sky-400 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i data-lucide="git-compare" class="w-4 h-4 text-sky-600"></i>
                Rekonsiliasi Mutasi Bank
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                <span class="font-semibold text-amber-600">{{ $rekonsiliasiRingkas['belum_cocok'] }}</span> tagihan belum cocok mutasi
                · <span class="font-semibold text-emerald-600">{{ $rekonsiliasiRingkas['sudah_lunas'] }}</span> sudah lunas (T.A. {{ $ta }})
            </p>
        </div>
        <a href="{{ route('keuangan.verifikasi', ['tab' => 'validasi']) }}#validasi"
           class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold inline-flex items-center gap-2 whitespace-nowrap">
            <i data-lucide="upload" class="w-4 h-4"></i> Impor Mutasi di Verifikasi
        </a>
    </div>

    <div class="card p-4 text-xs text-slate-500 dark:text-slate-400 flex gap-2">
        <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
        <p>Pada kolom <b>Nomor Rekening / VA</b>, gunakan <code class="px-1 rounded bg-slate-100 dark:bg-slate-700">{va}</code> untuk menyisipkan nomor Virtual Account masing-masing siswa secara otomatis. Tulis <b>langkah pembayaran</b> satu per baris.</p>
    </div>

    <form method="POST" action="{{ route('keuangan.bank.update') }}" class="space-y-4">
        @csrf

        <template x-for="(b, i) in banks" :key="i">
            <div class="card p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg" :style="'background:'+b.warna"></span>
                        <input type="text" :name="'banks['+i+'][nama]'" x-model="b.nama" placeholder="Nama bank (mis. BCA)" required
                               class="form-input !w-44 font-bold text-sm">
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-xs font-semibold cursor-pointer">
                            <input type="checkbox" :name="'banks['+i+'][aktif]'" x-model="b.aktif" class="rounded">
                            <span :class="b.aktif ? 'text-emerald-600' : 'text-slate-400'" x-text="b.aktif ? 'Aktif' : 'Nonaktif'"></span>
                        </label>
                        <button @click="removeBank(i)" type="button" class="p-1.5 rounded-lg text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Atas Nama</label>
                        <input type="text" :name="'banks['+i+'][atas_nama]'" x-model="b.atas_nama" placeholder="SMP Maitreyawira" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Nomor Rekening / VA</label>
                        <input type="text" :name="'banks['+i+'][nomor]'" x-model="b.nomor" placeholder="1234567890 atau 8810{va}" class="form-input text-sm font-mono">
                    </div>
                </div>

                <div>
                    <label class="form-label">Langkah Pembayaran (satu per baris)</label>
                    <textarea :name="'banks['+i+'][langkah]'" x-model="b.langkah" rows="5" class="form-input text-sm" placeholder="Buka aplikasi m-banking&#10;Pilih transfer..."></textarea>
                </div>
                <input type="hidden" :name="'banks['+i+'][warna]'" x-model="b.warna">
                <div class="flex items-center gap-2">
                    <label class="form-label !mb-0">Warna label</label>
                    <input type="color" x-model="b.warna" class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-600 cursor-pointer p-0.5 bg-transparent">
                </div>
            </div>
        </template>

        <template x-if="banks.length===0">
            <div class="card p-10 text-center text-slate-400">
                <i data-lucide="landmark" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                <p class="text-sm">Belum ada bank. Klik "Tambah Bank".</p>
            </div>
        </template>

        <div class="flex justify-end gap-2 sticky bottom-3">
            <button type="submit" class="btn-primary flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function bankStudio(init) {
    return {
        banks: init || [],
        addBank() {
            this.banks.push({ nama:'', atas_nama:'', nomor:'', warna:'#64748b', langkah:'', aktif:true });
            this.$nextTick(() => lucide.createIcons());
        },
        removeBank(i) {
            $.confirm({
                title:'Hapus bank ini?', content:'Bank & langkahnya akan dihapus dari daftar.', type:'red',
                buttons:{ hapus:{ text:'Ya, Hapus', btnClass:'btn-red', action:()=>{ this.banks.splice(i,1); } }, batal:{ text:'Batal' } }
            });
        },
    }
}
</script>
@endpush
