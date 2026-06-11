@extends('layouts.app')
@section('title', 'Jam Pelajaran / Minggu')

@section('content')
@php $breadcrumbs = [['label'=>'Editor Jadwal','url'=>route('jadwal.index')], ['label'=>'Jam/Minggu','url'=>'#']]; @endphp

<div class="max-w-3xl mx-auto space-y-5" x-data="jpTable()">
    <div class="flex items-center gap-3">
        <a href="{{ route('jadwal.index') }}" class="grid place-items-center w-10 h-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary transition">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="page-title">Jam Pelajaran / Minggu</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Atur berapa JP per minggu tiap mapel — dipakai saat Auto-Generate</p>
        </div>
    </div>

    <div class="bg-primary-50 text-primary rounded-2xl p-4 text-sm flex items-start gap-2.5">
        <i data-lucide="info" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
        <p>Saat generate, JP tiap mapel dibagi menjadi <strong>blok jam berurutan</strong> dan disebar ke hari berbeda. Contoh: 6 JP → blok <strong>2-2-2</strong> (3 hari), 4 JP → <strong>2-2</strong> (2 hari), 5 JP → <strong>3-2</strong>.</p>
    </div>

    <form method="POST" action="{{ route('jadwal.jp.save') }}">
        @csrf
        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>Mata Pelajaran</th>
                            <th class="hide-mobile">Kode</th>
                            <th class="text-center w-32">JP / Minggu</th>
                            <th class="hide-mobile">Pembagian Blok</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pelajarans as $i => $p)
                        <tr x-data="{ jp: {{ (int)($p->jp ?? 0) }} }">
                            <td class="text-slate-400 text-xs">{{ $i+1 }}</td>
                            <td class="font-medium text-slate-800 dark:text-slate-200">{{ $p->nama }}</td>
                            <td class="hide-mobile">@if($p->kode)<span class="badge bg-primary-50 text-primary font-mono">{{ $p->kode }}</span>@else<span class="text-slate-300">—</span>@endif</td>
                            <td class="text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" @click="jp = Math.max(0, jp-1)" class="grid place-items-center w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700">−</button>
                                    <input type="number" name="jp[{{ $p->uuid }}]" x-model.number="jp" min="0" max="40" class="form-input !w-14 text-center !py-1.5 font-bold">
                                    <button type="button" @click="jp = Math.min(40, jp+1)" class="grid place-items-center w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-600 text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700">+</button>
                                </div>
                            </td>
                            <td class="hide-mobile">
                                <span class="text-sm font-mono text-slate-500" x-text="blockPreview(jp)"></span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center py-10 text-slate-400">
                            <i data-lucide="book-open" class="w-9 h-9 mx-auto mb-2 opacity-30"></i>
                            <p>Belum ada mata pelajaran.</p>
                            <a href="{{ route('pelajaran.index') }}" class="text-primary hover:underline text-sm">+ Tambah pelajaran</a>
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($pelajarans->isNotEmpty())
        <div class="flex items-center justify-between mt-4 flex-wrap gap-3">
            <p class="text-sm text-slate-500">Total: <span class="font-bold text-slate-700 dark:text-slate-200" x-text="total()"></span> JP/minggu</p>
            <button type="submit" class="btn-primary px-6 py-3 rounded-xl text-sm font-bold flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Simpan JP
            </button>
        </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
function jpTable() {
    return {
        blockPreview(n) {
            n = parseInt(n) || 0;
            if (n <= 0) return '—';
            if (n <= 3) return n + ' (1 hari)';
            let b = [], m = n;
            while (m > 0) { if (m % 2 === 0) { b.push(2); m -= 2; } else { b.push(3); m -= 3; } }
            return b.join('-') + ' (' + b.length + ' hari)';
        },
        total() {
            let t = 0;
            document.querySelectorAll('input[name^="jp["]').forEach(i => t += parseInt(i.value) || 0);
            return t;
        }
    }
}
</script>
@endpush
@endsection
