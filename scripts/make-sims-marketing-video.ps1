param(
    [string] $EdgeVoice = "id-ID-GadisNeural",
    [switch] $RegenerateAudio
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$assetDir = Join-Path $root "public\images\panduan"
$outDir = Join-Path $root "public\videos"
$workDir = Join-Path $outDir "sims-marketing-90detik-indonesia-work"
$audioDir = Join-Path $workDir "audio"
$slideDir = Join-Path $workDir "slides"
$sceneDir = Join-Path $workDir "scenes"
$finalVideo = Join-Path $outDir "sims-marketing-90detik-indonesia.mp4"

New-Item -ItemType Directory -Force -Path $outDir, $workDir, $audioDir, $slideDir, $sceneDir | Out-Null

$scenes = @(
    @{
        Key = "01"
        Label = "Masalah Sekolah"
        Title = "Sekolah modern butuh data yang menyatu"
        Subtitle = "Akademik, absensi, pembayaran, dan komunikasi tidak lagi berjalan sendiri-sendiri."
        Text = "Hasilnya: pekerjaan lebih ringan, laporan lebih cepat, dan keputusan sekolah lebih tepat."
        Images = @("login.png", "dashboard.png", "pengumuman.png")
        Voice = "Di banyak sekolah, pekerjaan harian sering terpecah di banyak file dan aplikasi. SIMS menyatukan data penting sekolah agar semuanya lebih mudah dipantau."
    },
    @{
        Key = "02"
        Label = "Solusi"
        Title = "Satu aplikasi untuk seluruh peran"
        Subtitle = "Admin, guru, siswa, orang tua, bendahara, dan pimpinan punya ruang kerja masing-masing."
        Text = "Setiap pengguna melihat menu yang relevan, sehingga aplikasi terasa sederhana sejak pertama dipakai."
        Images = @("dashboard.png", "data-siswa.png", "data-guru.png")
        Voice = "Melalui dashboard SIMS, setiap peran langsung melihat informasi yang dibutuhkan. Admin, guru, siswa, orang tua, bendahara, dan kepala sekolah bekerja dari satu sistem yang sama."
    },
    @{
        Key = "03"
        Label = "Guru"
        Title = "Guru lebih cepat menyiapkan pembelajaran"
        Subtitle = "Asisten Guru membantu membuat soal, rangkuman, bahan ajar, dan draf pembelajaran."
        Text = "Hasilnya bisa langsung dikirim ke Arena Belajar untuk kuis interaktif di Ruang Kelas."
        Images = @("asisten-ai.png", "ai-foto-soal.png", "arena-belajar.png")
        Voice = "Untuk guru, SIMS membantu menyiapkan pembelajaran lebih cepat. Asisten Guru dapat membantu membuat soal, rangkuman, dan bahan ajar, lalu mengirimkannya ke Arena Belajar."
    },
    @{
        Key = "04"
        Label = "Siswa"
        Title = "Belajar terasa lebih dekat dan interaktif"
        Subtitle = "Materi, tugas, kuis, misi, hasil, dan leaderboard tersedia dalam satu alur."
        Text = "Siswa merasa mudah mengikuti kelas, guru lebih cepat melihat perkembangan belajar."
        Images = @("ruang-kelas.png", "arena-kuis.png", "arena-misi.png")
        Voice = "Siswa belajar dari Ruang Kelas dengan pengalaman yang lebih interaktif. Guru dapat memantau hasil, melihat perkembangan, dan memindahkan nilai tanpa input ulang."
    },
    @{
        Key = "05"
        Label = "Akademik"
        Title = "Nilai sampai rapor jadi lebih tertata"
        Subtitle = "Formatif, sumatif, KKTP, rekap nilai, dan rapor berada dalam alur Kurikulum Merdeka."
        Text = "Sekolah mendapatkan proses penilaian yang rapi, konsisten, dan mudah ditelusuri."
        Images = @("penilaian.png", "rekap-nilai.png", "cetak-rapor.png")
        Voice = "Untuk akademik, nilai formatif, sumatif, KKTP, rekap, sampai rapor tersusun rapi dalam alur Kurikulum Merdeka. Sekolah lebih mudah menjaga konsistensi data."
    },
    @{
        Key = "06"
        Label = "Absensi"
        Title = "Kehadiran lebih cepat dan akurat"
        Subtitle = "Absensi wajah, QR dengan validasi GPS, kiosk piket, kalender, dan presensi guru."
        Text = "Hasilnya: rekap kehadiran lebih mudah dibaca, dan kedisiplinan lebih cepat ditindaklanjuti."
        Images = @("absen-qr.png", "kalender-absensi.png", "presensi-guru.png")
        Voice = "Untuk kehadiran, SIMS mendukung absensi wajah, QR dengan validasi GPS, kiosk piket, presensi guru, dan kalender absensi. Rekap jadi lebih cepat dibaca."
    },
    @{
        Key = "07"
        Label = "Layanan"
        Title = "Orang tua merasa lebih terhubung"
        Subtitle = "Pengumuman, grup kelas, chatbot helpdesk, tagihan SPP, upload bukti, dan notifikasi Android."
        Text = "Informasi resmi lebih jelas, pembayaran lebih transparan, dan bantuan masuk dari kanal yang tertata."
        Images = @("pengumuman.png", "chat-inbox.png", "keuangan-spp.png")
        Voice = "Orang tua juga merasakan manfaatnya. Pengumuman, grup kelas, chatbot helpdesk, tagihan SPP, upload bukti pembayaran, dan notifikasi Android membuat komunikasi lebih tertata."
    },
    @{
        Key = "08"
        Label = "Manajemen"
        Title = "Pimpinan mendapat gambaran yang utuh"
        Subtitle = "Analisis AI, Dokumen AI, Sarpras, aset, denah, booking, kerusakan, dan laporan."
        Text = "Kepala sekolah dan yayasan dapat melihat operasional sekolah dengan lebih jelas dan terukur."
        Images = @("sarpras-dashboard.png", "sarpras-denah.png", "sarpras-inventaris.png")
        Voice = "Untuk pimpinan, SIMS menyajikan gambaran sekolah yang lebih utuh. Ada Analisis AI, Dokumen AI, dan Sarpras lengkap untuk aset, denah, booking, kerusakan, dan laporan."
    },
    @{
        Key = "09"
        Label = "Demo"
        Title = "Saatnya sekolah bekerja lebih rapi"
        Subtitle = "SIMS dirancang untuk sekolah Indonesia: mudah dipakai, modular, aman, dan siap didemokan."
        Text = "Jadwalkan demo SIMS dan rasakan bagaimana operasional sekolah bisa lebih mudah dari satu aplikasi."
        Images = @("dashboard.png", "pengaturan-sistem.png", "app-download.png")
        Voice = "SIMS dirancang untuk sekolah Indonesia: mudah dipakai, modular, aman, dan siap dikembangkan sesuai kebutuhan sekolah. Jadwalkan demo SIMS untuk sekolah Anda."
    }
)

Add-Type -AssemblyName System.Drawing

function New-Brush($color) {
    return New-Object System.Drawing.SolidBrush($color)
}

function Draw-WrappedText {
    param(
        [System.Drawing.Graphics] $Graphics,
        [string] $Text,
        [System.Drawing.Font] $Font,
        [System.Drawing.Brush] $Brush,
        [System.Drawing.RectangleF] $Rect,
        [float] $LineHeight
    )

    $words = $Text -split "\s+"
    $line = ""
    $y = $Rect.Y

    foreach ($word in $words) {
        $candidate = if ($line.Length -eq 0) { $word } else { "$line $word" }
        $size = $Graphics.MeasureString($candidate, $Font)
        if ($size.Width -gt $Rect.Width -and $line.Length -gt 0) {
            $Graphics.DrawString($line, $Font, $Brush, [System.Drawing.PointF]::new($Rect.X, $y))
            $line = $word
            $y += $LineHeight
            if ($y -gt ($Rect.Bottom - $LineHeight)) { break }
        } else {
            $line = $candidate
        }
    }

    if ($line.Length -gt 0 -and $y -le ($Rect.Bottom - $LineHeight)) {
        $Graphics.DrawString($line, $Font, $Brush, [System.Drawing.PointF]::new($Rect.X, $y))
    }
}

function Draw-ImageCard {
    param(
        [System.Drawing.Graphics] $Graphics,
        [string] $Path,
        [int] $X,
        [int] $Y,
        [int] $W,
        [int] $H
    )

    if (-not (Test-Path -LiteralPath $Path)) { return }

    $shadowBrush = New-Brush ([System.Drawing.Color]::FromArgb(42, 15, 23, 42))
    $cardBrush = New-Brush ([System.Drawing.Color]::FromArgb(255, 255, 255, 255))
    $borderPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(220, 226, 232, 240), 3)
    $Graphics.FillRectangle($shadowBrush, $X + 12, $Y + 16, $W, $H)
    $Graphics.FillRectangle($cardBrush, $X, $Y, $W, $H)
    $Graphics.DrawRectangle($borderPen, $X, $Y, $W, $H)

    $img = [System.Drawing.Image]::FromFile($Path)
    try {
        $scale = [Math]::Min(($W - 36) / $img.Width, ($H - 36) / $img.Height)
        $drawW = [int]($img.Width * $scale)
        $drawH = [int]($img.Height * $scale)
        $drawX = $X + [int](($W - $drawW) / 2)
        $drawY = $Y + [int](($H - $drawH) / 2)
        $Graphics.DrawImage($img, $drawX, $drawY, $drawW, $drawH)
    } finally {
        $img.Dispose()
        $shadowBrush.Dispose()
        $cardBrush.Dispose()
        $borderPen.Dispose()
    }
}

function New-Slide {
    param($Scene, [string] $Path, [int] $Index, [int] $Total)

    $bmp = New-Object System.Drawing.Bitmap(1920, 1080)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit

    $bg = New-Object System.Drawing.Drawing2D.LinearGradientBrush(
        [System.Drawing.Rectangle]::new(0, 0, 1920, 1080),
        [System.Drawing.Color]::FromArgb(255, 239, 249, 244),
        [System.Drawing.Color]::FromArgb(255, 250, 246, 239),
        35
    )
    $g.FillRectangle($bg, 0, 0, 1920, 1080)
    $bg.Dispose()

    $accent = New-Brush ([System.Drawing.Color]::FromArgb(255, 43, 122, 94))
    $accentSoft = New-Brush ([System.Drawing.Color]::FromArgb(38, 43, 122, 94))
    $orangeSoft = New-Brush ([System.Drawing.Color]::FromArgb(52, 229, 153, 108))
    $text = New-Brush ([System.Drawing.Color]::FromArgb(255, 30, 41, 59))
    $muted = New-Brush ([System.Drawing.Color]::FromArgb(255, 71, 85, 105))
    $white = New-Brush ([System.Drawing.Color]::White)

    $fontSmall = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
    $fontTitle = New-Object System.Drawing.Font("Segoe UI", 58, [System.Drawing.FontStyle]::Bold)
    $fontSubtitle = New-Object System.Drawing.Font("Segoe UI", 31, [System.Drawing.FontStyle]::Regular)
    $fontBody = New-Object System.Drawing.Font("Segoe UI", 27, [System.Drawing.FontStyle]::Regular)
    $fontLogo = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)

    $g.FillRectangle($accentSoft, 0, 0, 1920, 1080)
    $g.FillEllipse($orangeSoft, 1380, -220, 780, 780)
    $g.FillEllipse($accentSoft, -240, 720, 620, 620)

    $g.FillRectangle($accent, 90, 76, 160, 54)
    $g.DrawString("SIMS", $fontLogo, $white, 112, 84)
    $g.DrawString($Scene.Label.ToUpperInvariant(), $fontSmall, $accent, 284, 88)
    $g.DrawString(("{0:00}/{1:00}" -f $Index, $Total), $fontSmall, $muted, 1700, 88)

    Draw-WrappedText $g $Scene.Title $fontTitle $text ([System.Drawing.RectangleF]::new(90, 172, 760, 160)) 68
    Draw-WrappedText $g $Scene.Subtitle $fontSubtitle $muted ([System.Drawing.RectangleF]::new(92, 344, 720, 140)) 42

    $g.FillRectangle((New-Brush ([System.Drawing.Color]::FromArgb(238, 255, 255, 255))), 90, 528, 730, 286)
    Draw-WrappedText $g $Scene.Text $fontBody $text ([System.Drawing.RectangleF]::new(124, 560, 660, 216)) 37

    $img1 = Join-Path $assetDir $Scene.Images[0]
    $img2 = Join-Path $assetDir $Scene.Images[1]
    $img3 = Join-Path $assetDir $Scene.Images[2]

    Draw-ImageCard $g $img1 880 178 740 455
    Draw-ImageCard $g $img2 1130 575 560 332
    Draw-ImageCard $g $img3 1370 138 390 258

    $g.DrawString("Mudah dipakai. Rapi dipantau. Siap didemokan.", $fontSmall, $muted, 92, 972)

    $fontSmall.Dispose()
    $fontTitle.Dispose()
    $fontSubtitle.Dispose()
    $fontBody.Dispose()
    $fontLogo.Dispose()
    $accent.Dispose()
    $accentSoft.Dispose()
    $orangeSoft.Dispose()
    $text.Dispose()
    $muted.Dispose()
    $white.Dispose()
    $g.Dispose()

    $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
}

$concatFile = Join-Path $workDir "concat.txt"
Set-Content -LiteralPath $concatFile -Value "" -Encoding ASCII

$index = 1
foreach ($scene in $scenes) {
    $slidePath = Join-Path $slideDir ("scene-{0}.png" -f $scene.Key)
    $audioPath = Join-Path $audioDir ("scene-{0}.mp3" -f $scene.Key)
    $videoPath = Join-Path $sceneDir ("scene-{0}.mp4" -f $scene.Key)

    if ($RegenerateAudio) {
        if (Test-Path -LiteralPath $audioPath) {
            Remove-Item -LiteralPath $audioPath -Force
        }
        & edge-tts --voice $EdgeVoice --rate "+8%" --text $scene.Voice --write-media $audioPath | Out-Null
    } elseif (-not (Test-Path -LiteralPath $audioPath)) {
        throw "Missing existing audio file: $audioPath"
    }

    New-Slide -Scene $scene -Path $slidePath -Index $index -Total $scenes.Count

    $durationRaw = & ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $audioPath
    $duration = [double]::Parse($durationRaw, [System.Globalization.CultureInfo]::InvariantCulture)
    $fadeOutStart = [Math]::Max(0.5, $duration - 0.45).ToString("0.###", [System.Globalization.CultureInfo]::InvariantCulture)
    $durationText = $duration.ToString("0.###", [System.Globalization.CultureInfo]::InvariantCulture)
    $vf = "fade=t=in:st=0:d=0.35,fade=t=out:st=$fadeOutStart`:d=0.35,format=yuv420p"

    & ffmpeg -y -loop 1 -i $slidePath -i $audioPath -t $durationText -vf $vf -c:v libx264 -preset veryfast -tune stillimage -c:a aac -b:a 192k -shortest $videoPath | Out-Null

    $safePath = $videoPath.Replace("\", "/").Replace("'", "'\''")
    Add-Content -LiteralPath $concatFile -Value "file '$safePath'" -Encoding ASCII
    $index++
}

& ffmpeg -y -f concat -safe 0 -i $concatFile -c copy $finalVideo | Out-Null

$finalDuration = & ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $finalVideo
Write-Host "Video created: $finalVideo"
Write-Host "Audio scenes: $audioDir"
Write-Host "Duration seconds: $finalDuration"
