<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canva_connections', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid')->unique();
            $table->string('canva_user_id', 120)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('display_name', 191)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes', 500)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->foreign('user_uuid')
                ->references('uuid')->on('users')
                ->cascadeOnDelete();
        });

        Schema::table('teacher_presentations', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_presentations', 'canva_design_id')) {
                $table->string('canva_design_id', 120)->nullable()->after('slides');
            }
            if (! Schema::hasColumn('teacher_presentations', 'canva_edit_url')) {
                $table->string('canva_edit_url', 1000)->nullable()->after('canva_design_id');
            }
            if (! Schema::hasColumn('teacher_presentations', 'canva_view_url')) {
                $table->string('canva_view_url', 1000)->nullable()->after('canva_edit_url');
            }
            if (! Schema::hasColumn('teacher_presentations', 'canva_exported_pdf_path')) {
                $table->string('canva_exported_pdf_path', 500)->nullable()->after('canva_view_url');
            }
            if (! Schema::hasColumn('teacher_presentations', 'canva_last_synced_at')) {
                $table->timestamp('canva_last_synced_at')->nullable()->after('canva_exported_pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_presentations', function (Blueprint $table) {
            foreach ([
                'canva_design_id',
                'canva_edit_url',
                'canva_view_url',
                'canva_exported_pdf_path',
                'canva_last_synced_at',
            ] as $column) {
                if (Schema::hasColumn('teacher_presentations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('canva_connections');
    }
};
