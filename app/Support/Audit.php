<?php

namespace App\Support;

use App\Models\ForumAudit;

/**
 * Audit ringan (pengganti spatie/activitylog yang tidak terpasang).
 * Menulis ke tabel forum_audits yang bentuknya generik (user_id, action, subject, meta)
 * — dipakai bersama modul Forum & Ruang Kelas agar konsisten.
 */
class Audit
{
    public static function log(string $action, ?object $subject = null, array $meta = []): void
    {
        ForumAudit::create([
            'user_id'      => auth()->id(),
            'action'       => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject->uuid ?? null,
            'meta'         => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
