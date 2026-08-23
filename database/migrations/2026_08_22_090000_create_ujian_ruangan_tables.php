<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem pelaksanaan ujian periode (mis. PAS Semester 1) — paket (folder yg
 * mengelompokkan beberapa Ujian sekaligus), ruangan fisik (roster siswa),
 * jadwal (hari×jam per-mapel — SEKALIGUS pengisi ujian_kelas.dibuka_mulai/
 * dibuka_sampai yg sudah ada tapi selama ini tak pernah diisi), dan dokumen
 * resmi per-ruangan-per-hari (daftar hadir + berita acara). SENGAJA tak
 * menyentuh mekanisme token/attempt siswa yg sudah ada — ruangan murni
 * lapisan pengawasan+administrasi fisik di ATAS modul Ujian.
 *
 * TIDAK ADA penugasan pengawas manual — akses "mengawasi" & pengisian daftar
 * hadir keduanya lewat scan QR ruangan (lihat UjianRuanganScanController):
 * siswa scan → catat hadir sendiri; guru scan → masuk ke monitor, guru MANA
 * PUN boleh, asal ruangan ini punya UjianJadwal di HARI itu (lihat
 * UjianRuanganPolicy::awasi()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_paket', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama');
            $table->string('jenis', 16)->default('pas'); // harian|pts|pas|uas (sama spt Ujian::jenis)
            $table->foreignId('id_semester')->nullable()->constrained('semesters')->nullOnDelete();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 16)->default('draft'); // draft|berjalan|selesai
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();
        });

        Schema::table('ujians', function (Blueprint $table) {
            // Nullable & lepas-pasang — ujian lama maupun baru boleh gabung paket kapan saja,
            // tak wajib (ujian standalone di luar paket tetap harus jalan spt sebelumnya).
            $table->uuid('id_ujian_paket')->nullable()->after('id_materi');
            $table->foreign('id_ujian_paket')->references('uuid')->on('ujian_paket')->nullOnDelete();
        });

        Schema::create('ujian_ruangan', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian_paket');
            $table->string('nama');
            $table->unsignedInteger('kapasitas')->nullable();
            // Link opsional ke modul Sarpras (kalau sekolah pakainya) — TIDAK wajib, ruangan
            // ujian tetap berdiri sendiri dgn nama/kapasitas sendiri walau Sarpras nonaktif.
            $table->uuid('denah_ruangan_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->foreign('id_ujian_paket')->references('uuid')->on('ujian_paket')->cascadeOnDelete();
            $table->foreign('denah_ruangan_id')->references('id')->on('sarpras_denah_ruangan')->nullOnDelete();
        });

        Schema::create('ujian_ruangan_peserta', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ruangan');
            $table->uuid('id_siswa');
            $table->unsignedInteger('nomor_urut')->nullable(); // nomor kursi, opsional
            $table->timestamps();

            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('siswa')->cascadeOnDelete();
            $table->unique(['id_ruangan', 'id_siswa']);
        });

        Schema::create('ujian_jadwal', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian_paket');
            $table->uuid('id_ujian'); // ujian spesifik (satu mapel) yg dibuka slot ini
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('sesi_label')->nullable(); // mis. "Sesi 1"
            $table->timestamps();

            $table->foreign('id_ujian_paket')->references('uuid')->on('ujian_paket')->cascadeOnDelete();
            $table->foreign('id_ujian')->references('uuid')->on('ujians')->cascadeOnDelete();
        });

        Schema::create('ujian_daftar_hadir', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ruangan');
            $table->uuid('id_siswa');
            $table->date('tanggal');
            $table->string('status', 16)->default('hadir'); // hadir|izin|sakit|alpa (sama spt Absensi)
            $table->text('keterangan')->nullable();
            $table->uuid('dicatat_oleh')->nullable();
            $table->timestamp('dicatat_pada')->nullable();
            $table->timestamps();

            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('siswa')->cascadeOnDelete();
            $table->foreign('dicatat_oleh')->references('uuid')->on('users')->nullOnDelete();
            $table->unique(['id_ruangan', 'id_siswa', 'tanggal']);
        });

        Schema::create('ujian_berita_acara', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ruangan');
            $table->date('tanggal');
            $table->time('jam_mulai_aktual')->nullable();
            $table->time('jam_selesai_aktual')->nullable();
            $table->unsignedInteger('jumlah_hadir')->nullable();
            $table->unsignedInteger('jumlah_tidak_hadir')->nullable();
            $table->longText('catatan_kejadian')->nullable();
            $table->uuid('diisi_oleh')->nullable();
            $table->timestamp('diisi_pada')->nullable();
            $table->timestamps();

            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->foreign('diisi_oleh')->references('uuid')->on('users')->nullOnDelete();
            $table->unique(['id_ruangan', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_berita_acara');
        Schema::dropIfExists('ujian_daftar_hadir');
        Schema::dropIfExists('ujian_jadwal');
        Schema::dropIfExists('ujian_ruangan_peserta');
        Schema::dropIfExists('ujian_ruangan');
        Schema::table('ujians', function (Blueprint $table) {
            $table->dropForeign(['id_ujian_paket']);
            $table->dropColumn('id_ujian_paket');
        });
        Schema::dropIfExists('ujian_paket');
    }
};
