@extends('layouts.app')
@section('title', 'Langganan')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="page-title">Langganan SIMS</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Tetapkan dan pantau masa aktif lisensi. Saat kadaluarsa, seluruh pengguna selain superadmin akan terkunci.
            Checklist demo/jual: <code class="text-xs">docs/demo-school-checklist.md</code>.
        </p>
    </div>

    {{-- ── Status lisensi berjalan ─────────────────────────────────────────── --}}
    <div class="card p-5">
        @if($langganan)
            @php
                $sisa = $langganan->sisaHari();
                $tingkat = $langganan->tingkatPeringatan();
                $badge = match ($tingkat) {
                    'kadaluarsa' => ['bg-rose-500/10 text-rose-600 dark:text-rose-400', 'circle-x', 'Kadaluarsa'],
                    'merah'      => ['bg-rose-500/10 text-rose-600 dark:text-rose-400', 'alarm-clock', 'Segera berakhir'],
                    'kuning'     => ['bg-amber-500/10 text-amber-600 dark:text-amber-400', 'clock-alert', 'Hampir berakhir'],
                    'info'       => ['bg-sky-500/10 text-sky-600 dark:text-sky-400', 'info', 'Perlu perhatian'],
                    default      => ['bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', 'badge-check', 'Aktif'],
                };
            @endphp
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $badge[0] }}">
                        <i data-lucide="{{ $badge[1] }}" class="w-3.5 h-3.5"></i>{{ $badge[2] }}
                    </span>
                    <p class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">
                        @if($sisa > 0)
                            Langganan aktif · sisa {{ $sisa }} hari
                        @elseif($sisa === 0)
                            Langganan berakhir hari ini
                        @else
                            Kadaluarsa {{ abs($sisa) }} hari lalu
                        @endif
                    </p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $langganan->durasi_bulan }} bulan ·
                        {{ $langganan->mulai_pada->translatedFormat('d F Y') }} —
                        <span class="font-semibold">{{ $langganan->berakhir_pada->translatedFormat('d F Y') }}</span>
                        @if($langganan->paket) · Paket <span class="capitalize font-semibold">{{ $langganan->paket }}</span> @endif
                    </p>
                    @if($langganan->catatan)
                    <p class="text-xs text-slate-400 dark:text-slate-500">Catatan: {{ $langganan->catatan }}</p>
                    @endif
                </div>

                {{-- Perpanjang cepat --}}
                <form method="POST" action="{{ route('langganan.perpanjang') }}" class="flex items-end gap-2">
                    @csrf
                    <div>
                        <label class="form-label" for="perpanjang_durasi">Perpanjang</label>
                        <select id="perpanjang_durasi" name="durasi_bulan" class="form-select">
                            <option value="3">3 bulan</option>
                            <option value="6">6 bulan</option>
                            <option value="12" selected>12 bulan</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold">
                        <i data-lucide="calendar-plus" class="w-4 h-4"></i>Perpanjang
                    </button>
                </form>
            </div>
        @else
            <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400">
                <i data-lucide="badge-alert" class="w-5 h-5"></i>
                <p class="text-sm">Belum ada langganan yang ditetapkan. Gunakan formulir di bawah untuk menetapkan masa aktif pertama.</p>
            </div>
        @endif
    </div>

    {{-- ── Tetapkan masa langganan ─────────────────────────────────────────── --}}
    <div class="card p-5">
        <h2 class="font-bold text-slate-700 dark:text-slate-200 mb-1">{{ $langganan ? 'Tetapkan Ulang Langganan' : 'Tetapkan Langganan' }}</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
            Tanggal berakhir dihitung dari tanggal mulai + durasi (kalender nyata).
            @if($langganan) Menetapkan ulang akan <span class="font-semibold">menggantikan</span> masa aktif saat ini. @endif
        </p>

        @if($errors->any())
        <div class="mb-4 rounded-xl bg-rose-500/10 px-4 py-3 text-sm text-rose-600 dark:text-rose-400">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('langganan.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label class="form-label" for="durasi_bulan">Durasi <span class="text-rose-500">*</span></label>
                <select id="durasi_bulan" name="durasi_bulan" class="form-select" required>
                    <option value="3"  @selected(old('durasi_bulan') == 3)>3 bulan</option>
                    <option value="6"  @selected(old('durasi_bulan') == 6)>6 bulan</option>
                    <option value="12" @selected(old('durasi_bulan', 12) == 12)>12 bulan</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="mulai_pada">Tanggal mulai <span class="text-rose-500">*</span></label>
                <input type="date" id="mulai_pada" name="mulai_pada" class="form-input"
                       value="{{ old('mulai_pada', now()->toDateString()) }}" required>
            </div>
            <div>
                <label class="form-label" for="paket">Paket (opsional)</label>
                <select id="paket" name="paket" class="form-select">
                    <option value="" @selected(!old('paket', $langganan->paket ?? ''))>—</option>
                    <option value="dasar" @selected(old('paket', $langganan->paket ?? '') === 'dasar')>Dasar</option>
                    <option value="pro" @selected(old('paket', $langganan->paket ?? '') === 'pro')>Pro</option>
                    <option value="enterprise" @selected(old('paket', $langganan->paket ?? '') === 'enterprise')>Enterprise</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="catatan">Catatan (opsional)</label>
                <input type="text" id="catatan" name="catatan" class="form-input" maxlength="2000"
                       value="{{ old('catatan') }}" placeholder="mis. pembayaran transfer 11 Juli">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="btn-primary inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold">
                    <i data-lucide="badge-check" class="w-4 h-4"></i>Simpan Masa Langganan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
