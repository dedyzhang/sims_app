<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laragear\WebAuthn\Enums\Formats;

return new class extends Migration
{
    /**
     * Tabel `webauthn_credentials` lama dibuat dengan skema Laragear versi lama
     * (kolom: name, type, algorithms, attachment, device_type, backed_up, disabled_at).
     * Package yang terpasang sekarang memakai skema berbeda (user_id, alias, rp_id,
     * origin, attestation_format, certificates) sehingga pendaftaran biometrik gagal:
     * "table webauthn_credentials has no column named user_id".
     *
     * Belum ada credential yang tersimpan (semua insert gagal), jadi tabel aman
     * di-drop lalu dibuat ulang.
     *
     * Kolom & index ditulis MANUAL (bukan lewat WebAuthnCredential::migration())
     * supaya tidak bergantung pada versi package laragear/webauthn/meta-model yang
     * terpasang di server (perilaku `->morph()` bisa berbeda antar versi/instalasi).
     * Dua hal yang wajib dijaga:
     * 1) `authenticatable_id` harus UUID (bukan integer bawaan Laravel), karena model
     *    `authenticatable` (User) di app ini pakai UUID string sebagai primary key.
     * 2) Nama index dipendekkan manual — default Laravel untuk index morph
     *    (`webauthn_credentials_authenticatable_type_authenticatable_id_index`)
     *    melebihi batas 64 karakter identifier MySQL (baru ketahuan di MySQL
     *    produksi; SQLite lokal tidak menegakkan batas ini).
     */
    public function up(): void
    {
        Schema::dropIfExists('webauthn_credentials');

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->string('id', 510)->primary();

            $table->string('authenticatable_type');
            $table->uuid('authenticatable_id');
            $table->index(['authenticatable_type', 'authenticatable_id'], 'webauthn_cred_auth_index');

            $table->uuid('user_id');
            $table->string('alias')->nullable();
            $table->unsignedBigInteger('counter')->nullable();
            $table->string('rp_id');
            $table->string('origin');
            $table->json('transports')->nullable();
            $table->uuid('aaguid')->nullable();
            $table->text('public_key');
            $table->string('attestation_format')->default(Formats::None->value);
            $table->json('certificates')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
