<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GameQuiz extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'game_quizzes';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /** play_mode: 'bebas' (siswa pilih solo/live sendiri) | 'solo' (solo saja) | 'live' (live saja). */
    protected $fillable = [
        'classroom_id', 'created_by', 'title', 'instructions', 'mode', 'template', 'scoring_mode',
        'max_score', 'hide_scores', 'show_leaderboard', 'instant_feedback',
        'is_locked', 'access_token', 'opens_at', 'due_at', 'status', 'play_mode',
    ];

    protected function casts(): array
    {
        return [
            'max_score'         => 'integer',
            'hide_scores'       => 'boolean',
            'show_leaderboard'  => 'boolean',
            'instant_feedback'  => 'boolean',
            'is_locked'         => 'boolean',
            'opens_at'          => 'datetime',
            'due_at'            => 'datetime',
        ];
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function questions()
    {
        return $this->hasMany(GameQuestion::class, 'quiz_id', 'uuid')->orderBy('sort_order');
    }

    public function assignments()
    {
        return $this->hasMany(GameQuizAssignment::class, 'quiz_id', 'uuid');
    }

    public function assignmentFor(Classroom $classroom): ?GameQuizAssignment
    {
        return $this->assignments->firstWhere('classroom_id', $classroom->uuid)
            ?? $this->assignments()->where('classroom_id', $classroom->uuid)->first();
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** Token 4 huruf untuk kunci solo (pola sama materi/tugas Ruang Kelas). */
    public static function generateAccessToken(): string
    {
        return Str::upper(Str::random(4));
    }

    /** Solo selalu wajib token (cegah kebocoran soal). */
    public function requiresSoloToken(): bool
    {
        return $this->allowsSolo();
    }

    /** Kunci solo + pastikan ada token (generate bila kosong). */
    public function enableSoloLock(?string $token = null): void
    {
        $this->is_locked = true;
        $this->access_token = $token
            ?: ($this->access_token ?: static::generateAccessToken());
    }

    /** Kuis terbit/tertutup wajib punya token arena bila mode solo/live aktif. */
    public function ensureAccessTokenForPublished(): void
    {
        if (! in_array($this->status, ['published', 'closed'], true)) {
            return;
        }
        if (! $this->allowsSolo() && ! $this->allowsLive()) {
            return;
        }
        if (trim((string) ($this->access_token ?? '')) !== '') {
            return;
        }

        $this->forceFill([
            'is_locked'     => true,
            'access_token'  => static::generateAccessToken(),
        ])->save();
    }

    public function isOpenNow(?GameQuizAssignment $assignment = null): bool
    {
        if (!$this->isPublished()) {
            return false;
        }
        $opens = $assignment?->opens_at ?? $this->opens_at;
        $due = $assignment?->due_at ?? $this->due_at;
        if ($opens && now()->lt($opens)) {
            return false;
        }
        if ($due && now()->gt($due)) {
            return false;
        }
        if ($assignment && $assignment->status === 'closed') {
            return false;
        }
        return $this->status !== 'closed';
    }

    public function scoringModeLabel(): string
    {
        return $this->scoring_mode === 'competitive' ? 'Kompetitif' : 'Akurasi';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'Terbit',
            'closed'    => 'Ditutup',
            default     => 'Draf',
        };
    }

    public function activeLiveSession(?Classroom $classroom = null): ?GameLiveSession
    {
        $classroom = $classroom ?? $this->classroom;
        if (!$classroom) {
            return null;
        }

        return GameLiveSession::where('quiz_id', $this->uuid)
            ->where('classroom_id', $classroom->uuid)
            ->whereIn('status', ['lobby', 'question', 'reveal', 'standings'])
            ->latest()
            ->first();
    }

    public function hasActiveLiveSession(?Classroom $classroom = null): bool
    {
        return (bool) $this->activeLiveSession($classroom);
    }

    public function allowsSolo(): bool
    {
        return ($this->play_mode ?? 'bebas') !== 'live';
    }

    public function allowsLive(): bool
    {
        return ($this->play_mode ?? 'bebas') !== 'solo';
    }

    public function playModeLabel(): string
    {
        return match ($this->play_mode ?? 'bebas') {
            'solo' => 'Solo saja',
            'live' => 'Live saja',
            default => 'Bebas (solo & live)',
        };
    }
}
