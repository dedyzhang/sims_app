@extends('layouts.app')
@section('title', 'Hasil Pemilihan — '.$pemilihan->nama)

@section('content')
<div class="space-y-6" x-data="osisHasil('{{ route('osis.hasil.data', $pemilihan) }}')" x-init="init()">
    <div>
        <a href="{{ route('osis.show', $pemilihan) }}" class="text-xs text-slate-400 hover:text-primary inline-flex items-center gap-1 mb-1">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> {{ $pemilihan->nama }}
        </a>
        <h1 class="page-title">Hasil Pemilihan</h1>
        <p class="text-xs text-slate-400 mt-0.5">Diperbarui otomatis tiap 15 detik</p>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="card p-4">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                <i data-lucide="users" class="w-4 h-4"></i> Suara Siswa
            </h2>
            <canvas id="chartSiswa" height="220"></canvas>
        </div>
        <div class="card p-4">
            <h2 class="font-semibold text-slate-700 dark:text-slate-200 mb-3 flex items-center gap-2">
                <i data-lucide="graduation-cap" class="w-4 h-4"></i> Suara Guru
            </h2>
            <canvas id="chartGuru" height="220"></canvas>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function osisHasil(url) {
    return {
        chartSiswa: null, chartGuru: null,
        async init() {
            await this.muat();
            window.simsPollInterval(() => this.muat(), 15000); // skip saat tab background
        },
        async muat() {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;
            const cfg = (data, warna) => ({
                type: 'bar',
                data: { labels: d.labels, datasets: [{ data, backgroundColor: warna, borderRadius: 6 }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
            });
            if (this.chartSiswa) { this.chartSiswa.data = cfg(d.siswa, '#7ba088').data; this.chartSiswa.update(); }
            else this.chartSiswa = new Chart(document.getElementById('chartSiswa'), cfg(d.siswa, '#7ba088'));
            if (this.chartGuru) { this.chartGuru.data = cfg(d.guru, '#3b82f6').data; this.chartGuru.update(); }
            else this.chartGuru = new Chart(document.getElementById('chartGuru'), cfg(d.guru, '#3b82f6'));
        },
    };
}
</script>
@endpush
