<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft saran OCR bukti transfer (A2) — HITL, bukan sumber kebenaran nominal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spp_ocr_drafts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('pembayaran_uuid');
            $table->json('saran');
            $table->string('file_path')->nullable();
            $table->uuid('dibuat_oleh')->nullable();
            $table->timestamp('kadaluarsa_pada')->nullable();
            $table->timestamps();

            $table->index('pembayaran_uuid');
            $table->index('kadaluarsa_pada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spp_ocr_drafts');
    }
};
