<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        // Path ke service account JSON Firebase (TIDAK di-commit). Bila file tidak
        // ada, FcmService->enabled() = false dan push FCM dilewati diam-diam.
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
        // Default sync agar push real-time tetap jalan walau queue worker database
        // belum aktif. Bisa diubah ke database/redis jika worker produksi sudah siap.
        'queue_connection' => env('FCM_QUEUE_CONNECTION', 'sync'),
    ],

    /*
    | Canva Connect (Public) — OAuth per-guru, jalur gratis Canva Pendidikan.
    | Guru login dengan akun belajar.id di layar Canva. Bukan Enterprise/Private.
    */
    'canva' => [
        'client_id' => env('CANVA_CLIENT_ID'),
        'client_secret' => env('CANVA_CLIENT_SECRET'),
        'redirect_uri' => env('CANVA_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/ai/teacher/canva/callback'),
        'auth_url' => env('CANVA_AUTH_URL', 'https://www.canva.com/api/oauth/authorize'),
        'token_url' => env('CANVA_TOKEN_URL', 'https://api.canva.com/rest/v1/oauth/token'),
        'api_base' => env('CANVA_API_BASE', 'https://api.canva.com/rest/v1'),
        'scopes' => array_values(array_filter(array_map(
            'trim',
            explode(' ', (string) env(
                'CANVA_SCOPES',
                'design:meta:read design:content:read design:content:write profile:read'
            )),
        ))),
        'allowed_email_suffix' => env('CANVA_ALLOWED_EMAIL_SUFFIX', '.belajar.id'),
        'timeout' => (int) env('CANVA_TIMEOUT', 30),
        'export_timeout' => (int) env('CANVA_EXPORT_TIMEOUT', 90),
        'export_poll_micros' => (int) env('CANVA_EXPORT_POLL_MICROS', 800_000),
        'export_disk' => env('CANVA_EXPORT_DISK', 'local'),
        'export_directory' => env('CANVA_EXPORT_DIRECTORY', 'canva-exports'),
        'revoke_url' => env('CANVA_REVOKE_URL', 'https://api.canva.com/rest/v1/oauth/revoke'),
        'export_allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'CANVA_EXPORT_ALLOWED_HOSTS',
                'export-download.canva.com,document-export.canva.com'
            )),
        ))),
    ],

];
