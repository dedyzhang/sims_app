<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('survei_respons', 'kendala')) {
            Schema::table('survei_respons', function (Blueprint $table): void {
                $table->json('kendala')->nullable()->after('jawaban');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('survei_respons', 'kendala')) {
            Schema::table('survei_respons', function (Blueprint $table): void {
                $table->dropColumn('kendala');
            });
        }
    }
};
