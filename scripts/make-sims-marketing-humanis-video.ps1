param(
    [string] $HostVoice = "id-ID-ArdiNeural",
    [string] $CoHostVoice = "id-ID-GadisNeural",
    [switch] $RegenerateAudio,
    [switch] $RegenerateMusic
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$assetDir = Join-Path $root "public\images\panduan"
$outDir = Join-Path $root "public\videos"
$workDir = Join-Path $outDir "sims-marketing-humanis-edukasi-work"
$audioDir = Join-Path $workDir "audio"
$turnDir = Join-Path $workDir "audio-turns"
$slideDir = Join-Path $workDir "slides"
$sceneDir = Join-Path $workDir "scenes"
$finalVideo = Join-Path $outDir "sims-marketing-humanis-edukasi.mp4"
$narratedVideo = Join-Path $workDir "sims-marketing-humanis-narration-only.mp4"
$musicPath = Join-Path $workDir "musik-edukasi-lembut.wav"

New-Item -ItemType Directory -Force -Path $outDir, $workDir, $audioDir, $turnDir, $slideDir, $sceneDir | Out-Null

$scenes = @(
    @{
        Key = "01"
        Label = "Tantangan"
        Persona = "Untuk kepala sekolah dan yayasan"
        Title = "Data sekolah rapi dalam satu pusat"
        Subtitle = "Nilai, absensi, pembayaran, pengumuman, dan laporan tidak lagi tercecer di banyak tempat."
        Text = "Hasilnya: pekerjaan harian lebih ringan, informasi lebih cepat ditemukan, dan keputusan sekolah lebih mudah diambil."
        Images = @("login.png", "dashboard.png", "pengumuman.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Bapak Ibu, bayangkan satu pagi di sekolah. Guru membutuhkan data nilai, bendahara mengecek SPP, orang tua menunggu informasi resmi." },
            @{ Voice = $CoHostVoice; Text = "SIMS membantu menyatukan alur itu, sehingga data penting sekolah lebih rapi dan mudah dipantau dari satu sistem." }
        )
    },
    @{
        Key = "02"
        Label = "Satu Sistem"
        Persona = "Untuk semua peran di sekolah"
        Title = "Setiap pengguna langsung tahu harus ke mana"
        Subtitle = "Admin, guru, siswa, orang tua, bendahara, dan pimpinan melihat menu sesuai kebutuhannya."
        Text = "Tidak perlu terasa rumit. SIMS dibuat modular, jelas, dan nyaman dipakai dalam aktivitas sekolah sehari-hari."
        Images = @("dashboard.png", "data-siswa.png", "data-guru.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Di SIMS, setiap peran punya ruang kerjanya sendiri. Admin fokus pada data, guru fokus mengajar, orang tua fokus memantau." },
            @{ Voice = $CoHostVoice; Text = "Jadi aplikasi terasa lebih sederhana, karena yang tampil adalah hal yang memang dibutuhkan pengguna." }
        )
    },
    @{
        Key = "03"
        Label = "Guru"
        Persona = "Untuk guru dan operator akademik"
        Title = "Persiapan mengajar jadi lebih cepat"
        Subtitle = "Asisten Guru membantu membuat soal, rangkuman, bahan ajar, dan draf pembelajaran."
        Text = "Guru punya lebih banyak waktu untuk mendampingi siswa, bukan hanya mengulang pekerjaan administrasi."
        Images = @("asisten-ai.png", "ai-foto-soal.png", "arena-belajar.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Untuk guru, SIMS tidak hanya menyimpan tugas. Ada Asisten Guru yang membantu menyiapkan soal, rangkuman, dan bahan ajar." },
            @{ Voice = $CoHostVoice; Text = "Lalu hasilnya bisa masuk ke Arena Belajar, sehingga siswa belajar dengan kuis dan aktivitas yang lebih interaktif." }
        )
    },
    @{
        Key = "04"
        Label = "Siswa"
        Persona = "Untuk pengalaman belajar siswa"
        Title = "Belajar terasa dekat, jelas, dan interaktif"
        Subtitle = "Ruang Kelas, kuis, misi belajar, hasil, dan leaderboard berada dalam alur yang mudah diikuti."
        Text = "Siswa tahu apa yang harus dikerjakan. Guru melihat progres kelas tanpa rekap manual yang melelahkan."
        Images = @("ruang-kelas.png", "arena-kuis.png", "arena-misi.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Dari sisi siswa, pengalaman belajarnya dibuat lebih dekat. Mereka masuk Ruang Kelas, melihat materi, mengerjakan kuis, lalu melihat hasilnya." },
            @{ Voice = $CoHostVoice; Text = "Untuk guru, progres belajar lebih mudah dibaca, dan nilai bisa ditindaklanjuti tanpa input ulang yang panjang." }
        )
    },
    @{
        Key = "05"
        Label = "Akademik"
        Persona = "Untuk kurikulum dan wali kelas"
        Title = "Penilaian sampai rapor lebih tertata"
        Subtitle = "Formatif, sumatif, KKTP, rekap nilai, dan rapor mengikuti alur Kurikulum Merdeka."
        Text = "Sekolah mendapat data akademik yang konsisten, mudah ditelusuri, dan siap dipakai saat pelaporan."
        Images = @("penilaian.png", "rekap-nilai.png", "cetak-rapor.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Untuk akademik, SIMS membantu merapikan nilai formatif, sumatif, KKTP, rekap, sampai cetak rapor." },
            @{ Voice = $CoHostVoice; Text = "Bagi sekolah, ini berarti proses penilaian lebih konsisten, lebih mudah ditelusuri, dan lebih siap saat dibutuhkan." }
        )
    },
    @{
        Key = "06"
        Label = "Kehadiran"
        Persona = "Untuk piket, tata tertib, dan guru"
        Title = "Absensi dan kedisiplinan lebih mudah dipantau"
        Subtitle = "Absensi wajah, QR dengan GPS, kiosk piket, kalender absensi, dan presensi guru."
        Text = "Rekap kehadiran lebih cepat dibaca, tindak lanjut lebih jelas, dan sekolah punya kontrol yang lebih baik."
        Images = @("absen-qr.png", "kalender-absensi.png", "presensi-guru.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Untuk kehadiran, SIMS mendukung absensi wajah, QR dengan validasi GPS, kiosk piket, kalender absensi, dan presensi guru." },
            @{ Voice = $CoHostVoice; Text = "Rekapnya lebih cepat dibaca, sehingga sekolah bisa menindaklanjuti kedisiplinan dengan lebih tertib." }
        )
    },
    @{
        Key = "07"
        Label = "Orang Tua"
        Persona = "Untuk komunikasi sekolah dan keluarga"
        Title = "Orang tua merasa lebih terhubung"
        Subtitle = "Pengumuman, grup kelas, chatbot helpdesk, tagihan SPP, upload bukti, dan notifikasi Android."
        Text = "Informasi resmi lebih jelas, pembayaran lebih transparan, dan pertanyaan masuk dari kanal yang tertata."
        Images = @("pengumuman.png", "chat-inbox.png", "keuangan-spp.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Orang tua juga merasakan manfaatnya. Pengumuman, grup kelas, tagihan SPP, upload bukti pembayaran, dan notifikasi Android ada dalam satu alur." },
            @{ Voice = $CoHostVoice; Text = "Komunikasi jadi lebih tertata, dan sekolah terlihat lebih responsif di mata keluarga siswa." }
        )
    },
    @{
        Key = "08"
        Label = "Manajemen"
        Persona = "Untuk pimpinan dan yayasan"
        Title = "Operasional sekolah terlihat lebih utuh"
        Subtitle = "Analisis AI, Dokumen AI, Sarpras, aset, denah, booking, kerusakan, dan laporan."
        Text = "Pimpinan tidak hanya menerima data akhir, tetapi dapat melihat gambaran operasional sekolah secara lebih menyeluruh."
        Images = @("sarpras-dashboard.png", "sarpras-denah.png", "sarpras-inventaris.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "Untuk pimpinan, SIMS menyajikan gambaran sekolah yang lebih utuh. Ada Analisis AI, Dokumen AI, dan Sarpras yang lengkap." },
            @{ Voice = $CoHostVoice; Text = "Mulai dari aset, denah, booking, kerusakan, sampai laporan, semuanya membantu sekolah bekerja lebih terukur." }
        )
    },
    @{
        Key = "09"
        Label = "Demo"
        Persona = "Siap untuk sekolah Indonesia"
        Title = "SIMS siap didemokan untuk sekolah Anda"
        Subtitle = "Mudah dipakai, modular, aman, dan dapat dikembangkan sesuai kebutuhan sekolah."
        Text = "Jadwalkan demo SIMS dan lihat bagaimana operasional sekolah bisa terasa lebih ringan dari satu aplikasi."
        Images = @("dashboard.png", "pengaturan-sistem.png", "app-download.png")
        VoiceTurns = @(
            @{ Voice = $HostVoice; Text = "SIMS dirancang untuk sekolah Indonesia yang ingin bekerja lebih rapi, lebih cepat, dan lebih mudah dipantau." },
            @{ Voice = $CoHostVoice; Text = "Jadwalkan demo SIMS untuk sekolah Bapak Ibu, dan rasakan bagaimana satu sistem bisa membantu banyak peran sekaligus." }
        )
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

    $shadowBrush = New-Brush ([System.Drawing.Color]::FromArgb(38, 15, 23, 42))
    $cardBrush = New-Brush ([System.Drawing.Color]::FromArgb(255, 255, 255, 255))
    $barBrush = New-Brush ([System.Drawing.Color]::FromArgb(255, 235, 247, 241))
    $dotBrush = New-Brush ([System.Drawing.Color]::FromArgb(255, 45, 128, 96))
    $borderPen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(225, 226, 232, 240), 2)

    $Graphics.FillRectangle($shadowBrush, $X + 14, $Y + 18, $W, $H)
    $Graphics.FillRectangle($cardBrush, $X, $Y, $W, $H)
    $Graphics.FillRectangle($barBrush, $X, $Y, $W, 38)
    $Graphics.FillEllipse($dotBrush, $X + 18, $Y + 13, 12, 12)
    $Graphics.FillEllipse($dotBrush, $X + 38, $Y + 13, 12, 12)
    $Graphics.FillEllipse($dotBrush, $X + 58, $Y + 13, 12, 12)
    $Graphics.DrawRectangle($borderPen, $X, $Y, $W, $H)

    $img = [System.Drawing.Image]::FromFile($Path)
    try {
        $scale = [Math]::Min(($W - 34) / $img.Width, ($H - 70) / $img.Height)
        $drawW = [int]($img.Width * $scale)
        $drawH = [int]($img.Height * $scale)
        $drawX = $X + [int](($W - $drawW) / 2)
        $drawY = $Y + 48 + [int](($H - 58 - $drawH) / 2)
        $Graphics.DrawImage($img, $drawX, $drawY, $drawW, $drawH)
    } finally {
        $img.Dispose()
        $shadowBrush.Dispose()
        $cardBrush.Dispose()
        $barBrush.Dispose()
        $dotBrush.Dispose()
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
        [System.Drawing.Color]::FromArgb(255, 247, 251, 249),
        [System.Drawing.Color]::FromArgb(255, 255, 250, 244),
        24
    )
    $g.FillRectangle($bg, 0, 0, 1920, 1080)
    $bg.Dispose()

    $green = New-Brush ([System.Drawing.Color]::FromArgb(255, 35, 117, 88))
    $greenSoft = New-Brush ([System.Drawing.Color]::FromArgb(36, 35, 117, 88))
    $amber = New-Brush ([System.Drawing.Color]::FromArgb(255, 221, 151, 72))
    $amberSoft = New-Brush ([System.Drawing.Color]::FromArgb(48, 221, 151, 72))
    $navy = New-Brush ([System.Drawing.Color]::FromArgb(255, 26, 37, 58))
    $muted = New-Brush ([System.Drawing.Color]::FromArgb(255, 74, 88, 110))
    $white = New-Brush ([System.Drawing.Color]::White)
    $panel = New-Brush ([System.Drawing.Color]::FromArgb(244, 255, 255, 255))
    $linePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(255, 213, 224, 219), 2)

    $fontTiny = New-Object System.Drawing.Font("Segoe UI", 20, [System.Drawing.FontStyle]::Bold)
    $fontSmall = New-Object System.Drawing.Font("Segoe UI", 24, [System.Drawing.FontStyle]::Bold)
    $fontTitle = New-Object System.Drawing.Font("Segoe UI", 55, [System.Drawing.FontStyle]::Bold)
    $fontSubtitle = New-Object System.Drawing.Font("Segoe UI", 29, [System.Drawing.FontStyle]::Regular)
    $fontBody = New-Object System.Drawing.Font("Segoe UI", 27, [System.Drawing.FontStyle]::Regular)
    $fontLogo = New-Object System.Drawing.Font("Segoe UI", 28, [System.Drawing.FontStyle]::Bold)

    $g.FillRectangle($greenSoft, 0, 0, 1920, 1080)
    $g.FillRectangle($amberSoft, 0, 844, 1920, 236)
    $g.FillEllipse($greenSoft, 1360, -260, 780, 780)
    $g.FillEllipse($amberSoft, -250, 670, 660, 660)

    $g.FillRectangle($green, 86, 76, 164, 56)
    $g.DrawString("SIMS", $fontLogo, $white, 113, 84)
    $g.DrawString($Scene.Label.ToUpperInvariant(), $fontSmall, $green, 286, 88)
    $g.DrawString(("{0:00}/{1:00}" -f $Index, $Total), $fontSmall, $muted, 1700, 68)

    $g.FillRectangle($panel, 88, 170, 760, 690)
    $g.DrawLine($linePen, 126, 250, 810, 250)
    $g.DrawString($Scene.Persona, $fontTiny, $amber, 126, 205)
    Draw-WrappedText $g $Scene.Title $fontTitle $navy ([System.Drawing.RectangleF]::new(124, 278, 680, 160)) 64
    Draw-WrappedText $g $Scene.Subtitle $fontSubtitle $muted ([System.Drawing.RectangleF]::new(126, 454, 655, 140)) 40
    $g.FillRectangle((New-Brush ([System.Drawing.Color]::FromArgb(255, 238, 248, 243))), 124, 620, 650, 190)
    Draw-WrappedText $g $Scene.Text $fontBody $navy ([System.Drawing.RectangleF]::new(152, 646, 590, 142)) 35

    $img1 = Join-Path $assetDir $Scene.Images[0]
    $img2 = Join-Path $assetDir $Scene.Images[1]
    $img3 = Join-Path $assetDir $Scene.Images[2]

    Draw-ImageCard $g $img1 892 168 770 462
    Draw-ImageCard $g $img2 1100 610 600 332
    Draw-ImageCard $g $img3 1394 110 386 256

    $g.FillRectangle($green, 88, 948, 760, 52)
    $g.DrawString("Mudah dipakai. Rapi dipantau. Siap didemokan.", $fontSmall, $white, 113, 957)

    $fontTiny.Dispose()
    $fontSmall.Dispose()
    $fontTitle.Dispose()
    $fontSubtitle.Dispose()
    $fontBody.Dispose()
    $fontLogo.Dispose()
    $green.Dispose()
    $greenSoft.Dispose()
    $amber.Dispose()
    $amberSoft.Dispose()
    $navy.Dispose()
    $muted.Dispose()
    $white.Dispose()
    $panel.Dispose()
    $linePen.Dispose()
    $g.Dispose()

    $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
}

function New-SilenceMp3 {
    param([string] $Path)
    if (Test-Path -LiteralPath $Path) { return }
    & ffmpeg -y -f lavfi -i "anullsrc=r=24000:cl=mono" -t 0.38 -q:a 9 -acodec libmp3lame $Path | Out-Null
}

function New-SceneDialogueAudio {
    param($Scene, [string] $AudioPath, [string] $SceneTurnDir)

    New-Item -ItemType Directory -Force -Path $SceneTurnDir | Out-Null
    $silencePath = Join-Path $SceneTurnDir "pause.mp3"
    New-SilenceMp3 -Path $silencePath

    $concatPath = Join-Path $SceneTurnDir "concat.txt"
    Set-Content -LiteralPath $concatPath -Value "" -Encoding ASCII

    $turnIndex = 1
    foreach ($turn in $Scene.VoiceTurns) {
        $turnPath = Join-Path $SceneTurnDir ("turn-{0:00}.mp3" -f $turnIndex)
        if ($RegenerateAudio -or -not (Test-Path -LiteralPath $turnPath)) {
            if (Test-Path -LiteralPath $turnPath) {
                Remove-Item -LiteralPath $turnPath -Force
            }
            & edge-tts --voice $turn.Voice --rate "+2%" --text $turn.Text --write-media $turnPath | Out-Null
        }

        $safeTurnPath = $turnPath.Replace("\", "/").Replace("'", "'\''")
        Add-Content -LiteralPath $concatPath -Value "file '$safeTurnPath'" -Encoding ASCII

        if ($turnIndex -lt $Scene.VoiceTurns.Count) {
            $safeSilencePath = $silencePath.Replace("\", "/").Replace("'", "'\''")
            Add-Content -LiteralPath $concatPath -Value "file '$safeSilencePath'" -Encoding ASCII
        }
        $turnIndex++
    }

    if ($RegenerateAudio -or -not (Test-Path -LiteralPath $AudioPath)) {
        & ffmpeg -y -f concat -safe 0 -i $concatPath -vn -acodec libmp3lame -q:a 4 $AudioPath | Out-Null
    }
}

function Write-WavHeader {
    param(
        [System.IO.BinaryWriter] $Writer,
        [int] $SampleRate,
        [int] $Channels,
        [int] $BitsPerSample,
        [int] $SampleCount
    )

    $bytesPerSample = [int]($BitsPerSample / 8)
    $dataSize = $SampleCount * $Channels * $bytesPerSample
    $Writer.Write([System.Text.Encoding]::ASCII.GetBytes("RIFF"))
    $Writer.Write([int](36 + $dataSize))
    $Writer.Write([System.Text.Encoding]::ASCII.GetBytes("WAVE"))
    $Writer.Write([System.Text.Encoding]::ASCII.GetBytes("fmt "))
    $Writer.Write([int]16)
    $Writer.Write([int16]1)
    $Writer.Write([int16]$Channels)
    $Writer.Write([int]$SampleRate)
    $Writer.Write([int]($SampleRate * $Channels * $bytesPerSample))
    $Writer.Write([int16]($Channels * $bytesPerSample))
    $Writer.Write([int16]$BitsPerSample)
    $Writer.Write([System.Text.Encoding]::ASCII.GetBytes("data"))
    $Writer.Write([int]$dataSize)
}

function New-SoftEducationMusic {
    param([string] $Path, [double] $DurationSeconds)

    if ((Test-Path -LiteralPath $Path) -and -not $RegenerateMusic) { return }

    $sampleRate = 24000
    $channels = 2
    $bits = 16
    $sampleCount = [int][Math]::Ceiling($DurationSeconds * $sampleRate)
    $bpm = 84.0
    $beat = 60.0 / $bpm
    $twoPi = 2.0 * [Math]::PI
    $chords = @(
        @(261.63, 329.63, 392.00),
        @(220.00, 261.63, 329.63),
        @(174.61, 220.00, 261.63),
        @(196.00, 246.94, 293.66)
    )

    $stream = [System.IO.File]::Create($Path)
    $writer = New-Object System.IO.BinaryWriter($stream)
    try {
        Write-WavHeader -Writer $writer -SampleRate $sampleRate -Channels $channels -BitsPerSample $bits -SampleCount $sampleCount
        for ($i = 0; $i -lt $sampleCount; $i++) {
            $t = $i / $sampleRate
            $beatIndex = [int][Math]::Floor($t / $beat)
            $chordIndex = [int]([Math]::Floor($beatIndex / 4) % $chords.Count)
            $localBeat = $t - ([Math]::Floor($t / $beat) * $beat)
            $notes = $chords[$chordIndex]
            $arpFreq = $notes[$beatIndex % $notes.Count] * 2.0

            $pad = 0.0
            foreach ($freq in $notes) {
                $pad += [Math]::Sin($twoPi * $freq * $t) * 0.018
                $pad += [Math]::Sin($twoPi * ($freq * 2.0) * $t) * 0.006
            }

            $pluckEnv = [Math]::Exp(-4.2 * $localBeat)
            $pluck = ([Math]::Sin($twoPi * $arpFreq * $t) * 0.075 + [Math]::Sin($twoPi * ($arpFreq * 2.0) * $t) * 0.018) * $pluckEnv
            $fadeIn = [Math]::Min(1.0, $t / 3.0)
            $fadeOut = [Math]::Min(1.0, [Math]::Max(0.0, ($DurationSeconds - $t) / 4.0))
            $sample = ($pad + $pluck) * $fadeIn * $fadeOut * 0.75
            $sample = [Math]::Max(-1.0, [Math]::Min(1.0, $sample))
            $intSample = [int16][Math]::Round($sample * 32767)
            $writer.Write($intSample)
            $writer.Write($intSample)
        }
    } finally {
        $writer.Dispose()
        $stream.Dispose()
    }
}

$concatFile = Join-Path $workDir "concat.txt"
Set-Content -LiteralPath $concatFile -Value "" -Encoding ASCII

$index = 1
foreach ($scene in $scenes) {
    $slidePath = Join-Path $slideDir ("scene-{0}.png" -f $scene.Key)
    $audioPath = Join-Path $audioDir ("scene-{0}.mp3" -f $scene.Key)
    $sceneTurnDir = Join-Path $turnDir ("scene-{0}" -f $scene.Key)
    $videoPath = Join-Path $sceneDir ("scene-{0}.mp4" -f $scene.Key)

    New-SceneDialogueAudio -Scene $scene -AudioPath $audioPath -SceneTurnDir $sceneTurnDir
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

& ffmpeg -y -fflags +genpts -f concat -safe 0 -i $concatFile -avoid_negative_ts make_zero -c:v libx264 -preset veryfast -pix_fmt yuv420p -c:a aac -b:a 160k $narratedVideo | Out-Null

$finalDurationRaw = & ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $narratedVideo
$finalDuration = [double]::Parse($finalDurationRaw, [System.Globalization.CultureInfo]::InvariantCulture)
New-SoftEducationMusic -Path $musicPath -DurationSeconds $finalDuration

$fadeOutMusicStart = [Math]::Max(0.5, $finalDuration - 4.0).ToString("0.###", [System.Globalization.CultureInfo]::InvariantCulture)
$filter = "[0:a]aresample=async=1:first_pts=0,loudnorm=I=-16:TP=-1.5:LRA=11[narr];[1:a]aresample=async=1:first_pts=0,volume=0.16,afade=t=in:st=0:d=2,afade=t=out:st=$fadeOutMusicStart`:d=4[music];[narr][music]amix=inputs=2:duration=first:dropout_transition=2,aresample=48000[a]"
& ffmpeg -y -i $narratedVideo -i $musicPath -filter_complex $filter -map 0:v -map "[a]" -c:v copy -c:a aac -ar 48000 -b:a 192k $finalVideo | Out-Null

$checkedDuration = & ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $finalVideo
Write-Host "Video created: $finalVideo"
Write-Host "Narration-only video: $narratedVideo"
Write-Host "Audio scenes: $audioDir"
Write-Host "Background music: $musicPath"
Write-Host "Duration seconds: $checkedDuration"
