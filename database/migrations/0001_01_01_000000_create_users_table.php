<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('username')->unique();
            $table->string('identifier')->nullable()->index(); // NIK/NIP/NIS untuk login alternatif
            $table->string('password');
            $table->string('access'); // superadmin/admin/kurikulum/kesiswaan/sapras/kepala/guru/siswa/ortu
            $table->string('pin', 6)->nullable(); // PIN login mobile
            $table->string('reset_token')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->uuidMorphs('authenticatable', 'webauthn_cred_auth_index');
            $table->string('name')->nullable();
            $table->string('type', 32);
            $table->json('transports');
            $table->string('attachment')->nullable();
            $table->json('algorithms');
            $table->text('public_key');
            $table->unsignedSmallInteger('counter');
            $table->string('device_type', 32);
            $table->boolean('backed_up');
            $table->string('aaguid')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
