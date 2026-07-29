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
        // Round 1: guru yg sudah absen MASUK jadi tak pernah dikenali kamera lagi walau kiosk sudah
        // dipindah ke mode Pulang. Round 2: dibuat auto-detect PENUH (scanMode diabaikan total).
        // Round 3: toggle "Pulang" yg SENGAJA dipilih jadi kepaksa nyoba masuk dulu — dicoba pakai
        // auto-flip. Round 4: auto-flip ITU SENDIRI jadi masalah baru — tab "Datang" ikut mencatat
        // "Pulang" jg. Diperbaiki dgn mode MURNI ikut tab, isFaceLocked() jg dibuat tab-aware.
        // Round 5: isFaceLocked() yg tab-aware itu dipakai render() utk MENYARING KANDIDAT WAJAH sama
        // sekali (bukan cuma menahan aksi) — guru yg tab-nya "salah" jadi TAK PERNAH DICOBA DIKENALI
        // kamera (kotak abu2 "—") sampai reload. Sempat "diperbaiki" dgn isFaceLocked() balik murni
        // "terkunci hanya kalau dua2nya tercatat" (tak tab-aware sama sekali).
        //
        // Round 6 (diminta user scr eksplisit): isFaceLocked() tab-aware itu ternyata BUKAN cuma bug
        // — juga fitur performa yg diinginkan: guru yg sudah absen MASUK tak perlu terus dicocokkan
        // kamera selama tab masih "Masuk" (mengurangi jumlah wajah yg dibandingkan tiap frame). Jadi
        // tab-aware DIKEMBALIKAN, TAPI harus tetap menjamin: begitu tab pindah ke "Pulang", guru yg
        // BELUM absen pulang harus tetap bisa dikenali TERUS-MENERUS sampai sukses — termasuk yg
        // sempat ditolak krn agenda belum diisi (jangan ikut disaring keluar hanya krn riwayat gagal;
        // itu ditangani _pulangBlockedAt di onMatch(), bukan di isFaceLocked()).
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        // isFaceLocked(): terkunci total kalau dua2nya tercatat; kalau toggle tampil, tab-aware lagi
        // (skip kandidat yg aksi tab-nya sudah tercatat) — TAPI komentarnya harus menegaskan syarat
        // "tetap terbaca sampai sukses" di tab Pulang, bukan sekadar kode telanjang tanpa penjelasan.
        $this->assertStringContainsString(
            "if(this.hasGuru && this.qrEnabled){\n                    return this.scanMode === 'pulang' ? !!s.pulangMarked : !!s.marked;\n                }",
            $source
        );
        $this->assertStringContainsString('return !!s.marked;', $source);

        // onMatch(): mode MURNI ikut toggle scanMode saat toggle tampil — TAK ADA lagi auto-flip
        // (behavior round 3 yg jadi sumber bug round 4 harus tetap hilang).
        $this->assertStringContainsString(
            "const mode = (this.hasGuru && this.qrEnabled)\n"
            . "                    ? (this.scanMode === 'pulang' ? 'pulang' : 'masuk')\n"
            . "                    : (s.marked ? 'pulang' : 'masuk');",
            $source
        );
        $this->assertStringNotContainsString("let mode = this.scanMode === 'pulang' ? 'pulang' : 'masuk';", $source);
        $this->assertStringNotContainsString("if(mode === 'masuk' && s.marked) mode = 'pulang';", $source);
        $this->assertStringContainsString('if(s._masukBusy || s._pulangBusy) return;', $source);

        // Cabang pulang: jeda 8 detik setelah ditolak (mis. agenda belum diisi) TIDAK boleh ikut
        // menyaring dari isFaceLocked() — supaya percobaan berikutnya di tab Pulang tetap terbaca.
        // (Cache _faceLocked jg WAJIB dilepas di jalur ini — lihat test_cache_face_locked_dilepas_saat_cooldown_aktif.)
        $this->assertStringContainsString("if(s._pulangBlockedAt && (Date.now()-s._pulangBlockedAt) < 8000){ delete this._faceLocked[uuid]; return; }", $source);

        // Toggle scanMode kini dipakai bareng utk Kartu ID (barcode/QR) DAN wajah — tetap reset streak.
        $this->assertStringContainsString("@click=\"scanMode='masuk'; _streak={}\"", $source);
        $this->assertStringContainsString("@click=\"scanMode='pulang'; _streak={}\"", $source);
    }

    /** Diminta user scr eksplisit (round 6): begitu tab kiosk pindah ke "Pulang", guru yg BELUM
     *  sukses absen pulang harus tetap jadi kandidat wajah — termasuk yg berkali-kali ditolak krn
     *  agenda belum diisi. isFaceLocked() tak boleh mengunci mereka hanya krn s.pulangMarked masih
     *  false; satu2nya syarat lock di tab Pulang adalah s.pulangMarked SUDAH true (sukses). */
    public function test_isFaceLocked_tetap_membuka_kandidat_pulang_walau_pernah_ditolak_agenda(): void
    {
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));
        // Syarat lock di tab pulang murni `!!s.pulangMarked` — tak ada embel2 lain (mis. cek
        // _pulangBlockedAt atau riwayat gagal) yg bisa membuatnya terkunci padahal belum sukses.
        $this->assertStringContainsString("this.scanMode === 'pulang' ? !!s.pulangMarked : !!s.marked;", $source);
    }

    public function test_guru_yg_belum_absen_masuk_langsung_dicatat_pulang_saat_toggle_pulang(): void
    {
        // Kasus konkret: kiosk di tab "Pulang" (misal sore hari), guru yg PAGINYA LUPA absen masuk
        // (s.marked masih false) discan — jangan dicoba dicatat sbg masuk dulu, langsung skip ke
        // pulang. Berlaku selama toggle-nya sendiri TAMPIL (mode murni ikut tab 'pulang' apapun
        // status s.marked-nya) — beda dari cabang fallback (toggle tak ada) yg baru lihat s.marked.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));
        $this->assertStringContainsString("? (this.scanMode === 'pulang' ? 'pulang' : 'masuk')", $source);
    }

    public function test_tab_datang_tidak_lagi_ikut_mencatat_pulang(): void
    {
        // Regresi round 4 yg baru dilaporkan: operator di tab "Datang", guru yg discan malah IKUT
        // tercatat "Pulang" jg (bukan cuma masuk). Penyebabnya auto-flip round 3 yg mengubah mode
        // jadi 'pulang' kalau s.marked kebetulan sudah true. Pastikan pola auto-flip itu sudah hilang
        // total dari onMatch().
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));
        $this->assertStringNotContainsString("mode = 'pulang'", $source);
        $this->assertStringNotContainsString("mode = 'masuk';\n", $source);
    }

    public function test_cache_face_locked_dilepas_saat_sukses_bukan_cuma_saat_gagal(): void
    {
        // Bug nyata dilaporkan user: guru cuma bisa absen DATANG, lalu tak pernah terdeteksi lagi
        // buat absen PULANG. Root cause: this._faceLocked[uuid] di-set true SEBELUM fetch (line
        // ~954 & ~970) supaya frame berikutnya tak memicu onMatch() lagi selagi request masih
        // diproses — tapi dulu HANYA callback gagal/catch yg menghapusnya lagi; callback SUKSES
        // tak pernah menghapusnya. Akibatnya isFaceLocked() short-circuit true selamanya dari cache
        // ini (mendahului cek s.marked/s.pulangMarked yg sebenarnya), jadi wajah guru itu tak pernah
        // diproses onMatch() lagi sepanjang sesi halaman — persis walau state absen pulang-nya masih
        // kosong. Fix: hapus cache ini juga di jalur SUKSES (masuk maupun pulang).
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        // Cabang sukses PULANG (guru): hapus cache sebelum s.pulangMarked=true.
        $this->assertMatchesRegularExpression(
            "/delete this\\._faceLocked\\[uuid\\];\\s*\\n\\s*s\\.pulangMarked=true;/",
            $source,
            'Cabang sukses pulang harus melepas cache _faceLocked, bukan cuma cabang gagal.'
        );
        // Cabang sukses MASUK (guru): hapus cache sebelum s.marked=true.
        $this->assertMatchesRegularExpression(
            "/delete this\\._faceLocked\\[uuid\\];\\s*\\n\\s*s\\.marked=true; s\\.justMarked=true;\\s*\\n\\s*const key=\\+\\+this\\._seq; const jam=d\\.jam/",
            $source,
            'Cabang sukses masuk harus melepas cache _faceLocked, bukan cuma cabang gagal.'
        );

        // Jalur Kartu ID guru (barcode/QR) TIDAK lagi mengunci _faceLocked permanen setelah sukses —
        // sebelumnya ini jg jadi sumber bug yg sama kalau guru absen masuk via kartu lalu wajahnya
        // tak pernah terbaca lagi utk absen pulang.
        $this->assertStringNotContainsString("s.justMarked = true;\n                    this._faceLocked[d.uuid] = true;", $source);
    }

    /** Bug nyata dilaporkan user: guru yang BARU SELESAI mengisi agenda tetap tidak bisa absen
     *  pulang — harus reload halaman dulu baru wajahnya terbaca lagi. Root cause: 3 early-return
     *  di onMatch() (jeda 8 detik setelah ditolak, utk cabang pulang-guru, masuk-guru, & masuk-
     *  siswa) TIDAK melepas this._faceLocked[uuid] sebelum return — padahal flag itu SUDAH ditulis
     *  true oleh render() SEBELUM onMatch() dipanggil. Begitu percobaan kedua (dalam 8 detik
     *  setelah percobaan pertama ditolak) memicu onMatch() lagi, ia langsung kena early-return ini
     *  TANPA sempat menghapus cache-nya lagi — beda dari jalur fetch (yg selalu menghapusnya di
     *  .then()/.catch()). Cache itu lalu nyangkut PERMANEN krn tak ada kode lain yg menghapusnya,
     *  jadi isFaceLocked() short-circuit true selamanya utk orang ini (baris paling atas fungsi
     *  itu) — wajahnya tak pernah dicocokkan kamera lagi sepanjang sesi halaman, walau agenda sudah
     *  diisi & cooldown 8 detik sudah lewat lama. Satu-satunya jalan keluar sebelumnya: reload. */
    public function test_cache_face_locked_dilepas_saat_cooldown_aktif(): void
    {
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString(
            "if(s._pulangBlockedAt && (Date.now()-s._pulangBlockedAt) < 8000){ delete this._faceLocked[uuid]; return; }",
            $source,
            'Cabang pulang (guru) harus melepas cache _faceLocked selama masih dlm jeda cooldown, bukan cuma di jalur fetch.'
        );
        $this->assertStringContainsString(
            "if(s._masukBlockedAt && (Date.now()-s._masukBlockedAt) < 8000){ delete this._faceLocked[uuid]; return; }",
            $source,
            'Cabang masuk (guru & siswa) harus melepas cache _faceLocked selama masih dlm jeda cooldown.'
        );
        // Pastikan pola LAMA yg jadi sumber bug (return polos tanpa delete) sudah tak ada lagi.
        $this->assertStringNotContainsString(
            "if(s._pulangBlockedAt && (Date.now()-s._pulangBlockedAt) < 8000) return;",
            $source
        );
        $this->assertStringNotContainsString(
            "if(s._masukBlockedAt && (Date.now()-s._masukBlockedAt) < 8000) return;",
            $source
        );
    }
}
