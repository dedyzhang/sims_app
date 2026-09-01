<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SATU tabel gabungan pemilih + suara (keputusan produk: hasil tak perlu rahasia
 * total, admin boleh audit "siapa pilih paslon X"). `token` = secret unik per-voter
 * dicetak sbg QR — divalidasi murni via lookup token (BUKAN via EnsureKioskOrPermission,
 * krn di sini token per ORANG, bukan satu token bersama seperti kiosk absensi).
 * `sudah_memilih_at` NULL = belum memilih; diisi di DALAM DB::transaction()+
 * lockForUpdate() (lihat OsisVoteController::store()) supaya submit ganda hampir
 * bersamaan (double-tap / 2 tab) TIDAK PERNAH lolos berdua — beda dari guard lama
 * AbsensiController::markByBarcode() yg cuma cek row via PHP SEBELUM insert
 * (rentan race condition).
 * *_snapshot: salinan nama/NIS/kelas SAAT token dibuat — dipakai cetak massal &
 * fallback tampilan; relasi live ke siswa/guru tetap ada (siswa()/guru()) untuk
 * data yg butuh selalu terkini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osis_pemilih', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_pemilihan');
            $table->string('tipe_pemilih', 10); // siswa|guru
            $table->uuid('id_siswa')->nullable();
            $table->uuid('id_guru')->nullable();
            $table->string('nama_snapshot', 100);
            $table->string('nis_snapshot', 30)->nullable();
            $table->string('kelas_snapshot', 30)->nullable();
            $table->string('token', 64)->unique();
            $table->uuid('id_paslon_dipilih')->nullable();
            $table->timestamp('sudah_memilih_at')->nullable();
            $table->string('ip_saat_memilih', 45)->nullable();
            $table->string('user_agent_saat_memilih', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_pemilihan')->references('uuid')->on('osis_pemilihan')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('siswa')->cascadeOnDelete();
            $table->foreign('id_guru')->references('uuid')->on('gurus')->cascadeOnDelete();
            $table->foreign('id_paslon_dipilih')->references('uuid')->on('osis_paslon')->nullOnDelete();

            // NULL diperlakukan "distinct" oleh MySQL/Postgres/SQLite — jadi banyak baris guru
            // (id_siswa NULL) tetap boleh, tapi 1 siswa tak bisa py 2 token di pemilihan yg sama.
            // Nama index custom (bukan auto-generate Laravel) — auto-generate utk 2 index komposit
            // di bawah tembus 61-62 dari batas 64 char MySQL (margin cuma 2-3 char, terlalu mepet
            // mengingat app ini sebelumnya pernah kena masalah persis ini di tabel lain).
            $table->unique(['id_pemilihan', 'id_siswa']);
            $table->unique(['id_pemilihan', 'id_guru']);
            $table->index(['id_pemilihan', 'tipe_pemilih', 'sudah_memilih_at'], 'osis_pemilih_dashboard_index'); // dashboard live
            $table->index(['id_pemilihan', 'tipe_pemilih', 'id_paslon_dipilih'], 'osis_pemilih_hasil_index'); // chart hasil
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osis_pemilih');
    }
};
