<?php

use App\Models\GameQuiz;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        GameQuiz::query()
            ->whereIn('status', ['published', 'closed'])
            ->where(function ($q) {
                $q->whereNull('access_token')->orWhere('access_token', '');
            })
            ->orderBy('uuid')
            ->each(function (GameQuiz $quiz) {
                $quiz->ensureAccessTokenForPublished();
            });
    }

    public function down(): void
    {
        // Tidak mengosongkan token — data siswa/guru mungkin sudah memakainya.
    }
};
