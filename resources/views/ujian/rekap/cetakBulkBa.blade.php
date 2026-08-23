<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bulk Berita Acara</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; font-size: 13px; }

        .page { width: 170mm; padding: 18mm 20mm; background: #fff; }

        .kop { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 4mm; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .kop .logo-cell { width: 60px; text-align: center; }
        .kop .logo-cell img { width: 60px; height: 60px; object-fit: contain; }
        .kop .ident-cell { text-align: center; }
        .kop .ident-cell p, .kop .ident-cell h1, .kop .ident-cell h2 { margin: 1px 0; line-height: 1.25; }

        .judul { text-align: center; margin: 3mm 0 6mm; }
        .judul .t1 { font-size: 17px; font-weight: 700; letter-spacing: .5px; margin: 0; text-decoration: underline; }
        .judul .t2 { font-size: 14px; font-weight: 700; margin: 3px 0 0; }
        .judul .t3 { font-size: 13px; margin: 2px 0 0; }
        .judul .t4 { font-size: 13px; margin: 2px 0 0; }

        .narasi { text-align: justify; line-height: 1.6; margin-bottom: 6mm; }

        table.fields { width: 100%; border-collapse: collapse; margin-bottom: 6mm; font-size: 13px; }
        table.fields td { padding: 2.5px 0; vertical-align: top; }
        table.fields .lbl { width: 46mm; }
        table.fields .colon { width: 5mm; }

        .catatan-title { font-weight: 700; margin-bottom: 2mm; }
        .catatan-box { border: 1px solid #000; min-height: 20mm; padding: 3mm; margin-bottom: 8mm; white-space: pre-line; line-height: 1.5; }

        .ttd-heading { margin-bottom: 6mm; }
        table.ttd { width: 100%; border-collapse: collapse; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; }
        table.ttd .peran { margin: 0 0 16mm; }
        table.ttd .nm { font-weight: 700; text-decoration: underline; margin: 0; }
        table.ttd .nik { margin: 2px 0 0; }

        @page { size: A4; margin: 0; }
        
        .page-wrapper {
            page-break-after: always;
        }
        .page-wrapper:last-child {
            page-break-after: auto;
        }
    </style>
</head>
<body>
    @foreach($pdfData as $data)
        <div class="page-wrapper">
            @include('ujian.ruangan._beritaAcaraBody', [
                'ruangan' => $data['ruangan'],
                'paket' => $data['paket'],
                'beritaAcara' => $data['beritaAcara'],
                'sesi' => $data['sesi'],
                'jumlahSeharusnya' => $data['jumlahSeharusnya'],
            ])
        </div>
    @endforeach
</body>
</html>
