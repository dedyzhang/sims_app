<?php

namespace App\Services;

use App\Models\CanvaConnection;
use App\Models\Setting;
use App\Models\TeacherPresentation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Gateway Canva Connect (Public) — OAuth PKCE per-guru, jalur gratis Canva Pendidikan.
 * Gate belajar.id memakai email yang disimpan di SIMS (canva_belajar_id), karena
 * API Connect Public tidak mengembalikan email profil.
 */
class CanvaConnectService
{
    public function configured(): bool
    {
        return trim((string) config('services.canva.client_id')) !== ''
            && trim((string) config('services.canva.client_secret')) !== '';
    }

    public function featureEnabled(): bool
    {
        return (Setting::get('canva_connect_aktif', '1') ?? '1') === '1';
    }

    public function allowedEmailSuffix(): string
    {
        $fromSetting = trim((string) (Setting::get('canva_allowed_email_suffix') ?? ''));
        $suffix = $fromSetting !== ''
            ? $this->normalizeSuffix($fromSetting)
            : $this->normalizeSuffix((string) config('services.canva.allowed_email_suffix', '.belajar.id'));

        return $this->isValidBelajarIdSuffix($suffix) ? $suffix : '.belajar.id';
    }

    public function isValidBelajarIdSuffix(string $suffix): bool
    {
        $suffix = $this->normalizeSuffix($suffix);
        if ($suffix === '' || strlen($suffix) > 80) {
            return false;
        }

        // Hanya domain belajar.id — tolak .com, gmail.com, dsb.
        return str_ends_with($suffix, '.belajar.id');
    }

    public function isBelajarIdEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $suffix = $this->allowedEmailSuffix();

        if (str_starts_with($suffix, '@')) {
            return str_ends_with($email, $suffix);
        }

        if (str_starts_with($suffix, '.')) {
            return str_ends_with($email, $suffix);
        }

        return str_ends_with($email, '@'.$suffix) || str_ends_with($email, '.'.$suffix);
    }

    public function assertBelajarIdEmail(string $email): void
    {
        if (! $this->isBelajarIdEmail($email)) {
            $suffix = $this->allowedEmailSuffix();
            throw new RuntimeException(
                "Hubungkan ulang dengan akun belajar.id sekolah (email berakhiran {$suffix}), bukan email pribadi."
            );
        }
    }

    public function assertUserBelajarIdReady(User $user): string
    {
        $email = strtolower(trim((string) ($user->canva_belajar_id ?? '')));
        if ($email === '') {
            throw new RuntimeException(
                'Isi email belajar.id Anda di Asisten Guru sebelum menghubungkan Canva.'
            );
        }
        $this->assertBelajarIdEmail($email);

        return $email;
    }

    /** @return array{url:string,state:string,code_verifier:string} */
    public function beginAuthorization(): array
    {
        $this->ensureConfigured();

        $state = Str::random(40);
        $codeVerifier = $this->generateCodeVerifier();
        $challenge = $this->codeChallenge($codeVerifier);
        $scopes = implode(' ', (array) config('services.canva.scopes', []));

        $query = http_build_query([
            'code_challenge' => $challenge,
            'code_challenge_method' => 's256',
            'response_type' => 'code',
            'client_id' => config('services.canva.client_id'),
            'redirect_uri' => config('services.canva.redirect_uri'),
            'scope' => $scopes,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => rtrim((string) config('services.canva.auth_url'), '?').'?'.$query,
            'state' => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    public function completeAuthorization(User $user, string $code, string $codeVerifier): CanvaConnection
    {
        $this->ensureConfigured();
        if (! $this->featureEnabled()) {
            throw new RuntimeException('Integrasi Canva dimatikan di Pengaturan Sistem.');
        }

        $gateEmail = $this->assertUserBelajarIdReady($user);

        $token = $this->exchangeAuthorizationCode($code, $codeVerifier);
        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Canva tidak mengembalikan access token.');
        }

        $profile = $this->fetchUserProfile($accessToken);

        return CanvaConnection::updateOrCreate(
            ['user_uuid' => $user->uuid],
            [
                'canva_user_id' => $profile['id'] ?? null,
                'email' => $gateEmail,
                'display_name' => $profile['display_name'] ?? null,
                'access_token' => $accessToken,
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : null,
                'scopes' => is_array($token['scope'] ?? null)
                    ? implode(' ', $token['scope'])
                    : (string) ($token['scope'] ?? implode(' ', (array) config('services.canva.scopes', []))),
                'connected_at' => now(),
            ],
        );
    }

    public function disconnect(User $user): void
    {
        $connection = $this->connectionFor($user);
        if ($connection) {
            $this->revokeTokensBestEffort($connection);
            $connection->delete();
        }
    }

    public function connectionFor(User $user): ?CanvaConnection
    {
        return CanvaConnection::where('user_uuid', $user->uuid)->first();
    }

    public function statusPayload(User $user): array
    {
        $connection = $this->connectionFor($user);

        return [
            'configured' => $this->configured(),
            'feature_enabled' => $this->featureEnabled(),
            'connected' => $connection !== null,
            'email_masked' => $connection?->emailMasked(),
            'display_name' => $connection?->display_name,
            'allowed_email_suffix' => $this->allowedEmailSuffix(),
            'belajar_hint' => $user->canva_belajar_id,
            'connected_at' => $connection?->connected_at?->toIso8601String(),
        ];
    }

    public function ensureAccessToken(CanvaConnection $connection): string
    {
        if (! $connection->isExpired()) {
            $token = $connection->plainAccessToken();
            if ($token !== '') {
                return $token;
            }
        }

        $refresh = $connection->plainRefreshToken();
        if (! $refresh) {
            throw new RuntimeException('Sesi Canva kedaluwarsa. Hubungkan ulang akun belajar.id.');
        }

        $token = $this->refreshAccessToken($refresh);
        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Gagal memperbarui sesi Canva. Hubungkan ulang akun belajar.id.');
        }

        $connection->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => $token['refresh_token'] ?? $refresh,
            'token_expires_at' => isset($token['expires_in'])
                ? now()->addSeconds((int) $token['expires_in'])
                : $connection->token_expires_at,
        ])->save();

        return $accessToken;
    }

    /**
     * @return array{id:string,title:?string,edit_url:?string,view_url:?string,urls:array}
     */
    public function createPresentationDesign(User $user, string $title): array
    {
        $connection = $this->requireConnection($user);
        $accessToken = $this->ensureAccessToken($connection);

        $response = Http::timeout((int) config('services.canva.timeout', 30))
            ->withToken($accessToken)
            ->acceptJson()
            ->post(rtrim((string) config('services.canva.api_base'), '/').'/designs', [
                'design_type' => [
                    'type' => 'preset',
                    'name' => 'presentation',
                ],
                'title' => Str::limit($title, 255, ''),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeApiError($response->status(), $response->json()));
        }

        $design = $response->json('design') ?? [];
        $id = (string) ($design['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Canva tidak mengembalikan ID desain.');
        }

        $editUrl = $this->sanitizeCanvaUrl($design['urls']['edit_url'] ?? null);
        $viewUrl = $this->sanitizeCanvaUrl($design['urls']['view_url'] ?? null);

        return [
            'id' => $id,
            'title' => $design['title'] ?? $title,
            'edit_url' => $editUrl,
            'view_url' => $viewUrl,
            'urls' => array_filter([
                'edit_url' => $editUrl,
                'view_url' => $viewUrl,
            ]),
        ];
    }

    /** @return list<array{id:string,title:?string,updated_at:?int}> */
    public function listDesigns(User $user, int $limit = 20): array
    {
        $connection = $this->requireConnection($user);
        $accessToken = $this->ensureAccessToken($connection);

        $response = Http::timeout((int) config('services.canva.timeout', 30))
            ->withToken($accessToken)
            ->acceptJson()
            ->get(rtrim((string) config('services.canva.api_base'), '/').'/designs', [
                'limit' => max(1, min(50, $limit)),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeApiError($response->status(), $response->json()));
        }

        $items = [];
        foreach ($response->json('items') ?? $response->json('designs') ?? [] as $row) {
            $design = $row['design'] ?? $row;
            $items[] = [
                'id' => (string) ($design['id'] ?? ''),
                'title' => $design['title'] ?? null,
                'updated_at' => isset($design['updated_at']) ? (int) $design['updated_at'] : null,
            ];
        }

        return array_values(array_filter($items, fn (array $item) => $item['id'] !== ''));
    }

    /**
     * @return array{id:string,title:?string,edit_url:?string,view_url:?string}
     */
    public function getDesign(User $user, string $designId): array
    {
        $connection = $this->requireConnection($user);
        $accessToken = $this->ensureAccessToken($connection);

        $response = Http::timeout((int) config('services.canva.timeout', 30))
            ->withToken($accessToken)
            ->acceptJson()
            ->get(rtrim((string) config('services.canva.api_base'), '/').'/designs/'.$designId);

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeApiError($response->status(), $response->json()));
        }

        $design = $response->json('design') ?? [];

        return [
            'id' => (string) ($design['id'] ?? $designId),
            'title' => $design['title'] ?? null,
            'edit_url' => $this->sanitizeCanvaUrl($design['urls']['edit_url'] ?? null),
            'view_url' => $this->sanitizeCanvaUrl($design['urls']['view_url'] ?? null),
        ];
    }

    /**
     * Export desain ke PDF (atau ZIP multi-halaman) dan simpan di disk privat.
     *
     * @return array{path:string,url:string,job_id:?string,pages:int}
     */
    public function exportPresentationPdf(User $user, TeacherPresentation $presentation): array
    {
        $designId = trim((string) $presentation->canva_design_id);
        if ($designId === '') {
            throw new RuntimeException('Presentasi belum tertaut ke desain Canva.');
        }

        $connection = $this->requireConnection($user);
        $accessToken = $this->ensureAccessToken($connection);
        $base = rtrim((string) config('services.canva.api_base'), '/');

        $create = Http::timeout((int) config('services.canva.timeout', 30))
            ->withToken($accessToken)
            ->acceptJson()
            ->post($base.'/exports', [
                'design_id' => $designId,
                'format' => [
                    'type' => 'pdf',
                    'export_quality' => 'regular',
                ],
            ]);

        if ($create->failed()) {
            throw new RuntimeException($this->normalizeApiError($create->status(), $create->json()));
        }

        $job = $create->json('job') ?? $create->json();
        $jobId = (string) ($job['id'] ?? '');
        if ($jobId === '') {
            throw new RuntimeException('Canva tidak mengembalikan ID job export.');
        }

        $deadline = now()->addSeconds((int) config('services.canva.export_timeout', 90));
        $downloadUrls = [];
        $pollMicros = max(0, (int) config('services.canva.export_poll_micros', 800_000));

        while (now()->lt($deadline)) {
            if ($pollMicros > 0) {
                usleep($pollMicros);
            }

            $poll = Http::timeout((int) config('services.canva.timeout', 30))
                ->withToken($accessToken)
                ->acceptJson()
                ->get($base.'/exports/'.$jobId);

            if ($poll->failed()) {
                throw new RuntimeException($this->normalizeApiError($poll->status(), $poll->json()));
            }

            $job = $poll->json('job') ?? $poll->json();
            $status = strtolower((string) ($job['status'] ?? ''));

            if (in_array($status, ['success', 'completed'], true)) {
                $urls = $job['urls'] ?? $job['result']['url'] ?? null;
                if (is_string($urls) && $urls !== '') {
                    $downloadUrls = [$urls];
                } elseif (is_array($urls)) {
                    $downloadUrls = array_values(array_filter(array_map('strval', $urls)));
                }
                break;
            }

            if (in_array($status, ['failed', 'error'], true)) {
                $detail = (string) ($job['error']['message'] ?? $job['error']['code'] ?? '');
                throw new RuntimeException(
                    $detail !== ''
                        ? 'Export Canva gagal: '.$detail
                        : 'Export Canva gagal. Coba lagi beberapa saat.'
                );
            }
        }

        if ($downloadUrls === []) {
            throw new RuntimeException('Export Canva masih diproses terlalu lama. Coba lagi.');
        }

        $binaries = [];
        foreach ($downloadUrls as $downloadUrl) {
            $binaries[] = $this->downloadExportBinary($downloadUrl);
        }

        $disk = (string) config('services.canva.export_disk', 'local');
        $dir = trim((string) config('services.canva.export_directory', 'canva-exports'), '/');
        $userDir = $dir.'/'.$user->uuid;
        $stamp = now()->format('YmdHis');

        if (count($binaries) === 1) {
            $path = $userDir.'/'.$presentation->uuid.'-'.$stamp.'.pdf';
            Storage::disk($disk)->put($path, $binaries[0]);
        } else {
            $path = $userDir.'/'.$presentation->uuid.'-'.$stamp.'.zip';
            $zipBinary = $this->zipPdfPages($binaries);
            Storage::disk($disk)->put($path, $zipBinary);
        }

        $presentation->forceFill([
            'canva_exported_pdf_path' => $path,
            'canva_last_synced_at' => now(),
        ])->save();

        return [
            'path' => $path,
            'url' => route('ai.teacher.presentasi.canva.download', $presentation),
            'job_id' => $jobId,
            'pages' => count($binaries),
        ];
    }

    public function requireConnection(User $user): CanvaConnection
    {
        if (! $this->featureEnabled()) {
            throw new RuntimeException('Integrasi Canva dimatikan di Pengaturan Sistem.');
        }

        $connection = $this->connectionFor($user);
        if (! $connection) {
            throw new RuntimeException('Hubungkan akun Canva Pendidikan (belajar.id) terlebih dahulu.');
        }

        if ($connection->email && ! $this->isBelajarIdEmail($connection->email)) {
            $this->disconnect($user);
            throw new RuntimeException(
                'Akun Canva bukan belajar.id. Hubungkan ulang dengan akun belajar.id sekolah.'
            );
        }

        return $connection;
    }

    public function sanitizeCanvaUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || (! str_ends_with($host, '.canva.com') && $host !== 'canva.com')) {
            return null;
        }

        return $url;
    }

    public function assertExportPathOwnedBy(User $user, string $path): void
    {
        $dir = trim((string) config('services.canva.export_directory', 'canva-exports'), '/');
        $expected = $dir.'/'.$user->uuid.'/';
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if ($normalized === '' || str_contains($normalized, '..') || ! str_starts_with($normalized, $expected)) {
            throw new RuntimeException('Path ekspor Canva tidak valid.');
        }
    }

    private function downloadExportBinary(string $downloadUrl): string
    {
        $this->assertSafeExportDownloadUrl($downloadUrl);

        $response = Http::timeout((int) config('services.canva.export_timeout', 90))
            ->withOptions(['allow_redirects' => false])
            ->get($downloadUrl);

        if ($response->redirect()) {
            $location = (string) $response->header('Location');
            $this->assertSafeExportDownloadUrl($location);
            $response = Http::timeout((int) config('services.canva.export_timeout', 90))
                ->withOptions(['allow_redirects' => false])
                ->get($location);
        }

        if ($response->failed()) {
            throw new RuntimeException('Gagal mengunduh file PDF dari Canva (HTTP '.$response->status().').');
        }

        $binary = $response->body();
        if ($binary === '' || $binary === false) {
            throw new RuntimeException('Gagal mengunduh file PDF dari Canva.');
        }

        if (! str_starts_with($binary, '%PDF')) {
            throw new RuntimeException('File yang diunduh dari Canva bukan PDF yang valid.');
        }

        return $binary;
    }

    private function assertSafeExportDownloadUrl(string $url): void
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('URL unduhan Canva tidak aman.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedExact = array_map('strtolower', (array) config('services.canva.export_allowed_hosts', []));
        $ok = in_array($host, $allowedExact, true)
            || str_ends_with($host, '.canva.com')
            || $host === 'canva.com';

        if (! $ok) {
            throw new RuntimeException('Host unduhan Canva tidak diizinkan.');
        }
    }

    /** @param list<string> $pdfBinaries */
    private function zipPdfPages(array $pdfBinaries): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'canva-zip-');
        if ($tmp === false) {
            throw new RuntimeException('Gagal membuat arsip ekspor Canva.');
        }

        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip ZIP ekspor Canva.');
        }

        foreach ($pdfBinaries as $i => $binary) {
            $zip->addFromString(sprintf('halaman-%02d.pdf', $i + 1), $binary);
        }
        $zip->close();

        $contents = file_get_contents($zipPath);
        @unlink($zipPath);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Gagal membaca arsip ZIP ekspor Canva.');
        }

        return $contents;
    }

    private function revokeTokensBestEffort(CanvaConnection $connection): void
    {
        $token = $connection->plainRefreshToken() ?: $connection->plainAccessToken();
        if ($token === '' || $token === null) {
            return;
        }

        try {
            $clientId = (string) config('services.canva.client_id');
            $clientSecret = (string) config('services.canva.client_secret');
            $revokeUrl = (string) config(
                'services.canva.revoke_url',
                'https://api.canva.com/rest/v1/oauth/revoke'
            );

            Http::asForm()
                ->timeout(10)
                ->withBasicAuth($clientId, $clientSecret)
                ->post($revokeUrl, ['token' => $token]);
        } catch (\Throwable) {
            // Best-effort — tetap hapus baris lokal.
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Canva Connect belum dikonfigurasi (CANVA_CLIENT_ID / CANVA_CLIENT_SECRET).');
        }
    }

    private function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $clientId = (string) config('services.canva.client_id');
        $clientSecret = (string) config('services.canva.client_secret');

        $response = Http::asForm()
            ->timeout((int) config('services.canva.timeout', 30))
            ->withBasicAuth($clientId, $clientSecret)
            ->post((string) config('services.canva.token_url'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => config('services.canva.redirect_uri'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeApiError($response->status(), $response->json()));
        }

        return $response->json() ?? [];
    }

    private function refreshAccessToken(string $refreshToken): array
    {
        $clientId = (string) config('services.canva.client_id');
        $clientSecret = (string) config('services.canva.client_secret');

        $response = Http::asForm()
            ->timeout((int) config('services.canva.timeout', 30))
            ->withBasicAuth($clientId, $clientSecret)
            ->post((string) config('services.canva.token_url'), [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeApiError($response->status(), $response->json()));
        }

        return $response->json() ?? [];
    }

    /** @return array{id:?string,display_name:?string} */
    private function fetchUserProfile(string $accessToken): array
    {
        $base = rtrim((string) config('services.canva.api_base'), '/');

        $response = Http::timeout((int) config('services.canva.timeout', 30))
            ->withToken($accessToken)
            ->acceptJson()
            ->get($base.'/users/me');

        if ($response->successful()) {
            $json = $response->json() ?? [];

            return [
                'id' => $json['team_user']['user_id'] ?? $json['user']['id'] ?? null,
                'display_name' => $this->fetchDisplayName($accessToken, $base),
            ];
        }

        throw new RuntimeException('Gagal membaca profil Canva.');
    }

    private function fetchDisplayName(string $accessToken, string $base): ?string
    {
        $fallback = Http::timeout((int) config('services.canva.timeout', 30))
            ->withToken($accessToken)
            ->acceptJson()
            ->get($base.'/users/me/profile');

        if (! $fallback->successful()) {
            return null;
        }

        $json = $fallback->json() ?? [];
        $profile = $json['profile'] ?? $json;

        return $profile['display_name'] ?? null;
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function normalizeSuffix(string $suffix): string
    {
        $suffix = strtolower(trim($suffix));
        if ($suffix === '') {
            return '.belajar.id';
        }

        return $suffix;
    }

    private function normalizeApiError(int $status, ?array $json): string
    {
        $message = (string) ($json['message'] ?? $json['error_description'] ?? $json['error'] ?? '');

        return match (true) {
            $status === 401 => 'Sesi Canva tidak valid. Hubungkan ulang akun belajar.id.',
            $status === 403 => 'Canva menolak akses. Pastikan akun belajar.id punya izin desain.',
            $status === 404 => 'Desain Canva tidak ditemukan.',
            $status === 429 => 'Batas permintaan Canva tercapai. Coba lagi sebentar.',
            $message !== '' => 'Canva: '.$message,
            default => 'Terjadi kesalahan saat menghubungi Canva (HTTP '.$status.').',
        };
    }
}
