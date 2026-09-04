<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Daftar kanonik widget polling/interval yang admin bisa matikan SATU-PER-SATU lewat
 * tab "Performa Server" (bukan cuma satu tombol besar/darurat) — dibaca sebagai
 * window.SIMS_POLLING_NONAKTIF di layouts/app.blade.php, dicek per-kode di tiap
 * window.simsPollInterval(fn, ms, kode).
 *
 * Kelompok 'Notifikasi & Badge' berbagi SATU request HTTP dgn bel notifikasi
 * (NotificationController::badgesLainnya()) — mematikan salah satu ANGGOTA-nya
 * sendirian cuma menghentikan tampilannya diperbarui (request-nya tetap jalan krn
 * dipakai bel notifikasi); mematikan "Bel Notifikasi" sendiri yang benar2
 * menghentikan request-nya (dan ikut membekukan 4 badge itu juga).
 *
 * Ujian & pemantauan ruangan ujian SENGAJA tak masuk daftar ini — itu tak pernah
 * bisa dimatikan lewat sini (dipanggil tanpa kode sama sekali di kodenya).
 */
class PollingWidget
{
    public static function semua(): array
    {
        return [
            'notifikasi' => [
                'label' => 'Bel Notifikasi',
                'kelompok' => 'Notifikasi & Badge',
                'catatan' => 'Mematikan ini ikut menghentikan 4 badge di bawah (satu request HTTP yang sama).',
            ],
            'badge_grup' => [
                'label' => 'Badge Grup Chat (sidebar)',
                'kelompok' => 'Notifikasi & Badge',
                'catatan' => 'Numpang request Bel Notifikasi — mematikan ini sendirian cuma membekukan angkanya, tak mengurangi request.',
            ],
            'badge_chatbot' => [
                'label' => 'Badge Chatbot (gelembung mengambang)',
                'kelompok' => 'Notifikasi & Badge',
                'catatan' => 'Numpang request Bel Notifikasi — mematikan ini sendirian cuma membekukan angkanya, tak mengurangi request.',
            ],
            'badge_chat_admin' => [
                'label' => 'Badge Chat-Admin',
                'kelompok' => 'Notifikasi & Badge',
                'catatan' => 'Numpang request Bel Notifikasi — mematikan ini sendirian cuma membekukan angkanya, tak mengurangi request.',
            ],
            'badge_masukan' => [
                'label' => 'Badge Masukan/Feedback',
                'kelompok' => 'Notifikasi & Badge',
                'catatan' => 'Numpang request Bel Notifikasi — mematikan ini sendirian cuma membekukan angkanya, tak mengurangi request.',
            ],
            'ticker' => ['label' => 'Ticker Statistik Dashboard', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'komentar_kelas' => ['label' => 'Komentar Materi/Tugas (Ruang Kelas)', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'lock_monitor' => ['label' => 'Panel Kunci-Layar (pemantauan materi/tugas)', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'private_chat' => ['label' => 'Chat Pribadi', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'pesan_grup' => ['label' => 'Pesan Grup Chat (ruang obrolan)', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'forum_komentar' => ['label' => 'Komentar Forum Diskusi', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'kuota_ai_guru' => ['label' => 'Kuota AI Guru', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'chatbot_widget' => ['label' => 'Widget Chatbot (panel obrolan)', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'chatbot_admin_inbox' => ['label' => 'Inbox Chatbot Admin', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'geofence_absen' => ['label' => 'Geofence Absen QR', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'osis_dashboard' => ['label' => 'Dashboard Live OSIS', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'osis_hasil' => ['label' => 'Hasil OSIS', 'kelompok' => 'Widget Lain', 'catatan' => null],
            'arena_live' => [
                'label' => 'Arena Belajar Live',
                'kelompok' => 'Arena Belajar',
                'catatan' => 'Kuis yang sedang live akan berhenti update kalau dimatikan pas dipakai.',
            ],
            'arena_latihan' => ['label' => 'Arena Belajar Latihan', 'kelompok' => 'Arena Belajar', 'catatan' => null],
            'arena_latihan_tamu' => ['label' => 'Arena Latihan Tamu (tanpa login)', 'kelompok' => 'Arena Belajar', 'catatan' => null],
        ];
    }

    public static function kodeValid(): array
    {
        return array_keys(self::semua());
    }

    /** '1' (default) = polling jalan normal (checkbox tercentang); '0' = dimatikan admin. */
    public static function settingKey(string $kode): string
    {
        return 'polling_aktif_'.$kode;
    }

    public static function aktif(string $kode): bool
    {
        return Setting::get(self::settingKey($kode), '1') === '1';
    }

    public static function nonaktif(string $kode): bool
    {
        return ! self::aktif($kode);
    }

    /** @return array<int, string> kode yang saat ini nonaktif — dikirim ke JS sbg window.SIMS_POLLING_NONAKTIF */
    public static function daftarNonaktif(): array
    {
        return array_values(array_filter(self::kodeValid(), fn (string $kode) => self::nonaktif($kode)));
    }
}
