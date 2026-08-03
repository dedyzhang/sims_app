@extends('layouts.app')
@section('title', 'SPP — ' . $kelas->nama_lengkap)

@push('styles')
<style>
    .spp-cell { transition: all .12s; }
    .spp-cell:hover { transform: scale(1.06); z-index: 1; }
    .spp-belum    { background:#f1f5f9; color:#64748b; }
    .dark .spp-belum   { background:#334155; color:#94a3b8; }
    .spp-menunggu { background:#fef3c7; color:#b45309; }
    .dark .spp-menunggu{ background:#78350f; color:#fde68a; }
    .spp-terverifikasi { background:#e0f2fe; color:#0369a1; }
    .dark .spp-terverifikasi { background:#0c4a6e; color:#bae6fd; }
    .spp-lunas    { background:#d1fae5; color:#047857; }
    .dark .spp-lunas   { background:#064e3b; color:#6ee7b7; }
    .spp-ditolak  { background:#fee2e2; color:#b91c1c; }
    .dark .spp-ditolak { background:#7f1d1d; color:#fecaca; }
    .spp-grid th, .spp-grid td { white-space: nowrap; }
    .spp-sticky { position: sticky; left: 0; z-index: 2; }
</style>
@endpush

@section('content')
<div x-data="sppGrid()" class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> / {{ $kelas->nama_lengkap }}</nav>
            <h1 class="page-title">{{ $kelas->nama_lengkap }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Tahun Ajaran {{ $ta }} · {{ count($rows) }} siswa · klik sel untuk ubah status / nominal / tanggal</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('keuangan.kelas.pengaturan', ['kelas'=>$kelas->uuid,'ta'=>$ta]) }}"
               class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-primary/40 text-primary hover:bg-primary/5">
                <i data-lucide="wallet-cards" class="w-4 h-4"></i> <span class="hidden sm:inline">Atur VA &amp; Nominal</span>
            </a>
            <form method="GET" class="flex items-center gap-2">
                <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                    @foreach($taOptions as $opt)
                        <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Legenda --}}
    <div class="flex flex-wrap gap-3 text-xs">
        <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded spp-lunas inline-block"></span> Lunas</span>
        <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded spp-terverifikasi inline-block"></span> Terverifikasi</span>
        <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded spp-menunggu inline-block"></span> Menunggu verifikasi</span>
        <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded spp-ditolak inline-block"></span> Ditolak</span>
        <span class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded spp-belum inline-block"></span> Belum bayar</span>
    </div>

    {{-- Grid --}}
    <div class="card p-0 overflow-x-auto">
        <table class="spp-grid w-full text-sm border-collapse">
            <thead>
                <tr class="text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <th class="spp-sticky bg-white dark:bg-slate-800 text-left font-semibold px-4 py-3">Siswa</th>
                    @foreach($bulanList as $b)
                        <th class="px-2 py-3 text-center font-semibold text-[11px]">{{ \Illuminate\Support\Str::substr($b['label'],0,3) }}<br><span class="text-slate-300 dark:text-slate-500 font-normal">'{{ substr($b['year'],2) }}</span></th>
                    @endforeach
                    <th class="px-3 py-3 text-center font-semibold">Belum Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                @php $s = $row['siswa']; @endphp
                <tr class="border-b border-slate-100 dark:border-slate-700/60 hover:bg-slate-50/60 dark:hover:bg-slate-700/30">
                    <td class="spp-sticky bg-white dark:bg-slate-800 px-4 py-2">
                        <p class="font-medium text-slate-700 dark:text-slate-200">{{ $s->nama }}</p>
                        <p class="text-[11px] text-slate-400">NIS {{ $s->nis }}</p>
                    </td>
                    @php
                        $unpaidTotal = 0;
                        $nowStart = \Illuminate\Support\Carbon::now()->startOfMonth();
                    @endphp
                    @foreach($bulanList as $b)
                        @php
                            $p = $row['bayar'][$b['idx']] ?? null;
                            if ($p && in_array($p->status, ['belum', 'ditolak'])) {
                                $tglBulan = \App\Support\TahunAjaran::tanggal($ta, $b['idx'])->startOfMonth();
                                if ($tglBulan <= $nowStart) {
                                    $unpaidTotal += $p->nominal;
                                }
                            }
                            $cellData = $p ? [
                                'uuid'          => $p->uuid,
                                'id_siswa'      => $s->uuid,
                                'siswa'         => $s->nama,
                                'bulan'         => $p->bulan,
                                'bulan_label'   => $b['label'] . ' ' . $b['year'],
                                'status'        => $p->status,
                                'nominal'       => (int) $p->nominal,
                                'tanggal_bayar' => optional($p->tanggal_bayar)->format('Y-m-d'),
                                'catatan'       => $p->catatan,
                                'catatan_bendahara' => $p->catatan_bendahara,
                                'bukti'         => $p->bukti_url,
                                'bank'          => $p->bank,
                            ] : null;
                        @endphp
                        <td class="px-1 py-1 text-center">
                            @if($p)
                            <button type="button"
                                    class="spp-cell w-9 h-9 rounded-lg relative mx-auto spp-{{ $p->status }}"
                                    title="{{ $b['label'] }} {{ $b['year'] }} — {{ ucfirst($p->status) }}{{ $p->tanggal_bayar ? ' (' . $p->tanggal_bayar->format('d M Y') . ')' : '' }}"
                                    @click="openCell({{ \Illuminate\Support\Js::from($cellData) }})">
                                @if($p->tanggal_bayar && $p->status !== 'belum')
                                    <div class="absolute inset-0 p-1 font-bold text-[9.5px] leading-none">
                                        <span class="absolute top-1 left-1.5">{{ $p->tanggal_bayar->format('d') }}</span>
                                        <svg class="absolute inset-0 w-full h-full text-current opacity-15 pointer-events-none" viewBox="0 0 36 36" fill="none">
                                            <line x1="24" y1="12" x2="12" y2="24" stroke="currentColor" stroke-width="0.8"/>
                                        </svg>
                                        <span class="absolute bottom-1 right-1.5">{{ $p->tanggal_bayar->format('m') }}</span>
                                    </div>
                                @else
                                    <div class="absolute inset-0 grid place-items-center">
                                        @switch($p->status)
                                            @case('lunas')<i data-lucide="check" class="w-4 h-4"></i>@break
                                            @case('terverifikasi')<i data-lucide="badge-check" class="w-4 h-4"></i>@break
                                            @case('menunggu')<i data-lucide="clock" class="w-4 h-4"></i>@break
                                            @case('ditolak')<i data-lucide="x" class="w-4 h-4"></i>@break
                                            @default<span class="text-[11px]">—</span>
                                        @endswitch
                                    </div>
                                @endif
                            </button>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-3 py-2 text-center">
                        @if($unpaidTotal === 0)
                            <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 font-semibold">Rp 0</span>
                        @else
                            <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 font-semibold">Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal editor sel --}}
    <div x-show="open" x-transition.opacity style="display:none" class="fixed inset-0 z-[9990] grid place-items-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="open=false">
        <div class="card !rounded-2xl w-full max-w-md lg:max-w-4xl p-5 space-y-4 max-h-[92vh] overflow-y-auto" @click.stop>
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-bold text-slate-800 dark:text-slate-100" x-text="cell.siswa"></h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400" x-text="cell.bulan_label"></p>
                </div>
                <button @click="open=false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            {{-- Desktop: 2 kolom berdampingan agar tak perlu scroll. Mobile: tetap 1 kolom (default, tanpa prefix lg:). --}}
            <div class="space-y-4 lg:space-y-0 lg:grid lg:grid-cols-5 lg:gap-5">
                <div class="lg:col-span-2 space-y-4">
                    {{-- Bukti --}}
                    <template x-if="cell.bukti">
                        <a :href="cell.bukti" target="_blank" class="block rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 hover:opacity-90">
                            <img :src="cell.bukti" alt="Bukti" class="w-full max-h-48 object-contain bg-slate-50 dark:bg-slate-900">
                            <p class="text-center text-xs text-slate-500 dark:text-slate-400 py-1.5 flex items-center justify-center gap-1"><i data-lucide="external-link" class="w-3 h-3"></i> Lihat bukti <span x-show="cell.bank" x-text="'· '+cell.bank"></span></p>
                        </a>
                    </template>

                    <div>
                        <label class="form-label mb-2">Status</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200 cursor-pointer p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50" :class="cell.status === 'belum' && 'bg-primary/5 border-primary text-primary font-semibold'">
                                <input type="radio" value="belum" x-model="cell.status" class="text-primary focus:ring-primary border-slate-300 dark:border-slate-600">
                                <span>Belum bayar</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200 cursor-pointer p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50" :class="cell.status === 'menunggu' && 'bg-primary/5 border-primary text-primary font-semibold'">
                                <input type="radio" value="menunggu" x-model="cell.status" class="text-primary focus:ring-primary border-slate-300 dark:border-slate-600">
                                <span>Menunggu</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200 cursor-pointer p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50" :class="cell.status === 'terverifikasi' && 'bg-primary/5 border-primary text-primary font-semibold'">
                                <input type="radio" value="terverifikasi" x-model="cell.status" class="text-primary focus:ring-primary border-slate-300 dark:border-slate-600">
                                <span>Terverifikasi</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200 cursor-pointer p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50" :class="cell.status === 'lunas' && 'bg-primary/5 border-primary text-primary font-semibold'">
                                <input type="radio" value="lunas" x-model="cell.status" class="text-primary focus:ring-primary border-slate-300 dark:border-slate-600">
                                <span>Lunas</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200 cursor-pointer p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 col-span-2" :class="cell.status === 'ditolak' && 'bg-primary/5 border-primary text-primary font-semibold'">
                                <input type="radio" value="ditolak" x-model="cell.status" class="text-primary focus:ring-primary border-slate-300 dark:border-slate-600">
                                <span>Ditolak</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-3 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Nominal (Rp)</label>
                            <input type="number" min="0" x-model.number="cell.nominal" class="form-input text-sm">
                        </div>
                        <div>
                            <label class="form-label">Tgl Bayar <span class="text-rose-500" x-show="cell.status !== 'belum'">*</span></label>
                            <input type="date" x-model="cell.tanggal_bayar" class="form-input text-sm">
                        </div>
                    </div>
                    <div x-show="cell.status==='ditolak'">
                        <label class="form-label">Catatan / alasan tolak</label>
                        <input type="text" x-model="cell.catatan" maxlength="500" class="form-input text-sm" placeholder="mis. Nominal kurang / bukti buram">
                    </div>
                    <div>
                        <label class="form-label">Catatan untuk orang tua (opsional)</label>
                        <textarea x-model="cell.catatan_bendahara" maxlength="500" rows="2" class="form-input text-sm" placeholder="mis. Sudah dapat potongan yatim piatu / termasuk biaya study tour bulan ini"></textarea>
                        <p class="text-[11px] text-slate-400 mt-1">Tampil ke orang tua di halaman tagihan bulan ini, apa pun statusnya. Tidak ikut terhapus saat status diubah.</p>
                    </div>

                    {{-- Pilihan Bulan Pembayaran (Bulk) --}}
                    <div class="border-t border-slate-100 dark:border-slate-700/60 pt-3">
                        <div class="flex justify-between items-center mb-2">
                            <label class="form-label !mb-0 font-semibold text-slate-700 dark:text-slate-300">Terapkan untuk Bulan:</label>
                            <div class="flex gap-2 text-[11px]">
                                <button type="button" @click="selectAll()" class="text-primary hover:underline font-medium">Pilih Semua</button>
                                <span class="text-slate-300 dark:text-slate-600">|</span>
                                <button type="button" @click="resetSelection()" class="text-slate-500 hover:underline font-medium">Reset</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <template x-for="b in bulanList" :key="b.idx">
                                <label class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400 cursor-pointer p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                                       :class="selectedBulans.includes(b.idx) ? 'bg-primary/5 border-primary text-primary font-semibold' : ''">
                                    <input type="checkbox" :value="b.idx" x-model="selectedBulans" class="rounded text-primary focus:ring-primary border-slate-300 dark:border-slate-600">
                                    <span x-text="b.label.substring(0,3)"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 pt-1">
                <button @click="open=false" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
                <button @click="save()" :disabled="saving" class="btn-primary flex-1 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2">
                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="saving"></i>
                    <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function sppGrid() {
    return {
        open: false,
        saving: false,
        cell: {},
        selectedBulans: [],
        bulanList: @json($bulanList),
        openCell(data) {
            this.cell = { ...data };
            this.selectedBulans = [data.bulan];
            this.open = true;
            this.$nextTick(() => lucide.createIcons());
        },
        selectAll() {
            this.selectedBulans = this.bulanList.map(b => b.idx);
        },
        resetSelection() {
            this.selectedBulans = [this.cell.bulan];
        },
        async save() {
            if (this.cell.status !== 'belum' && !this.cell.tanggal_bayar) {
                $.alert({ title: 'Wajib Diisi', content: 'Tanggal pembayaran harus dicantumkan.', type: 'red' });
                return;
            }
            this.saving = true;
            try {
                const res = await fetch(`{{ url('keuangan/pembayaran') }}/${this.cell.uuid}/cell`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content'), 'Accept': 'application/json' },
                    body: JSON.stringify({
                        status: this.cell.status,
                        nominal: this.cell.nominal,
                        tanggal_bayar: this.cell.tanggal_bayar || null,
                        catatan: this.cell.catatan || null,
                        catatan_bendahara: this.cell.catatan_bendahara || null,
                        selected_bulans: this.selectedBulans.map(x => parseInt(x)),
                    }),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                showToast('Pembayaran diperbarui');
                setTimeout(() => window.location.reload(), 400);
            } catch (e) {
                $.alert({ title: 'Gagal', content: 'Gagal menyimpan perubahan, coba lagi.', type: 'red' });
                this.saving = false;
            }
        },
    }
}
</script>
@endpush
