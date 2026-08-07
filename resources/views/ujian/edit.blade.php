@extends('layouts.app')
@section('title', 'Susun Soal — ' . $ujian->judul)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('ujian.index') }}" class="hover:underline">Ujian</a> /
            <a href="{{ route('ujian.show', $ujian) }}" class="hover:underline">{{ $ujian->judul }}</a> / Susun Soal
        </nav>
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="page-title">Susun Soal</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Total poin: {{ $ujian->soal->sum('poin') }} · {{ $ujian->soal->count() }} soal</p>
            </div>
            @if($ujian->status === 'draft')
            <button type="button" @click="$dispatch('buka-bank-soal')" class="px-4 py-2 rounded-xl text-xs font-semibold border border-primary text-primary hover:bg-primary/5 flex items-center gap-1.5 flex-shrink-0">
                <i data-lucide="library" class="w-4 h-4"></i> Sisipkan dari Bank Soal
            </button>
            @endif
        </div>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Modal "Sisipkan dari Bank Soal" — muat daftar soal bank mapel ini via fetch JSON
         (bukan Blade) begitu dibuka, supaya tak perlu reload halaman ujian utk pilih soal. --}}
    @if($ujian->status === 'draft' && $ujian->pelajaran)
    <div x-data="bankSoalPicker({{ Js::from(route('bank-soal.pilih', $ujian->pelajaran)) }}, {{ Js::from(route('ujian.soal.sisipkanBank', $ujian)) }})"
         x-show="open" x-cloak
         @buka-bank-soal.window="buka()"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @click.self="open = false">
        <div class="card w-full max-w-lg max-h-[80vh] flex flex-col p-5" x-show="open" x-transition>
            <div class="flex items-center justify-between mb-1">
                <h2 class="font-bold text-slate-800 dark:text-slate-100">Sisipkan dari Bank Soal</h2>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <p class="text-xs text-slate-400 mb-3">{{ $ujian->pelajaran->nama }}</p>

            <template x-if="loading">
                <p class="text-sm text-slate-400 py-8 text-center">Memuat...</p>
            </template>
            <template x-if="!loading && daftar.length === 0">
                <div class="py-8 text-center text-slate-400">
                    <p class="text-sm">Belum ada soal di Bank Soal mapel ini.</p>
                    <a href="{{ route('bank-soal.show', $ujian->pelajaran) }}" target="_blank" class="text-xs text-primary hover:underline mt-1 inline-block">Buka Bank Soal →</a>
                </div>
            </template>

            <div class="flex-1 overflow-y-auto space-y-2 -mx-1 px-1" x-show="!loading && daftar.length > 0">
                <template x-for="s in daftar" :key="s.uuid">
                    <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-slate-200 dark:border-slate-600 cursor-pointer hover:border-primary">
                        <input type="checkbox" :value="s.uuid" x-model="terpilih" class="rounded text-primary focus:ring-primary mt-0.5 flex-shrink-0">
                        <div class="min-w-0">
                            <p class="text-xs text-slate-400" x-text="s.label + ' · ' + s.poin + ' poin'"></p>
                            <p class="text-sm truncate" x-text="s.cuplik"></p>
                        </div>
                    </label>
                </template>
            </div>

            <form method="POST" :action="submitUrl" class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3 flex-shrink-0">
                @csrf
                <template x-for="uuid in terpilih" :key="uuid"><input type="hidden" name="soal[]" :value="uuid"></template>
                <p class="text-xs text-slate-400" x-text="terpilih.length + ' dipilih'"></p>
                <button type="submit" :disabled="terpilih.length === 0" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold disabled:opacity-40">Sisipkan</button>
            </form>
        </div>
    </div>
    @endif

    @if($ujian->status !== 'draft')
    <div class="card p-4 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/20 border-l-4 !border-l-amber-500">
        Ujian ini sudah {{ $ujian->statusLabel() }} — soal tidak bisa diubah. Tutup ulang ke draf lewat admin bila perlu revisi.
    </div>
    @else

    {{-- Daftar soal --}}
    <div class="space-y-3">
        @foreach($ujian->soal as $i => $soal)
        <div class="card p-5" x-data="soalForm({
                tipe: '{{ $soal->tipe }}',
                teks_soal: {{ Js::from($soal->teks_soal) }},
                poin: {{ $soal->poin }},
                penjelasan: {{ Js::from($soal->penjelasan ?? '') }},
                opsi: {{ Js::from($soal->opsi->map(fn($o) => ['teks' => $o->teks_opsi, 'benar' => $o->is_benar])->all()) }},
                pasangan: {{ Js::from(($soal->meta['pairs'] ?? []) ? collect($soal->meta['pairs'])->map(fn($p) => ['kiri'=>$p['left'],'kanan'=>$p['right']])->all() : []) }},
                kunci_esai: {{ Js::from($soal->meta['kunci_jawaban'] ?? '') }},
                open: false,
              })"
             x-init="_rootEl = $el">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="open = !open; if(open) $nextTick(() => window.UjianEditor && window.UjianEditor.mountAll())">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 grid place-items-center text-xs font-bold flex-shrink-0">{{ $i + 1 }}</span>
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400">{{ $soal->typeLabel() }} · {{ $soal->poin }} poin</p>
                        <p class="text-sm font-medium truncate">{{ Str::limit(strip_tags($soal->teks_soal), 80) }}</p>
                    </div>
                </div>
                <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 flex-shrink-0 transition" :class="open && 'rotate-180'"></i>
            </div>

            <form x-show="open" x-cloak x-transition method="POST" action="{{ route('ujian.soal.update', [$ujian, $soal]) }}" class="mt-4 space-y-3 border-t border-slate-100 dark:border-slate-700 pt-4">
                @csrf
                @include('ujian.partials.soal-fields')
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold">Simpan Perubahan</button>
                    <button type="button" @click="if(confirm('Simpan salinan soal ini ke Bank Soal ({{ $ujian->pelajaran?->nama }})?')) document.getElementById('simpan-bank-{{ $soal->uuid }}').submit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-primary hover:bg-primary/5">Simpan ke Bank</button>
                    <button type="button" @click="if(confirm('Hapus soal ini?')) document.getElementById('hapus-soal-{{ $soal->uuid }}').submit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 ml-auto">Hapus</button>
                </div>
            </form>
        </div>
        <form id="hapus-soal-{{ $soal->uuid }}" method="POST" action="{{ route('ujian.soal.destroy', [$ujian, $soal]) }}" class="hidden">@csrf @method('DELETE')</form>
        <form id="simpan-bank-{{ $soal->uuid }}" method="POST" action="{{ route('ujian.soal.simpanBank', [$ujian, $soal]) }}" class="hidden">@csrf</form>
        @endforeach
    </div>

    {{-- Tambah soal baru --}}
    <div class="card p-5" x-data="soalForm({ tipe: 'mcq', teks_soal: '', poin: 1, penjelasan: '', opsi: [{teks:'',benar:true},{teks:'',benar:false}], pasangan: [{kiri:'',kanan:''},{kiri:'',kanan:''}], kunci_esai: '', open: true })"
         x-init="_rootEl = $el; $nextTick(() => window.UjianEditor && window.UjianEditor.mountAll())">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-primary"></i> Tambah Soal</h2>
        <form method="POST" action="{{ route('ujian.soal.store', $ujian) }}" class="space-y-3">
            @csrf
            @include('ujian.partials.soal-fields')
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold">Tambah Soal</button>
        </form>
    </div>
    @endif

    <div class="flex justify-end">
        <a href="{{ route('ujian.show', $ujian) }}" class="text-sm text-primary hover:underline">Selesai, kembali ke ringkasan ujian →</a>
    </div>
</div>
@endsection

@push('scripts')
@include('ujian.partials.soal-form-script')
<script>
function bankSoalPicker(pilihUrl, submitUrl) {
    return {
        open: false, loading: false, daftar: [], terpilih: [], submitUrl,
        buka() {
            this.open = true;
            this.terpilih = [];
            if (this.daftar.length === 0) this.muat();
        },
        muat() {
            this.loading = true;
            fetch(pilihUrl, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(json => { this.daftar = json; })
                .finally(() => { this.loading = false; });
        },
    }
}
</script>
@endpush
