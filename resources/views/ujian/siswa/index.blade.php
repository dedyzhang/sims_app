@extends('layouts.app')
@section('title', 'Ujian Saya')

@section('content')
<div class="max-w-2xl mx-auto space-y-5">
    <div>
        <h1 class="page-title">Ujian Saya</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Daftar ujian yang ditetapkan untuk kelas Anda.</p>
    </div>

    <x-qr-scan-button label="Scan QR Ruangan Ujian" />

    <div class="space-y-6">
        @forelse($groupedUjianKelas as $groupName => $ujianKelasList)
        <div x-data="{ open: true }">
            <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 uppercase tracking-wider mb-3 focus:outline-none transition w-full text-left">
                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="!open ? '-rotate-90' : ''"></i>
                {{ $groupName }}
                <span class="text-xs font-medium bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full ml-1">{{ $ujianKelasList->count() }}</span>
            </button>
            <div x-show="open" x-transition class="grid gap-3">
                @foreach($ujianKelasList as $uk)
                @php
                    $attempt = $attempts->get($uk->uuid);
                    $sudahSelesai = $attempt && in_array($attempt->status, ['submitted','dinilai']);
                @endphp
                <div class="card p-5 flex items-center justify-between gap-4 min-w-0">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-400">{{ $uk->ujian->pelajaran?->nama }} &middot; {{ $uk->ujian->jenisLabel() }} &middot; {{ $uk->ujian->durasi_menit }} menit</p>
                        <h2 class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $uk->ujian->judul }}</h2>
                        <p class="text-xs mt-1">
                            @if($sudahSelesai)
                                <span class="badge bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300">Sudah Dikumpulkan</span>
                            @elseif($attempt?->isLocked())
                                <span class="badge bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">Terkunci</span>
                            @elseif($attempt)
                                <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">Sedang Dikerjakan</span>
                            @elseif($uk->belumDimulai() && !in_array($uk->id_ujian, $susulanIds))
                                <span class="badge bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
                                    <i data-lucide="clock" class="w-3 h-3 inline"></i> Belum Dimulai
                                </span>
                            @elseif(!$uk->isOpenNow() && !in_array($uk->id_ujian, $susulanIds))
                                <span class="badge bg-slate-100 dark:bg-slate-700 text-slate-500">Ujian Ditutup</span>
                            @else
                                <span class="badge bg-primary/10 text-primary">{{ in_array($uk->id_ujian, $susulanIds) ? 'Ujian Susulan' : 'Siap Dikerjakan' }}</span>
                            @endif
                            @if($uk->belumDimulai() && !in_array($uk->id_ujian, $susulanIds))
                                <span class="block text-slate-400 mt-1">Dibuka {{ $uk->dibuka_mulai->translatedFormat('d M Y, H:i') }}</span>
                                <span class="block font-mono font-bold text-amber-600 dark:text-amber-400 mt-0.5" data-hitung-mundur="{{ $uk->dibuka_mulai->getTimestampMs() }}"></span>
                            @endif
                        </p>
                    </div>
                    @if($sudahSelesai)
                        <a href="{{ route('ujian.siswa.hasil', [$uk->ujian, $attempt]) }}" class="px-4 py-2 rounded-xl text-xs font-semibold border border-slate-200 dark:border-slate-600 whitespace-nowrap">Lihat Hasil</a>
                    @elseif($uk->isOpenNow() || $attempt || in_array($uk->id_ujian, $susulanIds))
                        <a href="{{ route('ujian.siswa.gate', $uk->ujian) }}" class="btn-primary px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap">{{ $attempt ? 'Lanjutkan' : 'Mulai' }}</a>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @empty
        <div class="card p-10 text-center text-slate-400">
            <i data-lucide="file-check-2" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
            <p class="text-sm font-medium">Belum ada ujian untuk kelas Anda.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
// Hitung mundur per ujian yg belum dimulai + muat ulang OTOMATIS sekali saat yg terdekat
// tiba (jeda acak 0–15 dtk per siswa) — siswa tak perlu menekan refresh berulang kali sambil
// menunggu (tiap refresh = 1 request penuh ke server, ×ratusan siswa). Jam dihitung dari jam
// SERVER, bukan jam HP siswa.
(function () {
    const el = document.querySelectorAll('[data-hitung-mundur]');
    if (!el.length) return;
    const selisih = {{ now()->getTimestampMs() }} - Date.now();
    const jedaAcak = Math.floor(Math.random() * 15000);
    const dua = (n) => String(n).padStart(2, '0');
    let sudahReload = false;

    const tick = () => {
        const kini = Date.now() + selisih;
        let terdekat = Infinity;
        el.forEach((s) => {
            const sisa = Number(s.dataset.hitungMundur) - kini;
            terdekat = Math.min(terdekat, sisa);
            if (sisa > 0) {
                const dtk = Math.ceil(sisa / 1000);
                const hari = Math.floor(dtk / 86400);
                const jam = Math.floor((dtk % 86400) / 3600);
                const mnt = Math.floor((dtk % 3600) / 60);
                s.textContent = 'Dimulai dalam ' + (hari > 0 ? hari + ' hari ' : '') + (hari > 0 || jam > 0 ? dua(jam) + ':' : '') + dua(mnt) + ':' + dua(dtk % 60);
            } else {
                s.textContent = 'Membuka… halaman akan diperbarui otomatis';
            }
        });
        if (!sudahReload && terdekat + jedaAcak <= 0) {
            sudahReload = true;
            window.location.reload();
        }
    };
    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
