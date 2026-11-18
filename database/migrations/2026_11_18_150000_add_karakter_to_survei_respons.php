<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('survei_respons', 'karakter')) {
            Schema::table('survei_respons', function (Blueprint $table): void {
                $table->string('karakter', 255)->nullable()->after('kendala');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('survei_respons', 'karakter')) {
            Schema::table('survei_respons', function (Blueprint $table): void {
                $table->dropColumn('karakter');
            });
        }
    }
};
