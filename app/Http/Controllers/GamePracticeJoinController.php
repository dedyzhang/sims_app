<?php

namespace App\Http\Controllers;

use App\Models\GamePracticeParticipant;
use App\Models\GamePracticeSession;
use App\Models\GameQuiz;
use App\Services\GameAnswerGrader;
use App\Services\GamePracticeSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sisi TAMU (publik, TANPA login) fitur "Latihan" — scan QR/barcode di lobi → isi nama →
 * langsung ikut. Pola identitas persis Pemilihan OSIS: token per-orang di query string
 * (?g=guest_token), BUKAN cookie/session Laravel, tak pernah Auth::login(). Lihat
 * GamePracticeController utk sisi guru (host, login) yg memicu state machine yg SAMA lewat
 * GamePracticeSessionService.
 */
class GamePracticeJoinController extends Controller
{
    public function __construct(private GamePracticeSessionService $service)
    {
    }

    private function findSession(string $joinToken): GamePracticeSession
    {
        $session = GamePracticeSession::where('join_token', Str::upper($joinToken))->first();
        abort_unless($session, 404, 'Kode latihan tidak dikenali. Sesi mungkin sudah dihapus atau belum dimulai.');

        return $session;
    }

    private function findParticipant(GamePracticeSession $session, ?string $guestToken): ?GamePracticeParticipant
    {
        if (!$guestToken) {
            return null;
        }

        return GamePracticeParticipant::where('session_id', $session->uuid)
            ->where('guest_token', $guestToken)
            ->first();
    }

    public function show(Request $request, string $joinToken)
    {
        $session = $this->findSession($joinToken);
        $participant = $this->findParticipant($session, $request->query('g'));

        if (!$participant && $session->status === 'ended') {
            return view('arena-latihan.berakhir', compact('session'));
        }

        if (!$participant) {
            $quiz = GameQuiz::findOrFail($session->quiz_id);

            return view('arena-latihan.gabung', compact('session', 'joinToken', 'quiz'));
        }

        $this->service->touchParticipant($participant);
        $quiz = GameQuiz::findOrFail($session->quiz_id);

        return view('arena-latihan.main', [
            'session'     => $session,
            'quiz'        => $quiz,
            'participant' => $participant,
            'joinToken'   => $joinToken,
            'stateUrl'    => route('latihan.publik.state', $joinToken),
            'boardUrl'    => route('latihan.publik.board', $joinToken),
            'answerUrl'   => route('latihan.publik.answer', $joinToken),
        ]);
    }

    public function join(Request $request, string $joinToken)
    {
        $session = $this->findSession($joinToken);

        $data = $request->validate([
            'guest_name'   => 'required|string|max:60',
            'claimed_role' => 'nullable|in:guru,siswa',
        ], [
            'guest_name.required' => 'Nama wajib diisi.',
        ]);

        $count = GamePracticeParticipant::where('session_id', $session->uuid)->count();
        if ($count >= GamePracticeSessionService::MAX_PARTICIPANTS) {
            return back()->withErrors(['guest_name' => 'Sesi latihan ini sudah penuh ('.GamePracticeSessionService::MAX_PARTICIPANTS.' peserta). Minta guru buat sesi latihan baru.']);
        }

        $participant = GamePracticeParticipant::create([
            'session_id'   => $session->uuid,
            'guest_name'   => trim($data['guest_name']),
            'claimed_role' => $data['claimed_role'] ?? null,
            'guest_token'  => Str::random(40),
            'joined_at'    => now(),
            'last_seen_at' => now(),
        ]);

        return redirect()->route('latihan.publik.show', ['joinToken' => $joinToken, 'g' => $participant->guest_token]);
    }

    public function state(Request $request, string $joinToken)
    {
        $session = $this->findSession($joinToken);
        $participant = $this->findParticipant($session, $request->query('g'));
        abort_unless($participant, 403, 'Belum bergabung ke sesi latihan ini.');

        $quiz = GameQuiz::findOrFail($session->quiz_id);

        if ($session->isActive()) {
            $this->service->touchParticipant($participant);
            $session = $this->service->autoAdvanceIfNeeded($session, $quiz);
        }

        return response()->json([
            'ok'      => true,
            'session' => $this->service->sessionPayload($session, $quiz, $participant),
        ]);
    }

    public function leaderboard(Request $request, string $joinToken)
    {
        $session = $this->findSession($joinToken);
        $participant = $this->findParticipant($session, $request->query('g'));
        abort_unless($participant, 403, 'Belum bergabung ke sesi latihan ini.');

        return response()->json([
            'ok'          => true,
            'leaderboard' => $this->service->leaderboardRows($session),
        ]);
    }

    public function answer(Request $request, string $joinToken, GameAnswerGrader $grader)
    {
        $session = $this->findSession($joinToken);
        $participant = $this->findParticipant($session, $request->input('g'));
        abort_unless($participant, 403, 'Belum bergabung ke sesi latihan ini.');

        $data = $request->validate([
            'question_id'        => ['required', 'uuid'],
            'selected_option_id' => ['nullable', 'uuid'],
            'answer_text'        => ['nullable', 'string', 'max:10000'],
        ]);

        $quiz = GameQuiz::findOrFail($session->quiz_id);
        $result = $this->service->recordAnswer(
            $participant,
            $quiz,
            $data['question_id'],
            $data['selected_option_id'] ?? null,
            $data['answer_text'] ?? null,
            $grader
        );

        if (!$result['ok']) {
            return response()->json($result, $result['status'] === 'locked' ? 409 : 422);
        }

        // Semua yg gabung sudah jawab? Langsung maju (bukan tunggu poll 4 detik berikutnya) —
        // pola sama GameLiveController::answer().
        $fresh = $session->fresh();
        if ($fresh && $fresh->isActive()) {
            $this->service->autoAdvanceIfNeeded($fresh, $quiz);
        }

        return response()->json($result);
    }
}
