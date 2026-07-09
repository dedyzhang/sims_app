@extends('layouts.app')
@section('title', 'Kartu Pelajar')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="page-title">Kartu Pelajar Digital</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kartu dibuat <b>otomatis</b> dari data siswa (identitas sekolah, foto, & QR) — siswa langsung bisa mengunduhnya. Unggah file hanya bila ingin memakai desain kartu <b>kustom</b> yang menggantikan versi otomatis.</p>
    </div>

    @if($errors->has('kartu'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-900/20 dark:border-rose-800 px-4 py-3 text-sm text-rose-600 dark:text-rose-400">
            {{ $errors->first('kartu') }}
        </div>
    @endif

    {{-- Filter --}}
    <form method="GET" class="card p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label text-xs">Cari Siswa</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nama / NIS / NISN" class="form-input py-2 text-sm">
        </div>
        <div class="min-w-40">
            <label class="form-label text-xs">Kelas</label>
            <select name="kelas" class="form-select py-2 text-sm">
                <option value="">Semua Kelas</option>
                @foreach($kelas as $k)
                    <option value="{{ $k->uuid }}" @selected($kelasId == $k->uuid)>{{ $k->tingkat }} {{ $k->kelas }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="search" class="w-4 h-4"></i> Cari</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100 dark:border-slate-700">
                    <th class="px-4 py-3 font-semibold">Siswa</th>
                    <th class="px-4 py-3 font-semibold">Kelas</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Unggah / Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($siswa as $s)
                <tr class="align-top">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $s->nama }}</p>
                        <p class="text-xs text-slate-400">NIS {{ $s->nis ?: '—' }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $s->kelas ? $s->kelas->tingkat.' '.$s->kelas->kelas : '—' }}</td>
                    <td class="px-4 py-3">
                        @if($s->kartuPelajar)
                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-900/40 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:text-violet-400"><i data-lucide="image" class="w-3 h-3"></i> Kustom</span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400"><i data-lucide="sparkles" class="w-3 h-3"></i> Otomatis</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('kartu-pelajar.store', $s->uuid) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="kartu" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                                       class="text-xs text-slate-500 dark:text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100 cursor-pointer max-w-[190px]">
                                <button type="submit" class="btn-primary px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap">{{ $s->kartuPelajar ? 'Ganti' : 'Unggah Kustom' }}</button>
                            </form>
                            <a href="{{ route('kartu-pelajar.kelola.lihat', $s->uuid) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="eye" class="w-3.5 h-3.5"></i> Lihat</a>
                            @if($s->kartuPelajar)
                                <form method="POST" action="{{ route('kartu-pelajar.destroy', $s->uuid) }}" onsubmit="return confirm('Hapus kartu kustom {{ $s->nama }}? Kartu otomatis akan dipakai kembali.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 dark:border-rose-800 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">Tidak ada siswa ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $siswa->links() }}</div>
</div>
@endsection
