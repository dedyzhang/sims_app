<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\GamePracticeSession;
use App\Models\GameQuiz;
use App\Services\GamePracticeSessionService;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Sisi GURU (login) fitur "Latihan" — rehearsal Arena Belajar sebelum live sungguhan.
 * Beda sengaja dari GameLiveController: TIDAK mensyaratkan $quiz->isPublished() (guru harus
 * bisa uji-coba kuis yg masih draft), tapi tetap mensyaratkan allowsLive() + ada soal.
 * Identitas peserta di sisi TAMU (publik, tanpa login) ditangani terpisah oleh
 * GamePracticeJoinController — lihat itu utk alur scan-QR/isi-nama.
 */
class GamePracticeController extends Controller
{
    public function __construct(private GamePracticeSessionService $service)
    {
    }

    private function latestSession(Classroom $classroom, GameQuiz $quiz): ?GamePracticeSession
    {
        return GamePracticeSession::where('quiz_id', $quiz->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->latest()
            ->first();
    }

    public function show(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);
        abort_unless($quiz->allowsLive(), 422, 'Kuis ini disetel "Solo saja" — mode live/latihan tidak tersedia.');

        $session = $this->latestSession($classroom, $quiz);
        $joinUrl = $session ? route('latihan.publik.show', $session->join_token) : null;
        $joinQrSvg = $session ? QrCode::format('svg')->size(220)->margin(1)->generate($joinUrl) : null;
        $joinBarcodePayload = $session ? 'SIMS-ARENA:LATIHAN:'.$session->join_token : null;

        return view('arena-belajar.latihan', compact('classroom', 'quiz', 'session', 'joinUrl', 'joinQrSvg', 'joinBarcodePayload'));
    }

    public function start(Request $request, Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);
        abort_unless($quiz->questions()->exists(), 422, 'Kuis harus punya soal.');
        abort_unless($quiz->allowsLive(), 422, 'Kuis ini disetel "Solo saja" — mode live/latihan tidak tersedia.');

        $this->service->startSession($quiz, $classroom, $request->user());

        return redirect()->route('classroom.arena.latihan.show', [$classroom, $quiz])
            ->with('success', 'Sesi latihan dimulai. Bagikan QR/kode ke peserta uji coba.');
    }

    public function advance(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $session = $this->latestSession($classroom, $quiz);
        abort_unless($session && $session->isActive(), 404, 'Tidak ada sesi latihan aktif.');

        $session = $this->service->advance($session, $quiz);

        return response()->json(['ok' => true, 'session' => $this->service->sessionPayload($session, $quiz)]);
    }

    public function end(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $session = $this->latestSession($classroom, $quiz);
        if ($session) {
            $this->service->end($session);
        }

        return redirect()->route('classroom.arena.latihan.show', [$classroom, $quiz])
            ->with('success', 'Sesi latihan diakhiri.');
    }

    public function state(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $session = $this->latestSession($classroom, $quiz);
        if ($session && $session->isActive()) {
            $session = $this->service->autoAdvanceIfNeeded($session, $quiz);
        }

        return response()->json([
            'ok'      => true,
            'session' => $session ? $this->service->sessionPayload($session, $quiz) : null,
        ]);
    }

    public function leaderboard(Classroom $classroom, GameQuiz $quiz)
    {
        abort_unless($quiz->classroom_id === $classroom->uuid, 404);
        $this->authorize('manage', $quiz);

        $session = $this->latestSession($classroom, $quiz);

        return response()->json([
            'ok'          => true,
            'leaderboard' => $session ? $this->service->leaderboardRows($session) : [],
        ]);
    }
}
