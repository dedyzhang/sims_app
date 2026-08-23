<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bulk Daftar Hadir</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; font-size: 12.5px; }

        .page { width: 170mm; padding: 18mm 20mm; background: #fff; }

        .kop { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 4mm; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .kop .logo-cell { width: 60px; text-align: center; }
        .kop .logo-cell img { width: 60px; height: 60px; object-fit: contain; }
        .kop .ident-cell { text-align: center; }
        .kop .ident-cell p, .kop .ident-cell h1, .kop .ident-cell h2 { margin: 1px 0; line-height: 1.25; }

        .judul { text-align: center; margin: 3mm 0 6mm; }
        .judul .t1 { font-size: 16px; font-weight: 700; letter-spacing: .5px; margin: 0; text-decoration: underline; }
        .judul .t2 { font-size: 13px; margin: 3px 0 0; }

        table.info { width: 100%; border-collapse: collapse; margin-bottom: 5mm; font-size: 12.5px; }
        table.info td { padding: 1.5px 0; }
        table.info .lbl { width: 40mm; }
        table.info .colon { width: 5mm; }

        table.roster { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; }
        table.roster th, table.roster td { border: 1px solid #000; padding: 3px 6px; vertical-align: middle; }
        table.roster th { background: #e5e5e5 !important; font-weight: 700; text-align: center; }
        .no { width: 8%; text-align: center; }
        .nis { width: 16%; text-align: center; }
        .kls { width: 12%; text-align: center; }
        .status { width: 14%; text-align: center; }
        .ket { width: 20%; }

        .ttd-heading { margin: 10mm 0 8mm; text-align: right; }
        .ttd { text-align: right; }
        .ttd .peran { margin: 0 0 20mm; }
        .ttd .nm { font-weight: 700; text-decoration: underline; margin: 0; }

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
            @include('ujian.ruangan._hadirBody', [
                'ruangan' => $data['ruangan'],
                'paket' => $data['paket'],
                'sesi' => $data['sesi'],
                'tanggal' => $data['tanggal'],
                'hadirBySiswa' => $data['hadirBySiswa'],
                'isAdhoc' => $data['isAdhoc'],
                'beritaAcara' => $data['beritaAcara'],
            ])
        </div>
    @endforeach
</body>
</html>
