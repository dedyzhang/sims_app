@extends('layouts.app')
@section('title', 'Wawasan Operasional SPP')

@section('content')
<div class="space-y-5" x-data="{
    loading: false,
    narasi: '',
    error: '',
    async mintaNarasi() {
        this.loading = true;
        this.error = '';
        this.narasi = '';
        try {
            const res = await fetch(@js(route('keuangan.bendahara-ai.wawasan.narasi')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ tahun_ajaran: @js($ta) }),
            });
            const data = await res.json();
            if (!data.ok) {
                this.error = data.message || 'Gagal membuat narasi.';
                return;
            }
            this.narasi = data.answer || '';
        } catch (e) {
            this.error = 'Koneksi gagal. Coba lagi.';
        } finally {
            this.loading = false;
        }
    }
}">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">
                <a href="{{ route('keuangan.index', ['ta'=>$ta]) }}" class="hover:underline">Keuangan</a> / Wawasan
            </nav>
            <h1 class="page-title">Wawasan Operasional</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pola antrian & waktu bayar · <strong>bukan</strong> narasi pimpinan · tanpa nominal rupiah di narasi AI</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="ta" onchange="this.form.submit()" class="form-input !w-auto text-sm">
                @foreach($taOptions as $opt)
                    <option value="{{ $opt }}" @selected($opt===$ta)>T.A. {{ $opt }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="card p-4">
            <p class="text-xs text-slate-400">Menunggu Verifikasi</p>
            <p class="text-xl font-bold text-amber-600">{{ $ringkasan['antrian']['menunggu'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400">Tunggu Validasi Bank</p>
            <p class="text-xl font-bold text-sky-600">{{ $ringkasan['antrian']['terverifikasi'] }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400">Bayar Terlambat (dari lunas)</p>
            <p class="text-xl font-bold text-rose-600">{{ $ringkasan['keterlambatan']['persen'] }}%</p>
            <p class="text-[10px] text-slate-400">{{ $ringkasan['keterlambatan']['terlambat'] }}/{{ $ringkasan['keterlambatan']['total_lunas'] }} transaksi</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-slate-400">Hari Bayar Terpopuler</p>
            <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $ringkasan['hari_terpopuler'] ?? '—' }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card p-5">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">Poin Wawasan (aturan sistem)</h2>
            <ul class="space-y-2 text-sm text-slate-600 dark:text-slate-300 list-disc pl-4">
                @foreach($ringkasan['poin_narasi'] as $poin)
                    <li>{{ $poin }}</li>
                @endforeach
            </ul>
        </div>
        <div class="card p-5">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-3">Pola Hari Bayar (jumlah transaksi lunas)</h2>
            <div class="space-y-2">
                @php $maxHari = max(1, collect($ringkasan['hari_bayar'])->max('jumlah')); @endphp
                @foreach($ringkasan['hari_bayar'] as $h)
                    <div class="flex items-center gap-2 text-xs">
                        <span class="w-14 text-slate-500">{{ $h['hari'] }}</span>
                        <div class="flex-1 h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" style="width: {{ $maxHari > 0 ? round($h['jumlah'] / $maxHari * 100) : 0 }}%"></div>
                        </div>
                        <span class="w-6 text-right font-mono">{{ $h['jumlah'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card p-5">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Narasi AI (opsional, non-nominal)</h2>
            <button type="button" @click="mintaNarasi()" :disabled="loading"
                class="btn btn-primary btn-sm inline-flex items-center gap-1.5">
                <i data-lucide="sparkles" class="w-4 h-4" :class="loading ? 'animate-pulse' : ''"></i>
                <span x-text="loading ? 'Memproses...' : 'Buat Narasi'"></span>
            </button>
        </div>
        <p class="text-xs text-slate-500 mb-3">AI hanya menarasikan pola waktu & antrian — tidak menghitung rupiah. Gunakan poin aturan di atas bila AI belum dikonfigurasi.</p>
        <template x-if="error">
            <p class="text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 rounded-lg p-3" x-text="error"></p>
        </template>
        <div x-show="narasi" class="prose prose-sm dark:prose-invert max-w-none text-slate-700 dark:text-slate-200 whitespace-pre-wrap" x-text="narasi"></div>
    </div>

    <div class="card p-4 border-l-4 border-emerald-400">
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-2">Ekspor Paket Verifikasi</p>
        <p class="text-xs text-slate-500 mb-3">Unduh daftar verifikasi untuk arsip sekolah (termasuk nominal di spreadsheet/PDF — bukan bagian narasi AI).</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('keuangan.bendahara-ai.export-paket', ['ta'=>$ta, 'format'=>'excel']) }}" class="btn btn-outline btn-sm">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
            </a>
            <a href="{{ route('keuangan.bendahara-ai.export-paket', ['ta'=>$ta, 'format'=>'pdf']) }}" class="btn btn-outline btn-sm">
                <i data-lucide="file-text" class="w-4 h-4"></i> PDF
            </a>
            <a href="{{ route('keuangan.bendahara-ai.export-paket', ['ta'=>$ta, 'format'=>'excel', 'status'=>'menunggu']) }}" class="btn btn-outline btn-sm text-amber-700">
                Excel — hanya menunggu
            </a>
        </div>
    </div>
</div>
@endsection
