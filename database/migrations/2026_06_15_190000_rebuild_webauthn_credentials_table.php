<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Laragear\WebAuthn\Models\WebAuthnCredential;

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
     * di-drop lalu dibuat ulang menggunakan builder resmi package agar selalu
     * sinkron dengan versi Laragear yang aktif.
     */
    public function up(): void
    {
        Schema::dropIfExists('webauthn_credentials');

        WebAuthnCredential::migration()->up();
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
