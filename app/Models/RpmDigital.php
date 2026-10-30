<?php

namespace App\Models;

use App\Support\LearningDocument;
use App\Support\SchoolLetterhead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Dokumen RPM Digital (Rencana Pembelajaran Mendalam).
 * Menyimpan dokumen RPM tersimpan hasil generate AI langsung,
 * upload PDF materi pelajaran, atau import dari riwayat Asisten Guru.
 */
class RpmDigital extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rpm_digitals';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'school_id',
        'user_uuid',
        'title',
        'mapel',
        'kelas',
        'semester',
        'alokasi_waktu',
        'topik',
        'source_type',
        'source_file_name',
        'content',
        'interactive_state',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'interactive_state' => 'array',
        ];
    }

    /** Relasi ke pembuat dokumen (User). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Parse konten teks menjadi struktur dokumen formal RPM (Kop, DPL, Desain, dsb).
     *
     * @return array
     */
    public function parsedDocument(): array
    {
        return LearningDocument::parse(SchoolLetterhead::ensurePrefix($this->content ?? ''));
    }

    /** Label sumber pembuatan dokumen yang ramah pengguna. */
    public function sourceTypeLabel(): string
    {
        return match ($this->source_type) {
            'ai_direct' => 'Murni AI',
            'pdf_upload' => 'Upload PDF',
            'asisten_guru' => 'Asisten Guru',
            default => 'Manual',
        };
    }

    /** Badge warna untuk tipe sumber. */
    public function sourceTypeBadgeClass(): string
    {
        return match ($this->source_type) {
            'ai_direct' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            'pdf_upload' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            'asisten_guru' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
            default => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-900/40 dark:text-slate-300 dark:border-slate-800',
        };
    }

    /** Scope filter pencarian teks. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $clean = trim($term);

        return $query->where(function (Builder $q) use ($clean) {
            $q->where('title', 'like', "%{$clean}%")
                ->orWhere('topik', 'like', "%{$clean}%")
                ->orWhere('mapel', 'like', "%{$clean}%")
                ->orWhere('kelas', 'like', "%{$clean}%");
        });
    }

    /** Scope dokumen milik user tertentu. */
    public function scopeForUser(Builder $query, string $userUuid): Builder
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * Ekstrak rincian identitas untuk grid 6 kotak (Satuan Pendidikan, Mapel, Kelas, Alokasi, Model, Penyusun).
     *
     * @return array<string, string>
     */
    public function extractIdentity(): array
    {
        $doc = $this->parsedDocument();
        $identityMap = [];
        foreach ($doc['identity'] ?? [] as $row) {
            $identityMap[$row['label']] = $row['value'];
        }

        $modelName = 'Discovery Learning';
        foreach ($doc['desain'] ?? [] as $d) {
            if (str_contains($d['label'] ?? '', 'Praktik Pedagogis')) {
                $lines = $d['lines'] ?? [];
                if (! empty($lines)) {
                    $modelName = $lines[0];
                }
                break;
            }
        }

        return [
            'satuan_pendidikan' => $identityMap['SEKOLAH'] ?? \App\Support\SchoolLetterhead::schoolName(),
            'mapel' => $identityMap['MATA PELAJARAN'] ?? ($this->mapel ?: 'Pendidikan Agama dan Budi Pekerti'),
            'kelas' => $identityMap['KELAS / SEMESTER'] ?? ($this->kelas ? 'D / '.$this->kelas : 'D / VIII'),
            'alokasi_waktu' => $identityMap['ALOKASI WAKTU'] ?? ($this->alokasi_waktu ?: '2 × 40 menit (1 pertemuan)'),
            'model' => $modelName,
            'penyusun' => $identityMap['NAMA GURU'] ?? ($this->user?->name ?: 'Nama Guru Mapel, S.Pd.'),
        ];
    }

    /**
     * Ekstrak soal-soal pilihan ganda dari asesmen awal / lampiran untuk kuis interaktif.
     *
     * @return array<int, array{number: int, question: string, options: array<string, string>, correct?: string}>
     */
    public function extractQuizQuestions(): array
    {
        $doc = $this->parsedDocument();
        $questions = [];

        $searchTexts = [];
        foreach ($doc['lampiran'] ?? [] as $lamp) {
            if (str_contains(strtoupper($lamp['heading'] ?? ''), 'AWAL') || str_contains(strtoupper($lamp['heading'] ?? ''), 'LAMPIRAN 1')) {
                foreach ($lamp['blocks'] ?? [] as $b) {
                    if ($b['type'] === 'text') {
                        $searchTexts = array_merge($searchTexts, $b['lines'] ?? []);
                    }
                }
            }
        }

        if (empty($searchTexts)) {
            $searchTexts = explode("\n", $doc['text'] ?? '');
        }

        $currentQ = null;
        foreach ($searchTexts as $line) {
            $trimmed = trim($line);
            if (preg_match('/^(\d+)[\.\)]\s*(.+)$/u', $trimmed, $m)) {
                if ($currentQ !== null && count($currentQ['options']) >= 2) {
                    $questions[] = $currentQ;
                }
                $currentQ = [
                    'number' => (int) $m[1],
                    'question' => trim($m[2]),
                    'options' => [],
                    'correct' => 'a', // default acuan kunci
                ];
            } elseif ($currentQ !== null && preg_match('/^([a-dA-D])[\.\)]\s*(.+)$/u', $trimmed, $m)) {
                $currentQ['options'][strtolower($m[1])] = trim($m[2]);
            } elseif ($currentQ !== null && empty($currentQ['options']) && $trimmed !== '' && ! str_starts_with($trimmed, 'LAMPIRAN')) {
                $currentQ['question'] .= ' '.$trimmed;
            }
        }
        if ($currentQ !== null && count($currentQ['options']) >= 2) {
            $questions[] = $currentQ;
        }

        // Pertanyaan acuan bila belum terdefinisi di teks
        if (empty($questions)) {
            $topik = $this->topik ?: $this->title;
            $questions = [
                [
                    'number' => 1,
                    'question' => 'Apa tujuan utama dari memahami materi pada topik '.$topik.'?',
                    'options' => [
                        'a' => 'Memahami konsep dasar dan penerapannya dalam kehidupan sehari-hari',
                        'b' => 'Menghafal seluruh definisi tanpa perlu memahami maknanya',
                        'c' => 'Hanya untuk memenuhi kelengkapan administratif kelas',
                        'd' => 'Mengetahui istilah teoritis tanpa kaitan kontekstual',
                    ],
                    'correct' => 'a',
                ],
                [
                    'number' => 2,
                    'question' => 'Manakah pilar Pembelajaran Mendalam yang paling menekankan refleksi dan kesadaran diri?',
                    'options' => [
                        'a' => 'Berkesadaran (Mindful)',
                        'b' => 'Mekanistik',
                        'c' => 'Formalitas semata',
                        'd' => 'Kompetisi individual',
                    ],
                    'correct' => 'a',
                ],
                [
                    'number' => 3,
                    'question' => 'Bagaimana wujud penerapan dimensi kolaborasi dalam kegiatan belajar ini?',
                    'options' => [
                        'a' => 'Bekerja bersama kelompok untuk mendiskusikan dan memecahkan masalah kontekstual',
                        'b' => 'Mengerjakan tugas secara tertutup dari teman sekelas',
                        'c' => 'Menyerahkan seluruh tugas kepada satu orang anggota',
                        'd' => 'Menghindari interaksi selama proses belajar mengajar',
                    ],
                    'correct' => 'a',
                ],
            ];
        }

        return $questions;
    }
}
