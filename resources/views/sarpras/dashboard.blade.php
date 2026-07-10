@extends('sarpras.layouts.app')
@section('title', 'Dashboard Sarpras')
@section('sarpras_title', 'Dashboard Sarana & Prasarana')
@section('sarpras_subtitle', 'Pusat kontrol inventaris, ruangan, kerusakan, peminjaman, pengadaan, perawatan, mutasi, dan laporan aset sekolah.')

@section('sarpras_actions')
    @can('sarpras.kerusakan.lapor')
        <a href="{{ route('sarpras.kerusakan.create') }}" class="btn-primary inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Lapor Kerusakan
        </a>
    @endcan
    @can('sarpras.peminjaman.ajukan')
        <a href="{{ route('sarpras.peminjaman.create') }}" class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-800">
            <i data-lucide="hand-helping" class="w-4 h-4"></i> Ajukan Pinjam
        </a>
    @endcan
@endsection

@section('sarpras_body')
@php
    $totalKondisi = (int) $asetPerKondisi->sum();
    $asetBaik = (int) ($asetPerKondisi['baik'] ?? 0);
    $skorKesehatan = $totalKondisi > 0 ? round($asetBaik / $totalKondisi * 100) : 0;
    $rasioRisiko = $totalAset > 0 ? round($asetBerisiko / $totalAset * 100) : 0;

    $tone = [
        'slate' => ['bg' => 'bg-slate-100 dark:bg-slate-800', 'text' => 'text-slate-700 dark:text-slate-200', 'ring' => 'border-slate-200 dark:border-slate-700'],
        'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-950/40', 'text' => 'text-emerald-700 dark:text-emerald-300', 'ring' => 'border-emerald-200 dark:border-emerald-900/70'],
        'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-950/40', 'text' => 'text-amber-700 dark:text-amber-300', 'ring' => 'border-amber-200 dark:border-amber-900/70'],
        'rose' => ['bg' => 'bg-rose-100 dark:bg-rose-950/40', 'text' => 'text-rose-700 dark:text-rose-300', 'ring' => 'border-rose-200 dark:border-rose-900/70'],
        'blue' => ['bg' => 'bg-blue-100 dark:bg-blue-950/40', 'text' => 'text-blue-700 dark:text-blue-300', 'ring' => 'border-blue-200 dark:border-blue-900/70'],
        'cyan' => ['bg' => 'bg-cyan-100 dark:bg-cyan-950/40', 'text' => 'text-cyan-700 dark:text-cyan-300', 'ring' => 'border-cyan-200 dark:border-cyan-900/70'],
        'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-950/40', 'text' => 'text-violet-700 dark:text-violet-300', 'ring' => 'border-violet-200 dark:border-violet-900/70'],
    ];

    $kpi = [
        ['label' => 'Total Aset', 'value' => number_format($totalAset, 0, ',', '.') . ' unit', 'note' => $nilaiTotalRp, 'icon' => 'archive', 'tone' => 'amber', 'url' => route('sarpras.aset.index')],
        ['label' => 'Nilai Buku', 'value' => $nilaiBukuRp, 'note' => 'Estimasi setelah penyusutan', 'icon' => 'wallet-cards', 'tone' => 'emerald', 'url' => route('sarpras.aset.index')],
        ['label' => 'Kerusakan Terbuka', 'value' => $kerusakanTerbuka . ' laporan', 'note' => $kerusakanDarurat . ' tinggi/darurat', 'icon' => 'triangle-alert', 'tone' => 'rose', 'url' => route('sarpras.kerusakan.index')],
        ['label' => 'Peminjaman Aktif', 'value' => $peminjamanAktif . ' proses', 'note' => $peminjamanMenunggu . ' menunggu', 'icon' => 'hand-helping', 'tone' => 'blue', 'url' => route('sarpras.peminjaman.index')],
        ['label' => 'Booking Menunggu', 'value' => $bookingMenunggu . ' jadwal', 'note' => 'Approval ruang ke depan', 'icon' => 'calendar-clock', 'tone' => 'cyan', 'url' => route('sarpras.booking.index')],
        ['label' => 'Pengadaan Pending', 'value' => $pengadaanPending . ' usulan', 'note' => $pengadaanDisetujui . ' sudah disetujui', 'icon' => 'shopping-cart', 'tone' => 'violet', 'url' => route('sarpras.pengadaan.index')],
        ['label' => 'Perbaikan Berjalan', 'value' => $perbaikanBerjalan . ' pekerjaan', 'note' => $biayaPerbaikanBulanIniRp . ' bulan ini', 'icon' => 'wrench', 'tone' => 'slate', 'url' => route('sarpras.perbaikan.index')],
        ['label' => 'Pemeliharaan Jatuh Tempo', 'value' => $jadwalJatuhTempo . ' jadwal', 'note' => $asetTanpaLokasi . ' aset tanpa lokasi', 'icon' => 'calendar-clock', 'tone' => 'emerald', 'url' => route('sarpras.perbaikan.index')],
    ];

    $conditionLabels = ['baik' => 'Baik', 'rusak_ringan' => 'Rusak Ringan', 'rusak_berat' => 'Rusak Berat', 'hilang' => 'Hilang'];
    $conditionColors = ['baik' => '#10b981', 'rusak_ringan' => '#f59e0b', 'rusak_berat' => '#ef4444', 'hilang' => '#64748b'];
    $statusLabels = ['aktif' => 'Aktif', 'dipinjam' => 'Dipinjam', 'perbaikan' => 'Perbaikan', 'dihapus' => 'Dihapus', 'dimutasi' => 'Dimutasi'];
    $roomLabels = ['tersedia' => 'Tersedia', 'digunakan' => 'Digunakan', 'maintenance' => 'Maintenance'];
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" data-drag-container="sarpras_kpi">
        @foreach($kpi as $item)
            @php $c = $tone[$item['tone']] ?? $tone['slate']; @endphp
            <a href="{{ $item['url'] }}" data-drag-id="{{ Str::slug($item['label']) }}" class="card card-hover p-5 flex items-start justify-between gap-4 min-h-[132px]">
                <div class="min-w-0">
                    <p class="text-[11px] font-extrabold uppercase text-slate-400 dark:text-slate-500">{{ $item['label'] }}</p>
                    <p class="text-xl font-extrabold text-slate-800 dark:text-slate-100 mt-2 break-words">{{ $item['value'] }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">{{ $item['note'] }}</p>
                </div>
                <span class="grid place-items-center w-11 h-11 rounded-xl shrink-0 {{ $c['bg'] }} {{ $c['text'] }}">
                    <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5"></i>
                </span>
            </a>
        @endforeach
    </div>

    {{-- ===== Mini-map Peta Sekolah: thumbnail denah per lantai + badge kerusakan ===== --}}
    @if($denahPeta->isNotEmpty())
    <section class="card p-5" data-drag-container="sarpras_peta">
        <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
            <div>
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Peta Sekolah — Ringkasan Denah</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Klik denah untuk melihat ruangan, status pemakaian, dan titik kerusakan secara interaktif.</p>
            </div>
            <a href="{{ route('sarpras.denah.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:underline">
                <i data-lucide="map" class="w-4 h-4"></i> Semua denah
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($denahPeta as $d)
                @php
                    $kr = (int) ($kerusakanPerDenah[$d->id] ?? 0);
                    $hasGambar = !empty($d->gambar_path);
                @endphp
                <a href="{{ route('sarpras.denah.show', $d) }}" class="group relative rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden hover:border-primary/40 hover:shadow-md transition-all duration-200">
                    {{-- Thumbnail --}}
                    <div class="relative aspect-[3/2] bg-slate-100 dark:bg-slate-800">
                        @if($hasGambar)
                            <img loading="lazy" src="{{ \Illuminate\Support\Facades\Storage::url($d->gambar_path) }}" alt="{{ $d->nama }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="absolute inset-0 grid place-items-center text-slate-400 dark:text-slate-500">
                                <i data-lucide="image-off" class="w-7 h-7 mb-1"></i>
                                <span class="text-[10px] font-bold">Belum ada gambar</span>
                            </div>
                        @endif
                        {{-- Badge lantai --}}
                        <span class="absolute top-2 left-2 bg-slate-900/80 text-white text-[10px] font-bold px-2 py-0.5 rounded-lg backdrop-blur-sm">
                            {{ $d->lantai ? 'Lt. ' . $d->lantai : ($d->gedung ?: 'Denah') }}
                        </span>
                        {{-- Badge kerusakan (merah, berkedip) --}}
                        @if($kr > 0)
                            <span class="absolute top-2 right-2 inline-flex items-center gap-1 bg-rose-500 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-lg shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                {{ $kr }} kerusakan
                            </span>
                        @endif
                    </div>
                    {{-- Info --}}
                    <div class="p-3">
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate group-hover:text-primary transition-colors">{{ $d->nama }}</p>
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            <span class="inline-flex items-center gap-1"><i data-lucide="door-open" class="w-3 h-3"></i> {{ $d->ruangan_count }} ruang</span>
                            @if($d->gedung)
                                <span class="truncate ml-2">{{ $d->gedung }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        @if($denahPeta->count() === 8)
            <p class="text-center text-xs text-slate-400 dark:text-slate-500 mt-3">Menampilkan 8 denah pertama. <a href="{{ route('sarpras.denah.index') }}" class="font-bold text-primary hover:underline">Lihat semua &rarr;</a></p>
        @endif
    </section>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <section class="card p-5 xl:col-span-2" data-drag-container="sarpras_command">
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Antrean Kerja Sarpras</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Urutan pekerjaan harian yang perlu diputuskan atau ditindaklanjuti.</p>
                </div>
                <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ collect($antreanKerja)->sum('count') }} item</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($antreanKerja as $item)
                    @php $c = $tone[$item['tone']] ?? $tone['slate']; @endphp
                    <a href="{{ $item['url'] }}" data-drag-id="{{ Str::slug($item['label']) }}" class="flex items-center gap-3 p-4 rounded-2xl border {{ $c['ring'] }} hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                        <span class="grid place-items-center w-10 h-10 rounded-xl {{ $c['bg'] }} {{ $c['text'] }}"><i data-lucide="{{ $item['icon'] }}" class="w-5 h-5"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ $item['label'] }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $item['count'] }} data perlu dicek</span>
                        </span>
                        <span class="text-xl font-extrabold {{ $c['text'] }}">{{ $item['count'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Kesehatan Aset</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Proporsi aset baik vs aset berisiko.</p>
                </div>
                <span class="text-3xl font-extrabold {{ $skorKesehatan >= 80 ? 'text-emerald-600' : ($skorKesehatan >= 60 ? 'text-amber-600' : 'text-rose-600') }}">{{ $skorKesehatan }}%</span>
            </div>
            <div class="h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $skorKesehatan }}%"></div>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl border border-emerald-200 dark:border-emerald-900/60 p-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Aset Baik</p>
                    <p class="text-xl font-extrabold text-emerald-600 mt-1">{{ $asetBaik }}</p>
                </div>
                <div class="rounded-2xl border border-rose-200 dark:border-rose-900/60 p-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Aset Berisiko</p>
                    <p class="text-xl font-extrabold text-rose-600 mt-1">{{ $asetBerisiko }} <span class="text-xs font-bold">({{ $rasioRisiko }}%)</span></p>
                </div>
            </div>
            <a href="{{ route('sarpras.aset.index', ['kondisi' => 'rusak_ringan']) }}" class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-rose-600 hover:underline">
                <i data-lucide="arrow-right" class="w-4 h-4"></i> Cek aset rusak
            </a>
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Kondisi Fisik</h2>
                <a href="{{ route('sarpras.aset.index') }}" class="text-xs font-bold text-primary hover:underline">Inventaris</a>
            </div>
            <div class="space-y-3">
                @foreach($conditionLabels as $key => $label)
                    @php
                        $count = (int) ($asetPerKondisi[$key] ?? 0);
                        $pct = $totalKondisi > 0 ? round($count / $totalKondisi * 100) : 0;
                    @endphp
                    <a href="{{ route('sarpras.aset.index', ['kondisi' => $key]) }}" class="block">
                        <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                            <span class="text-slate-700 dark:text-slate-200">{{ $label }}</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ $count }} unit</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $conditionColors[$key] }}"></div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Status Inventaris</h2>
                <a href="{{ route('sarpras.aset.index') }}" class="text-xs font-bold text-primary hover:underline">Detail</a>
            </div>
            <div class="space-y-2.5">
                @foreach($statusLabels as $key => $label)
                    <a href="{{ route('sarpras.aset.index', ['status' => $key]) }}" class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $label }}</span>
                        <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ (int) ($asetPerStatus[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Status Ruangan</h2>
                <a href="{{ route('sarpras.booking.index') }}" class="text-xs font-bold text-primary hover:underline">Booking</a>
            </div>
            <div class="space-y-2.5">
                @foreach($roomLabels as $key => $label)
                    <a href="{{ route('sarpras.booking.index', ['status' => $key]) }}" class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ $label }}</span>
                        <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ (int) ($ruanganPerStatus[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Aset Perlu Tindakan</h2>
                <a href="{{ route('sarpras.aset.index') }}" class="text-xs font-bold text-primary hover:underline">Semua aset</a>
            </div>
            <div class="space-y-2.5">
                @forelse($asetPerluTindakan as $aset)
                    <a href="{{ route('sarpras.aset.show', $aset) }}" class="flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 transition">
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $aset->nama }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $aset->kode }} - {{ $aset->ruangan?->nama ?? 'Tanpa lokasi' }}</span>
                        </span>
                        <span class="text-right shrink-0">
                            <span class="badge {{ in_array($aset->kondisi, ['rusak_berat', 'hilang']) ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ $conditionLabels[$aset->kondisi] ?? ucfirst($aset->kondisi) }}</span>
                            <span class="block text-[11px] text-slate-400 mt-1">{{ $statusLabels[$aset->status] ?? ucfirst($aset->status) }}</span>
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">Belum ada aset yang perlu tindakan khusus.</p>
                @endforelse
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Pemeliharaan 14 Hari</h2>
                @can('sarpras.jadwal.kelola')
                    <a href="{{ route('sarpras.jadwal.create') }}" class="text-xs font-bold text-primary hover:underline">Tambah jadwal</a>
                @endcan
            </div>
            <div class="space-y-2.5">
                @forelse($jadwalMendatang as $jadwal)
                    @php $overdue = $jadwal->tgl_berikutnya?->isPast() && ! $jadwal->tgl_berikutnya?->isToday(); @endphp
                    <a href="{{ route('sarpras.perbaikan.index') }}" class="flex items-center gap-3 p-3 rounded-xl border {{ $overdue ? 'border-rose-200 dark:border-rose-900/60' : 'border-slate-100 dark:border-slate-700' }} hover:bg-slate-50 dark:hover:bg-slate-800/60 transition">
                        <span class="grid place-items-center w-10 h-10 rounded-xl {{ $overdue ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">
                            <i data-lucide="calendar-clock" class="w-5 h-5"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $jadwal->nama }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $jadwal->aset?->nama ?? 'Umum' }}</span>
                        </span>
                        <span class="text-xs font-bold {{ $overdue ? 'text-rose-600' : 'text-slate-500 dark:text-slate-400' }}">{{ $jadwal->tgl_berikutnya?->format('d M') }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">Tidak ada jadwal pemeliharaan dalam 14 hari.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Kerusakan Terbaru</h2>
                <a href="{{ route('sarpras.kerusakan.index') }}" class="text-xs font-bold text-primary hover:underline">Lihat semua</a>
            </div>
            <div class="space-y-2.5">
                @forelse($kerusakanTerbaru as $k)
                    <a href="{{ route('sarpras.kerusakan.show', $k) }}" class="block p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-rose-300 dark:hover:border-rose-800 transition">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100 line-clamp-2">{{ $k->deskripsi }}</p>
                            <span class="badge bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 shrink-0">{{ ucfirst($k->urgensi) }}</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">{{ $k->aset?->nama ?? 'Tanpa aset' }} - {{ $k->created_at?->diffForHumans() }}</p>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">Belum ada laporan kerusakan.</p>
                @endforelse
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Booking Hari Ini</h2>
                <a href="{{ route('sarpras.booking.index') }}" class="text-xs font-bold text-primary hover:underline">Kalender</a>
            </div>
            <div class="space-y-2.5">
                @forelse($bookingHariIni as $b)
                    <a href="{{ route('sarpras.booking.index') }}" class="block p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-800 transition">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $b->ruangan?->nama ?? $b->ruangan?->kode ?? 'Ruangan' }}</p>
                            <span class="text-xs font-extrabold text-blue-600 shrink-0">{{ $b->mulai?->format('H:i') }}-{{ $b->selesai?->format('H:i') }}</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">{{ $b->keperluan }} - {{ $b->pemohon?->name ?? '-' }}</p>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">Tidak ada booking ruangan hari ini.</p>
                @endforelse
            </div>
        </section>

        <section class="card p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Pengadaan Aktif</h2>
                <a href="{{ route('sarpras.pengadaan.index') }}" class="text-xs font-bold text-primary hover:underline">Semua</a>
            </div>
            <div class="space-y-2.5">
                @forelse($pengadaanTerbaru as $p)
                    <a href="{{ route('sarpras.pengadaan.show', $p) }}" class="block p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-violet-300 dark:hover:border-violet-800 transition">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">{{ $p->judul }}</p>
                            <span class="badge {{ $p->status === 'diajukan' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' }}">{{ ucfirst($p->status) }}</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $p->total_estimasi_rp }} - {{ $p->created_at?->diffForHumans() }}</p>
                    </a>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400 py-8 text-center">Tidak ada pengadaan aktif.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
            <div>
                <h2 class="font-extrabold text-slate-800 dark:text-slate-100">Shortcut Fitur Standar</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Akses cepat untuk pekerjaan rutin operator sarana prasarana.</p>
            </div>
            <span class="badge bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">Master, transaksi, laporan</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            @can('sarpras.aset.kelola')
                <a href="{{ route('sarpras.aset.create') }}" class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"><i data-lucide="package-plus" class="w-5 h-5 text-primary"></i><span class="block text-sm font-bold mt-3 text-slate-700 dark:text-slate-200">Tambah Aset</span></a>
            @endcan
            @can('sarpras.pengadaan.ajukan')
                <a href="{{ route('sarpras.pengadaan.create') }}" class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"><i data-lucide="shopping-cart" class="w-5 h-5 text-violet-600"></i><span class="block text-sm font-bold mt-3 text-slate-700 dark:text-slate-200">Ajukan Pengadaan</span></a>
            @endcan
            @can('sarpras.denah.kelola')
                <a href="{{ route('sarpras.denah.create') }}" class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"><i data-lucide="map-plus" class="w-5 h-5 text-emerald-600"></i><span class="block text-sm font-bold mt-3 text-slate-700 dark:text-slate-200">Kelola Denah</span></a>
            @endcan
            @can('sarpras.perbaikan.kelola')
                <a href="{{ route('sarpras.perbaikan.create') }}" class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"><i data-lucide="wrench" class="w-5 h-5 text-amber-600"></i><span class="block text-sm font-bold mt-3 text-slate-700 dark:text-slate-200">Buat Perbaikan</span></a>
            @endcan
            @can('sarpras.penghapusan.ajukan')
                <a href="{{ route('sarpras.penghapusan.create') }}" class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"><i data-lucide="trash-2" class="w-5 h-5 text-rose-600"></i><span class="block text-sm font-bold mt-3 text-slate-700 dark:text-slate-200">Ajukan Hapus</span></a>
            @endcan
            @can('sarpras.laporan.lihat')
                <a href="{{ route('sarpras.laporan.index') }}" class="p-4 rounded-2xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/60 transition"><i data-lucide="file-bar-chart" class="w-5 h-5 text-blue-600"></i><span class="block text-sm font-bold mt-3 text-slate-700 dark:text-slate-200">Laporan Aset</span></a>
            @endcan
        </div>
    </section>
</div>
@endsection