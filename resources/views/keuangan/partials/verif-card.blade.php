{{--
    Kartu verifikasi satu pembayaran (boleh banyak bulan / satu batch).
    Variabel: $group (Collection<SppPembayaran>), $mode ('verify' | 'validate').
    - mode 'verify'   : tahap 1 (menunggu → terverifikasi)  → tombol "Verifikasi Bukti"
    - mode 'validate' : tahap 2 (terverifikasi → lunas)      → tombol "Validasi (Lunas)"
--}}
@php
    $first  = $group->first();
    $jumlah = $group->count();
    $total  = (int) $group->sum('nominal');
    $isVerify = $mode === 'verify';
    $aksiRoute = $isVerify ? route('keuangan.verify-batch') : route('keuangan.validate-batch');
    $aksiLabel = $isVerify ? 'Verifikasi Bukti' : 'Validasi (Lunas)';
    $aksiWarna = $isVerify ? 'blue' : 'blue';
    $konfirmasi = $isVerify
        ? "Tandai bukti {$jumlah} bulan ({$first->siswa?->nama}) sebagai TERVERIFIKASI? Pastikan nominal & bukti sesuai."
        : "Validasi {$jumlah} bulan ({$first->siswa?->nama}, total Rp ".number_format($total,0,',','.').") via rekening koran & tandai LUNAS?";
@endphp
<div class="card p-4 flex flex-col sm:flex-row gap-4"
     x-data="{
        rejectOpen: false,
        reviseOpen: false,
        ocr: {
            loading: false,
            error: '',
            open: false,
            saran: null,
            url: @js(route('keuangan.bendahara-ai.ocr', $first)),
            buktiUrl: @js($first->bukti_url),
        },
        ocrHasSaran() {
            const s = this.ocr.saran;
            if (!s) return false;
            return !!(s.nama_pengirim || s.tanggal || s.referensi || s.nominal_teks);
        },
        async bacaBukti() {
            if (this.ocr.loading || !this.ocr.buktiUrl) return;
            this.ocr.loading = true;
            this.ocr.error = '';
            this.ocr.open = false;
            this.ocr.saran = null;
            try {
                const imgRes = await fetch(this.ocr.buktiUrl, { credentials: 'same-origin' });
                if (!imgRes.ok) throw new Error('Gagal mengambil gambar bukti.');
                const blob = await imgRes.blob();
                const form = new FormData();
                form.append('bukti', blob, 'bukti.jpg');
                const r = await fetch(this.ocr.url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: form,
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.ok) {
                    this.ocr.saran = d.saran || null;
                    this.ocr.open = true;
                } else {
                    this.ocr.error = d.message || 'Gagal membaca bukti — isi manual.';
                }
            } catch (e) {
                this.ocr.error = e?.message || 'Gagal membaca bukti — isi manual.';
            } finally {
                this.ocr.loading = false;
            }
        },
        terapkanSaran() {
            if (!this.ocr.saran) return;
            this.reviseOpen = true;
            this.rejectOpen = false;
            const form = this.$el.querySelector('form[action*=revise]');
            if (!form) return;
            if (this.ocr.saran.tanggal) {
                const tgl = form.querySelector('input[name=tanggal_bayar]');
                if (tgl) tgl.value = this.ocr.saran.tanggal;
            }
            if (this.ocr.saran.referensi) {
                const bank = form.querySelector('input[name=bank]');
                if (bank && !bank.value.trim()) bank.value = this.ocr.saran.referensi;
            }
        },
     }">
    {{-- Bukti --}}
    <a href="{{ $first->bukti_url }}" target="_blank" class="block w-full sm:w-40 flex-shrink-0 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 hover:opacity-90">
        @if($first->bukti_url)
            <img src="{{ $first->bukti_url }}" alt="Bukti" class="w-full h-40 object-cover bg-slate-50 dark:bg-slate-900">
        @else
            <div class="w-full h-40 grid place-items-center text-slate-300"><i data-lucide="image-off" class="w-8 h-8"></i></div>
        @endif
    </a>

    {{-- Info --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="font-bold text-slate-800 dark:text-slate-100">{{ $first->siswa?->nama }}</p>
                <p class="text-xs text-slate-400">{{ $first->siswa?->kelas?->nama_lengkap }} · NIS {{ $first->siswa?->nis }}</p>
            </div>
            @if($jumlah > 1)
                <span class="badge bg-primary/15 text-primary flex items-center gap-1"><i data-lucide="layers" class="w-3 h-3"></i> {{ $jumlah }} bulan</span>
            @endif
        </div>

        <div class="flex flex-wrap gap-1.5 mt-2.5">
            @foreach($group->sortBy('bulan') as $p)
                <span class="badge {{ $isVerify ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300' }}">{{ $p->label_bulan }}</span>
            @endforeach
        </div>

        @if(!empty($anomaliFlags))
        <div class="flex flex-wrap gap-1.5 mt-2">
            @foreach($anomaliFlags as $flag)
                <span class="badge text-[10px] {{ ($flag['tingkat'] ?? '') === 'tinggi' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}" title="{{ $flag['label'] ?? '' }}">
                    <i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> {{ $flag['label'] ?? $flag['kode'] }}
                </span>
            @endforeach
        </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 text-sm">
            <div><span class="text-slate-400 text-xs block">Total</span><span class="font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($total,0,',','.') }}</span></div>
            <div><span class="text-slate-400 text-xs block">Bank / Metode</span><span class="font-medium text-slate-700 dark:text-slate-200">{{ $first->bank ?? '-' }}</span></div>
            <div><span class="text-slate-400 text-xs block">Tgl Bayar</span><span class="font-medium text-slate-700 dark:text-slate-200">{{ optional($first->tanggal_bayar)->format('d/m/Y') ?? '-' }}</span></div>
            <div><span class="text-slate-400 text-xs block">{{ $isVerify ? 'Diunggah' : 'Diverifikasi' }}</span><span class="font-medium text-slate-700 dark:text-slate-200">{{ optional($isVerify ? $first->updated_at : $first->diverifikasi_pada)?->diffForHumans() ?? '-' }}</span></div>
        </div>

        @unless($isVerify)
        <p class="text-xs text-sky-600 dark:text-sky-400 mt-2 flex items-center gap-1"><i data-lucide="info" class="w-3.5 h-3.5"></i> Bukti sudah diverifikasi. Validasi dana masuk lewat rekening koran bank sebelum menandai lunas.</p>
        @endunless

        @if($first->bukti_url)
        <p x-show="ocr.error" x-cloak class="text-xs text-rose-600 dark:text-rose-400 mt-2 flex items-center gap-1">
            <i data-lucide="alert-circle" class="w-3.5 h-3.5 flex-shrink-0"></i>
            <span x-text="ocr.error"></span>
        </p>

        <div x-show="ocr.open" x-transition x-cloak class="mt-3 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 space-y-2">
            <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                Saran OCR — periksa manual sebelum verifikasi
            </p>
            <p x-show="!ocrHasSaran()" class="text-xs text-slate-500 dark:text-slate-400">
                Tidak ada teks terbaca dari bukti. Isi nominal, tanggal, dan bank secara manual.
            </p>
            <div x-show="ocrHasSaran()" class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                <div>
                    <span class="text-slate-400 text-xs block">Pengirim</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200" x-text="ocr.saran?.nama_pengirim || '—'"></span>
                </div>
                <div>
                    <span class="text-slate-400 text-xs block">Tanggal</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200" x-text="ocr.saran?.tanggal || '—'"></span>
                </div>
                <div>
                    <span class="text-slate-400 text-xs block">Referensi</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200" x-text="ocr.saran?.referensi || '—'"></span>
                </div>
                <div>
                    <span class="text-slate-400 text-xs block">Nominal (teks)</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200" x-text="ocr.saran?.nominal_teks || '—'"></span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <button type="button" @click="terapkanSaran()" x-show="ocrHasSaran()"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white hover:bg-indigo-700">
                    <i data-lucide="arrow-down-to-line" class="w-3.5 h-3.5"></i> Gunakan ke form revisi
                </button>
                <button type="button" @click="ocr.open=false"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                    Tutup
                </button>
            </div>
        </div>
        @endif

        {{-- Aksi --}}
        <div class="flex flex-wrap gap-2 mt-4">
            @if($first->bukti_url)
            <button type="button" @click="bacaBukti()" :disabled="ocr.loading"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold border border-indigo-200 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 disabled:opacity-50">
                <i data-lucide="scan-text" class="w-4 h-4" :class="ocr.loading && 'animate-pulse'"></i>
                <span x-text="ocr.loading ? 'Membaca bukti…' : 'Baca Bukti'"></span>
            </button>
            @endif
            <form method="POST" action="{{ $aksiRoute }}"
                  onsubmit="return confirmAction(this, '{{ addslashes($konfirmasi) }}', '{{ $aksiWarna }}')">
                @csrf
                @foreach($group as $p)<input type="hidden" name="ids[]" value="{{ $p->uuid }}">@endforeach
                <button class="btn-primary flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-bold">
                    <i data-lucide="{{ $isVerify ? 'badge-check' : 'check-check' }}" class="w-4 h-4"></i> {{ $aksiLabel }} {{ $jumlah > 1 ? '· '.$jumlah.' bln' : '' }}
                </button>
            </form>
            <button @click="reviseOpen=!reviseOpen; rejectOpen=false" type="button" class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold border border-amber-200 dark:border-amber-700 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20">
                <i data-lucide="pencil" class="w-4 h-4"></i> Revisi
            </button>
            <button @click="rejectOpen=!rejectOpen; reviseOpen=false" type="button" class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold border border-rose-200 dark:border-rose-700 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                <i data-lucide="x" class="w-4 h-4"></i> Tolak
            </button>
        </div>

        {{-- Form revisi (perbaiki nominal/tanggal/bank) --}}
        <form x-show="reviseOpen" x-transition x-cloak method="POST" action="{{ route('keuangan.revise-batch') }}" class="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 space-y-2.5">
            @csrf
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Revisi nominal per bulan</p>
            @foreach($group->sortBy('bulan') as $p)
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-600 dark:text-slate-300 w-28 flex-shrink-0">{{ $p->label_bulan }}</span>
                <div class="relative flex-1">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400">Rp</span>
                    <input type="number" name="nominal[{{ $p->uuid }}]" value="{{ (int) $p->nominal }}" min="0" class="form-input text-sm !pl-8">
                </div>
            </div>
            @endforeach
            <div class="grid grid-cols-2 gap-2 pt-1">
                <div>
                    <label class="form-label !text-[11px]">Tgl Bayar</label>
                    <input type="date" name="tanggal_bayar" value="{{ optional($first->tanggal_bayar)->format('Y-m-d') }}" class="form-input text-sm">
                </div>
                <div>
                    <label class="form-label !text-[11px]">Bank / Metode</label>
                    <input type="text" name="bank" value="{{ $first->bank }}" maxlength="60" class="form-input text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="reviseOpen=false" class="px-3 py-1.5 rounded-lg text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
                <button class="btn-primary px-4 py-1.5 rounded-lg text-sm font-bold flex items-center gap-1.5"><i data-lucide="save" class="w-3.5 h-3.5"></i> Simpan Revisi</button>
            </div>
        </form>

        {{-- Form tolak --}}
        <form x-show="rejectOpen" x-transition x-cloak method="POST" action="{{ route('keuangan.reject-batch') }}" class="mt-3 flex flex-col sm:flex-row gap-2">
            @csrf
            @foreach($group as $p)<input type="hidden" name="ids[]" value="{{ $p->uuid }}">@endforeach
            <input type="text" name="catatan" required maxlength="500" placeholder="Alasan penolakan (mis. nominal kurang / dana tidak masuk)" class="form-input text-sm flex-1 min-w-0">
            <button class="w-full sm:w-auto shrink-0 px-4 py-2 rounded-xl text-sm font-bold text-white bg-rose-500 hover:bg-rose-600">Tolak {{ $jumlah > 1 ? 'Semua' : '' }}</button>
        </form>
    </div>
</div>
