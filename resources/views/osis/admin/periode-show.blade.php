@extends('layouts.app')
@section('title', $pemilihan->nama)

@section('content')
<div class="space-y-5" x-data="{ tambahPaslon: false, editPaslon: null }">
    <div>
        <a href="{{ route('osis.index') }}" class="text-xs text-slate-400 hover:text-primary inline-flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Semua Periode
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="page-title">{{ $pemilihan->nama }}</h1>
                @if ($pemilihan->tahun_ajaran)<p class="text-sm text-slate-500 dark:text-slate-400">{{ $pemilihan->tahun_ajaran }}</p>@endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('osis.dashboard', $pemilihan) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
                    <i data-lucide="activity" class="w-3.5 h-3.5"></i> Dashboard Live
                </a>
                <a href="{{ route('osis.hasil', $pemilihan) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
                    <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i> Hasil
                </a>
            </div>
        </div>
    </div>

    {{-- Status pemilihan --}}
    @php
        $statusEfektif = $pemilihan->statusEfektif();
        $pesanBukaKonfirmasi = ($pemilihan->jadwal_mulai && $pemilihan->jadwal_mulai->isFuture())
            ? 'Buka pemilihan? Publik baru bisa mulai memilih setelah jadwal mulai ('.$pemilihan->jadwal_mulai->locale('id')->translatedFormat('d M Y, H:i').' WIB) tiba.'
            : 'Buka pemilihan? Publik langsung bisa mulai memilih.';
    @endphp
    <div class="card p-4 flex flex-wrap items-center gap-3">
        <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">Status:</span>
        <span class="badge {{ ['draft' => 'bg-slate-100 text-slate-600', 'dibuka' => 'bg-emerald-100 text-emerald-700', 'ditutup' => 'bg-rose-100 text-rose-600', 'terjadwal' => 'bg-amber-100 text-amber-700'][$statusEfektif] }}">
            {{ $statusEfektif === 'terjadwal' ? 'Terjadwal' : ucfirst($statusEfektif) }}
        </span>
        @if ($statusEfektif === 'terjadwal')
            <span class="text-xs text-slate-400">Mulai {{ $pemilihan->jadwal_mulai->locale('id')->translatedFormat('d M Y, H:i') }} WIB</span>
        @endif
        <div class="flex gap-2 ml-auto">
            @foreach (['draft' => 'Draf', 'dibuka' => 'Buka', 'ditutup' => 'Tutup'] as $val => $label)
                @if ($pemilihan->status !== $val)
                <form method="POST" action="{{ route('osis.status', $pemilihan) }}"
                      onsubmit="return confirmAction(this, '{{ $val === 'dibuka' ? $pesanBukaKonfirmasi : ($val === 'ditutup' ? 'Tutup pemilihan? Publik tak bisa memilih lagi.' : 'Set ke draf?') }}', '{{ $val === 'ditutup' ? 'red' : 'orange' }}')">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $val }}">
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                        {{ $label }}
                    </button>
                </form>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Jadwal pemilihan (opsional) — gerbang tambahan di atas status, lihat OsisPemilihan::bolehMemilihSekarang() --}}
    <div class="card p-4">
        <div class="flex items-center gap-2 mb-2">
            <i data-lucide="calendar-clock" class="w-4 h-4 text-slate-400"></i>
            <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">Jadwal Pemilihan (opsional)</span>
        </div>
        <p class="text-xs text-slate-400 mb-3">
            Kalau diisi, publik tetap tidak bisa memilih sebelum waktu mulai tiba — walau status sudah "Buka".
            Kosongkan lalu simpan untuk menghapus jadwal (voting kembali mengikuti status saja).
        </p>
        <form method="POST" action="{{ route('osis.jadwal', $pemilihan) }}" class="grid sm:grid-cols-[1fr_1fr_auto] gap-2 items-end">
            @csrf @method('PATCH')
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Mulai</label>
                <input type="datetime-local" name="jadwal_mulai" class="form-input"
                       value="{{ old('jadwal_mulai', $pemilihan->jadwal_mulai?->format('Y-m-d\TH:i')) }}">
            </div>
            <div>
                <label class="text-xs text-slate-400 mb-1 block">Selesai (opsional)</label>
                <input type="datetime-local" name="jadwal_selesai" class="form-input"
                       value="{{ old('jadwal_selesai', $pemilihan->jadwal_selesai?->format('Y-m-d\TH:i')) }}">
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:var(--cp)">Simpan</button>
        </form>
        @error('jadwal_selesai') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Paslon --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200">Paslon</h2>
            <button @click="tambahPaslon = !tambahPaslon" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--cp)">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah Paslon
            </button>
        </div>

        <div x-show="tambahPaslon" x-cloak class="card p-4 mb-3">
            @include('osis.admin.partials.form-paslon', ['action' => route('osis.paslon.store', $pemilihan), 'paslon' => null])
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            @forelse ($pemilihan->paslon as $p)
            <div class="card p-4">
                <template x-if="editPaslon !== '{{ $p->uuid }}'">
                    <div class="flex gap-3">
                        <div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0">
                            @if ($p->foto_url)<img src="{{ $p->foto_url }}" class="w-full h-full object-cover">@endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="badge bg-primary/10 text-primary">No. {{ $p->nomor_urut }}</span>
                            <div class="font-bold text-slate-800 dark:text-white">{{ $p->nama_ketua }}@if($p->nama_wakil) & {{ $p->nama_wakil }}@endif</div>
                            @if ($p->visi)<p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">{{ $p->visi }}</p>@endif
                            <div class="flex gap-2 mt-2">
                                <button @click="editPaslon = '{{ $p->uuid }}'" class="text-xs text-primary font-semibold">Edit</button>
                                <form method="POST" action="{{ route('osis.paslon.destroy', [$pemilihan, $p]) }}" onsubmit="return confirmDelete(this)">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-500 font-semibold">Hapus</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="editPaslon === '{{ $p->uuid }}'">
                    <div>
                        @include('osis.admin.partials.form-paslon', ['action' => route('osis.paslon.update', [$pemilihan, $p]), 'paslon' => $p, 'method' => 'PUT'])
                        <button @click="editPaslon = null" class="text-xs text-slate-400 mt-2">Batal</button>
                    </div>
                </template>
            </div>
            @empty
            <p class="text-sm text-slate-400 col-span-2 py-4 text-center">Belum ada paslon. Tambahkan lewat tombol di atas.</p>
            @endforelse
        </div>
    </div>

    {{-- Token & Cetak QR --}}
    <div>
        <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-2">Token Pemilih &amp; Cetak QR</h2>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-left">Kelas</th>
                        <th class="text-center w-32">Token Dibuat</th>
                        <th class="text-center w-64">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kelasList as $k)
                    <tr>
                        <td class="font-medium text-slate-700 dark:text-slate-200">Kelas {{ $k->tingkat }}{{ $k->kelas }}</td>
                        <td class="text-center text-sm">{{ $tokenPerKelas[$k->uuid] ?? 0 }} / {{ $k->siswa_count }}</td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('osis.pemilih.generateSiswa', $pemilihan) }}" class="inline">
                                @csrf
                                <input type="hidden" name="id_kelas" value="{{ $k->uuid }}">
                                <button type="submit" class="text-xs text-primary font-semibold mr-3">Generate Token</button>
                            </form>
                            @if (($tokenPerKelas[$k->uuid] ?? 0) > 0)
                                <a href="{{ route('osis.pemilih.cetakKelas', [$pemilihan, $k]) }}" target="_blank" class="text-xs text-slate-500 dark:text-slate-400 font-semibold mr-3">Cetak QR</a>
                                <a href="{{ route('osis.pemilih.cetakAbsensiKelas', [$pemilihan, $k]) }}" target="_blank" class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Cetak Absensi</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    <tr>
                        <td class="font-medium text-slate-700 dark:text-slate-200">Guru &amp; Karyawan</td>
                        <td class="text-center text-sm">{{ $tokenGuru }} / {{ $totalGuru }}</td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('osis.pemilih.generateGuru', $pemilihan) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs text-primary font-semibold mr-3">Generate Token</button>
                            </form>
                            @if ($tokenGuru > 0)
                                <a href="{{ route('osis.pemilih.cetakGuru', $pemilihan) }}" target="_blank" class="text-xs text-slate-500 dark:text-slate-400 font-semibold mr-3">Cetak QR</a>
                                <a href="{{ route('osis.pemilih.cetakAbsensiGuru', $pemilihan) }}" target="_blank" class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Cetak Absensi</a>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
@endsection
