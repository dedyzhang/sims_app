<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan grup chat — DIMATERIALISASI, bukan diturunkan on-the-fly.
 *
 * Alasan materialisasi: (1) read watermark per anggota butuh baris per (grup,user)
 * apa pun caranya, jadi materialisasi keanggotaan praktis gratis; (2) endpoint poll
 * jalan tiap ~4 detik per user — menurunkan keanggotaan on-the-fly berarti tiap poll
 * harus UNION 4 sumber (walikelas, ngajars, siswa.id_kelas, orangtua→siswa.id_kelas),
 * sedangkan dgn baris materialized cek keanggotaan = 1 index lookup.
 * Preseden: classroom_members (App\Services\ClassroomService).
 *
 * Harga yang dibayar: butuh titik sync di controller + rekonsiliasi nightly
 * (grupchat:sinkron). Rekonsiliasi WAJIB, bukan opsional — siswa.id_kelas dimutasi
 * dari banyak jalur kode dan mungkin dari impor/SQL mentah yang tak memanggil sync.
 *
 * `peran` sengaja TERPISAH dari users.access: satu guru bisa 'walikelas' di grup A
 * dan 'guru' di grup B.
 *
 * `left_at` = soft-leave, bukan delete — pertahankan watermark & jejak audit.
 *
 * `joined_seq` membatasi riwayat yang boleh dibaca, HANYA untuk peran siswa/orangtua.
 * Staf (walikelas/guru/admin) melihat riwayat penuh supaya tetap bisa memoderasi saat
 * walikelas berganti di tengah tahun. Backfill awal mengisi 0 → tak ada yg kehilangan
 * apa pun; yang ditutup adalah siswa pindah ke 7B di Oktober lalu membaca diskusi 7B
 * bulan September, dan ortu baru membaca diskusi paguyuban tentang anak lain.
 *
 * `last_notified_seq` dipakai digest FCM (grupchat:kirim-notif) supaya satu push
 * mewakili banyak pesan, dan supaya user yg sedang membuka grup tak pernah di-push.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grup_chat_members', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('grup_id');
            $table->uuid('user_id');
            $table->enum('peran', ['walikelas', 'guru', 'siswa', 'orangtua', 'admin']);
            // Untuk ortu: anak yg jadi dasar keanggotaan. Untuk siswa: dirinya sendiri.
            $table->uuid('id_siswa')->nullable();

            $table->boolean('can_write')->default(true);

            $table->timestamp('joined_at')->nullable();
            $table->unsignedBigInteger('joined_seq')->default(0);
            $table->timestamp('left_at')->nullable();

            $table->unsignedBigInteger('last_read_seq')->default(0);
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_notified_seq')->default(0);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('muted_until')->nullable();

            $table->timestamps();

            $table->foreign('grup_id')->references('uuid')->on('grup_chats')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('siswa')->nullOnDelete();

            $table->unique(['grup_id', 'user_id']);
            $table->index(['user_id', 'left_at']);   // daftar grup user + badge sidebar
            $table->index(['grup_id', 'left_at']);   // daftar penerima notif / anggota aktif
            $table->index('id_siswa');               // sync saat siswa pindah / lulus
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grup_chat_members');
    }
};
