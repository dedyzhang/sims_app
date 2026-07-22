<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\GameQuiz;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Payload QR/barcode untuk siswa gabung Arena Belajar (solo token & live). */
class ArenaJoinQr
{
    public static function soloJoinUrl(Classroom $classroom, GameQuiz $quiz): ?string
    {
        $token = $quiz->access_token;
        if (! $token) {
            return null;
        }

        return route('classroom.arena.show', [
            $classroom,
            $quiz,
            'join' => 'solo',
            't'    => $token,
        ]);
    }

    public static function liveJoinUrl(Classroom $classroom, GameQuiz $quiz): ?string
    {
        $token = $quiz->access_token;
        $params = [$classroom, $quiz];
        if ($token) {
            $params['join'] = 'live';
            $params['t'] = $token;
        }

        return route('classroom.arena.live', $params);
    }

    /** Payload ringkas untuk barcode USB (Code128) — siswa join Live. */
    public static function liveBarcodePayload(GameQuiz $quiz): ?string
    {
        $token = Str::upper(trim((string) ($quiz->access_token ?? '')));

        return $token !== '' ? 'SIMS-ARENA:LIVE:'.$token : null;
    }

    /** Payload ringkas untuk barcode USB (Code128) — siswa join Solo. */
    public static function soloBarcodePayload(GameQuiz $quiz): ?string
    {
        $token = Str::upper(trim((string) ($quiz->access_token ?? '')));

        return $token !== '' ? 'SIMS-ARENA:SOLO:'.$token : null;
    }

    public static function svg(string $payload, int $size = 168): string
    {
        return QrCode::format('svg')->size($size)->margin(1)->generate($payload);
    }
}
