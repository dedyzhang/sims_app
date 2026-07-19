@extends('sarpras.layouts.app')
@section('title', 'Booking Ruangan')
@section('sarpras_title', 'Booking Ruangan')
@section('sarpras_subtitle', auth()->user()?->can('sarpras.booking.kelola')
    ? 'Kelola status ruangan dan setujui jadwal pemakaian kelas, laboratorium, serta ruang rapat.'
    : 'Ajukan jadwal pemakaian ruang dan pantau status booking Anda. Untuk pinjam barang, buka Pinjam Barang.')

@section('sarpras_body')
@php
    $statusMeta = [
        'tersedia'    => ['Tersedia',    '#10b981', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600', 'door-open'],
        'digunakan'   => ['Digunakan',   '#f59e0b', 'bg-amber-100 dark:bg-amber-900/40 text-amber-600',     'users'],
        'maintenance' => ['Maintenance', '#ef4444', 'bg-rose-100 dark:bg-rose-900/40 text-rose-600',         'wrench'],
    ];
    $bookingBadge = [
        'diajukan'  => ['Menunggu', 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300'],
        'disetujui' => ['Disetujui','bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300'],
        'ditolak'   => ['Ditolak',  'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300'],
        'selesai'   => ['Selesai',  'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'],
    ];
@endphp

<div x-data="{ open: {{ ($errors->has('ruangan_id') || $errors->has('keperluan') || $errors->has('tanggal') || $errors->has('jam_mulai') || $errors->has('jam_selesai')) ? 'true' : 'false' }}, openTambah: {{ ($errors->has('kode') || $errors->has('nama') || $errors->has('warna')) ? 'true' : 'false' }}, openEdit: false, form: { ruangan_id: '{{ old('ruangan_id') }}' }, editForm: { action: '', kode: '', nama: '', kapasitas: '', warna: '#3b82f6', pos_x: '', pos_y: '', lebar: '', tinggi: '' } }" class="space-y-5">

    {{-- Judul sub-modul + aksi --}}
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <h2 class="flex items-center gap-2 text-lg font-bold text-slate-800 dark:text-slate-100">
            <span class="grid place-items-center w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/40 text-cyan-500"><i data-lucide="calendar-clock" class="w-4 h-4"></i></span>
            Jadwal &amp; Status Ruangan
        </h2>
        <div class="flex items-center gap-2 flex-wrap">
            @can('sarpras.denah.kelola')
            <button type="button" @click="openTambah = !openTambah"
                    class="sarpras-google-btn-ghost px-4 py-2.5 text-xs sm:text-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Ruangan
            </button>
            @endcan
            @can('sarpras.peminjaman.ajukan')
            <button type="button" @click="form.ruangan_id=''; open=true"
                    class="sarpras-google-btn-primary px-5 py-2.5 text-xs sm:text-sm">
                <i data-lucide="plus" class="w-4 h-4"></i> Ajukan Booking
            </button>
            @endcan
        </div>
    </div>

    @can('sarpras.denah.kelola')
        {{-- Panel tambah ruangan secara manual --}}
        <div x-show="openTambah" x-transition class="mb-4 rounded-lg border border-slate-200 bg-slate-50/50 p-4" style="display: none;">
            <h4 class="font-semibold text-gray-800 dark:text-slate-100 text-sm mb-3">Tambah Ruangan Baru</h4>
            @php $allDenahs = \App\Sarpras\Models\Denah::orderBy('nama')->get(); @endphp
            <form method="POST" action="{{ route('sarpras.ruangan.store', ':denah') }}" enctype="multipart/form-data"
                  onsubmit="this.action = this.action.replace(':denah', document.getElementById('select-denah').value); return true;"
                  class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                @csrf
                <input type="hidden" name="pos_x" value="50">
                <input type="hidden" name="pos_y" value="50">
                <input type="hidden" name="lebar" value="14">
                <input type="hidden" name="tinggi" value="9">
                <input type="hidden" name="status" value="tersedia">

                <div>
                    <label class="block text-gray-600 dark:text-slate-300 text-xs font-semibold mb-1">Denah / Layout <span class="text-red-500">*</span></label>
                    <select id="select-denah" required class="w-full border rounded px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100">
                        <option value="" disabled selected>Pilih Denah/Lantai</option>
                        @foreach($allDenahs as $d)
                            <option value="{{ $d->id }}">{{ $d->nama }} (Gedung: {{ $d->gedung ?? '—' }}, Lantai: {{ $d->lantai ?? '—' }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600 dark:text-slate-300 text-xs font-semibold mb-1">Kode Ruangan (mis. 7A) <span class="text-red-500">*</span></label>
                    <input name="kode" required placeholder="mis. 7A" class="w-full border rounded px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-gray-600 dark:text-slate-300 text-xs font-semibold mb-1">Nama Ruangan <span class="text-red-500">*</span></label>
                    <input name="nama" required placeholder="mis. Kelas 7A" class="w-full border rounded px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100">
                </div>
                <div>
                    <label class="block text-gray-600 dark:text-slate-300 text-xs font-semibold mb-1">Kapasitas Orang / Warna</label>
                    <div class="flex gap-1.5">
                        <input name="kapasitas" type="number" min="0" placeholder="Kapasitas" class="w-full border rounded px-3 py-2 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 min-w-0">
                        <input name="warna" type="color" value="#3b82f6" class="h-[38px] w-12 border rounded px-1 py-1 cursor-pointer bg-white shrink-0">
                        <button type="submit" class="bg-slate-900 hover:bg-slate-800 dark:bg-primary dark:hover:bg-primary-hover text-white rounded px-4 font-bold transition">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    @endcan

    {{-- Kartu status ruangan --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach($statusMeta as $key => [$label, $color, $chip, $icon])
        <div class="card p-5 flex items-center gap-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl flex-shrink-0 {{ $chip }}"><i data-lucide="{{ $icon }}" class="w-6 h-6"></i></span>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">{{ $summary[$key] ?? 0 }} ruangan</p>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Antrian menunggu persetujuan --}}
    @if($canApprove && $pending->isNotEmpty())
    <div class="card p-5 border-l-4 border-amber-400">
        <h3 class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-100 mb-3">
            <i data-lucide="clock" class="w-5 h-5 text-amber-500"></i> Menunggu Persetujuan ({{ $pending->count() }})
        </h3>
        <div class="space-y-2.5">
            @foreach($pending as $b)
            <div class="flex items-center justify-between gap-3 flex-wrap p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40">
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $b->ruangan?->kode }} — {{ $b->keperluan }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $b->pemohon?->name ?? '—' }} · {{ $b->mulai->format('d/m/Y') }} · {{ $b->mulai->format('H:i') }}–{{ $b->selesai->format('H:i') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('sarpras.booking.setujui', $b) }}">
                        @csrf
                        <button class="px-4 py-1.5 rounded-lg text-sm font-bold text-white bg-emerald-500 hover:bg-emerald-600">Setujui</button>
                    </form>
                    <form method="POST" action="{{ route('sarpras.booking.tolak', $b) }}"
                          onsubmit="return confirmAction(this, 'Tolak booking {{ addslashes($b->ruangan?->kode) }} ({{ $b->keperluan }})?', 'red')">
                        @csrf
                        <button class="px-4 py-1.5 rounded-lg text-sm font-semibold border border-rose-300 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20">Tolak</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Filter status --}}
    <form method="GET" class="flex items-center gap-2">
        <select name="status" onchange="this.form.submit()" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Semua status</option>
            @foreach(\App\Sarpras\Models\DenahRuangan::STATUS as $k => $l)
                <option value="{{ $k }}" @selected($statusFilter===$k)>{{ $l }}</option>
            @endforeach
        </select>
    </form>

    {{-- Kartu ruangan --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" data-drag-container="booking_rooms">
        @foreach($rooms as $room)
        @php [$slabel, $scolor, $schip] = $statusMeta[$room->status] ?? $statusMeta['tersedia']; @endphp
        <div class="card p-5 flex flex-col gap-3 transition-all duration-200" data-drag-id="{{ $room->id }}">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-bold text-slate-800 dark:text-slate-100">{{ $room->kode }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $room->nama }}{{ ($room->gedung || $room->lantai) ? ' • '.trim($room->gedung.' '.$room->lantai) : '' }}</p>
                </div>
                <span class="badge {{ $room->status==='tersedia' ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300' : ($room->status==='digunakan' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' : 'bg-rose-100 dark:bg-rose-900 text-rose-700 dark:text-rose-300') }}">{{ $slabel }}</span>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">Kapasitas: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $room->kapasitas ? $room->kapasitas.' orang' : '—' }}</span></p>

            @if(!empty($room->fasilitas))
            <div class="flex flex-wrap gap-1.5">
                @foreach($room->fasilitas as $f)
                    <span class="text-[11px] px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $f }}</span>
                @endforeach
            </div>
            @endif

            <div class="mt-auto pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                @can('sarpras.peminjaman.ajukan')
                <button type="button" @click="form.ruangan_id='{{ $room->id }}'; open=true"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                    <i data-lucide="plus" class="w-4 h-4"></i> Ajukan penggunaan
                </button>
                @else
                <div></div>
                @endcan

                @can('sarpras.denah.kelola')
                <div class="flex items-center gap-1">
                    @if($room->denah)
                    <a href="{{ route('sarpras.denah.hotspot', $room->denah) }}"
                       class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950/20 transition"
                       title="Atur Tata Letak / Posisi (Drag & Drop)">
                        <i data-lucide="move" class="w-4 h-4"></i>
                    </a>
                    @endif
                    <button type="button" @click="editForm.action='{{ route('sarpras.ruangan.update', $room) }}'; editForm.kode='{{ $room->kode }}'; editForm.nama='{{ $room->nama }}'; editForm.kapasitas='{{ $room->kapasitas }}'; editForm.warna='{{ $room->warna_hex }}'; editForm.pos_x='{{ $room->pos_x }}'; editForm.pos_y='{{ $room->pos_y }}'; editForm.lebar='{{ $room->lebar }}'; editForm.tinggi='{{ $room->tinggi }}'; openEdit=true"
                            class="p-1.5 text-slate-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition" title="Edit Ruangan">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                    </button>
                    <form method="POST" action="{{ route('sarpras.ruangan.destroy', $room) }}"
                          onsubmit="return confirmAction(this, 'Hapus ruangan &ldquo;{{ $room->kode }}&rdquo;? Semua aset di dalamnya akan terputus dari ruangan ini.', 'red')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-1.5 text-slate-400 hover:text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/20 transition" title="Hapus Ruangan">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
                @endcan
            </div>
        </div>
        @endforeach
    </div>

    {{-- Riwayat booking (untuk pemohon: miliknya) --}}
    @if($bookings->isNotEmpty())
    <div class="card p-5">
        <h3 class="font-bold text-slate-800 dark:text-slate-100 mb-3">Log Reservasi &amp; Jadwal Ruangan</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-400 dark:text-slate-500 border-b border-slate-100 dark:border-slate-700">
                    <th class="pb-2 font-semibold">Ruangan</th><th class="pb-2 font-semibold">Kegiatan</th><th class="pb-2 font-semibold">Waktu / Tanggal</th><th class="pb-2 font-semibold">Status</th>
                </tr></thead>
                <tbody>
                @foreach($bookings as $b)
                    @php [$blabel, $bcls] = $bookingBadge[$b->status] ?? ['—','bg-slate-100 text-slate-500']; @endphp
                    <tr class="border-b border-slate-50 dark:border-slate-700/50">
                        <td class="py-2.5">
                            <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $b->ruangan?->kode }}</p>
                            <p class="text-[11px] text-slate-400">{{ trim(($b->ruangan?->gedung ?? '').' '.($b->ruangan?->lantai ?? '')) ?: $b->ruangan?->nama }}</p>
                        </td>
                        <td class="py-2.5">
                            <p class="text-slate-700 dark:text-slate-200">{{ $b->keperluan }}</p>
                            <p class="text-[11px] text-slate-400">Oleh: {{ $b->pemohon?->name ?? '—' }}</p>
                        </td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300">
                            {{ $b->mulai->format('d/m/Y') }}<br><span class="text-[11px] text-slate-400">{{ $b->mulai->format('H:i') }} – {{ $b->selesai->format('H:i') }}</span>
                        </td>
                        <td class="py-2.5"><span class="badge {{ $bcls }}">{{ $blabel }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Modal ajukan booking --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[9990] grid place-items-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="open=false">
        <div class="card !rounded-2xl w-full max-w-md p-5 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Ajukan Penggunaan Ruangan</h3>
                <button @click="open=false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            @if($errors->any())
            <div class="rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs px-3 py-2">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('sarpras.booking.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="form-label">Ruangan</label>
                    <select name="ruangan_id" x-model="form.ruangan_id" required class="form-input text-sm">
                        <option value="">— pilih ruangan —</option>
                        @foreach($allRooms as $r)
                            <option value="{{ $r->id }}">{{ $r->kode }} — {{ $r->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Keperluan</label>
                    <input type="text" name="keperluan" value="{{ old('keperluan') }}" required maxlength="255" class="form-input text-sm" placeholder="mis. Rapat Komite Sekolah">
                </div>
                <div>
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" value="{{ old('tanggal', now()->addDay()->format('Y-m-d')) }}" required class="form-input text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="jam_mulai" value="{{ old('jam_mulai', '08:00') }}" required class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="jam_selesai" value="{{ old('jam_selesai', '10:00') }}" required class="form-input text-sm">
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" @click="open=false" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
                    <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-sm font-bold">Kirim Pengajuan</button>
                </div>
            </form>
        </div>
    </div>

    @can('sarpras.denah.kelola')
    {{-- Modal edit ruangan --}}
    <div x-show="openEdit" x-cloak x-transition.opacity class="fixed inset-0 z-[9990] grid place-items-center p-4 bg-slate-900/50 backdrop-blur-sm" @click.self="openEdit=false">
        <div class="card !rounded-2xl w-full max-w-md p-5 space-y-4" @click.stop>
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-100">Edit Ruangan</h3>
                <button @click="openEdit=false" class="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form method="POST" :action="editForm.action" class="space-y-3">
                @csrf @method('PUT')
                <input type="hidden" name="pos_x" x-model="editForm.pos_x">
                <input type="hidden" name="pos_y" x-model="editForm.pos_y">
                <input type="hidden" name="lebar" x-model="editForm.lebar">
                <input type="hidden" name="tinggi" x-model="editForm.tinggi">
                <div>
                    <label class="form-label">Kode Ruangan</label>
                    <input type="text" name="kode" x-model="editForm.kode" required class="form-input text-sm">
                </div>
                <div>
                    <label class="form-label">Nama Ruangan</label>
                    <input type="text" name="nama" x-model="editForm.nama" required class="form-input text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Kapasitas</label>
                        <input type="number" name="kapasitas" x-model="editForm.kapasitas" class="form-input text-sm">
                    </div>
                    <div>
                        <label class="form-label">Warna Blok</label>
                        <input type="color" name="warna" x-model="editForm.warna" class="h-[38px] w-full border rounded px-1 py-1 cursor-pointer bg-white dark:bg-slate-800">
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="button" @click="openEdit=false" class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Batal</button>
                    <button type="submit" class="btn-primary flex-1 py-2.5 rounded-xl text-sm font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
