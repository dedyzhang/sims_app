@extends('layouts.app')
@section('title', ($payable ? 'Bayar SPP ' : 'Bukti SPP ') . $pembayaran->label_bulan)

@section('content')
<div x-data="payForm()" class="space-y-5 max-w-5xl mx-auto">

    {{-- Header --}}
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.tagihan.index', ['anak'=>$siswa->uuid]) }}" class="hover:underline">Tagihan SPP</a> / {{ $pembayaran->label_bulan }}</nav>
        <h1 class="page-title">{{ $payable ? 'Pembayaran' : 'Bukti Pembayaran' }} SPP {{ $pembayaran->label_bulan }}</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $siswa->nama }} · {{ $siswa->kelas?->nama_lengkap }}</p>
    </div>

    {{-- ================= MODE STRUK (sudah bayar / menunggu) ================= --}}
    @unless($payable)
        <div class="max-w-2xl mx-auto space-y-5">
            {{-- Nominal --}}
            <div class="card p-5 text-center bg-gradient-to-br from-primary/10 to-transparent">
                <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">Nominal</p>
                <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1">Rp {{ number_format($pembayaran->nominal,0,',','.') }}</p>
                @if($va)
                    <div x-data="{copied:false}" class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <span class="text-xs text-slate-400">VA</span>
                        <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ $va }}</span>
                        <button type="button" @click="navigator.clipboard.writeText('{{ $va }}'); copied=true; setTimeout(()=>copied=false,1500)" class="text-primary"><i data-lucide="copy" class="w-4 h-4" x-show="!copied"></i><i data-lucide="check" class="w-4 h-4 text-emerald-500" x-show="copied" x-cloak></i></button>
                    </div>
                @endif
            </div>

            {{-- Langkah alur status --}}
            @php
                $tahap = ['menunggu'=>1, 'terverifikasi'=>2, 'lunas'=>3][$pembayaran->status] ?? 1;
            @endphp
            <div class="card p-4">
                <div class="flex items-center gap-1 text-[11px] sm:text-xs">
                    @foreach([1=>['Menunggu','clock','amber'], 2=>['Terverifikasi','badge-check','sky'], 3=>['Lunas','check-circle-2','emerald']] as $no => [$lbl,$ic,$clr])
                        <div class="flex items-center gap-1.5 {{ $tahap >= $no ? 'text-'.$clr.'-600 dark:text-'.$clr.'-400 font-semibold' : 'text-slate-300 dark:text-slate-600' }}">
                            <span class="w-6 h-6 rounded-full grid place-items-center {{ $tahap >= $no ? 'bg-'.$clr.'-100 dark:bg-'.$clr.'-900' : 'bg-slate-100 dark:bg-slate-700' }}">
                                @if($tahap > $no)<i data-lucide="check" class="w-3 h-3"></i>@else{{ $no }}@endif
                            </span>
                            <span class="hidden sm:inline">{{ $lbl }}</span>
                        </div>
                        @if($no < 3)<div class="flex-1 h-0.5 {{ $tahap > $no ? 'bg-primary/40' : 'bg-slate-100 dark:bg-slate-700' }}"></div>@endif
                    @endforeach
                </div>
            </div>

            @if($pembayaran->status==='lunas')
            <div class="card p-4 border-l-4 border-emerald-400 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">Lunas ✅</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Pembayaran sudah divalidasi lewat rekening koran bank.
                        @if($pembayaran->diverifikasi_pada) Selesai {{ $pembayaran->diverifikasi_pada->format('d M Y, H:i') }} @endif
                        @if($pembayaran->verifikator) oleh {{ $pembayaran->verifikator->name }} @endif
                    </p>
                </div>
            </div>
            @elseif($pembayaran->status==='terverifikasi')
            <div class="card p-4 border-l-4 border-sky-400 flex items-start gap-3">
                <i data-lucide="badge-check" class="w-5 h-5 text-sky-500 flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">Sudah terverifikasi</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Bukti pembayaranmu sudah dicek bendahara. Tahap terakhir: bendahara memvalidasi dana masuk lewat rekening koran resmi bank sebelum ditandai lunas. Mohon tunggu sebentar lagi 🙏</p>
                </div>
            </div>
            @else
            <div class="card p-4 border-l-4 border-amber-400 flex items-start gap-3">
                <i data-lucide="clock" class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">Menunggu verifikasi bendahara</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Bukti sudah terkirim{{ $pembayaran->bank ? ' · via '.$pembayaran->bank : '' }}. Bendahara akan memeriksa buktimu terlebih dahulu.</p>
                </div>
            </div>
            @endif

            @if($pembayaran->catatan_bendahara)
            <div class="card p-4 border-l-4 border-sky-400 flex items-start gap-3">
                <i data-lucide="message-square-text" class="w-5 h-5 text-sky-500 flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">Catatan dari bendahara</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $pembayaran->catatan_bendahara }}</p>
                </div>
            </div>
            @endif

            {{-- Bukti --}}
            <div class="card p-4">
                <p class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2 flex items-center gap-1.5"><i data-lucide="receipt-text" class="w-4 h-4"></i> Bukti Pembayaran</p>
                @if($pembayaran->bukti_url)
                    <a href="{{ $pembayaran->bukti_url }}" target="_blank" class="block rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 hover:opacity-90">
                        <img src="{{ $pembayaran->bukti_url }}" alt="Bukti pembayaran" class="w-full max-h-[420px] object-contain bg-slate-50 dark:bg-slate-900">
                        <p class="text-center text-xs text-slate-500 dark:text-slate-400 py-2 flex items-center justify-center gap-1"><i data-lucide="external-link" class="w-3 h-3"></i> Ketuk untuk perbesar</p>
                    </a>
                @else
                    <p class="text-sm text-slate-400 text-center py-6">Tidak ada gambar bukti (pembayaran ditandai langsung oleh bendahara).</p>
                @endif
                <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
                    <div><span class="text-slate-400 text-xs block">Tanggal bayar</span><span class="font-medium text-slate-700 dark:text-slate-200">{{ optional($pembayaran->tanggal_bayar)->format('d M Y') ?? '-' }}</span></div>
                    <div><span class="text-slate-400 text-xs block">Bank / Metode</span><span class="font-medium text-slate-700 dark:text-slate-200">{{ $pembayaran->bank ?? '-' }}</span></div>
                </div>
            </div>

            <a href="{{ route('keuangan.tagihan.index', ['anak'=>$siswa->uuid]) }}" class="block text-center text-sm text-primary hover:underline">← Kembali ke daftar tagihan</a>
        </div>
    @else
    {{-- ================= MODE BAYAR (belum / ditolak) ================= --}}
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
            
            <!-- Kolom Kiri: Info Tagihan & Bulan -->
            <div class="space-y-5 md:col-span-6 lg:col-span-7">
                @if($pembayaran->status==='ditolak')
                <div class="card p-4 border-l-4 border-rose-400 flex items-start gap-3">
                    <i data-lucide="x-circle" class="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-slate-100">Pembayaran sebelumnya ditolak</p>
                        <p class="text-xs text-rose-500">{{ $pembayaran->catatan ?? 'Silakan unggah ulang bukti yang benar.' }}</p>
                    </div>
                </div>
                @endif

                @if($pembayaran->catatan_bendahara)
                <div class="card p-4 border-l-4 border-sky-400 flex items-start gap-3">
                    <i data-lucide="message-square-text" class="w-5 h-5 text-sky-500 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-slate-100">Catatan dari bendahara</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $pembayaran->catatan_bendahara }}</p>
                    </div>
                </div>
                @endif

                {{-- Nominal --}}
                <div class="card p-5 text-center bg-gradient-to-br from-primary/10 to-transparent">
                    <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total yang harus dibayar</p>
                    <p class="text-3xl font-extrabold text-slate-800 dark:text-slate-100 mt-1" x-text="rupiah(total)">Rp {{ number_format($pembayaran->nominal,0,',','.') }}</p>
                    @if($pembayaran->jatuh_tempo)
                        <p class="text-xs text-rose-500 mt-1">Jatuh tempo: {{ $pembayaran->jatuh_tempo->format('d M Y') }}</p>
                    @endif
                    @if($va)
                        <div x-data="{copied:false}" class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                            <span class="text-xs text-slate-400">VA</span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ $va }}</span>
                            <button type="button" @click="navigator.clipboard.writeText('{{ $va }}'); copied=true; setTimeout(()=>copied=false,1500)" class="text-primary"><i data-lucide="copy" class="w-4 h-4" x-show="!copied"></i><i data-lucide="check" class="w-4 h-4 text-emerald-500" x-show="copied" x-cloak></i></button>
                        </div>
                    @endif
                </div>

                {{-- Bayar sekaligus bulan lain --}}
                @if($lainnya->isNotEmpty())
                <div class="card p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <i data-lucide="layers" class="w-5 h-5 text-primary"></i>
                        <h2 class="font-bold text-slate-800 dark:text-slate-100">Bayar Sekaligus</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Centang bulan lain yang ingin dibayar bersamaan. Cukup satu bukti transfer untuk semuanya.</p>

                    <label class="flex items-center justify-between p-3 rounded-xl bg-primary/5 border border-primary/20 mb-2">
                        <span class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            <i data-lucide="check-square" class="w-4 h-4 text-primary"></i> {{ $pembayaran->label_bulan }} <span class="text-[11px] font-normal text-slate-400">(bulan ini)</span>
                        </span>
                        <span class="font-bold text-slate-700 dark:text-slate-200">Rp {{ number_format($pembayaran->nominal,0,',','.') }}</span>
                    </label>

                    <div class="space-y-1.5">
                        @foreach($lainnya as $l)
                        <label class="flex items-center justify-between p-3 rounded-xl border border-slate-200 dark:border-slate-700 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/40">
                            <span class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" value="{{ $l->uuid }}" @change="toggleExtra('{{ $l->uuid }}', {{ (int)$l->nominal }}, $event.target.checked)" class="rounded text-primary">
                                {{ $l->label_bulan }}
                                @if($l->status==='ditolak')<span class="badge bg-rose-100 dark:bg-rose-900 text-rose-600 dark:text-rose-300 text-[10px]">ditolak</span>@endif
                            </span>
                            <span class="font-semibold text-slate-600 dark:text-slate-300">Rp {{ number_format($l->nominal,0,',','.') }}</span>
                        </label>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
                        <span class="text-sm font-semibold text-slate-500 dark:text-slate-400"><span x-text="count"></span> bulan dipilih</span>
                        <span class="text-lg font-extrabold text-primary" x-text="rupiah(total)"></span>
                    </div>
                </div>
                @endif
            </div>

            <!-- Kolom Kanan: Metode & Unggah Bukti -->
            <div class="space-y-5 md:col-span-6 lg:col-span-5">
                {{-- Pilih metode (accordion ala marketplace) --}}
                <div>
                    <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-2 px-1">Pilih Metode Pembayaran</h2>
                    <div class="space-y-2.5">
                        @forelse($banks as $bank)
                        <div class="card overflow-hidden">
                            <button type="button" @click="toggle('{{ $loop->index }}')" class="w-full flex items-center gap-3 p-4 text-left">
                                <span class="w-10 h-10 rounded-lg grid place-items-center text-white font-bold text-xs flex-shrink-0" style="background:{{ $bank['warna'] ?? '#64748b' }}">{{ \Illuminate\Support\Str::substr($bank['nama'],0,4) }}</span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $bank['nama'] }}</p>
                                    <p class="text-xs text-slate-400">a.n. {{ $bank['atas_nama'] ?: '—' }}</p>
                                </div>
                                <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 transition-transform" :class="opened==='{{ $loop->index }}' && 'rotate-180'"></i>
                            </button>
                            <div x-show="opened==='{{ $loop->index }}'" x-collapse x-cloak class="px-4 pb-4 border-t border-slate-100 dark:border-slate-700 pt-3 space-y-3">
                                <div x-data="{copied:false}" class="flex items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900">
                                    <div>
                                        <p class="text-[11px] text-slate-400 uppercase">No. Rekening / VA</p>
                                        <p class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ $bank['nomor'] ?: '-' }}</p>
                                    </div>
                                    <button type="button" @click="navigator.clipboard.writeText('{{ $bank['nomor'] }}'); copied=true; setTimeout(()=>copied=false,1500)" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-primary border border-primary/30 hover:bg-primary/5 flex items-center gap-1">
                                        <i data-lucide="copy" class="w-3.5 h-3.5" x-show="!copied"></i><i data-lucide="check" class="w-3.5 h-3.5" x-show="copied" x-cloak></i>
                                        <span x-text="copied ? 'Disalin' : 'Salin'"></span>
                                    </button>
                                </div>
                                @if(!empty($bank['langkah']))
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Langkah pembayaran:</p>
                                    <ol class="space-y-1.5">
                                        @foreach($bank['langkah'] as $step)
                                        <li class="flex gap-2 text-xs text-slate-600 dark:text-slate-300">
                                            <span class="w-4 h-4 rounded-full bg-primary/15 text-primary grid place-items-center font-bold flex-shrink-0 text-[10px]">{{ $loop->iteration }}</span>
                                            <span>{{ $step }}</span>
                                        </li>
                                        @endforeach
                                    </ol>
                                </div>
                                @endif
                                <button type="button" @click="pilihBank('{{ addslashes($bank['nama']) }}')" class="btn-primary w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                                    <i data-lucide="upload" class="w-4 h-4"></i> Sudah transfer? Unggah bukti
                                </button>
                            </div>
                        </div>
                        @empty
                        <div class="card p-6 text-center text-slate-400 text-sm">Belum ada metode pembayaran yang diatur. Hubungi bendahara sekolah.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Form upload bukti --}}
                <div class="card p-5 space-y-4" id="upload-bukti" x-show="banks_exist">
                    <div class="flex items-center gap-2">
                        <i data-lucide="receipt" class="w-5 h-5 text-primary"></i>
                        <h2 class="font-bold text-slate-800 dark:text-slate-100">Unggah Bukti Pembayaran</h2>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Untuk <span class="font-semibold" x-text="count"></span> bulan · total <span class="font-semibold text-primary" x-text="rupiah(total)"></span></p>

                    <form @submit.prevent="submitBukti()" class="space-y-3">
                        <div>
                            <label class="form-label">Bank / metode yang dipakai</label>
                            <select x-model="bank" required class="form-input text-sm">
                                <option value="">— pilih bank —</option>
                                @foreach($banks as $bank)
                                    <option value="{{ $bank['nama'] }}">{{ $bank['nama'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Tanggal transfer</label>
                            <input type="date" x-model="tanggal" max="{{ now()->format('Y-m-d') }}" class="form-input text-sm">
                        </div>
                        <div>
                            <label class="form-label">Foto / screenshot bukti transfer</label>
                            <label class="flex flex-col items-center justify-center gap-2 p-6 rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600 cursor-pointer hover:border-primary/50 transition" :class="preview && 'hidden'">
                                <i data-lucide="image-plus" class="w-8 h-8 text-slate-300"></i>
                                <span class="text-xs text-slate-400">Ketuk untuk pilih gambar (otomatis dikompres)</span>
                                <input type="file" accept="image/*" class="hidden" @change="pickFile($event)">
                            </label>
                            <template x-if="preview">
                                <div class="relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                                    <img :src="preview" class="w-full max-h-72 object-contain bg-slate-50 dark:bg-slate-900">
                                    <button type="button" @click="clearFile()" class="absolute top-2 right-2 w-8 h-8 rounded-full bg-slate-900/60 text-white grid place-items-center hover:bg-rose-500"><i data-lucide="x" class="w-4 h-4"></i></button>
                                    <p class="text-center text-xs text-slate-500 dark:text-slate-400 py-1.5" x-text="sizeLabel"></p>
                                </div>
                            </template>
                        </div>
                        <button type="submit" :disabled="!blob || !bank || sending" class="btn-primary w-full py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="sending"></i>
                            <i data-lucide="send" class="w-4 h-4" x-show="!sending"></i>
                            <span x-text="sending ? 'Mengirim...' : ('Kirim Bukti (' + count + ' bulan)')"></span>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    @endunless
</div>
@endsection

@push('scripts')
<script>
function payForm() {
    return {
        opened: @json(($payable ?? false) && count($banks) ? '0' : ''),
        banks_exist: @json(count($banks) > 0),
        baseNominal: {{ (int) $pembayaran->nominal }},
        extras: {},               // uuid => nominal (yang dicentang)
        bank: '',
        tanggal: '{{ now()->format('Y-m-d') }}',
        blob: null,
        preview: '',
        sizeLabel: '',
        sending: false,

        get count() { return 1 + Object.keys(this.extras).length; },
        get total() { return this.baseNominal + Object.values(this.extras).reduce((a,b)=>a+b, 0); },

        rupiah(n) { return 'Rp ' + (n||0).toLocaleString('id-ID'); },
        toggleExtra(uuid, nominal, checked) {
            if (checked) this.extras[uuid] = nominal; else delete this.extras[uuid];
        },
        toggle(i) { this.opened = this.opened === i ? '' : i; this.$nextTick(()=>lucide.createIcons()); },
        pilihBank(nama) {
            this.bank = nama;
            document.getElementById('upload-bukti')?.scrollIntoView({ behavior:'smooth', block:'center' });
        },
        async pickFile(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            try {
                this.blob = await this.compress(file);
                if (this.preview) URL.revokeObjectURL(this.preview);
                this.preview = URL.createObjectURL(this.blob);
                this.sizeLabel = (this.blob.size/1024).toFixed(0) + ' KB (terkompres)';
                this.$nextTick(()=>lucide.createIcons());
            } catch (_) {
                $.alert({ title:'Gagal', content:'Gagal memproses gambar. Coba gambar lain.', type:'red' });
            }
        },
        clearFile() {
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview=''; this.blob=null; this.sizeLabel='';
        },
        compress(file) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => {
                    const max = 1280;
                    let { width:w, height:h } = img;
                    if (w > max || h > max) { const r = Math.min(max/w, max/h); w = Math.round(w*r); h = Math.round(h*r); }
                    const cv = document.createElement('canvas');
                    cv.width = w; cv.height = h;
                    cv.getContext('2d').drawImage(img, 0, 0, w, h);
                    cv.toBlob(b => b ? resolve(b) : reject(new Error('no blob')), 'image/jpeg', 0.7);
                };
                img.onerror = reject;
                img.src = URL.createObjectURL(file);
            });
        },
        async submitBukti() {
            if (!this.blob || !this.bank) return;
            this.sending = true;
            try {
                const fd = new FormData();
                fd.append('bank', this.bank);
                fd.append('tanggal_bayar', this.tanggal || '');
                fd.append('bukti', this.blob, 'bukti-spp.jpg');
                Object.keys(this.extras).forEach(uuid => fd.append('bulan_lain[]', uuid));
                const res = await fetch('{{ route('keuangan.tagihan.upload', $pembayaran) }}', {
                    method:'POST',
                    headers:{ 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content'), 'Accept':'application/json' },
                    body: fd,
                });
                if (res.redirected) { window.location = res.url; return; }
                if (!res.ok) throw new Error('HTTP '+res.status);
                window.location = '{{ route('keuangan.tagihan.index', ['anak'=>$siswa->uuid]) }}';
            } catch (_) {
                this.sending = false;
                $.alert({ title:'Gagal', content:'Gagal mengirim bukti, coba lagi.', type:'red' });
            }
        },
    }
}
</script>
@endpush
