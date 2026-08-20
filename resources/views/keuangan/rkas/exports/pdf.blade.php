<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>RKAS {{ $plan->tahun_anggaran }}</title><style>body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#1f2937}h1{font-size:18px;margin:0 0 4px}h2{font-size:12px;margin:18px 0 6px}.meta{margin:0 0 12px;color:#4b5563}.notice{border:1px solid #f59e0b;background:#fffbeb;padding:8px;margin:12px 0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:5px}th{background:#f3f4f6;text-align:left}.num{text-align:right}.small{font-size:8px;color:#6b7280}</style></head>
<body>
<h1>RKAS / BOSP — {{ $plan->nama_sekolah }}</h1>
<p class="meta">Tahun {{ $plan->tahun_anggaran }} · {{ $plan->jenjang }} · {{ $plan->sumber_dana }} · NPSN {{ $plan->npsn ?: '-' }}<br>Referensi: {{ $plan->referenceSet?->versi ?? '-' }} · Checksum: {{ $plan->referenceSet?->source_checksum ?? '-' }} · Status SIMS: {{ $plan->status }}</p>
<div class="notice">Berkas ini adalah alat bantu penyusunan dan pemeriksaan internal SIMS. Pengesahan, penatausahaan, dan sinkronisasi resmi dilakukan melalui aplikasi ARKAS/MARKAS.</div>
<table><tr><th>Pagu</th><th>Total rencana</th><th>Sisa pagu</th></tr><tr><td class="num">Rp {{ number_format((int)$plan->pagu,0,',','.') }}</td><td class="num">Rp {{ number_format($plan->totalPlanned(),0,',','.') }}</td><td class="num">Rp {{ number_format((int)$plan->pagu-$plan->totalPlanned(),0,',','.') }}</td></tr></table>
<h2>Kertas Kerja ARKAS</h2>
<table><thead><tr><th>Bulan</th><th>Kode</th><th>Komponen</th><th>Uraian belanja</th><th>Qty</th><th>Satuan</th><th>Harga</th><th>Total</th></tr></thead><tbody>@foreach($plan->items as $item)<tr><td>{{ $item->bulan_dianggarkan }}</td><td>{{ $item->kode_kegiatan }}</td><td>{{ $item->komponen }}</td><td>{{ $item->uraian_belanja }}</td><td class="num">{{ number_format((int)$item->jumlah,0,',','.') }}</td><td>{{ $item->satuan }}</td><td class="num">Rp {{ number_format((int)$item->harga_satuan,0,',','.') }}</td><td class="num">Rp {{ number_format((int)$item->total,0,',','.') }}</td></tr>@endforeach</tbody></table>
<h2>Temuan validasi</h2>
@if($plan->validations->isEmpty())<p>Tidak ada temuan tersimpan.</p>@else<table><tr><th>Kode</th><th>Tingkat</th><th>Pesan</th></tr>@foreach($plan->validations as $finding)<tr><td>{{ $finding->kode }}</td><td>{{ $finding->severity }}</td><td>{{ $finding->message }}</td></tr>@endforeach</table>@endif
<h2>Checklist input dan sinkronisasi</h2>
<table><tr><th>Langkah</th><th>Catatan</th></tr><tr><td>1. Validasi SIMS</td><td>Selesaikan seluruh error sebelum input.</td></tr><tr><td>2. Review</td><td>Review Excel/PDF bersama kepala sekolah/komite.</td></tr><tr><td>3. Input ARKAS</td><td>Dilakukan manual pada ARKAS desktop.</td></tr><tr><td>4. Pengesahan dan sinkronisasi</td><td>Dilakukan manual melalui ARKAS/MARKAS sesuai alur resmi.</td></tr><tr><td>5. Arsip SIMS</td><td>Catat status dan unggah bukti pada detail RKAS.</td></tr></table>
<p class="small">Dibuat oleh SIMS pada {{ now()->format('d-m-Y H:i') }}. Periksa kembali terhadap ARKAS/MARKAS dan Juknis aktif sebelum pengesahan.</p>
</body></html>
