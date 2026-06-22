@extends('layouts.app')
@section('title', 'Nilai Penjabaran')

@section('content')
@php
    $pelData = $pelajarans->map(fn($p) => [
        'uuid'     => $p->uuid,
        'nama'     => $p->nama,
        'kode'     => $p->kode,
        'komponen' => $p->penjabaranKomponen->map(fn($k) => ['uuid' => $k->uuid, 'nama' => $k->nama])->values(),
    ])->values();
@endphp

<div class="space-y-5" x-data="penjabaranCfg(@js($pelData))">
    <div class="flex items-center gap-3">
        <a href="{{ route('setting.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Nilai Penjabaran</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pilih mata pelajaran yang punya nilai penjabaran &amp; tentukan komponen nilainya (mis. B. Inggris → Listening, Speaking, Reading, Writing).</p>
        </div>
    </div>

    <form method="POST" action="{{ route('setting.penjabaran.save') }}" class="space-y-3">
        @csrf
        <div class="card p-3.5 flex items-center justify-between gap-3 sticky top-2 z-10 bg-white/90 dark:bg-slate-800/90 backdrop-blur">
            <p class="text-xs text-slate-500"><i data-lucide="info" class="w-3.5 h-3.5 inline"></i> Mapel dengan ≥1 komponen otomatis punya tab <b>Penjabaran</b> di penilaian. Kosongkan komponen untuk menonaktifkan.</p>
            <button type="submit" class="btn-primary flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="save" class="w-4 h-4"></i> Simpan</button>
        </div>

        <template x-for="p in pels" :key="p.uuid">
            <div class="card p-4" :class="p.komponen.length ? 'ring-1 ring-primary/30' : ''">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <span class="font-bold text-slate-800 dark:text-slate-100" x-text="p.nama"></span>
                        <span class="text-xs text-slate-400" x-text="p.kode ? '· '+p.kode : ''"></span>
                    </div>
                    <span class="badge flex-shrink-0" :class="p.komponen.length ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-slate-100 text-slate-400 dark:bg-slate-700'"
                          x-text="p.komponen.length ? ('Penjabaran • '+p.komponen.length+' nilai') : 'Tanpa penjabaran'"></span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <template x-for="(k, ki) in p.komponen" :key="ki">
                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-700/40 rounded-lg pl-2 pr-1 py-1 border border-slate-200 dark:border-slate-600">
                            <input type="hidden" name="k_uuid[]" :value="k.uuid">
                            <input type="hidden" name="k_pelajaran[]" :value="p.uuid">
                            <input type="text" name="k_nama[]" x-model="k.nama" placeholder="Nama nilai"
                                   class="w-32 bg-transparent border-0 outline-none text-sm py-1 text-slate-700 dark:text-slate-200">
                            <button type="button" @click="p.komponen.splice(ki, 1)" class="grid place-items-center w-6 h-6 rounded text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-lg leading-none">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="p.komponen.push({ uuid:'', nama:'' })"
                            class="flex items-center gap-1 text-sm font-semibold text-primary hover:underline px-2 py-1.5">
                        <i data-lucide="plus" class="w-4 h-4"></i> Tambah nilai
                    </button>
                </div>
            </div>
        </template>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold shadow-sm"><i data-lucide="save" class="w-4 h-4"></i> Simpan Konfigurasi</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function penjabaranCfg(pels) {
        return { pels: pels };
    }
</script>
@endpush
