<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>BA Serah Terima</title></head>
<body style="font-family: sans-serif; font-size: 12px;">
<h2 style="text-align:center">BERITA ACARA SERAH TERIMA BARANG</h2>
<p>Kode usulan: <strong>{{ $pengadaan->kode }}</strong></p>
<p>Judul: {{ $pengadaan->judul }}</p>
<p>Tanggal: {{ now()->format('d F Y') }}</p>
<table width="100%" border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;margin-top:16px">
    <tr><th>No</th><th>Nama Barang</th><th>Qty</th><th>Diterima</th><th>Kondisi</th></tr>
    @foreach($pengadaan->items as $i => $item)
    <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $item->nama_barang }}</td>
        <td>{{ $item->qty }} {{ $item->satuan }}</td>
        <td>{{ $item->qty_diterima ?? '—' }}</td>
        <td>{{ $item->kondisi_terima ?? '—' }}</td>
    </tr>
    @endforeach
</table>
<p style="margin-top:40px">Mengetahui,<br><br><br>________________________</p>
</body>
</html>
