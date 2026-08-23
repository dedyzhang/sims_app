@extends('sarpras.layouts.app')
@section('title', 'Peminjaman')
@section('sarpras_title', 'Peminjaman')

@section('sarpras_subtitle')
{{ ($hanyaMilikSaya ?? false)
    ? 'Pinjam barang atau ruangan. Sistem langsung mengecek jadwal dan menolak otomatis jika bentrok.'
    : 'Kelola peminjaman barang inventaris dan jadwal pemakaian ruangan dalam satu tempat.' }}
@endsection

@section('sarpras_actions')
    @can('sarpras.peminjaman.ajukan')
        <a href="{{ route('sarpras.peminjaman.create') }}" class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
            <i data-lucide="calendar-check" class="w-4 h-4"></i> Ajukan Peminjaman
        </a>
    @endcan
@endsection

@section('sarpras_body')
@php
    $tab = $tab ?? 'barang';
    $bStatus = [
        'diajukan' => ['Menunggu', 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300'],
        'dipinjam' => ['Dipinjam', 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300'],
        'ditolak' => ['Ditolak', 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
        'dikembalikan' => ['Selesai', 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'],
        'terlambat' => ['Terlambat', 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
    ];
    $statusMeta = [
        'tersedia' => ['Tersedia', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600'],
        'digunakan' => ['Digunakan', 'bg-amber-100 dark:bg-amber-900/40 text-amber-600'],
        'maintenance' => ['Maintenance', 'bg-rose-100 dark:bg-rose-900/40 text-rose-600'],
    ];
    $roomTone = [
        'tersedia' => [
            'card' => '!border-emerald-200 !bg-gradient-to-br !from-emerald-50 !via-white !to-cyan-50 hover:!border-emerald-300 hover:shadow-emerald-100/80 dark:!border-emerald-500/30 dark:!from-emerald-950/30 dark:!via-slate-900/70 dark:!to-cyan-950/20',
            'icon' => 'door-open',
            'iconBox' => 'bg-emerald-500 text-white shadow-emerald-500/25',
            'button' => 'border-emerald-200 bg-white/80 text-emerald-700 hover:bg-emerald-600 hover:text-white dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200',
            'note' => 'Siap dipakai',
        ],
        'digunakan' => [
            'card' => '!border-amber-200 !bg-gradient-to-br !from-amber-50 !via-white !to-orange-50 hover:!border-amber-300 hover:shadow-amber-100/80 dark:!border-amber-500/30 dark:!from-amber-950/30 dark:!via-slate-900/70 dark:!to-orange-950/20',
            'icon' => 'calendar-clock',
            'iconBox' => 'bg-amber-500 text-white shadow-amber-500/25',
            'button' => 'border-amber-200 bg-white/80 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200',
            'note' => 'Sedang digunakan',
        ],
        'maintenance' => [
            'card' => '!border-rose-200 !bg-gradient-to-br !from-rose-50 !via-white !to-pink-50 hover:!border-rose-300 hover:shadow-rose-100/80 dark:!border-rose-500/30 dark:!from-rose-950/30 dark:!via-slate-900/70 dark:!to-pink-950/20',
            'icon' => 'wrench',
            'iconBox' => 'bg-rose-500 text-white shadow-rose-500/25',
            'button' => 'border-rose-200 bg-white/80 text-rose-700 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200',
            'note' => 'Perlu perawatan',
        ],
    ];
@endphp

<div class="flex gap-2 mb-4 overflow-x-auto pb-1 sarpras-tabs">
    <a href="{{ route('sarpras.peminjaman.index', ['tab' => 'barang']) }}"
       class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap {{ $tab === 'barang' ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">Barang</a>
    <a href="{{ route('sarpras.peminjaman.index', ['tab' => 'ruangan']) }}"
       class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap {{ $tab === 'ruangan' ? 'bg-primary text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600' }}">Ruangan</a>
</div>

@if($tab === 'barang')
@can('sarpras.aset.lihat')
<div class="mb-4 rounded-[24px] border border-emerald-100 bg-gradient-to-r from-emerald-50 via-white to-sky-50 p-4 shadow-sm dark:border-emerald-500/20 dark:from-emerald-950/20 dark:via-slate-900/60 dark:to-sky-950/20">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-500/20">
                <i data-lucide="package-plus" class="h-6 w-6"></i>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-extrabold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Data Inventaris Sarpras</p>
                <h3 class="mt-0.5 text-lg font-extrabold text-slate-800 dark:text-slate-100">Kelola barang dan perlengkapan sekolah</h3>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    Tambahkan aset sarana prasarana secara manual atau impor Excel. Data ini dipakai saat peminjaman, laporan kerusakan, dan inventaris ruangan.
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:justify-end">
            <a href="{{ route('sarpras.aset.index') }}" class="sarpras-google-btn-ghost justify-center px-4 py-2.5 text-xs sm:text-sm">
                <i data-lucide="archive" class="h-4 w-4"></i> Buka Inventaris
            </a>
            @can('sarpras.aset.kelola')
                <a href="{{ route('sarpras.aset.create') }}" class="sarpras-google-btn-primary justify-center px-4 py-2.5 text-xs sm:text-sm">
                    <i data-lucide="plus" class="h-4 w-4"></i> Tambah Manual
                </a>
                <button type="button" data-toggle-peminjaman-aset-import class="sarpras-google-btn-success justify-center px-4 py-2.5 text-xs sm:text-sm">
                    <i data-lucide="upload" class="h-4 w-4"></i> Impor Excel
                </button>
            @endcan
        </div>
    </div>

    @can('sarpras.aset.kelola')
        <div data-peminjaman-aset-import-panel class="mt-4 hidden rounded-2xl border border-emerald-200 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-emerald-500/20 dark:bg-slate-900/60">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Impor daftar inventaris</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Gunakan template resmi agar kolom aset, kategori, ruangan, kondisi, dan status terbaca benar.</p>
                </div>
                <a href="{{ route('sarpras.aset.import.template') }}" class="inline-flex items-center gap-1.5 text-xs font-extrabold text-emerald-700 hover:underline dark:text-emerald-300">
                    <i data-lucide="download" class="h-4 w-4"></i> Unduh template Excel
                </a>
            </div>
            <form method="POST" action="{{ route('sarpras.aset.import') }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                @csrf
                <input type="hidden" name="after_import" value="peminjaman_barang">
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="sarpras-field min-w-0 flex-1 text-sm">
                <button class="sarpras-google-btn-success justify-center px-5 py-2.5 text-xs sm:text-sm">
                    <i data-lucide="upload-cloud" class="h-4 w-4"></i> Proses Impor
                </button>
            </form>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                Format kolom: <code>kode, nama, kategori, ruangan, merk, kondisi, status, tgl_perolehan, nilai_perolehan, sumber_dana</code>.
            </p>
        </div>
    @endcan
</div>
@endcan

<div class="card p-4 sm:p-5">
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full min-w-[760px] table-fixed text-sm">
            <colgroup>
                @unless($hanyaMilikSaya ?? false)<col class="w-[18%]">@endunless
                <col>
                <col class="w-[25%]">
                <col class="w-[16%]">
                <col class="w-[10%]">
            </colgroup>
            <thead><tr class="text-left text-slate-400 border-b dark:border-slate-700">
                @unless($hanyaMilikSaya ?? false)<th class="pb-3 pr-4">Peminjam</th>@endunless
                <th class="pb-3 pr-4">Detail</th><th class="pb-3 pr-4">Periode</th><th class="pb-3 pr-4">Status</th><th class="pb-3"></th>
            </tr></thead>
            <tbody>
            @forelse($peminjaman as $p)
                @php [$pl, $pc] = $bStatus[$p->status] ?? [ucfirst($p->status), 'bg-slate-100']; @endphp
                <tr class="border-b border-slate-50 dark:border-slate-700/50">
                    @unless($hanyaMilikSaya ?? false)
                        <td class="py-3 pr-4 align-top text-slate-700 dark:text-slate-200 break-words">{{ $p->peminjam?->name ?? '—' }}</td>
                    @endunless
                    <td class="py-3 pr-4 align-top">
                        <p class="font-semibold text-slate-800 dark:text-slate-100 leading-snug break-words whitespace-normal">{{ $p->keperluan }}</p>
                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                            @if($p->items_count > 0)<span>{{ $p->items_count }} barang</span>@endif
                            @if($p->ruangan)<span>{{ $p->ruangan->kode }}</span>@endif
                        </div>
                    </td>
                    <td class="py-3 pr-4 align-top text-slate-600 dark:text-slate-300 whitespace-normal leading-snug">{{ optional($p->mulai)->format('d/m/Y H:i') }} – {{ optional($p->selesai)->format('d/m/Y H:i') }}</td>
                    <td class="py-3 pr-4 align-top"><span class="badge inline-flex whitespace-nowrap {{ $pc }}">{{ $pl }}</span></td>
                    <td class="py-3 align-top"><a href="{{ route('sarpras.peminjaman.show', $p) }}" class="inline-flex items-center rounded-lg px-2.5 py-1.5 text-primary text-xs font-bold hover:bg-primary/5">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="{{ ($hanyaMilikSaya ?? false) ? 4 : 5 }}" class="py-8 text-center text-slate-500">Belum ada pengajuan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        @forelse($peminjaman as $p)
            @php [$pl, $pc] = $bStatus[$p->status] ?? [ucfirst($p->status), 'bg-slate-100']; @endphp
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        @unless($hanyaMilikSaya ?? false)
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Peminjam</p>
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100 break-words">{{ $p->peminjam?->name ?? '—' }}</p>
                        @else
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Pengajuan</p>
                        @endunless
                    </div>
                    <span class="badge inline-flex shrink-0 whitespace-nowrap {{ $pc }}">{{ $pl }}</span>
                </div>

                <div class="mt-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Detail</p>
                    <p class="mt-1 text-sm font-semibold leading-relaxed text-slate-800 dark:text-slate-100 break-words">{{ $p->keperluan }}</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
                        @if($p->items_count > 0)<span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $p->items_count }} barang</span>@endif
                        @if($p->ruangan)<span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $p->ruangan->kode }}</span>@endif
                    </div>
                </div>

                <div class="mt-3">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Periode</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ optional($p->mulai)->format('d/m/Y H:i') }} – {{ optional($p->selesai)->format('d/m/Y H:i') }}</p>
                </div>

                <a href="{{ route('sarpras.peminjaman.show', $p) }}" class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-primary/25 px-3 py-2 text-sm font-bold text-primary hover:bg-primary/5">Lihat detail</a>
            </article>
        @empty
            <div class="py-8 text-center text-slate-500">Belum ada pengajuan.</div>
        @endforelse
    </div>
</div>
@else
<div x-data="{ open: false, form: { ruangan_id: '' } }" class="space-y-4">
    @if(($canApprove ?? false) && ($pending ?? collect())->isNotEmpty())
    <div class="card p-5 border-l-4 border-amber-400">
        <h3 class="font-bold mb-3">Pengajuan Lama Menunggu Proses ({{ $pending->count() }})</h3>
        @foreach($pending as $b)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 mb-2">
            <div class="min-w-0">
                <p class="font-semibold text-slate-800 dark:text-slate-100 leading-snug break-words">{{ $b->ruangan?->kode }} — {{ $b->keperluan }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 break-words">{{ $b->peminjam?->name }} · {{ $b->mulai?->format('d/m/Y H:i') }}–{{ $b->selesai?->format('H:i') }}</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:shrink-0">
                <form method="POST" action="{{ route('sarpras.peminjaman.setujui', $b) }}" class="w-full sm:w-auto">@csrf<button class="w-full px-3 py-1.5 rounded-lg bg-emerald-500 text-white text-sm font-bold">Setujui</button></form>
                <form method="POST" action="{{ route('sarpras.peminjaman.tolak', $b) }}" class="w-full sm:w-auto">@csrf<input type="hidden" name="alasan_tolak" value="Ditolak operator"><button class="w-full px-3 py-1.5 rounded-lg border border-rose-300 text-rose-600 text-sm">Tolak</button></form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @can('sarpras.peminjaman.ajukan')
    <button type="button" @click="open=true" class="sarpras-google-btn-primary px-4 py-2 text-sm font-bold"><i data-lucide="calendar-check" class="w-4 h-4 inline"></i> Periksa Ketersediaan Ruangan</button>
    @endcan

    <div class="rounded-[24px] border border-sky-100 bg-gradient-to-r from-sky-50 via-white to-emerald-50 p-4 shadow-sm dark:border-sky-500/20 dark:from-sky-950/20 dark:via-slate-900/60 dark:to-emerald-950/20">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wide text-sky-600 dark:text-sky-300">Peta ketersediaan ruangan</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Warna kartu mengikuti status ruangan. Slot waktu tetap dicek otomatis saat pengajuan dibuat.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-[11px] font-bold">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Tersedia</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1.5 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Digunakan</span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1.5 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Maintenance</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($rooms ?? [] as $room)
        @php
            [$sl, $sc] = $statusMeta[$room->status] ?? $statusMeta['tersedia'];
            $tone = $roomTone[$room->status] ?? $roomTone['tersedia'];
            $asetPreview = $room->aset->take(4);
            $asetCount = (int) ($room->aset_count ?? $room->aset->count());
            $asetBaik = (int) ($room->aset_baik_count ?? $room->aset->where('kondisi', 'baik')->count());
            $asetBerisiko = (int) ($room->aset_berisiko_count ?? $room->aset->whereIn('kondisi', ['rusak_ringan', 'rusak_berat', 'hilang'])->count());
            $asetTidakAktif = (int) ($room->aset_tidak_aktif_count ?? $room->aset->where('status', '!=', 'aktif')->count());
        @endphp
        <div class="card group relative overflow-hidden p-5 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl {{ $tone['card'] }}">
            <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-white/45 blur-2xl dark:bg-white/5"></div>
            <div class="relative flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl shadow-lg {{ $tone['iconBox'] }}">
                        <i data-lucide="{{ $tone['icon'] }}" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100 break-words">{{ $room->kode }}</p>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400 leading-relaxed break-words">{{ $room->nama }}</p>
                        <p class="mt-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $tone['note'] }}</p>
                    </div>
                </div>
                <span class="badge inline-flex shrink-0 whitespace-nowrap {{ $sc }}">{{ $sl }}</span>
            </div>

            <div class="relative mt-4 rounded-2xl border border-white/70 bg-white/70 p-3 shadow-sm backdrop-blur dark:border-slate-700/60 dark:bg-slate-900/45">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wide text-slate-400">Inventaris kelas</p>
                        <p class="mt-0.5 text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ $asetCount }} unit perlengkapan</p>
                    </div>
                    <div class="flex shrink-0 gap-1.5 text-[10px] font-black">
                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200">{{ $asetBaik }} baik</span>
                        @if($asetBerisiko > 0)
                            <span class="rounded-full bg-amber-100 px-2 py-1 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200">{{ $asetBerisiko }} cek</span>
                        @endif
                        @if($asetTidakAktif > 0)
                            <span class="rounded-full bg-rose-100 px-2 py-1 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200">{{ $asetTidakAktif }} tidak aktif</span>
                        @endif
                    </div>
                </div>

                @if($asetPreview->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($asetPreview as $aset)
                            <span class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <i data-lucide="{{ $aset->kondisi === 'baik' ? 'package-check' : 'package-x' }}" class="h-3.5 w-3.5 shrink-0 {{ $aset->kondisi === 'baik' ? 'text-emerald-500' : 'text-amber-500' }}"></i>
                                <span class="truncate">{{ $aset->nama }}</span>
                            </span>
                        @endforeach
                        @if($asetCount > $asetPreview->count())
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">+{{ $asetCount - $asetPreview->count() }} lainnya</span>
                        @endif
                    </div>
                @else
                    <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">Belum ada inventaris yang tercatat di ruangan ini.</p>
                @endif

                <a href="{{ route('sarpras.ruangan.show', $room) }}" class="mt-3 inline-flex items-center gap-1.5 text-xs font-extrabold text-emerald-700 hover:underline dark:text-emerald-300">
                    Detail inventaris <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                </a>
            </div>

            @can('sarpras.peminjaman.ajukan')
                @if($room->status === 'tersedia')
                    <button type="button" @click="form.ruangan_id='{{ $room->id }}'; open=true" class="relative mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl border px-4 py-2.5 text-sm font-extrabold shadow-sm transition {{ $tone['button'] }}">
                        <i data-lucide="scan-search" class="h-4 w-4"></i> Periksa ketersediaan
                    </button>
                @else
                    <div class="relative mt-5 inline-flex w-full items-center justify-center gap-2 rounded-2xl border px-4 py-2.5 text-sm font-extrabold opacity-80 {{ $tone['button'] }}">
                        <i data-lucide="circle-slash" class="h-4 w-4"></i> Belum bisa dipakai
                    </div>
                @endif
            @endcan
        </div>
        @endforeach
    </div>

    <div class="card p-5">
        <h3 class="font-bold mb-3">Riwayat Jadwal Ruangan</h3>
        <div class="hidden md:block overflow-x-auto rounded-2xl border border-slate-100 dark:border-slate-700">
            <table class="w-full min-w-[680px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[18%]">
                    <col>
                    <col class="w-[24%]">
                    <col class="w-[16%]">
                </colgroup>
                <thead><tr class="text-left text-sky-700 bg-gradient-to-r from-sky-50 to-emerald-50 dark:from-sky-950/30 dark:to-emerald-950/20 dark:text-sky-200"><th class="py-3 px-4">Ruangan</th><th class="py-3 pr-4">Kegiatan</th><th class="py-3 pr-4">Waktu</th><th class="py-3">Status</th></tr></thead>
                <tbody>
                @forelse($peminjaman->whereNotNull('ruangan_id') as $b)
                    @php
                        [$bl, $bc] = $bStatus[$b->status] ?? ['—','bg-slate-100'];
                        $rowTone = match ($b->status) {
                            'dipinjam' => 'bg-blue-50/60 hover:bg-blue-50 dark:bg-blue-950/10 dark:hover:bg-blue-950/20',
                            'diajukan' => 'bg-amber-50/60 hover:bg-amber-50 dark:bg-amber-950/10 dark:hover:bg-amber-950/20',
                            'ditolak', 'terlambat' => 'bg-rose-50/60 hover:bg-rose-50 dark:bg-rose-950/10 dark:hover:bg-rose-950/20',
                            default => 'bg-white hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-800/40',
                        };
                    @endphp
                    <tr class="border-b border-white/70 transition last:border-b-0 dark:border-slate-700/50 {{ $rowTone }}">
                        <td class="py-3 pl-4 pr-4 align-top font-semibold text-slate-700 dark:text-slate-200 break-words">{{ $b->ruangan?->kode }}</td>
                        <td class="py-3 pr-4 align-top text-slate-700 dark:text-slate-200 leading-snug break-words whitespace-normal">{{ $b->keperluan }}</td>
                        <td class="py-3 pr-4 align-top text-slate-600 dark:text-slate-300 whitespace-normal">{{ $b->mulai?->format('d/m/Y H:i') }}</td>
                        <td class="py-3 align-top"><span class="badge inline-flex whitespace-nowrap {{ $bc }}">{{ $bl }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada jadwal ruangan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-3">
            @forelse($peminjaman->whereNotNull('ruangan_id') as $b)
                @php [$bl, $bc] = $bStatus[$b->status] ?? ['—','bg-slate-100']; @endphp
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Ruangan</p>
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100 break-words">{{ $b->ruangan?->kode }}</p>
                        </div>
                        <span class="badge inline-flex shrink-0 whitespace-nowrap {{ $bc }}">{{ $bl }}</span>
                    </div>
                    <div class="mt-3">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Kegiatan</p>
                        <p class="mt-1 text-sm font-semibold leading-relaxed text-slate-800 dark:text-slate-100 break-words">{{ $b->keperluan }}</p>
                    </div>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $b->mulai?->format('d/m/Y H:i') }}</p>
                </article>
            @empty
                <div class="py-8 text-center text-slate-500">Belum ada jadwal ruangan.</div>
            @endforelse
        </div>
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-[9990] grid place-items-center p-4 bg-slate-900/50" @click.self="open=false">
        <div class="card w-full max-w-md p-5" @click.stop>
            <h3 class="font-bold mb-1">Periksa Ketersediaan Ruangan</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Sistem langsung membaca tanggal dan jam. Jika sudah ada pemakaian, ruangan ditolak otomatis.</p>
            <form method="POST" action="{{ route('sarpras.peminjaman.ruangan.store') }}" class="space-y-3">
                @csrf
                <select name="ruangan_id" x-model="form.ruangan_id" required class="form-input text-sm w-full">
                    <option value="">— pilih ruangan —</option>
                    @foreach($allRooms ?? [] as $r)
                        <option value="{{ $r->id }}">{{ $r->kode }} — {{ $r->nama }}</option>
                    @endforeach
                </select>
                <input name="keperluan" required class="form-input text-sm w-full" placeholder="Keperluan">
                <input type="date" name="tanggal" value="{{ now()->addDay()->format('Y-m-d') }}" required class="form-input text-sm w-full">
                <div class="grid grid-cols-2 gap-2">
                    <input type="time" name="jam_mulai" value="08:00" required class="form-input text-sm">
                    <input type="time" name="jam_selesai" value="10:00" required class="form-input text-sm">
                </div>
                <div class="flex gap-2"><button type="button" @click="open=false" class="flex-1 py-2 rounded-xl border">Batal</button><button class="flex-1 py-2 rounded-xl bg-primary text-white font-bold">Periksa & Ajukan</button></div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('[data-toggle-peminjaman-aset-import]');
    const panel = document.querySelector('[data-peminjaman-aset-import-panel]');

    if (!toggle || !panel) {
        return;
    }

    toggle.addEventListener('click', function () {
        panel.classList.toggle('hidden');

        if (!panel.classList.contains('hidden')) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
});
</script>
@endpush
