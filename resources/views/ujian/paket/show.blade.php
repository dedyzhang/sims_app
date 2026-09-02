@extends('layouts.app')
@section('title', $paket->nama)

@section('content')
<div class="max-w-4xl mx-auto space-y-5" x-data="{ editOpen: false, ruanganOpen: false }">
    <div>
        <nav class="text-xs text-slate-400 mb-1"><a href="{{ route('ujian.paket.index') }}" class="hover:underline">Paket Ujian</a> / {{ $paket->nama }}</nav>
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="page-title">{{ $paket->nama }}</h1>
                    <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">{{ $paket->jenisLabel() }}</span>
                    @php
                        $statusBadge = match($paket->status) {
                            'berjalan' => 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300',
                            'selesai'  => 'bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300',
                            default    => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                        };
                    @endphp
                    <span class="badge {{ $statusBadge }}">{{ $paket->statusLabel() }}</span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    @if($paket->semester)Semester {{ $paket->semester->semester }} · {{ $paket->semester->tahun }} · @endif
                    @if($paket->tanggal_mulai){{ $paket->tanggal_mulai->translatedFormat('d M Y') }}@if($paket->tanggal_selesai) – {{ $paket->tanggal_selesai->translatedFormat('d M Y') }}@endif @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="editOpen = true" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">
                    <i data-lucide="pencil" class="w-4 h-4 inline"></i> Edit
                </button>
                <form method="POST" action="{{ route('ujian.paket.destroy', $paket) }}" onsubmit="return confirmAction(this, 'Hapus paket \'{{ addslashes($paket->nama) }}\'? Ujian anggota TIDAK ikut terhapus (jadi ujian standalone lagi), tapi ruangan/jadwal paket ini akan hilang permanen.', 'red')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-rose-600 border border-rose-200 dark:border-rose-800 hover:bg-rose-50 dark:hover:bg-rose-900/20">
                        <i data-lucide="trash-2" class="w-4 h-4 inline"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Ujian Anggota --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">Ujian Anggota ({{ $paket->ujian->count() }})</h2>
        <div class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($paket->ujian as $u)
            <div class="py-2.5 flex items-center justify-between gap-3">
                <a href="{{ route('ujian.show', $u) }}" class="min-w-0 flex-1">
                    <p class="font-medium text-sm text-slate-800 dark:text-slate-200 truncate">{{ $u->judul }}</p>
                    <p class="text-xs text-slate-500">{{ $u->pelajaran?->nama }} · {{ $u->statusLabel() }}</p>
                </a>
                <form method="POST" action="{{ route('ujian.paket.lepasUjian', [$paket, $u]) }}" onsubmit="return confirmAction(this, 'Lepas ujian ini dari paket? Ujian tetap ada sbg ujian standalone.', 'orange')">
                    @csrf
                    <button type="submit" class="text-xs text-rose-500 hover:underline">Lepas</button>
                </form>
            </div>
            @empty
            <p class="text-sm text-slate-400 py-2">Belum ada ujian anggota.</p>
            @endforelse
        </div>

        @if($calonUjian->isNotEmpty())
        <form method="POST" action="{{ route('ujian.paket.tambahUjian', $paket) }}" class="flex items-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
            @csrf
            <div class="flex-1">
                <label class="block text-xs font-medium text-slate-500 mb-1">Tambah ujian standalone ke paket ini</label>
                <select name="id_ujian" class="form-select py-1.5 text-sm" required>
                    <option value="">— pilih ujian —</option>
                    @foreach($calonUjian as $u)
                    <option value="{{ $u->uuid }}">{{ $u->judul }} ({{ $u->pelajaran?->nama }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700">Tambah</button>
        </form>
        @endif
    </div>

    {{-- Ruangan --}}
    <div class="card p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-slate-800 dark:text-slate-100">Ruangan ({{ $paket->ruangan->count() }})</h2>
            <button @click="ruanganOpen = true" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah Ruangan
            </button>
        </div>
        <div class="grid gap-2.5">
            @forelse($paket->ruangan as $r)
            <a href="{{ route('ujian.paket.ruangan.show', [$paket, $r]) }}" class="p-3.5 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-primary transition flex items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-sm">{{ $r->nama }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $r->peserta->count() }} peserta{{ $r->kapasitas ? ' / ' . $r->kapasitas . ' kapasitas' : '' }}</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400"></i>
            </a>
            @empty
            <p class="text-sm text-slate-400 py-2">Belum ada ruangan dibuat.</p>
            @endforelse
        </div>
    </div>

    {{-- Jadwal --}}
    <div class="card p-6 space-y-4">
        <h2 class="font-bold text-slate-800 dark:text-slate-100">Jadwal ({{ $paket->jadwal->count() }})</h2>
        <p class="text-xs text-slate-400">Menentukan kapan tiap ujian anggota terbuka bagi siswa (mengisi jendela buka/tutup token secara otomatis).</p>
        <div class="table-responsive">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Jam</th><th>Ujian</th><th>Sesi</th><th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($paket->jadwal->sortBy('tanggal') as $j)
                    <tr>
                        <td>{{ $j->tanggal->translatedFormat('d M Y') }}</td>
                        <td class="font-mono text-xs">{{ substr($j->jam_mulai,0,5) }}–{{ substr($j->jam_selesai,0,5) }}</td>
                        <td>{{ $j->ujian?->judul }}</td>
                        <td>{{ $j->sesi_label ?? '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('ujian.paket.jadwal.destroy', [$paket, $j]) }}" onsubmit="return confirmAction(this, 'Hapus jadwal ini?', 'red')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-rose-500 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-6 text-slate-400">Belum ada jadwal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($paket->ujian->isNotEmpty())
        <form method="POST" action="{{ route('ujian.paket.jadwal.store', $paket) }}" class="grid grid-cols-2 sm:grid-cols-5 gap-2 items-end pt-2 border-t border-slate-100 dark:border-slate-700">
            @csrf
            <div class="col-span-2 sm:col-span-1">
                <label class="block text-xs font-medium text-slate-500 mb-1">Ujian</label>
                <select name="id_ujian" class="form-select py-1.5 text-sm" required>
                    @foreach($paket->ujian as $u)
                    <option value="{{ $u->uuid }}">{{ $u->judul }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Tanggal</label>
                <input type="date" name="tanggal" class="form-input py-1.5 text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Jam Mulai</label>
                <input type="time" name="jam_mulai" class="form-input py-1.5 text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Jam Selesai</label>
                <input type="time" name="jam_selesai" class="form-input py-1.5 text-sm" required>
            </div>
            <div class="flex gap-2">
                <input type="text" name="sesi_label" placeholder="Sesi (opsional)" class="form-input py-1.5 text-sm flex-1">
                <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700 flex-shrink-0">Tambah</button>
            </div>
        </form>
        @else
        <p class="text-xs text-amber-600 dark:text-amber-400">Tambahkan ujian anggota dulu sebelum bisa membuat jadwal.</p>
        @endif
    </div>

    {{-- Modal Edit Paket --}}
    <div x-show="editOpen" x-cloak class="modal-backdrop" x-transition @click.self="editOpen=false">
        <div class="modal-box max-w-md w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Edit Paket</h3>
                <button @click="editOpen=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form method="POST" action="{{ route('ujian.paket.update', $paket) }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nama Paket</label>
                    <input type="text" name="nama" value="{{ $paket->nama }}" class="form-input" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Jenis</label>
                    <select name="jenis" class="form-select" required>
                        <option value="harian" @selected($paket->jenis==='harian')>Ulangan Harian</option>
                        <option value="pts" @selected($paket->jenis==='pts')>PTS</option>
                        <option value="pas" @selected($paket->jenis==='pas')>PAS</option>
                        <option value="uas" @selected($paket->jenis==='uas')>UAS</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="draft" @selected($paket->status==='draft')>Draf</option>
                        <option value="berjalan" @selected($paket->status==='berjalan')>Berjalan</option>
                        <option value="selesai" @selected($paket->status==='selesai')>Selesai</option>
                    </select>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 px-4 py-3"
                     x-data="{ wajibScan: {{ $paket->wajib_scan_qr ? 'true' : 'false' }} }">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Wajib Scan QR Sebelum Ujian</p>
                            <p class="text-xs text-slate-400 mt-0.5 leading-relaxed" x-show="!wajibScan">
                                Siswa langsung ke halaman ujian → pilih mapel → masukkan token → kerjakan. Tidak perlu scan QR sama sekali.
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5 leading-relaxed" x-show="wajibScan" x-cloak>
                                Siswa wajib scan QR ruangan dulu sebelum bisa memilih mapel ujian. Setelah scan SEKALI, siswa bisa mengikuti SEMUA ujian paket ini hari itu tanpa scan ulang per mapel.
                            </p>
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1.5 leading-relaxed" x-show="wajibScan" x-cloak>
                                <i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> Pastikan ruangan &amp; peserta sudah diatur sebelum mengaktifkan — siswa yang belum terdaftar di ruangan manapun tidak akan bisa mengikuti ujian sama sekali.
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-1">
                            <input type="checkbox" name="wajib_scan_qr" value="1" class="hidden peer" x-model="wajibScan">
                            <div class="relative w-11 h-6 bg-slate-200 dark:bg-slate-600 rounded-full peer-checked:bg-[color:var(--cp)] transition after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition peer-checked:after:translate-x-5"></div>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Semester</label>
                    <select name="id_semester" class="form-select">
                        <option value="">— tidak ditentukan —</option>
                        @foreach(\App\Models\Semester::orderByDesc('tahun')->orderByDesc('semester')->get() as $s)
                        <option value="{{ $s->id }}" @selected($paket->id_semester == $s->id)>Semester {{ $s->semester }} · {{ $s->tahun }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Mulai</label>
                        <input type="date" name="tanggal_mulai" value="{{ $paket->tanggal_mulai?->toDateString() }}" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Selesai</label>
                        <input type="date" name="tanggal_selesai" value="{{ $paket->tanggal_selesai?->toDateString() }}" class="form-input">
                    </div>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="editOpen=false" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</button>
                    <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Tambah Ruangan --}}
    <div x-show="ruanganOpen" x-cloak class="modal-backdrop" x-transition @click.self="ruanganOpen=false">
        <div class="modal-box max-w-sm w-full" @click.stop>
            <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Tambah Ruangan</h3>
                <button @click="ruanganOpen=false" class="p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form method="POST" action="{{ route('ujian.paket.ruangan.store', $paket) }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Nama Ruangan</label>
                    <input type="text" name="nama" placeholder="Contoh: Ruang 7A" class="form-input" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Kapasitas</label>
                    <input type="number" name="kapasitas" min="1" class="form-input">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Keterangan</label>
                    <textarea name="keterangan" rows="2" class="form-input"></textarea>
                </div>
                <div class="pt-2 flex justify-end gap-2">
                    <button type="button" @click="ruanganOpen=false" class="px-4 py-2 rounded-xl text-sm border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Batal</button>
                    <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold">Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
