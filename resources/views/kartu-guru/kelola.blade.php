@extends('layouts.app')
@section('title', 'Kartu ID Guru')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="page-title">Kartu ID Guru</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Kartu identitas dibuat <b>otomatis</b> dari data guru: logo & nama sekolah, foto, nama, <b>jabatan sesuai role akun</b> (jadi teks berulang di background kartu). Unggah foto berupa <b>PNG transparan yang sudah di-cutout</b> (setengah badan) supaya tampil besar menyatu dengan desain kartu.</p>
            <p class="text-xs text-slate-400 mt-1.5"><i data-lucide="scan-line" class="w-3.5 h-3.5 inline -mt-0.5"></i> QR absensi ada di <b>sisi belakang</b> kartu — PDF-nya 2 halaman (depan lalu belakang) supaya bisa dicetak <b>bolak-balik</b>, baik satu-satu maupun massal. Untuk cetak massal, atur duplex printer ke <b>"flip on long edge"</b> (paling umum) agar tiap kartu tetap sejajar dgn baliknya.</p>
        </div>
        <a href="{{ route('kartu-guru.cetak') }}" rel="noopener" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2">
            <i data-lucide="download" class="w-4 h-4"></i> Unduh Semua (Depan+Belakang)
        </a>
    </div>

    @if($errors->has('foto'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 dark:bg-rose-900/20 dark:border-rose-800 px-4 py-3 text-sm text-rose-600 dark:text-rose-400">
            {{ $errors->first('foto') }}
        </div>
    @endif

    {{-- Pencarian --}}
    <form method="GET" class="card p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="form-label text-xs">Cari Guru</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nama / NIP / NIK" class="form-input py-2 text-sm">
        </div>
        <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-sm font-bold flex items-center gap-2"><i data-lucide="search" class="w-4 h-4"></i> Cari</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100 dark:border-slate-700">
                    <th class="px-4 py-3 font-semibold">Guru</th>
                    <th class="px-4 py-3 font-semibold">Jabatan di Kartu</th>
                    <th class="px-4 py-3 font-semibold">Foto</th>
                    <th class="px-4 py-3 font-semibold">Unggah Foto / Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($gurus as $g)
                @php [$jabatanLabel, $jabatanBg] = $jabatans[$g->uuid]; @endphp
                <tr class="align-top">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $g->nama }}</p>
                        <p class="text-xs text-slate-400">{{ $g->nip ? 'NIP '.$g->nip : ($g->nik ? 'NIK '.$g->nik : '—') }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 dark:bg-blue-900/40 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:text-blue-300">{{ $jabatanLabel }}</span>
                        <p class="text-[10px] text-slate-400 mt-1">Background: <span class="font-mono font-bold">{{ $jabatanBg }}</span></p>
                    </td>
                    <td class="px-4 py-3">
                        @if($g->foto)
                            <img src="{{ asset('storage/'.$g->foto) }}" alt="Foto {{ $g->nama }}" class="w-12 h-14 object-cover rounded-lg border border-slate-200 dark:border-slate-600">
                        @else
                            <div class="w-12 h-14 rounded-lg bg-slate-100 dark:bg-slate-700 grid place-items-center text-slate-400"><i data-lucide="user" class="w-5 h-5"></i></div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('kartu-guru.foto', $g->uuid) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                @csrf
                                <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" required
                                       class="text-xs text-slate-500 dark:text-slate-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary hover:file:bg-primary-100 cursor-pointer max-w-[190px]">
                                <button type="submit" class="btn-primary px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap">{{ $g->foto ? 'Ganti' : 'Unggah' }}</button>
                            </form>
                            <a href="{{ route('kartu-guru.lihat', $g->uuid) }}" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 dark:border-slate-600 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="id-card" class="w-3.5 h-3.5"></i> Lihat Kartu</a>
                            @if($g->foto)
                                <form method="POST" action="{{ route('kartu-guru.foto.hapus', $g->uuid) }}" onsubmit="return confirm('Hapus foto {{ $g->nama }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 dark:border-rose-800 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Foto</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">Tidak ada guru ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $gurus->links() }}</div>
</div>
@endsection
