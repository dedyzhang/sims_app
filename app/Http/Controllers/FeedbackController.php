<?php

namespace App\Http\Controllers;

use App\Models\UserFeedback;
use App\Notifications\FeedbackSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class FeedbackController extends Controller
{
    private function canManage(): bool
    {
        return auth()->user()?->canAccess('manage_feedback') ?? false;
    }

    private function newFeedbackCount(): int
    {
        return UserFeedback::where('status', 'baru')->count();
    }

    public function badge()
    {
        abort_unless($this->canManage(), 403);

        return response()->json([
            'new_count' => $this->newFeedbackCount(),
        ]);
    }

    public function index(Request $request)
    {
        $canManage = $this->canManage();

        $query = UserFeedback::with(['user', 'responder'])->latest();

        if (! $canManage) {
            $query->where('user_uuid', $request->user()->uuid);
        }

        if ($canManage && $request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($canManage && $request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return view('feedback.index', [
            'feedback' => $query->paginate(15)->withQueryString(),
            'canManage' => $canManage,
            'categories' => UserFeedback::CATEGORIES,
            'statuses' => UserFeedback::STATUSES,
            'newFeedbackCount' => $canManage ? $this->newFeedbackCount() : 0,
        ]);
    }

    public function create(Request $request)
    {
        return view('feedback.create', [
            'categories' => UserFeedback::CATEGORIES,
            'contextUrl' => $request->query('from', url()->previous()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category' => 'required|string|in:'.implode(',', array_keys(UserFeedback::CATEGORIES)),
            'rating' => 'nullable|integer|min:1|max:5',
            'subject' => 'required|string|max:160',
            'message' => 'required|string|min:10|max:10000',
            'context_url' => 'nullable|string|max:2048',
        ]);

        $feedback = UserFeedback::create([
            ...$data,
            'user_uuid' => $request->user()->uuid,
            'status' => 'baru',
        ]);

        $this->notifyDevelopmentTeam($feedback);

        return redirect()
            ->route('feedback.show', $feedback)
            ->with('success', 'Masukan terkirim. Terima kasih, kami akan menindaklanjuti.');
    }

    private function notifyDevelopmentTeam(UserFeedback $feedback): void
    {
        $email = trim((string) config('feedback.development_email', ''));

        if ($email === '') {
            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new FeedbackSubmittedNotification($feedback));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim notifikasi email feedback.', [
                'feedback_uuid' => $feedback->uuid,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(Request $request, UserFeedback $feedback)
    {
        $canManage = $this->canManage();

        if (! $canManage && $feedback->user_uuid !== $request->user()->uuid) {
            abort(404);
        }

        if ($canManage && $feedback->status === 'baru') {
            $feedback->forceFill(['status' => 'dibaca'])->save();
        }

        $feedback->load(['user', 'responder']);

        return view('feedback.show', [
            'feedback' => $feedback,
            'canManage' => $canManage,
            'statuses' => UserFeedback::STATUSES,
            'newFeedbackCount' => $canManage ? $this->newFeedbackCount() : 0,
        ]);
    }

    public function respond(Request $request, UserFeedback $feedback)
    {
        abort_unless($this->canManage(), 403);

        $data = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(UserFeedback::STATUSES)),
            'admin_response' => 'nullable|string|max:10000',
        ]);

        $feedback->fill([
            'status' => $data['status'],
            'admin_response' => $data['admin_response'] ?? null,
        ]);

        if (filled($data['admin_response'] ?? null)) {
            $feedback->responded_by = $request->user()->uuid;
            $feedback->responded_at = now();
        }

        $feedback->save();

        return back()->with('success', 'Respon feedback disimpan.');
    }
}
