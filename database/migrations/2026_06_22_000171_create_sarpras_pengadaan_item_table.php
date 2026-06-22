<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_pengadaan_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('pengadaan_id')
                ->constrained('sarpras_pengadaan')->cascadeOnDelete();
            $table->foreignUuid('kategori_id')->nullable()
                ->constrained('sarpras_kategori_aset')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()
                ->constrained('sarpras_supplier')->nullOnDelete();
            $table->string('nama_barang');
            $table->unsignedInteger('qty')->default(1);
            $table->string('satuan')->default('unit');
            // UANG: integer rupiah per unit (BCMath).
            $table->unsignedBigInteger('estimasi_harga')->default(0);
            // Pencatatan penerimaan.
            $table->unsignedInteger('qty_diterima')->default(0);
            $table->string('kondisi_terima')->nullable();
            $table->date('tgl_terima')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_pengadaan_item');
    }
};
