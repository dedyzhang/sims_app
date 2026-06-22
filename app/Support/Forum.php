<?php

namespace App\Support;

use App\Models\ForumAudit;

/**
 * Katalog & helper Forum Diskusi.
 *
 * Catatan adaptasi: app ini TIDAK memakai spatie/laravel-permission. Role = kolom
 * users.access. Maka "permission" forum disimpan di tabel forum_role_permissions
 * (matriks access × permission) yang bisa diubah admin, dan dibaca runtime lewat
 * User::canForum(). Policy SELALU memanggil canForum(), bukan mengecek nama role.
 */
class Forum
{
    /** Daftar permission granular forum (untuk matriks & Policy). */
    public const PERMISSIONS = [
        'forum.view.all'       => 'Lihat semua forum (lintas kelas/mapel)',
        'forum.view.scope'     => 'Lihat forum lingkup sendiri (kelas/kategori)',
        'forum.topic.create'   => 'Buat topik',
        'forum.comment.create' => 'Balas / berkomentar',
        'forum.moderate'       => 'Moderasi (pin, lock, hapus, jawaban terbaik)',
        'forum.announce'       => 'Buat pengumuman resmi',
        'forum.manage_access'  => 'Atur matriks akses forum (admin)',
    ];

    public const CATEGORIES = [
        'akademik'   => 'Akademik',
        'kesiswaan'  => 'Kesiswaan',
        'sarpras'    => 'Sarpras',
        'umum'       => 'Umum',
        'pengumuman' => 'Pengumuman',
    ];

    public const AUDIENCES = [
        'siswa_guru'    => 'Siswa & Guru',
        'termasuk_ortu' => 'Termasuk Orang Tua',
    ];

    /** Label role (nilai users.access) yang muncul di matriks akses. */
    public const ROLE_LABELS = [
        'superadmin' => 'Super Admin',
        'admin'      => 'Admin / Operator',
        'kepala'     => 'Kepala Sekolah',
        'kurikulum'  => 'Waka Kurikulum',
        'kesiswaan'  => 'Waka Kesiswaan',
        'sapras'     => 'Waka Sarpras',
        'guru'       => 'Guru',
        'siswa'      => 'Siswa',
        'orangtua'   => 'Orang Tua',
        'yayasan'    => 'Yayasan',
    ];

    /**
     * Pemetaan DEFAULT role → permission (dipakai seeder saja; bukan aturan permanen,
     * admin dapat mengubah lewat halaman "Pengaturan Akses Forum").
     */
    public const DEFAULTS = [
        'superadmin' => ['forum.view.all', 'forum.view.scope', 'forum.topic.create', 'forum.comment.create', 'forum.moderate', 'forum.announce', 'forum.manage_access'],
        'admin'      => ['forum.view.all', 'forum.view.scope', 'forum.topic.create', 'forum.comment.create', 'forum.moderate', 'forum.announce', 'forum.manage_access'],
        'kepala'     => ['forum.view.all', 'forum.moderate', 'forum.announce'],
        'kurikulum'  => ['forum.view.all', 'forum.topic.create', 'forum.comment.create', 'forum.moderate', 'forum.announce'],
        'kesiswaan'  => ['forum.view.all', 'forum.topic.create', 'forum.comment.create', 'forum.moderate', 'forum.announce'],
        'sapras'     => ['forum.view.scope', 'forum.topic.create', 'forum.comment.create', 'forum.moderate'],
        'guru'       => ['forum.view.scope', 'forum.topic.create', 'forum.comment.create', 'forum.moderate'],
        'siswa'      => ['forum.view.scope', 'forum.comment.create'],
        'orangtua'   => ['forum.view.scope', 'forum.comment.create'],
        'yayasan'    => ['forum.view.all'],
    ];

    /**
     * Kategori yang menjadi "lingkup" suatu staf saat hanya punya forum.view.scope
     * (bukan view.all) dan TIDAK terikat kelas (mis. Waka Sarpras → sarpras).
     * Default; bisa Anda sesuaikan. Role yang terikat kelas (guru/siswa/ortu)
     * memakai lingkup kelas, bukan kategori (lihat ForumTopicPolicy).
     *
     * @return string[]|null  null = bukan staf kategori
     */
    public static function categoryScope(string $access): ?array
    {
        return match ($access) {
            'sapras'    => ['sarpras', 'umum', 'pengumuman'],
            'kesiswaan' => ['kesiswaan', 'umum', 'pengumuman'],
            'kurikulum' => ['akademik', 'umum', 'pengumuman'],
            default     => null,
        };
    }

    /**
     * Sanitasi body. Tanpa mews/purifier → simpan teks polos (anti-XSS),
     * format ditampilkan via nl2br(e()) saat render. Tidak menyimpan HTML mentah.
     */
    public static function sanitize(?string $text): string
    {
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /** Catat ke audit log forum. */
    public static function audit(string $action, ?object $subject = null, array $meta = []): void
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
