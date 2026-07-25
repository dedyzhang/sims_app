<?php

namespace App\Support;

use App\Models\GameQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Token gabung Arena (solo & live) — session unlock terpusat. */
class ArenaAccessToken
{
    public static function sessionKey(GameQuiz $quiz, string $scope = 'join'): string
    {
        return 'arena_'.$scope.'_unlock.'.$quiz->uuid;
    }

    /** Token wajib bila kuis terbit punya access_token dan mode siswa aktif. */
    public static function requiresToken(GameQuiz $quiz): bool
    {
        if (! $quiz->isPublished() && ! $quiz->isClosed()) {
            return false;
        }

        $token = trim((string) ($quiz->access_token ?? ''));

        return $token !== '' && ($quiz->allowsSolo() || $quiz->allowsLive());
    }

    public static function hasUnlock(GameQuiz $quiz): bool
    {
        if (! self::requiresToken($quiz)) {
            return true;
        }

        $expected = Str::upper(trim((string) $quiz->access_token));
        foreach (['join', 'solo', 'live'] as $scope) {
            $stored = session(self::sessionKey($quiz, $scope));
            if (is_string($stored) && $stored !== '' && hash_equals($expected, Str::upper($stored))) {
                return true;
            }
        }

        return false;
    }

    public static function grantUnlock(GameQuiz $quiz): void
    {
        $token = (string) $quiz->access_token;
        session([
            self::sessionKey($quiz, 'join') => $token,
            self::sessionKey($quiz, 'solo') => $token,
            self::sessionKey($quiz, 'live') => $token,
        ]);
    }

    /** @return string|null Pesan error jika token salah; null jika OK. */
    public static function validateAndGrant(Request $request, GameQuiz $quiz): ?string
    {
        if (! self::requiresToken($quiz) || self::hasUnlock($quiz)) {
            return null;
        }

        $token = Str::upper(trim((string) (
            $request->input('join_token')
            ?: $request->input('solo_token')
            ?: $request->input('live_token')
            ?: ($request->query('t') && in_array($request->query('join'), ['solo', 'live'], true)
                ? $request->query('t')
                : '')
        )));

        if ($token === '') {
            return null;
        }

        $expected = Str::upper(trim((string) ($quiz->access_token ?? '')));

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return $expected === ''
                ? 'Token belum diset guru. Minta guru generate token di panel experience.'
                : 'Token salah atau belum diisi. Minta token ke guru mapel.';
        }

        self::grantUnlock($quiz);

        return null;
    }
}
