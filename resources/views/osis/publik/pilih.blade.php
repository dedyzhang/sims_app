@extends('layouts.app')
@section('title', 'Pemilihan Ketua OSIS')

@section('content')
@php
    $namaPemilih = $pemilih->siswa->nama ?? $pemilih->guru->nama ?? $pemilih->nama_snapshot;
    $identitas = $pemilih->tipe_pemilih === 'siswa'
        ? trim(($pemilih->siswa->nis ?? $pemilih->nis_snapshot ?? '') . ' · Kelas ' . ($pemilih->kelas_snapshot ?? '-'))
        : 'Guru & Karyawan';
@endphp
<div class="max-w-lg mx-auto space-y-5 pb-28" x-data="osisPilih()">

    <div class="text-center pt-2 space-y-1">
        <div class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center text-white shadow-lg" style="background:var(--cp)">
            <i data-lucide="award" class="w-7 h-7"></i>
        </div>
        <h1 class="text-xl font-extrabold text-slate-800 dark:text-white mt-2">Pemilihan Ketua OSIS</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $pemilih->pemilihan->nama }}</p>
    </div>

    <div class="card p-4 flex items-center gap-3">
        <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0" style="background:var(--ca)">
            {{ mb_strtoupper(mb_substr($namaPemilih, 0, 1)) }}
        </div>
        <div class="min-w-0">
            <div class="font-bold text-slate-800 dark:text-white truncate">{{ $namaPemilih }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $identitas }}</div>
        </div>
        <span class="badge bg-primary/10 text-primary ml-auto flex-shrink-0">Belum Memilih</span>
    </div>

    @if ($errors->any())
        <div class="card p-3 border-l-4 !border-l-rose-500 text-sm text-rose-600 dark:text-rose-400">{{ $errors->first() }}</div>
    @endif

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2 px-1">Pilih Salah Satu Paslon</p>
        <div class="space-y-3">
            @foreach ($paslonList as $p)
            <label class="card p-4 block cursor-pointer transition ring-2"
                   :class="dipilih === '{{ $p->uuid }}' ? 'ring-primary' : 'ring-transparent'"
                   @click.prevent="dipilih = '{{ $p->uuid }}'">
                <div class="flex gap-3 items-center">
                    <span class="w-5 h-5 rounded-full border-2 flex-shrink-0 flex items-center justify-center transition"
                          :class="dipilih === '{{ $p->uuid }}' ? 'border-primary bg-primary' : 'border-slate-300 dark:border-slate-600'">
                        <i data-lucide="check" class="w-3 h-3 text-white" x-show="dipilih === '{{ $p->uuid }}'"></i>
                    </span>
                    <div class="w-14 h-14 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0">
                        @if ($p->foto_url)
                            <img src="{{ $p->foto_url }}" class="w-full h-full object-cover" alt="Foto paslon {{ $p->nomor_urut }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-slate-300"><i data-lucide="users" class="w-6 h-6"></i></div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <span class="badge bg-primary/10 text-primary">No. {{ $p->nomor_urut }}</span>
                        <div class="font-bold text-slate-800 dark:text-white leading-tight mt-0.5">
                            {{ $p->nama_ketua }}@if($p->nama_wakil) &amp; {{ $p->nama_wakil }}@endif
                        </div>
                    </div>
                </div>

                @if ($p->visi || count($p->misi_points))
                <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700" x-data="{ buka: false }">
                    <button type="button" @click.stop.prevent="buka = !buka"
                            class="flex items-center gap-1.5 text-xs font-semibold text-primary">
                        <i data-lucide="scroll-text" class="w-3.5 h-3.5"></i>
                        <span x-text="buka ? 'Sembunyikan Visi &amp; Misi' : 'Lihat Visi &amp; Misi'"></span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" ::class="buka && 'rotate-180'"></i>
                    </button>
                    <div x-show="buka" x-cloak x-collapse class="mt-2.5 space-y-2.5">
                        @if ($p->visi)
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-primary">Visi</p>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">{{ $p->visi }}</p>
                        </div>
                        @endif
                        @if (count($p->misi_points))
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-primary">Misi</p>
                            <ul class="mt-1 space-y-1">
                                @foreach ($p->misi_points as $m)
                                <li class="flex items-start gap-1.5 text-xs text-slate-600 dark:text-slate-300">
                                    <span class="w-1 h-1 rounded-full mt-1.5 flex-shrink-0" style="background:var(--cp)"></span>
                                    <span>{{ $m }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </label>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('osis.publik.store', $pemilih->token) }}"
          onsubmit="return confirmAction(this, 'Pilihan tidak dapat diubah setelah dikirim. Kirim sekarang?', 'red')"
          class="fixed bottom-0 left-0 right-0 p-4 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-100 dark:border-slate-700">
        @csrf
        <input type="hidden" name="id_paslon" :value="dipilih">
        <div class="max-w-lg mx-auto">
            <button type="submit" :disabled="!dipilih"
                    class="w-full py-3.5 rounded-2xl text-white font-bold shadow-lg transition disabled:opacity-40 disabled:shadow-none"
                    style="background:var(--cp)">
                Kirim Pilihan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function osisPilih() {
    return { dipilih: null };
}
</script>
@endpush
