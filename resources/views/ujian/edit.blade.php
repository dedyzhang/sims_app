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
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Total poin: {{ $ujian->soal->sum(fn($s) => $s->poinEfektif()) }} · {{ $ujian->soal->count() }} soal</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                <a href="{{ route('ujian.pratinjau', $ujian) }}" target="_blank" rel="noopener"
                   class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 flex items-center gap-1.5">
                    <i data-lucide="smartphone" class="w-4 h-4"></i> Pratinjau
                </a>

                {{-- Dropdown Ekspor Soal --}}
                <div x-data="{ openEkspor: false }" class="relative inline-block">
                    <button type="button" @click="openEkspor = !openEkspor" class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-1.5">
                        <i data-lucide="download" class="w-4 h-4"></i> Ekspor Soal
                        <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                    </button>
                    <div x-show="openEkspor" @click.outside="openEkspor = false" x-cloak class="absolute right-0 mt-1 w-48 rounded-xl bg-white dark:bg-slate-800 shadow-xl border border-slate-200 dark:border-slate-700 py-1.5 z-30">
                        <a href="{{ route('ujian.soal.export', ['ujian' => $ujian, 'format' => 'xlsx']) }}" class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">
                            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-600"></i> Unduh Excel (.xlsx)
                        </a>
                        <a href="{{ route('ujian.soal.export', ['ujian' => $ujian, 'format' => 'json']) }}" class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700">
                            <i data-lucide="code" class="w-4 h-4 text-blue-600"></i> Unduh JSON (.json)
                        </a>
                    </div>
                </div>

                @if($ujian->status === 'draft')
                {{-- Tombol Impor / Salin Soal --}}
                <button type="button" @click="$dispatch('buka-modal-import')" class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30 flex items-center gap-1.5">
                    <i data-lucide="upload" class="w-4 h-4"></i> Impor / Salin
                </button>

                <button type="button" @click="$dispatch('buka-bank-soal')" class="px-3.5 py-2 rounded-xl text-xs font-semibold border border-primary text-primary hover:bg-primary/5 flex items-center gap-1.5">
                    <i data-lucide="library" class="w-4 h-4"></i> Bank Soal
                </button>
                @endif
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="card p-4 border-l-4 !border-l-rose-500 text-sm text-rose-700 dark:text-rose-300">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Modal Impor / Salin Soal --}}
    @if($ujian->status === 'draft')
    <div x-data="modalImportSoal({{ Js::from(route('ujian.soal.sumber', $ujian)) }})"
         x-show="open" x-cloak
         @buka-modal-import.window="buka()"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         @click.self="open = false">
        <div class="card w-full max-w-xl max-h-[88vh] flex flex-col p-6 shadow-2xl relative" x-show="open" x-transition>
            <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-700 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 grid place-items-center">
                        <i data-lucide="import" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-800 dark:text-slate-100 text-base">Impor & Salin Soal</h2>
                        <p class="text-xs text-slate-400">Target Ujian: {{ $ujian->judul }} ({{ $ujian->pelajaran?->nama }})</p>
                    </div>
                </div>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b border-slate-200 dark:border-slate-700 mb-4">
                <button type="button" @click="tab = 'upload'"
                        :class="tab === 'upload' ? 'border-b-2 border-emerald-600 text-emerald-600 font-bold' : 'text-slate-500 hover:text-slate-700'"
                        class="pb-2.5 px-3 text-xs flex items-center gap-1.5 transition">
                    <i data-lucide="file-up" class="w-4 h-4"></i> Upload Berkas (Excel / JSON)
                </button>
                <button type="button" @click="tab = 'salin'; if(daftarSumber.length === 0) muatSumber();"
                        :class="tab === 'salin' ? 'border-b-2 border-emerald-600 text-emerald-600 font-bold' : 'text-slate-500 hover:text-slate-700'"
                        class="pb-2.5 px-3 text-xs flex items-center gap-1.5 transition">
                    <i data-lucide="copy" class="w-4 h-4"></i> Salin dari Ujian Lain
                </button>
            </div>

            {{-- Tab 1: Upload File --}}
            <div x-show="tab === 'upload'" class="space-y-4 overflow-y-auto pr-1">
                <div class="bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Belum punya format berkas?</p>
                        <p class="text-xs text-slate-400">Unduh template Excel resmi lengkap dengan panduan & contoh soal.</p>
                    </div>
                    <a href="{{ route('ujian.soal.template') }}" class="btn-secondary px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 flex-shrink-0">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i> Unduh Template Excel
                    </a>
                </div>

                <form method="POST" action="{{ route('ujian.soal.import', $ujian) }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Pilih Berkas Soal</label>
                        <input type="file" name="file" required accept=".xlsx,.xls,.csv,.json"
                               class="form-input w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 dark:file:bg-emerald-950/40 dark:file:text-emerald-300">
                        <p class="text-[11px] text-slate-400 mt-1">Format yang didukung: Excel (.xlsx, .xls), CSV (.csv), atau JSON (.json). Maks. 10 MB.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-2">Mode Penyimpanan</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="modeUpload === 'append' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700'">
                                <input type="radio" name="mode" value="append" x-model="modeUpload" class="text-emerald-600 focus:ring-emerald-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold block text-slate-800 dark:text-slate-200">Tambahkan (Append)</span>
                                    <span class="text-[11px] text-slate-400">Soal baru ditambahkan ke akhir daftar soal yang sudah ada.</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="modeUpload === 'replace' ? 'border-rose-500 bg-rose-50/40 dark:bg-rose-950/20' : 'border-slate-200 dark:border-slate-700'">
                                <input type="radio" name="mode" value="replace" x-model="modeUpload" class="text-rose-600 focus:ring-rose-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold block text-rose-700 dark:text-rose-400">Ganti Semua (Replace)</span>
                                    <span class="text-[11px] text-slate-400">Hapus seluruh soal yang ada saat ini dan ganti dengan berkas ini.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn-secondary px-4 py-2 rounded-xl text-xs font-semibold">Batal</button>
                        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5">
                            <i data-lucide="upload-cloud" class="w-4 h-4"></i> Impor Berkas
                        </button>
                    </div>
                </form>
            </div>

            {{-- Tab 2: Salin dari Ujian Lain --}}
            <div x-show="tab === 'salin'" class="space-y-4 overflow-y-auto pr-1">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Salin seluruh soal secara instan dari ujian lain di sistem (bisa dari mata pelajaran yang sama maupun mata pelajaran berbeda).
                </p>

                <template x-if="loadingSumber">
                    <div class="py-8 text-center text-slate-400">
                        <i data-lucide="loader-2" class="w-5 h-5 animate-spin mx-auto mb-2 text-primary"></i>
                        <p class="text-xs">Memuat daftar ujian...</p>
                    </div>
                </template>

                <template x-if="!loadingSumber && daftarSumber.length === 0">
                    <div class="py-8 text-center text-slate-400 bg-slate-50 dark:bg-slate-800/40 rounded-xl p-4">
                        <i data-lucide="file-question" class="w-6 h-6 mx-auto mb-1 text-slate-400"></i>
                        <p class="text-xs">Tidak ada ujian lain yang tersedia untuk disalin.</p>
                    </div>
                </template>

                <form x-show="!loadingSumber && daftarSumber.length > 0" method="POST" action="{{ route('ujian.soal.salin', $ujian) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">Pilih Ujian Sumber</label>
                        <select name="id_ujian_sumber" required x-model="sumberTerpilih" class="form-select w-full text-xs">
                            <option value="">-- Pilih Ujian Sumber --</option>
                            <template x-for="u in daftarSumber" :key="u.uuid">
                                <option :value="u.uuid" x-text="u.judul + ' (' + u.pelajaran + ') — ' + u.jumlah_soal + ' Soal'"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-2">Mode Penyalinan</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="modeSalin === 'append' ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-slate-700'">
                                <input type="radio" name="mode" value="append" x-model="modeSalin" class="text-emerald-600 focus:ring-emerald-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold block text-slate-800 dark:text-slate-200">Tambahkan (Append)</span>
                                    <span class="text-[11px] text-slate-400">Soal disalin ke akhir daftar soal saat ini.</span>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition"
                                   :class="modeSalin === 'replace' ? 'border-rose-500 bg-rose-50/40 dark:bg-rose-950/20' : 'border-slate-200 dark:border-slate-700'">
                                <input type="radio" name="mode" value="replace" x-model="modeSalin" class="text-rose-600 focus:ring-rose-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold block text-rose-700 dark:text-rose-400">Ganti Semua (Replace)</span>
                                    <span class="text-[11px] text-slate-400">Hapus seluruh soal ujian saat ini dan ganti dengan soal dari ujian sumber.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2">
                        <button type="button" @click="open = false" class="btn-secondary px-4 py-2 rounded-xl text-xs font-semibold">Batal</button>
                        <button type="submit" :disabled="!sumberTerpilih" class="btn-primary px-5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 disabled:opacity-40">
                            <i data-lucide="copy-check" class="w-4 h-4"></i> Salin Soal Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
                poin: {{ $soal->poin }},
                poin_salah: {{ isset($soal->meta['poin_salah']) ? $soal->meta['poin_salah'] : "''" }},
                skor_mode: {{ Js::from($soal->skor_mode ?? 'all_or_nothing') }},
                open: false,
                fetchUrl: '{{ route('ujian.soal.data', [$ujian, $soal]) }}'
              })"
             x-init="_rootEl = $el">
            <div class="flex items-center justify-between gap-3 cursor-pointer" @click="toggle()">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 grid place-items-center text-xs font-bold flex-shrink-0">
                        <template x-if="loadingData"><i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i></template>
                        <template x-if="!loadingData"><span>{{ $i + 1 }}</span></template>
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400">{{ $soal->typeLabel() }} · {{ $soal->poinEfektif() }} poin</p>
                        <p class="text-sm font-medium truncate">{{ Str::limit(strip_tags($soal->teks_soal), 80) }}</p>
                    </div>
                </div>
                <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 flex-shrink-0 transition" :class="open && 'rotate-180'"></i>
            </div>

            <form @submit="encodeForm" x-show="open" x-cloak x-transition method="POST" action="{{ route('ujian.soal.update', [$ujian, $soal]) }}" class="mt-4 space-y-3 border-t border-slate-100 dark:border-slate-700 pt-4">
                <template x-if="rendered">
                    <div>
                        @csrf
                        @include('ujian.partials.soal-fields')
                        <div class="flex gap-2 pt-1">
                            <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold">Simpan Perubahan</button>
                            <button type="button" @click="if(confirm('Simpan salinan soal ini ke Bank Soal ({{ $ujian->pelajaran?->nama }})?')) document.getElementById('simpan-bank-{{ $soal->uuid }}').submit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-primary hover:bg-primary/5">Simpan ke Bank</button>
                            <button type="button" @click="if(confirm('Hapus soal ini?')) document.getElementById('hapus-soal-{{ $soal->uuid }}').submit()" class="px-4 py-2 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 ml-auto">Hapus</button>
                        </div>
                    </div>
                </template>
            </form>
        </div>
        <form id="hapus-soal-{{ $soal->uuid }}" method="POST" action="{{ route('ujian.soal.destroy', [$ujian, $soal]) }}" class="hidden">@csrf @method('DELETE')</form>
        <form id="simpan-bank-{{ $soal->uuid }}" method="POST" action="{{ route('ujian.soal.simpanBank', [$ujian, $soal]) }}" class="hidden">@csrf</form>
        @endforeach
    </div>

    {{-- Tambah soal baru --}}
    <div class="card p-5" x-data="soalForm({ tipe: 'mcq', teks_soal: '', poin: 1, poin_salah: '', penjelasan: '', opsi: [{teks:'',benar:true},{teks:'',benar:false}], pasangan: [{kiri:'',kanan:''},{kiri:'',kanan:''}], kunci_esai: '', skor_mode: 'all_or_nothing', open: true })"
         x-init="_rootEl = $el; $nextTick(() => window.UjianEditor && window.UjianEditor.mountAll())">
        <h2 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-primary"></i> Tambah Soal</h2>
        <form @submit="encodeForm" method="POST" action="{{ route('ujian.soal.store', $ujian) }}" class="space-y-3">
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

function modalImportSoal(sumberUrl) {
    return {
        open: false,
        tab: 'upload',
        loadingSumber: false,
        daftarSumber: [],
        sumberTerpilih: '',
        modeUpload: 'append',
        modeSalin: 'append',
        buka() {
            this.open = true;
            if (this.daftarSumber.length === 0) this.muatSumber();
            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },
        muatSumber() {
            this.loadingSumber = true;
            fetch(sumberUrl, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(json => {
                    this.daftarSumber = json;
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                })
                .catch(err => console.error(err))
                .finally(() => { this.loadingSumber = false; });
        }
    }
}
</script>
@endpush

