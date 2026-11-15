<?php

namespace App\Support;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class FaceMatch
{
    /** Ambang default "kemungkinan wajah sama" (cosine) utk mesin Human.js. Bisa disetel via ?min=. */
    public const THRESHOLD = 0.92;

    /** Ambang "sampel wajah saling mendukung" (Human.js) — disamakan dgn supportThreshold di
     *  absensi/scan.blade.php (JS) supaya audit ini memprediksi hasil scan sungguhan, bukan angka
     *  sembarang. Jaga selaras manual kalau salah satu diubah. */
    public const SUPPORT_THRESHOLD = 0.62;

    /** Ambang setara utk InsightFace/ArcFace — TERPISAH krn skala cosine mentahnya jauh lebih
     *  rendah dari skor Human.js di atas (lihat komentar sama di absensi/scan.blade.php). PERKIRAAN
     *  AWAL dari literatur umum ArcFace, BELUM diverifikasi dgn kamera sungguhan sekolah ini. */
    public const THRESHOLD_INSIGHTFACE = 0.55;
    public const SUPPORT_THRESHOLD_INSIGHTFACE = 0.35;

    /** Ambang "kemungkinan wajah sama" yg sesuai dgn skala embedding kolom $column. */
    public static function thresholdFor(string $column): float
    {
        return $column === 'face_descriptor_if' ? self::THRESHOLD_INSIGHTFACE : self::THRESHOLD;
    }

    /** Ambang "sampel saling mendukung" yg sesuai dgn skala embedding kolom $column. */
    public static function supportThresholdFor(string $column): float
    {
        return $column === 'face_descriptor_if' ? self::SUPPORT_THRESHOLD_INSIGHTFACE : self::SUPPORT_THRESHOLD;
    }

    /** Kompresi foto wajah: lebar maksimum & kualitas WebP (lebih tinggi dari kompresi materi biasa — dipakai utk verifikasi visual). */
    private const PHOTO_MAX_WIDTH = 480;
    private const PHOTO_QUALITY = 88;

    /** Rata-rata sampel → 1 vektor ternormalisasi. */
    public static function centroid(?array $samples): ?array
    {
        $samples = array_values(array_filter((array) $samples, fn ($s) => is_array($s) && count($s) >= 64));
        if (empty($samples)) {
            return null;
        }

        $dim = count($samples[0]);
        $sum = array_fill(0, $dim, 0.0);
        foreach ($samples as $s) {
            for ($i = 0; $i < $dim; $i++) {
                $sum[$i] += (float) ($s[$i] ?? 0);
            }
        }
        $n = count($samples);
        $norm = 0.0;
        for ($i = 0; $i < $dim; $i++) {
            $sum[$i] /= $n;
            $norm += $sum[$i] * $sum[$i];
        }
        $norm = sqrt($norm);
        if ($norm > 0) {
            for ($i = 0; $i < $dim; $i++) {
                $sum[$i] /= $norm;
            }
        }

        return $sum;
    }

    /** URL foto wajah — hanya path storage faces/{uuid}_*.jpg milik pemilik. */
    public static function photoUrl(?string $v, ?string $ownerUuid = null): ?string
    {
        if (empty($v)) {
            return null;
        }
        if (str_starts_with($v, 'data:')) {
            return $v;
        }
        if (str_starts_with($v, 'http')) {
            return null;
        }
        // Terima jpg/jpeg/png/webp: foto wajah dikompres ke WebP (fallback JPEG bila driver tak
        // mendukung), jadi ekstensinya bisa bermacam — jangan batasi hanya .jpg (bikin URL null).
        if (! preg_match('/^faces\/([a-f0-9\-]+)_\d{14}\.(?:jpe?g|png|webp)$/', $v, $m)) {
            return null;
        }
        if ($ownerUuid !== null && $m[1] !== $ownerUuid) {
            return null;
        }

        return '/storage/'.$v;
    }

    /** Simpan foto (data-URL) ke storage Laravel, kembalikan PATH. */
    public static function saveFromDataUrl(?string $dataUrl, string $ownerUuid, ?string $oldPath = null): ?string
    {
        if (empty($dataUrl)) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }
        if (! str_starts_with($dataUrl, 'data:image/')) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }
        $comma = strpos($dataUrl, ',');
        if ($comma === false) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }
        $bin = base64_decode(substr($dataUrl, $comma + 1));
        if ($bin === false || @getimagesizefromstring($bin) === false) {
            return self::isValidStoredPath($oldPath, $ownerUuid) ? $oldPath : null;
        }

        // Kompres ulang di server (bukan simpan mentah dari kanvas browser): resize bila perlu
        // + re-encode WebP kualitas tinggi. Beda dari FileCompressionService (materi/dokumen) —
        // foto wajah dipakai utk verifikasi visual jadi kualitasnya sengaja dijaga lebih tinggi.
        $ext = 'jpg';
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($bin);
            if ($image->width() > self::PHOTO_MAX_WIDTH) {
                $image->scaleDown(width: self::PHOTO_MAX_WIDTH);
            }
            $bin = (string) $image->toWebp(self::PHOTO_QUALITY);
            $ext = 'webp';
        } catch (\Throwable $e) {
            // gagal re-encode (mis. driver tak tersedia) → simpan data asli apa adanya
        }

        // Foto lama dihapus DULU sebelum yg baru disimpan (bukan sebaliknya) — supaya storage
        // faces/ tak menumpuk file lama tiap kali registrasi ulang (Human.js <-> InsightFace
        // bolak-balik bisa dipanggil berkali-kali per hari saat kalibrasi).
        if ($oldPath && self::isValidStoredPath($oldPath, $ownerUuid)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = 'faces/' . $ownerUuid . '_' . now()->format('YmdHis') . '.' . $ext;
        Storage::disk('public')->put($path, $bin);

        return $path;
    }

    private static function isValidStoredPath(?string $path, string $ownerUuid): bool
    {
        if (empty($path) || str_starts_with($path, 'data:')) {
            return false;
        }

        return (bool) preg_match('/^faces\/'.preg_quote($ownerUuid, '/').'_\d{14}\.(?:jpe?g|png|webp)$/', $path);
    }

    /** Cosine similarity dua vektor ternormalisasi (= dot product). */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
        }

        return $dot;
    }

    /** Normalisasi satu vektor ke panjang 1 (dipakai sebelum cosine() bila vektor blm ternormalisasi). */
    public static function normalizeVec(array $v): array
    {
        $norm = 0.0;
        foreach ($v as $x) {
            $norm += $x * $x;
        }
        $norm = sqrt($norm);
        if ($norm <= 0) {
            return $v;
        }

        return array_map(fn ($x) => $x / $norm, $v);
    }

    /** Bersihkan cache pool vektor wajah (dipanggil saat ada siswa/guru daftar ulang wajah). */
    public static function clearPoolCache(?string $column = null): void
    {
        if ($column) {
            Cache::forget('face_enrolled_pool_' . $column);
        } else {
            Cache::forget('face_enrolled_pool_face_descriptor');
            Cache::forget('face_enrolled_pool_face_descriptor_if');
        }
    }

    /** Ambil dan cache pool vektor wajah seluruh siswa & guru terdaftar. */
    public static function getEnrolledPool(string $column): array
    {
        return Cache::remember('face_enrolled_pool_' . $column, 600, function () use ($column) {
            $pool = [];
            $siswaCols = ['uuid', 'nama', 'id_kelas', $column];
            foreach (Siswa::whereNotNull($column)->get($siswaCols) as $s) {
                $raw = (array) $s->{$column};
                $samples = [];
                foreach ($raw as $sample) {
                    if (is_array($sample) && count($sample) >= 64) {
                        $samples[] = self::normalizeVec(array_map('floatval', $sample));
                    }
                }
                if (!empty($samples)) {
                    $pool[] = [
                        'uuid'     => $s->uuid,
                        'nama'     => $s->nama,
                        'tipe'     => 'siswa',
                        'id_kelas' => $s->id_kelas,
                        'samples'  => $samples,
                    ];
                }
            }

            $guruCols = ['uuid', 'nama', $column];
            foreach (Guru::whereNotNull($column)->get($guruCols) as $g) {
                $raw = (array) $g->{$column};
                $samples = [];
                foreach ($raw as $sample) {
                    if (is_array($sample) && count($sample) >= 64) {
                        $samples[] = self::normalizeVec(array_map('floatval', $sample));
                    }
                }
                if (!empty($samples)) {
                    $pool[] = [
                        'uuid'     => $g->uuid,
                        'nama'     => $g->nama,
                        'tipe'     => 'guru',
                        'id_kelas' => null,
                        'samples'  => $samples,
                    ];
                }
            }

            return $pool;
        });
    }

    /**
     * Cari kecocokan wajah terbaik dari sebuah embedding wajah live terhadap seluruh siswa/guru
     * terdaftar secara server-side.
     *
     * @param array<int, float> $embedding Vektor embedding dari wajah di depan kamera
     * @param string|null $column Kolom descriptor ('face_descriptor' atau 'face_descriptor_if')
     * @param string|null $idKelas Filter opsional id_kelas (jika kios dibatasi kelas tertentu)
     * @return array{matched: bool, uuid?: string, nama?: string, tipe?: string, id_kelas?: ?string, score: float, gap?: float, reason?: string}
     */
    public static function findCandidate(array $embedding, ?string $column = null, ?string $idKelas = null): array
    {
        $column ??= FaceEngine::kolomDescriptor();
        $isIf = $column === 'face_descriptor_if';

        $threshold          = $isIf ? 0.38 : 0.66;
        $confidentThreshold = $isIf ? 0.55 : 0.80;
        $supportThreshold   = $isIf ? 0.35 : 0.62;
        $margin             = $isIf ? 0.05 : 0.08;
        $minSampleSupport   = 2;

        $queryVec = self::normalizeVec(array_map('floatval', $embedding));
        $pool = self::getEnrolledPool($column);

        $bestPerson = null;
        $bestSim = 0.0;
        $secondSim = 0.0;
        $bestMatch = null;

        foreach ($pool as $p) {
            if ($idKelas && $p['tipe'] === 'siswa' && $p['id_kelas'] !== $idKelas) {
                continue;
            }

            $sims = [];
            foreach ($p['samples'] as $samp) {
                $sims[] = self::cosine($queryVec, $samp);
            }
            rsort($sims);

            if (empty($sims)) {
                continue;
            }

            $top1 = $sims[0] ?? 0.0;
            $support = count(array_filter($sims, fn ($v) => $v >= $supportThreshold));
            $match = [
                'score'       => $top1,
                'top1'        => $top1,
                'support'     => $support,
                'sampleCount' => count($sims),
            ];

            if ($match['score'] > $bestSim) {
                $secondSim = $bestSim;
                $bestSim = $match['score'];
                $bestPerson = $p;
                $bestMatch = $match;
            } elseif ($match['score'] > $secondSim) {
                $secondSim = $match['score'];
            }
        }

        if (!$bestPerson || !$bestMatch) {
            return [
                'matched' => false,
                'score'   => 0.0,
                'reason'  => 'no_candidates',
            ];
        }

        $gap = $bestSim - $secondSim;
        $clearGap = ($gap >= $margin) || ($bestSim >= $confidentThreshold);
        $sampleAgreement = ($bestMatch['sampleCount'] <= 1)
            ? ($bestMatch['top1'] >= $threshold)
            : ($bestMatch['support'] >= $minSampleSupport || $bestMatch['top1'] >= $confidentThreshold);

        $strongMatch = ($bestSim >= $threshold) && $clearGap && $sampleAgreement;

        if ($strongMatch) {
            return [
                'matched'  => true,
                'uuid'     => $bestPerson['uuid'],
                'nama'     => $bestPerson['nama'],
                'tipe'     => $bestPerson['tipe'],
                'id_kelas' => $bestPerson['id_kelas'],
                'score'    => $bestSim,
                'gap'      => $gap,
            ];
        }

        return [
            'matched'      => false,
            'score'        => $bestSim,
            'second_score' => $secondSim,
            'gap'          => $gap,
            'reason'       => $bestSim < $threshold ? 'low_score' : (!$clearGap ? 'small_margin' : 'low_support'),
        ];
    }

    /**
     * Semua orang terdaftar wajah: [{uuid,nama,tipe,centroid,foto?,id_kelas}] (id_kelas null
     * utk guru). $column: kolom descriptor yg dibaca — 'face_descriptor' (Human.js, default)
     * atau 'face_descriptor_if' (InsightFace) — lihat App\Support\FaceEngine::kolomDescriptor().
     */
    public static function allRegistered(?string $excludeUuid = null, bool $withPhoto = false, string $column = 'face_descriptor'): array
    {
        $cols = ['uuid', 'nama', $column];
        if ($withPhoto) {
            $cols[] = 'face_photo';
        }
        $siswaCols = array_merge($cols, ['id_kelas']);

        $out = [];
        foreach (Siswa::whereNotNull($column)->get($siswaCols) as $s) {
            if ($s->uuid === $excludeUuid) {
                continue;
            }
            $c = self::centroid($s->{$column});
            if ($c) {
                $out[] = ['uuid' => $s->uuid, 'nama' => $s->nama, 'tipe' => 'siswa', 'centroid' => $c, 'foto' => $withPhoto ? self::photoUrl($s->face_photo, $s->uuid) : null, 'id_kelas' => $s->id_kelas];
            }
        }
        foreach (Guru::whereNotNull($column)->get($cols) as $g) {
            if ($g->uuid === $excludeUuid) {
                continue;
            }
            $c = self::centroid($g->{$column});
            if ($c) {
                $out[] = ['uuid' => $g->uuid, 'nama' => $g->nama, 'tipe' => 'guru', 'centroid' => $c, 'foto' => $withPhoto ? self::photoUrl($g->face_photo, $g->uuid) : null];
            }
        }

        return $out;
    }

    /**
     * Cari kemiripan tertinggi sebuah wajah baru dengan orang lain yang sudah terdaftar (kolom &
     * mesin sama — lihat allRegistered()). $column WAJIB diisi eksplisit (tak ada default) —
     * dulu default ke 'face_descriptor' yg jadi jebakan: pemanggil baru yg lupa mengisinya akan
     * diam2 membandingkan ke mesin/kolom yg salah tanpa error apa pun.
     */
    public static function bestMatch(?array $newDescriptors, ?string $excludeUuid, string $column): ?array
    {
        $c = self::centroid($newDescriptors);
        if (! $c) {
            return null;
        }

        $best = null;
        foreach (self::allRegistered($excludeUuid, false, $column) as $p) {
            $sim = self::cosine($c, $p['centroid']);
            if (! $best || $sim > $best['similarity']) {
                $best = ['uuid' => $p['uuid'], 'nama' => $p['nama'], 'tipe' => $p['tipe'], 'similarity' => $sim];
            }
        }

        return $best;
    }

    /** Semua pasangan wajah mirip (>= $min), urut menurun, utk kolom/mesin $column (default: mesin aktif). */
    public static function duplicatePairs(float $min, int $limit = 60, ?string $column = null): array
    {
        $column ??= FaceEngine::kolomDescriptor();
        $people = self::allRegistered(null, true, $column);
        $n = count($people);
        $pairs = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $sim = self::cosine($people[$i]['centroid'], $people[$j]['centroid']);
                if ($sim >= $min) {
                    $pairs[] = ['a' => $people[$i], 'b' => $people[$j], 'similarity' => $sim];
                }
            }
        }
        usort($pairs, fn ($x, $y) => $y['similarity'] <=> $x['similarity']);

        return array_slice($pairs, 0, $limit);
    }

    /**
     * Audit wajah yg berpotensi SULIT/TAK TERDETEKSI saat scan — beda dari duplicatePairs() yg
     * mencari wajah ganda (mirip orang lain). Ini memeriksa data & KONSISTENSI internal sampel
     * wajah milik satu orang: kalau sampelnya rusak/kosong, atau sampel2 miliknya sendiri tak
     * saling mirip (di bawah supportThresholdFor($column) — ambang "support" yg sama dipakai
     * scan.blade.php), live scan besar kemungkinan gagal mengenalinya krn gate
     * hasEnoughSampleAgreement() di JS tak akan terpenuhi. Return diurutkan: critical dulu, baru
     * warning. $column: kolom descriptor yg diaudit (default: mesin aktif — Setting → Mesin
     * Pengenalan Wajah).
     *
     * @return array<int, array{uuid:string,nama:string,tipe:string,foto:?string,id_kelas:?string,level:string,issue:string,detail:string}>
     */
    public static function unreadableFaces(?string $column = null): array
    {
        $column ??= FaceEngine::kolomDescriptor();
        $supportThreshold = self::supportThresholdFor($column);
        $out = [];

        $scan = function (string $modelClass, string $tipe) use (&$out, $column, $supportThreshold): void {
            // Tanpa daftar kolom eksplisit — Guru tak punya kolom id_kelas spt Siswa, jadi
            // $p->id_kelas nanti aman bernilai null utk guru lewat magic getter Eloquent.
            foreach ($modelClass::whereNotNull($column)->get() as $p) {
                $foto = self::photoUrl($p->face_photo, $p->uuid);
                $idKelas = $p->id_kelas ?? null;
                $samples = array_values(array_filter((array) $p->{$column}, fn ($s) => is_array($s) && count($s) >= 64));
                $samples = array_map(fn ($s) => self::normalizeVec(array_map('floatval', $s)), $samples);
                $n = count($samples);

                if ($n === 0) {
                    $out[] = [
                        'uuid' => $p->uuid, 'nama' => $p->nama, 'tipe' => $tipe, 'foto' => $foto, 'id_kelas' => $idKelas,
                        'level' => 'critical', 'issue' => 'Data wajah kosong/rusak',
                        'detail' => 'Tidak ada sampel wajah yang valid tersimpan — wajah ini tidak akan pernah dikenali saat scan. Perlu daftar ulang wajah.',
                    ];

                    continue;
                }

                if ($n === 1) {
                    $out[] = [
                        'uuid' => $p->uuid, 'nama' => $p->nama, 'tipe' => $tipe, 'foto' => $foto, 'id_kelas' => $idKelas,
                        'level' => 'warning', 'issue' => 'Hanya 1 sampel wajah',
                        'detail' => 'Hanya ada 1 sudut wajah terdaftar — deteksi kurang stabil. Disarankan daftar ulang wajah (3 posisi).',
                    ];

                    continue;
                }

                // Berapa sampel yg didukung oleh minimal 1 sampel lain miliknya sendiri (skor
                // tertinggi antar-sampel dipakai sbg indikator, bukan cuma pasangan pertama).
                $agreeing = 0;
                $bestPair = 0.0;
                for ($i = 0; $i < $n; $i++) {
                    $supported = false;
                    for ($j = 0; $j < $n; $j++) {
                        if ($i === $j) {
                            continue;
                        }
                        $sim = self::cosine($samples[$i], $samples[$j]);
                        if ($sim > $bestPair) {
                            $bestPair = $sim;
                        }
                        if ($sim >= $supportThreshold) {
                            $supported = true;
                        }
                    }
                    if ($supported) {
                        $agreeing++;
                    }
                }

                if ($agreeing === 0) {
                    $out[] = [
                        'uuid' => $p->uuid, 'nama' => $p->nama, 'tipe' => $tipe, 'foto' => $foto, 'id_kelas' => $idKelas,
                        'level' => 'critical', 'issue' => 'Sampel wajah tidak konsisten',
                        'detail' => 'Sampel wajah miliknya sendiri tidak saling mirip (kemiripan tertinggi antar-sampel hanya '.round($bestPair * 100).'%) — kemungkinan foto keliru/tercampur saat registrasi. Wajah kemungkinan besar TIDAK terdeteksi saat scan. Perlu daftar ulang wajah.',
                    ];
                } elseif ($agreeing < $n) {
                    $out[] = [
                        'uuid' => $p->uuid, 'nama' => $p->nama, 'tipe' => $tipe, 'foto' => $foto, 'id_kelas' => $idKelas,
                        'level' => 'warning', 'issue' => 'Konsistensi sampel rendah',
                        'detail' => ($n - $agreeing).' dari '.$n.' sampel wajah kurang konsisten dengan sampel lainnya — mungkin sulit terdeteksi di kondisi cahaya/sudut tertentu. Disarankan daftar ulang wajah.',
                    ];
                }
            }
        };

        $scan(Siswa::class, 'siswa');
        $scan(Guru::class, 'guru');

        usort($out, fn ($a, $b) => ($a['level'] === 'critical' ? 0 : 1) <=> ($b['level'] === 'critical' ? 0 : 1));

        return $out;
    }
}
