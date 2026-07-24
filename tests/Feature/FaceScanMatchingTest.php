<?php

namespace Tests\Feature;

use Tests\TestCase;

class FaceScanMatchingTest extends TestCase
{
    public function test_scan_wajah_memakai_gate_robust_anti_false_positive(): void
    {
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        // Kalibrasi dikembalikan PERSIS ke commit 21 Juli 2026 malam ("Perbaikan Face
        // Recognation dan validasi wajah", 10db675) atas permintaan eksplisit user — sesi
        // 22 Juli sempat menambah/mengubah lagi (confidentThreshold 0.82, margin 0.06,
        // minFaceFrac 0.12, singleSampleTop1) tanpa laporan membaik, jadi ditarik balik ke
        // titik yg diketahui stabil semalam sebelumnya.
        $this->assertStringContainsString('threshold:0.66', $source);
        $this->assertStringContainsString('confidentThreshold:0.80', $source);
        $this->assertStringContainsString('supportThreshold:0.62', $source);
        $this->assertStringContainsString('minSampleSupport:2', $source);
        $this->assertStringContainsString('margin:0.08', $source);
        $this->assertStringContainsString('minFaceFrac:0.14', $source);
        $this->assertStringContainsString('confirmFrames:2', $source);
        $this->assertStringContainsString('_faceLocked', $source);
        $this->assertStringContainsString('isKiosk', $source);
        $this->assertStringContainsString('afterFaceMarkSuccess', $source);
        $this->assertStringNotContainsString('singleSampleTop1', $source);
        $this->assertStringContainsString('robustPersonSimilarity(faceEmbedding, descriptors)', $source);
        $this->assertStringContainsString('hasEnoughSampleAgreement(match)', $source);
        $this->assertStringContainsString('rebuildEnrolled', $source);
        $this->assertStringContainsString('recordDiag', $source);
        $this->assertStringContainsString('submitBarcode', $source);
        $this->assertStringContainsString('_scanGen', $source);
        $this->assertStringContainsString('applyAutoExposure', $source);
        $this->assertStringContainsString('enhanceFrame', $source);
        // Auto exposure/kecerahan dikembalikan ke versi sederhana 21 Juli malam (hardware
        // exposureCompensation statis ke max + software brightness via enhanceFrame saja) —
        // sesi 22 Juli sempat menambah exposure adaptif per-frame (getVideoConstraints,
        // previewBrightness, maybeAdjustHardwareExposure) yg lalu diminta dikembalikan normal.
        $this->assertStringNotContainsString('getVideoConstraints', $source);
        $this->assertStringNotContainsString('previewBrightness', $source);
        $this->assertStringNotContainsString('maybeAdjustHardwareExposure', $source);
        $this->assertStringNotContainsString('autoExposureOn', $source);
        $this->assertStringNotContainsString('threshold:0.58', $source);
        $this->assertStringNotContainsString('threshold:0.70', $source);
        $this->assertStringNotContainsString('confirmFrames:1,', $source);
        $this->assertStringNotContainsString('confirmFrames:4', $source);
    }

    public function test_label_petunjuk_akurat_sesuai_gate_yang_gagal(): void
    {
        // Regresi konkret: label 'Dekatkan wajah' dulu HANYA muncul saat wajah SUDAH cukup
        // besar (bigEnough=true) — kasus paling umum di lapangan (wajah masih kecil/jauh dari
        // kamera) malah jatuh ke '—' polos tanpa petunjuk sama sekali. Pengguna yang berdiri
        // di jarak wajar dari kiosk tidak pernah diberi tahu utk mendekat — ini kandidat kuat
        // penyebab "susah terdeteksi" krn gagal SENYAP tanpa ada yg bisa dikoreksi pengguna.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString("label='Mendekat ke kamera'", $source);
        $this->assertStringContainsString("label='Tahan diam, perbaiki cahaya'", $source);
        $this->assertStringContainsString("label='Perjelas wajah'", $source);
        $this->assertStringContainsString("label='Coba lagi, hadap lurus'", $source);
        // Badge saat Human sama sekali tidak menemukan wajah di frame (bukan soal cocok/tidak)
        $this->assertStringContainsString('noFaceHint', $source);
        $this->assertStringContainsString('Wajah tidak terlihat', $source);
    }

    public function test_min_confidence_detektor_dikembalikan_ke_045(): void
    {
        // Regresi: sempat diturunkan ke 0.35 (harapan: tangkap wajah miring/tertutup sebagian),
        // tapi laporan lapangan SETELAHNYA justru "makin susah, kotak abu2 makin sering muncul"
        // — 0.35 meloloskan terlalu banyak deteksi kotak berkualitas rendah yg lalu gagal di
        // tahap kecocokan (bukan wajah asli tersembunyi, tapi TENGGELAM di antara noise).
        // 0.45 adalah nilai lama yg bertahun-tahun terbukti oke sblm sesi ini menyentuhnya.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('minConfidence:0.45', $source);
        $this->assertStringNotContainsString('minConfidence:0.35', $source);
    }

    public function test_panel_diagnostik_tersedia_utk_laporan_lapangan_berbasis_data(): void
    {
        // Riwayat kalibrasi ambang sudah bolak-balik berkali-kali murni berdasar laporan verbal
        // ("susah terdeteksi") tanpa data konkret ttg gate mana yg sebenarnya gagal — panel ini
        // menampilkan counter diag (yg sudah lama ada tapi tak pernah terlihat siapa pun) LANGSUNG
        // di halaman, supaya laporan berikutnya bisa disertai screenshot data nyata.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('showDiag', $source);
        $this->assertStringContainsString('Info Diagnostik', $source);
        $this->assertStringContainsString('diag.small_face', $source);
        $this->assertStringContainsString('diag.low_face_score', $source);
        $this->assertStringContainsString('diag.low_score', $source);
        $this->assertStringContainsString('diag.small_margin', $source);
        $this->assertStringContainsString('diag.low_support', $source);
    }

    public function test_kamera_wajah_juga_membaca_qr_kartu(): void
    {
        // Satu kamera = dua pembaca: deteksi wajah + decode QR kartu pelajar
        // (BarcodeDetector native, fallback jsQR), diatur setting scan_kiosk_mode.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('detectQrFromVideo', $source);
        $this->assertStringContainsString('onCameraQr', $source);
        $this->assertStringContainsString('BarcodeDetector', $source);
        $this->assertStringContainsString('scanKioskMode', $source);
        $this->assertStringContainsString('get faceEnabled()', $source);
        $this->assertStringContainsString('get qrEnabled()', $source);
    }

    public function test_skor_kecocokan_pakai_top1_bukan_dirata_rata_dgn_top2(): void
    {
        // Regresi: skor sempat dihitung top1*0.58+top2*0.42 — wajah yg SANGAT mirip salah satu
        // sampel terdaftar (top1 tinggi) tetap bisa gagal gate `threshold` kalau sampel lain punya
        // sudut/cahaya beda (top2 rendah menyeret skor turun). Ini bikin "Perjelas wajah" muncul
        // terus meski wajahnya sudah dikenali dgn baik. Korroborasi tetap dijaga lewat
        // hasEnoughSampleAgreement() sbg gate terpisah, bukan campur ke skor utama.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('const score = top1;', $source);
        $this->assertStringNotContainsString('top1 * 0.58 + top2 * 0.42', $source);
    }

    public function test_hud_atas_scan_wajah_tidak_pakai_3_badge_absolute_terpisah(): void
    {
        // Regresi: status/mode/counter dulu masing2 `absolute top-3 {left-3,left-1/2,right-3}` —
        // di layar HP sempit ketiganya berebut baris yg sama & saling tumpuk/terpotong (dilaporkan
        // user sbg "keluar dari viewportnya"). Sekarang satu wrapper flex-wrap supaya melipat ke
        // baris baru, bukan tumpuk, saat tak muat.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('flex flex-col gap-1.5 pointer-events-none', $source);
        $this->assertStringContainsString('flex items-start justify-between gap-1.5 flex-wrap', $source);
        $this->assertStringNotContainsString('absolute top-3 left-1/2 -translate-x-1/2', $source);
    }

    public function test_guru_tetap_bisa_dikenali_utk_pulang_setelah_absen_masuk(): void
    {
        // Regresi konkret dilaporkan user (round 1): guru yg sudah absen MASUK (s.marked=true) jadi
        // tak pernah dikenali kamera LAGI walau kiosk sudah dipindah ke mode Pulang — krn isFaceLocked()
        // dan awal onMatch() dulu sama2 mengunci berdasar s.marked TANPA peduli scanMode saat ini.
        // Fix pertama: buat isFaceLocked()/onMatch() sadar scanMode.
        //
        // Regresi lanjutan (round 2, dilaporkan lagi): kiosk tak dijaga terus — operator sering lupa
        // pindah toggle ke "Mode Pulang" di siang/sore hari, jadi guru yg mau pulang TETAP tak pernah
        // terbaca krn kiosk masih dianggap "Mode Masuk" (s.marked sudah true dari pagi → terkunci).
        // Fix final: mode masuk/pulang guru via wajah DIDETEKSI OTOMATIS per orang dari status
        // s.marked/s.pulangMarked-nya sendiri — sama sekali tak bergantung toggle scanMode manual lagi.
        // Toggle scanMode kini HANYA dipakai utk Kartu ID (barcode/QR).
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        // isFaceLocked(): guru terkunci hanya kalau masuk DAN pulang sudah dua-duanya tercatat.
        $this->assertStringContainsString("if(s.type==='guru') return !!(s.marked && s.pulangMarked);", $source);
        $this->assertStringContainsString('return !!s.marked;', $source);
        // Behavior lama (bergantung scanMode utk kunci) harus sudah hilang.
        $this->assertStringNotContainsString("if(this.scanMode==='pulang') return s.type!=='guru' || !!s.pulangMarked;", $source);

        // onMatch(): mode ditentukan dari status guru sendiri, bukan this.scanMode.
        $this->assertStringContainsString("const mode = s.marked ? 'pulang' : 'masuk';", $source);
        $this->assertStringNotContainsString("if(this.scanMode==='pulang'){", $source);
        $this->assertStringContainsString('if(s._masukBusy || s._pulangBusy) return;', $source);

        // Toggle Kartu ID tetap ada (masih dipakai barcode/QR) & tetap reset streak saat diganti.
        $this->assertStringContainsString("@click=\"scanMode='masuk'; _streak={}\"", $source);
        $this->assertStringContainsString("@click=\"scanMode='pulang'; _streak={}\"", $source);
    }
}
