<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percobaan ujian per siswa + jawabannya. urutan_soal/urutan_opsi dibuat SEKALI
 * saat mulai dan dipersist di sini — supaya reload halaman di tengah ujian tidak
 * mengacak ulang (siswa harus selalu melihat urutan yg sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_attempts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian_kelas');
            $table->uuid('id_siswa'); // users.uuid

            $table->json('urutan_soal'); // array uuid ujian_soal, urutan tampil ke siswa ini
            $table->json('urutan_opsi')->nullable(); // {soal_uuid: [opsi_uuid,...]} (match: array indeks pasangan)

            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('batas_waktu_pada')->nullable(); // = mulai_pada + durasi_menit, deadline server-authoritative
            $table->timestamp('selesai_pada')->nullable();

            $table->string('status', 16)->default('in_progress'); // in_progress|submitted|dinilai|dibatalkan
            $table->boolean('dikunci')->default(false); // anti-kecurangan lockout, terpisah dari status
            $table->boolean('auto_submit')->default(false); // true kalau difinalisasi paksa oleh sweep command

            $table->decimal('skor_objektif', 5, 2)->nullable();
            $table->decimal('total_skor', 5, 2)->nullable();
            $table->boolean('butuh_penilaian_manual')->default(false);
            $table->string('status_transfer_nilai', 20)->default('belum'); // belum|berhasil|gagal_terkunci|gagal_lain
            $table->unsignedInteger('durasi_ms')->nullable();

            $table->timestamps();

            $table->foreign('id_ujian_kelas')->references('uuid')->on('ujian_kelas')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('users')->cascadeOnDelete();
            // BUKAN unique constraint DB (reset-ulang butuh baris baru per siswa setelah
            // attempt lama dibatalkan) — "1 attempt aktif per siswa" ditegakkan di level
            // aplikasi (UjianSiswaController::start()).
            $table->index(['id_ujian_kelas', 'id_siswa', 'status']);
            $table->index(['status', 'batas_waktu_pada']); // dipakai sweep ujian:auto-submit
        });

        Schema::create('ujian_jawaban', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_attempt');
            $table->uuid('id_soal');

            $table->uuid('id_opsi_dipilih')->nullable(); // mcq, true_false (single-select)
            $table->json('opsi_dipilih_multi')->nullable(); // mcq_complex (array uuid opsi)
            $table->json('jawaban_pasangan')->nullable(); // match: {left: right}
            $table->text('jawaban_esai')->nullable();

            $table->boolean('is_benar')->nullable(); // null selamanya utk essay
            $table->decimal('skor_diperoleh', 5, 2)->default(0);
            $table->boolean('dinilai_manual')->default(false);
            $table->uuid('dinilai_oleh')->nullable();
            $table->text('catatan_guru')->nullable();
            $table->timestamp('dijawab_pada')->nullable();

            $table->timestamps();

            $table->foreign('id_attempt')->references('uuid')->on('ujian_attempts')->cascadeOnDelete();
            $table->foreign('id_soal')->references('uuid')->on('ujian_soal')->cascadeOnDelete();
            $table->foreign('id_opsi_dipilih')->references('uuid')->on('ujian_soal_opsi')->nullOnDelete();
            $table->foreign('dinilai_oleh')->references('uuid')->on('users')->nullOnDelete();
            $table->unique(['id_attempt', 'id_soal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_jawaban');
        Schema::dropIfExists('ujian_attempts');
    }
};
