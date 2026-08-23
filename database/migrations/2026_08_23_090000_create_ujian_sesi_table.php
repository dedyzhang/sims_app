<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Sesi" = entitas sungguhan (bukan sekadar teks label) yg menaungi 1+ baris
 * ujian_jadwal yg terjadi dlm 1 sitting fisik yg sama (mis. Pendidikan Agama &
 * Pendidikan Pancasila sama2 08:00-16:00 di hari yg sama, dibedakan admin lewat
 * sesi_label "1"/"2"). Dibutuhkan sbg entitas nyata (bukan derivasi ad-hoc dari
 * sesi_label+tanggal tiap request) supaya Berita Acara & Daftar Hadir per sesi
 * (fitur baru) py FK yg stabil thd edit — kalau admin ganti sesi_label suatu
 * jadwal, data lama yg nunjuk ke sini TAK jadi yatim diam2.
 *
 * TANPA unique constraint ketat di DB — pengelompokan (id_ujian_paket, tanggal,
 * label, jam_mulai, jam_selesai) di-enforce via firstOrCreate() di
 * UjianJadwalController, BUKAN constraint DB, supaya label NULL boleh berulang
 * (tiap jadwal tanpa label otomatis DIANGGAP SATU sesi kalau tanggal+jam sama —
 * itu memang perilaku yg diinginkan, bukan bug: 2 jadwal tanpa label tapi jam
 * beda TETAP jadi sesi terpisah krn jam ikut jadi bagian kunci pencocokan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_sesi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian_paket');
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->foreign('id_ujian_paket')->references('uuid')->on('ujian_paket')->cascadeOnDelete();
            $table->index(['id_ujian_paket', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_sesi');
    }
};
