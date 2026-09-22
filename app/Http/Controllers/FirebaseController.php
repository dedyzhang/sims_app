<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseRtdbService;

class FirebaseController extends Controller
{
    public function getToken(Request $request, FirebaseRtdbService $firebase)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! $firebase->enabled()) {
            return response()->json(['error' => 'Firebase not configured'], 503);
        }

        // Custom claims bisa ditambahkan jika perlu (misal: role admin, school_id)
        $claims = [
            'school_id' => $user->getRawOriginal('school_id') ?: 'default',
            'role'      => $user->access ?? 'siswa',
        ];

        $token = $firebase->createCustomToken($user->uuid, $claims);

        if (! $token) {
            return response()->json(['error' => 'Failed to generate token'], 500);
        }

        return response()->json(['token' => $token]);
    }
}
