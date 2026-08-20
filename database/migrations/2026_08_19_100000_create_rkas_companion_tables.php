<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_reference_sets', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('label');
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('versi', 32);
            $table->string('jenjang', 24);
            $table->string('sumber_dana', 80);
            $table->string('source_url', 500)->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->json('rules')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('imported_by')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tahun_anggaran', 'versi', 'jenjang', 'sumber_dana'], 'rkas_ref_sets_version_unique');
            $table->index(['tahun_anggaran', 'jenjang', 'sumber_dana', 'is_active'], 'rkas_ref_sets_lookup');
        });

        Schema::create('rkas_references', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('reference_set_uuid')->constrained('rkas_reference_sets', 'uuid')->cascadeOnDelete();
            $table->string('kode_kegiatan', 32);
            $table->string('snp', 120)->nullable();
            $table->string('komponen', 160)->nullable();
            $table->text('uraian_kegiatan');
            $table->string('kode_rekening_belanja', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['reference_set_uuid', 'kode_kegiatan'], 'rkas_references_code_unique');
            $table->index(['reference_set_uuid', 'komponen'], 'rkas_references_component_index');
        });

        Schema::create('rkas_plans', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('npsn', 20)->nullable();
            $table->string('nama_sekolah', 255)->nullable();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('jenjang', 24);
            $table->string('sumber_dana', 80);
            $table->foreignUuid('reference_set_uuid')->constrained('rkas_reference_sets', 'uuid')->restrictOnDelete();
            $table->unsignedBigInteger('pagu')->default(0);
            $table->string('status', 40)->default('draft');
            $table->timestamp('validated_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users', 'uuid')->restrictOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamps();

            $table->index(['tahun_anggaran', 'jenjang', 'sumber_dana'], 'rkas_plans_scope_index');
            $table->index(['status', 'created_by'], 'rkas_plans_status_index');
        });

        Schema::create('rkas_items', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('plan_uuid')->constrained('rkas_plans', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('reference_uuid')->nullable()->constrained('rkas_references', 'uuid')->nullOnDelete();
            $table->string('kode_kegiatan', 32);
            $table->string('komponen', 160)->nullable();
            $table->text('penjelasan_implementasi')->nullable();
            $table->text('uraian_belanja');
            $table->unsignedTinyInteger('bulan_dianggarkan');
            $table->unsignedBigInteger('jumlah')->default(0);
            $table->string('satuan', 40);
            $table->unsignedBigInteger('harga_satuan')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('kode_rekening_belanja', 64)->nullable();
            $table->timestamps();

            $table->index(['plan_uuid', 'kode_kegiatan'], 'rkas_items_plan_code_index');
        });

        Schema::create('rkas_validations', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('plan_uuid')->constrained('rkas_plans', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('item_uuid')->nullable()->constrained('rkas_items', 'uuid')->nullOnDelete();
            $table->string('kode', 80);
            $table->string('severity', 16);
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['plan_uuid', 'severity'], 'rkas_validations_plan_severity_index');
        });

        Schema::create('rkas_sync_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('plan_uuid')->constrained('rkas_plans', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->constrained('users', 'uuid')->restrictOnDelete();
            $table->string('status', 40);
            $table->text('note')->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['plan_uuid', 'status', 'occurred_at'], 'rkas_sync_logs_plan_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_sync_logs');
        Schema::dropIfExists('rkas_validations');
        Schema::dropIfExists('rkas_items');
        Schema::dropIfExists('rkas_plans');
        Schema::dropIfExists('rkas_references');
        Schema::dropIfExists('rkas_reference_sets');
    }
};
