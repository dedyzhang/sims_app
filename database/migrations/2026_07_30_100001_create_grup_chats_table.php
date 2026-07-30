<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul "Grup Chat" — grup percakapan otomatis per kelas.
 * - Tanpa school_id (single-school, konsisten dgn classrooms/forum).
 * - Dua tipe per kelas: 'kelas' (walikelas + siswa saja — guru pengajar/mapel
 *   SENGAJA tidak diikutkan, lihat App\Services\GrupChatService) dan
 *   'paguyuban' (walikelas + orang tua siswa kelas itu).
 *
 * SCOPE grup = (id_kelas, tipe, tahun_ajaran) — BUKAN id_semester. Tabel `kelas`
 * hanya menyimpan tingkat+huruf tanpa tahun, jadi "7A" dipakai ulang tiap tahun;
 * tanpa scope tahun, angkatan baru akan membaca percakapan angkatan lama. Scope ke
 * semester salah karena memecah percakapan di tengah tahun (Januari) padahal
 * komposisi kelas tidak berubah. `id_semester` disimpan hanya sebagai jejak
 * semester saat grup di-provision, bukan sebagai kunci scope.
 *
 * `last_seq` = counter pesan per grup (lihat grup_chat_messages.seq). Dari sini
 * unread dihitung sebagai aritmatika murni: last_seq - member.last_read_seq,
 * tanpa menyentuh tabel pesan sama sekali.
 *
 * `last_message_*` sengaja didenormalisasi supaya halaman DAFTAR grup dirender
 * tanpa join/agregasi ke tabel pesan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grup_chats', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->enum('tipe', ['kelas', 'paguyuban']);
            $table->uuid('id_kelas');                                // kelas.uuid
            $table->string('tahun_ajaran', 9);                       // mis. "2025/2026"
            $table->unsignedBigInteger('id_semester')->nullable();   // semesters.id (integer)

            $table->string('nama', 120);
            $table->enum('mode', ['diskusi', 'pengumuman'])->default('diskusi');
            $table->enum('status', ['aktif', 'arsip'])->default('aktif');

            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();
            $table->string('last_message_by', 80)->nullable();

            $table->timestamps();

            // restrictOnDelete (bukan cascade): kelas bisa punya riwayat percakapan
            // bertahun-tahun (lihat catatan scope di atas). Cascade akan menghapus
            // seluruh riwayat itu diam-diam begitu admin menghapus kelas — status
            // 'arsip' di atas justru dibuat supaya ada jalan lain selain hapus paksa.
            // KelasController::destroy() mengecek & mengosongkan grup yang belum
            // pernah dipakai sebelum menghapus; kelas dengan riwayat asli akan
            // ditolak oleh constraint ini sebagai jaring pengaman terakhir.
            $table->foreign('id_kelas')->references('uuid')->on('kelas')->restrictOnDelete();
            $table->foreign('id_semester')->references('id')->on('semesters')->nullOnDelete();

            // Kunci provisioning idempoten: satu grup per (kelas, tipe, tahun).
            $table->unique(['id_kelas', 'tipe', 'tahun_ajaran']);
            $table->index(['status', 'tahun_ajaran']);
            $table->index(['id_kelas', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grup_chats');
    }
};
