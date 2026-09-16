<?php

return [
    /*
    | Sandbox demo. Default mati supaya instalasi sekolah produksi tidak
    | menerima provisioning. Hidupkan hanya di deployment demo.
    */
    'enabled' => filter_var(env('DEMO_SANDBOX_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'hmac' => [
        'key_id' => env('DEMO_HMAC_KEY_ID', 'landing-v1'),
        'secret' => env('DEMO_HMAC_SECRET', ''),
        'timestamp_tolerance' => 300,
        'nonce_ttl' => 600,
    ],

    'limits' => [
        'min_duration_hours' => 24,
        'max_duration_hours' => 2160,
        'max_accounts' => 10,
        'roles' => ['kepala', 'admin', 'guru'],
    ],

    'school_display_name' => env('DEMO_SCHOOL_DISPLAY_NAME', "Sekolah Demo B'tive"),

    'landing_callback_url' => env('DEMO_LANDING_CALLBACK_URL', ''),
    'landing_hmac_key_id' => env('DEMO_LANDING_HMAC_KEY_ID', 'sandbox-v1'),
    'landing_hmac_secret' => env('DEMO_LANDING_HMAC_SECRET', ''),

    'integrations_enabled' => filter_var(env('DEMO_INTEGRATIONS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'reset' => [
        'manifest_version' => '1.0',
        'lock_seconds' => 600,
        'timezone' => 'Asia/Jakarta',
        'forbidden' => [
            'users',
            'gurus',
            'siswa',
            'kelas',
            'pelajarans',
            'demo_accesses',
            'demo_provisioning_events',
            'demo_reset_runs',
            'sessions',
            'migrations',
            'jobs',
            'failed_jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'settings',
            'activity_log',
            'forum_audits',
            'langganan',
            'permissions',
            'roles',
            'role_permissions',
            'forum_role_permissions',
            'model_has_roles',
            'model_has_permissions',
            'role_has_permissions',
            'webauthn_credentials',
            'password_reset_tokens',
        ],
        'truncate' => [
            'game_practice_answers',
            'game_practice_attempts',
            'game_practice_participants',
            'game_practice_sessions',
            'game_answers',
            'game_attempts',
            'game_focus_events',
            'game_live_participants',
            'game_live_sessions',
            'game_team_members',
            'game_teams',
            'game_question_options',
            'game_questions',
            'game_quiz_assignments',
            'game_quizzes',
            'classroom_submission_files',
            'classroom_submissions',
            'classroom_comments',
            'classroom_lock_events',
            'classroom_assignment_files',
            'classroom_assignment_links',
            'classroom_assignments',
            'classroom_material_files',
            'classroom_material_links',
            'classroom_materials',
            'forum_reactions',
            'forum_topic_reads',
            'forum_comments',
            'forum_topics',
            'notifications',
        ],
    ],

    /*
    | Mutasi (POST/PUT/PATCH/DELETE) demo: deny-by-default.
    | Nama route yang diizinkan, atau awalan diikuti titik.
    */
    'mutation_allow' => [
        'logout',
        'dashboard.layout',
        'profile.style',
        'notifications.read',
        'notifications.readAll',
        'classroom.',
        'latihan.',
        'forum.store',
        'forum.comment.',
        'forum.reaction.toggle',
    ],

    /*
    | Deny menang atas allow (lihat RestrictDemoMutations::handle). Daftar ini
    | WAJIB memuat setiap route yg memanggil penyedia AI berbayar — termasuk yg
    | namanya ikut ter-allow lewat prefix lebar 'classroom.' di atas, mis.
    | 'classroom.arena.quality-checker.*' (QuestionQualityCheckerController →
    | InteractsWithAi → Gemini). Tanpa entri itu, akun demo anonim bisa membakar
    | kuota AI sekolah walau DEMO_INTEGRATIONS_ENABLED=false.
    */
    'mutation_deny_prefixes' => [
        'setting.',
        'langganan.',
        'siswa.',
        'guru.',
        'keuangan.',
        'sarpras.',
        'ai.',
        'asisten.',
        'canva.',
        'presentasi.',
        'osis.',
        'classroom.arena.quality-checker',
    ],
];
