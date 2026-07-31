<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesan grup chat.
 *
 * URUTAN memakai `seq` (counter per grup dari grup_chats.last_seq), BUKAN created_at.
 * Dengan seq, cursor polling jadi eksak (seq > ?) dan unread = aritmatika murni
 * tanpa membaca tabel ini sama sekali. Bandingkan chatbot_messages yg terpaksa
 * memakai created_at >= cursor + dedup Set di frontend justru karena tak punya
 * kunci urut — jangan tiru kekurangannya.
 *
 * Auto-increment ditolak: SQLite (dipakai phpunit :memory:) hanya mendukung
 * AUTOINCREMENT pada INTEGER PRIMARY KEY, sedangkan PK di sini uuid.
 *
 * unique(grup_id, seq) sengaja UNIQUE, bukan index biasa — kalau ada race yang
 * menduplikasi counter, insert gagal keras daripada diam-diam merusak cursor
 * semua klien.
 *
 * sender_nama/sender_peran di-SNAPSHOT: User::displayName() untuk ortu melakukan
 * query Orangtua→siswa per pemanggilan, jadi render 50 pesan tanpa snapshot = 50
 * query. Snapshot juga mengawetkan riwayat saat akun dihapus/berganti kelas.
 *
 * reply_snippet/reply_nama di-snapshot supaya kutipan dirender tanpa join dan
 * tetap utuh saat pesan induk dihapus.
 *
 * Kolom attachment_* dan deleted_* sudah disiapkan di sini walau baru dipakai
 * Fase 2 & 3 — supaya tidak perlu migration susulan di tabel yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grup_chat_messages', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('grup_id');
            $table->unsignedBigInteger('seq');
            $table->uuid('user_id')->nullable();      // pesan bertahan walau akun dihapus
            $table->string('sender_nama', 80);
            $table->string('sender_peran', 20);

            $table->text('body')->nullable();         // null bila hanya lampiran

            $table->uuid('reply_to_id')->nullable();
            $table->string('reply_snippet', 160)->nullable();
            $table->string('reply_nama', 80)->nullable();

            $table->string('attachment_path', 255)->nullable();   // disk 'local', prefix chat/
            $table->enum('attachment_type', ['image', 'file'])->nullable();
            $table->string('attachment_name', 160)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();

            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();

            $table->foreign('grup_id')->references('uuid')->on('grup_chats')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->nullOnDelete();
            $table->foreign('reply_to_id')->references('uuid')->on('grup_chat_messages')->nullOnDelete();

            $table->unique(['grup_id', 'seq']);        // inti cursor polling
            $table->index(['grup_id', 'created_at']);  // separator tanggal / arsip
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grup_chat_messages');
    }
};
