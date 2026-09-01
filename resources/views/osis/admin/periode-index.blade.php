@extends('layouts.app')
@section('title', 'Pemilihan OSIS')

@section('content')
<div class="space-y-5" x-data="{ tambah: false }">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="page-title">Pemilihan OSIS</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kelola periode pemilihan ketua OSIS, paslon, token QR, dan hasil suara.</p>
        </div>
        <button @click="tambah = !tambah" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-white flex-shrink-0" style="background:var(--cp)">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Periode Baru
        </button>
    </div>

    <div x-show="tambah" x-cloak class="card p-4">
        <form method="POST" action="{{ route('osis.store') }}" class="grid sm:grid-cols-[1fr_auto_auto] gap-2">
            @csrf
            <input type="text" name="nama" required placeholder="Nama periode, mis. Pemilihan Ketua OSIS 2026/2027"
                   class="form-input" value="{{ old('nama') }}">
            <input type="text" name="tahun_ajaran" placeholder="Tahun ajaran (opsional)" class="form-input sm:w-44" value="{{ old('tahun_ajaran') }}">
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:var(--cp)">Simpan</button>
        </form>
        @error('nama') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="text-left">Periode</th>
                    <th class="text-center w-28">Status</th>
                    <th class="text-center w-24">Paslon</th>
                    <th class="text-center w-24">Pemilih</th>
                    <th class="text-center w-20">Aktif</th>
                    <th class="text-center w-24">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $p)
                <tr>
                    <td>
                        <div class="font-semibold text-slate-700 dark:text-slate-200">{{ $p->nama }}</div>
                        @if ($p->tahun_ajaran)<div class="text-xs text-slate-400">{{ $p->tahun_ajaran }}</div>@endif
                    </td>
                    <td class="text-center">
                        <span class="badge {{ ['draft' => 'bg-slate-100 text-slate-600', 'dibuka' => 'bg-emerald-100 text-emerald-700', 'ditutup' => 'bg-rose-100 text-rose-600'][$p->status] }}">
                            {{ ucfirst($p->status) }}
                        </span>
                    </td>
                    <td class="text-center text-sm">{{ $p->paslon_count }}</td>
                    <td class="text-center text-sm">{{ $p->pemilih_count }}</td>
                    <td class="text-center">
                        @if ($p->aktif)
                            <span class="badge bg-primary/10 text-primary"><i data-lucide="check" class="w-3 h-3"></i> Aktif</span>
                        @else
                            <form method="POST" action="{{ route('osis.aktifkan', $p) }}">@csrf @method('PATCH')
                                <button type="submit" class="text-xs text-slate-400 hover:text-primary">Jadikan aktif</button>
                            </form>
                        @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('osis.show', $p) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                            Kelola
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada periode pemilihan. Buat yang pertama lewat tombol di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
