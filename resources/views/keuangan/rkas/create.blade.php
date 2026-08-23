@extends('layouts.app')
@section('title', $mode === 'edit' ? 'Edit RKAS' : 'Buat RKAS')

@section('content')
@php
    $referencePayload = $referenceSets->map(fn($set) => [
        'uuid' => $set->uuid,
        'label' => $set->label,
        'references' => $set->references->map(fn($ref) => [
            'uuid' => $ref->uuid, 'kode' => $ref->kode_kegiatan, 'komponen' => $ref->komponen, 'uraian' => $ref->uraian_kegiatan,
        ])->values()->all(),
    ])->values()->all();
    $itemPayload = $items->map(fn($item) => [
        'reference_uuid' => $item->reference_uuid, 'penjelasan_implementasi' => $item->penjelasan_implementasi,
        'uraian_belanja' => $item->uraian_belanja, 'bulan_dianggarkan' => (int)$item->bulan_dianggarkan,
        'jumlah' => (int)$item->jumlah, 'satuan' => $item->satuan, 'harga_satuan' => (int)$item->harga_satuan,
    ])->values()->all();
@endphp
<div class="space-y-5" x-data="rkasForm(@js($itemPayload), @js($referencePayload))">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div><h1 class="page-title">{{ $mode === 'edit' ? 'Edit RKAS / BOSP' : 'Buat RKAS / BOSP' }}</h1><p class="text-sm text-slate-500 mt-1">Nominal total dihitung server-side. Pilih kode dari registry referensi yang aktif.</p></div>
        <a href="{{ route('keuangan.rkas.index') }}" class="text-sm text-slate-500 hover:underline">Kembali</a>
    </div>

    @if($errors->any()) <div class="card p-4 border-l-4 border-rose-400 text-sm text-rose-700"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

    <form method="POST" action="{{ $mode === 'edit' ? route('keuangan.rkas.update', $plan) : route('keuangan.rkas.store') }}" class="space-y-5">
        @csrf @if($mode === 'edit') @method('PUT') @endif
        <div class="card p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <label class="text-sm">Nama sekolah<input name="nama_sekolah" required value="{{ old('nama_sekolah', $plan->nama_sekolah) }}" class="form-input mt-1"></label>
            <label class="text-sm">NPSN<input name="npsn" value="{{ old('npsn', $plan->npsn) }}" class="form-input mt-1"></label>
            <label class="text-sm">Tahun anggaran<input name="tahun_anggaran" type="number" required value="{{ old('tahun_anggaran', $plan->tahun_anggaran) }}" class="form-input mt-1"></label>
            <label class="text-sm">Jenjang<input name="jenjang" required value="{{ old('jenjang', $plan->jenjang) }}" class="form-input mt-1" placeholder="Dikdasmen / PAUD / Kesetaraan"></label>
            <label class="text-sm">Sumber dana<input name="sumber_dana" required value="{{ old('sumber_dana', $plan->sumber_dana) }}" class="form-input mt-1"></label>
            <label class="text-sm">Pagu sumber dana<input name="pagu" type="number" min="1" required value="{{ old('pagu', $plan->pagu) }}" class="form-input mt-1"></label>
            <label class="text-sm md:col-span-2 xl:col-span-3">Paket referensi ARKAS
                <select name="reference_set_uuid" x-model="selectedReference" required class="form-input mt-1">
                    <option value="">Pilih referensi</option>
                    @foreach($referenceSets as $set)<option value="{{ $set->uuid }}">{{ $set->label }} — {{ $set->versi }} ({{ $set->jenjang }} / {{ $set->sumber_dana }})</option>@endforeach
                </select>
            </label>
        </div>

        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center"><div><h2 class="font-bold">Kertas Kerja ARKAS</h2><p class="text-xs text-slate-500">SPP tidak masuk dalam rencana BOSP ini.</p></div><button type="button" @click="addItem()" class="btn-secondary inline-flex items-center gap-1 text-sm"><i data-lucide="plus" class="w-4 h-4"></i> Tambah item</button></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[1100px]">
                    <thead class="bg-slate-50 dark:bg-slate-800/70 text-xs text-slate-500"><tr><th class="text-left p-3 w-64">Kode kegiatan</th><th class="text-left p-3 w-64">Uraian implementasi</th><th class="text-left p-3 w-64">Uraian belanja</th><th class="text-left p-3 w-20">Bulan</th><th class="text-left p-3 w-24">Jumlah</th><th class="text-left p-3 w-28">Satuan</th><th class="text-left p-3 w-36">Harga satuan</th><th class="text-right p-3 w-32">Total</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <template x-for="(item, index) in items" :key="item.key">
                        <tr>
                            <td class="p-2 align-top"><select :name="'items['+index+'][reference_uuid]'" x-model="item.reference_uuid" required class="form-input text-xs"><option value="">Pilih kode</option><template x-for="ref in references()" :key="ref.uuid"><option :value="ref.uuid" x-text="ref.kode+' — '+(ref.komponen || ref.uraian)"></option></template></select></td>
                            <td class="p-2 align-top"><textarea :name="'items['+index+'][penjelasan_implementasi]'" x-model="item.penjelasan_implementasi" rows="2" class="form-input text-xs" placeholder="Penjelasan singkat"></textarea></td>
                            <td class="p-2 align-top"><textarea :name="'items['+index+'][uraian_belanja]'" x-model="item.uraian_belanja" required rows="2" class="form-input text-xs" placeholder="Barang/jasa yang dibeli"></textarea></td>
                            <td class="p-2 align-top"><input :name="'items['+index+'][bulan_dianggarkan]'" x-model.number="item.bulan_dianggarkan" type="number" min="1" max="12" required class="form-input text-xs"></td>
                            <td class="p-2 align-top"><input :name="'items['+index+'][jumlah]'" x-model.number="item.jumlah" type="number" min="1" required class="form-input text-xs"></td>
                            <td class="p-2 align-top"><input :name="'items['+index+'][satuan]'" x-model="item.satuan" required class="form-input text-xs" placeholder="rim"></td>
                            <td class="p-2 align-top"><input :name="'items['+index+'][harga_satuan]'" x-model.number="item.harga_satuan" type="number" min="0" required class="form-input text-xs"></td>
                            <td class="p-2 align-top text-right font-semibold whitespace-nowrap" x-text="'Rp '+money(total(item))"></td>
                            <td class="p-2 align-top"><button type="button" @click="removeItem(index)" class="text-rose-500 hover:text-rose-700" title="Hapus"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr class="bg-slate-50 dark:bg-slate-800/70"><td colspan="7" class="p-3 text-right font-bold">Total rencana</td><td class="p-3 text-right font-bold" x-text="'Rp '+money(grandTotal())"></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="flex justify-end gap-2"><a href="{{ route('keuangan.rkas.index') }}" class="btn-secondary">Batal</a><button class="btn-primary inline-flex items-center gap-2"><i data-lucide="save" class="w-4 h-4"></i> Simpan draft</button></div>
    </form>
</div>
<script>
function rkasForm(initialItems, referenceSets) {
    const base = { reference_uuid:'', penjelasan_implementasi:'', uraian_belanja:'', bulan_dianggarkan:1, jumlah:1, satuan:'', harga_satuan:0 };
    return {
        selectedReference: @js(old('reference_set_uuid', $plan->reference_set_uuid)),
        items: (initialItems.length ? initialItems : [base]).map((item, index) => ({...base, ...item, key: index + '-' + Math.random()})),
        references() { return (referenceSets.find(set => set.uuid === this.selectedReference) || {}).references || []; },
        addItem() { this.items.push({...base, key: Date.now() + '-' + Math.random()}); },
        removeItem(index) { if (this.items.length > 1) this.items.splice(index, 1); },
        total(item) { return Math.max(0, Number(item.jumlah || 0) * Number(item.harga_satuan || 0)); },
        grandTotal() { return this.items.reduce((sum, item) => sum + this.total(item), 0); },
        money(value) { return new Intl.NumberFormat('id-ID').format(value || 0); }
    };
}
</script>
@endsection
