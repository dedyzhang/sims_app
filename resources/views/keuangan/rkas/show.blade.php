@extends('layouts.app')
@section('title', 'RKAS '.$plan->tahun_anggaran)

@section('content')
@php
    $statusLabels = ['draft'=>'Draft','validated'=>'Tervalidasi','ready_for_arkas_input'=>'Siap input ARKAS','submitted_in_arkas'=>'Dicatat submitted di ARKAS','approved'=>'Disetujui','revision_required'=>'Perlu revisi','archived'=>'Arsip'];
    $errorsFound = $plan->validations->where('severity', 'error');
    $warningsFound = $plan->validations->where('severity', 'warning');
    $total = $plan->totalPlanned();
    $statusOptions = match($plan->status) {
        'validated' => ['ready_for_arkas_input'=>'Siap input ARKAS', 'submitted_in_arkas'=>'Sudah input di ARKAS'],
        'ready_for_arkas_input' => ['submitted_in_arkas'=>'Sudah input di ARKAS'],
        'submitted_in_arkas' => ['approved'=>'Disetujui di ARKAS/MARKAS', 'revision_required'=>'Perlu revisi'],
        'approved' => ['archived'=>'Arsip'],
        default => [],
    };
@endphp
<style>
    .rkas-detail .rkas-action-button {
        min-height: 44px;
        padding: .65rem 1rem;
        border: 0;
        border-radius: 12px;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        background: #4f6ff2;
        color: #fff;
        font-size: .875rem;
        font-weight: 700;
        line-height: 1.2;
        box-shadow: 0 7px 16px -8px rgba(79, 111, 242, .75);
        transition: background .18s ease, box-shadow .18s ease, transform .18s ease;
    }
    .rkas-detail .rkas-action-button:hover { background: #3f5fe2; box-shadow: 0 10px 20px -8px rgba(79, 111, 242, .85); transform: translateY(-1px); }
    .rkas-detail .rkas-action-button:active { transform: translateY(0); }
    .rkas-detail .rkas-action-button:focus-visible { outline: 3px solid rgba(79, 111, 242, .28); outline-offset: 3px; }
    .rkas-detail .rkas-action-button > span:first-child {
        width: 22px;
        height: 22px;
        display: grid;
        place-items: center;
        border-radius: 7px;
        background: rgba(255,255,255,.16);
    }
    .dark .rkas-detail .rkas-action-button { background: #5c78ff; }
    .dark .rkas-detail .rkas-action-button:hover { background: #6b85ff; }
</style>
<div class="rkas-detail space-y-5">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div><a href="{{ route('keuangan.rkas.index') }}" class="text-sm text-slate-500 hover:underline">← Semua RKAS</a><h1 class="page-title mt-2">{{ $plan->nama_sekolah }} · RKAS {{ $plan->tahun_anggaran }}</h1><p class="text-sm text-slate-500 mt-1">{{ $plan->jenjang }} · {{ $plan->sumber_dana }} · Referensi {{ $plan->referenceSet?->versi ?? 'tidak tersedia' }}</p></div>
        <div class="flex gap-2 flex-wrap">
            @if($canManage && !in_array($plan->status, ['ready_for_arkas_input','submitted_in_arkas','approved','archived'], true)) <a href="{{ route('keuangan.rkas.edit', $plan) }}" class="btn-secondary inline-flex items-center gap-2"><i data-lucide="pencil" class="w-4 h-4"></i> Edit</a> @endif
            <a href="{{ route('keuangan.rkas.export.excel', $plan) }}" class="btn-secondary inline-flex items-center gap-2"><i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel</a>
            <a href="{{ route('keuangan.rkas.export.pdf', $plan) }}" class="btn-secondary inline-flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> PDF</a>
        </div>
    </div>

    @if(session('success')) <div class="card p-3 border-l-4 border-emerald-400 text-sm text-emerald-700 dark:text-emerald-300">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="card p-3 border-l-4 border-rose-400 text-sm text-rose-700 dark:text-rose-300">{{ session('error') }}</div> @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="card p-4"><p class="text-xs text-slate-500">Status</p><p class="font-bold mt-1">{{ $statusLabels[$plan->status] ?? $plan->status }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Pagu BOSP</p><p class="font-bold mt-1">Rp {{ number_format((int)$plan->pagu, 0, ',', '.') }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Total rencana</p><p class="font-bold mt-1">Rp {{ number_format($total, 0, ',', '.') }}</p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Sisa pagu</p><p class="font-bold mt-1 {{ $plan->pagu - $total < 0 ? 'text-rose-600' : 'text-emerald-600' }}">Rp {{ number_format((int)$plan->pagu - $total, 0, ',', '.') }}</p></div>
    </div>

    <div class="card p-4 border-l-4 border-indigo-400 text-sm text-slate-600 dark:text-slate-300">Berkas dan status di halaman ini adalah alat bantu/arsip internal SIMS. Tidak ada klaim data sudah masuk MARKAS sebelum diverifikasi melalui aplikasi ARKAS.</div>

    <div class="card p-4">
        <h2 class="font-bold">Checklist input dan sinkronisasi</h2>
        <ol class="list-decimal pl-5 mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-300">
            <li>Jalankan validasi SIMS dan selesaikan semua error.</li>
            <li>Unduh Excel/PDF dan review bersama kepala sekolah/komite.</li>
            <li>Input atau cocokkan Kertas Kerja pada ARKAS desktop.</li>
            <li>Lakukan pengesahan dan sinkronisasi resmi melalui ARKAS/MARKAS sesuai alur yang berlaku.</li>
            <li>Kembali ke SIMS untuk mencatat status serta bukti dokumen. Catatan ini bukan sinkronisasi otomatis.</li>
        </ol>
    </div>

    @if($canManage && in_array($plan->status, ['draft','validated','revision_required'], true))
    <div class="card p-4 flex items-center justify-between gap-3 flex-wrap">
        <div><h2 class="font-bold">Validasi sebelum input</h2><p class="text-xs text-slate-500">Perubahan item setelah validasi akan mengembalikan status ke draft.</p></div>
        <form method="POST" action="{{ route('keuangan.rkas.validate', $plan) }}">@csrf<button type="submit" class="rkas-action-button" aria-label="Jalankan validasi RKAS"><span><i data-lucide="shield-check" class="w-4 h-4"></i></span><span>Jalankan validasi</span></button></form>
    </div>
    @endif

    @if($errorsFound->isNotEmpty() || $warningsFound->isNotEmpty())
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        @if($errorsFound->isNotEmpty()) <div class="card p-4 border-l-4 border-rose-400"><h2 class="font-bold text-rose-700 mb-2">Error ({{ $errorsFound->count() }})</h2><ul class="space-y-2 text-sm text-rose-700">@foreach($errorsFound as $finding)<li><strong>{{ $finding->kode }}</strong>: {{ $finding->message }}</li>@endforeach</ul></div> @endif
        @if($warningsFound->isNotEmpty()) <div class="card p-4 border-l-4 border-amber-400"><h2 class="font-bold text-amber-700 mb-2">Peringatan ({{ $warningsFound->count() }})</h2><ul class="space-y-2 text-sm text-amber-700">@foreach($warningsFound as $finding)<li><strong>{{ $finding->kode }}</strong>: {{ $finding->message }}</li>@endforeach</ul></div> @endif
    </div>
    @else
        <div class="card p-4 border-l-4 border-emerald-400 text-sm text-emerald-700">Belum ada temuan validasi tersimpan. Jalankan validasi sebelum ekspor.</div>
    @endif

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700"><h2 class="font-bold">Kertas Kerja ARKAS</h2></div>
        <div class="overflow-x-auto"><table class="w-full text-sm min-w-[1000px]">
            <thead class="bg-slate-50 dark:bg-slate-800/70 text-xs text-slate-500"><tr><th class="text-left p-3">Bulan</th><th class="text-left p-3">Kode / Komponen</th><th class="text-left p-3">Uraian belanja</th><th class="text-right p-3">Qty</th><th class="text-left p-3">Satuan</th><th class="text-right p-3">Harga</th><th class="text-right p-3">Total</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">@forelse($plan->items as $item)<tr><td class="p-3">{{ $item->bulan_dianggarkan }}</td><td class="p-3"><span class="font-semibold">{{ $item->kode_kegiatan }}</span><div class="text-xs text-slate-500">{{ $item->komponen ?: $item->reference?->uraian_kegiatan }}</div></td><td class="p-3">{{ $item->uraian_belanja }} @if($item->penjelasan_implementasi)<div class="text-xs text-slate-500">{{ $item->penjelasan_implementasi }}</div>@endif</td><td class="p-3 text-right">{{ number_format((int)$item->jumlah, 0, ',', '.') }}</td><td class="p-3">{{ $item->satuan }}</td><td class="p-3 text-right">Rp {{ number_format((int)$item->harga_satuan, 0, ',', '.') }}</td><td class="p-3 text-right font-semibold">Rp {{ number_format((int)$item->total, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada item.</td></tr>@endforelse</tbody>
            <tfoot><tr class="bg-slate-50 dark:bg-slate-800/70 font-bold"><td colspan="6" class="p-3 text-right">Total</td><td class="p-3 text-right">Rp {{ number_format($total, 0, ',', '.') }}</td></tr></tfoot>
        </table></div>
    </div>

    @if($canManage && count($statusOptions))
    <div class="card p-4">
        <h2 class="font-bold">Catat proses ARKAS/MARKAS</h2><p class="text-xs text-slate-500 mt-1">Form ini hanya audit manual; tidak melakukan sinkronisasi eksternal.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('keuangan.rkas.status', $plan) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">@csrf
            <select name="status" required class="form-input">@foreach($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-input">
            <textarea name="note" rows="2" class="form-input md:col-span-2" placeholder="Catatan verifikasi, nomor berita acara, atau alasan revisi"></textarea>
            <div class="md:col-span-2 flex justify-end"><button type="submit" class="rkas-action-button" aria-label="Simpan audit status"><span><i data-lucide="save" class="w-4 h-4"></i></span><span>Simpan audit status</span></button></div>
        </form>
    </div>
    @endif

    <div class="card p-4">
        <h2 class="font-bold mb-3">Riwayat status</h2>
        <div class="space-y-3">@forelse($plan->syncLogs as $log)<div class="flex gap-3 text-sm"><div class="mt-1 w-2 h-2 rounded-full bg-indigo-500 shrink-0"></div><div><p class="font-semibold">{{ $statusLabels[$log->status] ?? $log->status }} · {{ $log->actor?->name ?? 'Pengguna' }}</p><p class="text-xs text-slate-500">{{ optional($log->occurred_at)->format('d M Y H:i') }} @if($log->note) · {{ $log->note }} @endif @if($log->evidence_path) · <a class="text-indigo-600 hover:underline" href="{{ route('keuangan.rkas.evidence', $log) }}">Bukti</a> @endif</p></div></div>@empty<p class="text-sm text-slate-500">Belum ada catatan status ARKAS/MARKAS.</p>@endforelse</div>
    </div>
</div>
@endsection
