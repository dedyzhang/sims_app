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
            // ── Panggilan latar dari halaman (termasuk di dalam aplikasi Android/WebView). Data log
            //    produksi 26-11-2026: ketiganya ±47% dari seluruh request PHP. Saat Mode Ujian aktif
            //    ketiganya otomatis berhenti (lihat aktif()/daftarNonaktif() di bawah).
            'notifikasi' => [
                'label' => 'Bel Notifikasi (notifications-json)',
                'kelompok' => 'Panggilan Latar Aplikasi & Notifikasi',
                'catatan' => 'Mengambil isi lonceng notifikasi tiap halaman dibuka. Mematikan ini ikut menghentikan 4 badge di kelompok "Notifikasi & Badge" (satu request HTTP yang sama).',
            ],
            'fcm_token' => [
                'label' => 'Pendaftaran HP untuk Push Notification (fcm-token)',
                'kelompok' => 'Panggilan Latar Aplikasi & Notifikasi',
                'catatan' => 'Aplikasi Android mendaftarkan token HP agar bisa menerima push notification. Kalau dimatikan: HP yang SUDAH terdaftar tetap menerima notifikasi; HP baru/instal ulang baru terdaftar setelah diaktifkan lagi.',
            ],
            'firebase_realtime' => [
                'label' => 'Firebase Realtime (firebase/custom-token)',
                'kelompok' => 'Panggilan Latar Aplikasi & Notifikasi',
                'catatan' => 'Update instan tanpa refresh. Kalau dimatikan: chat grup, chat pribadi, panel kunci layar, dan Arena Belajar (live & latihan) TIDAK update otomatis — pengguna harus refresh. Jangan matikan saat ada kuis Arena Belajar live.',
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
            'forum_komentar' => ['label' => 'Forum Diskusi (komentar & kehadiran)', 'kelompok' => 'Widget Lain', 'catatan' => null],
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
        if (ModeUjian::aktif()) {
            return false;
        }

        return Setting::get(self::settingKey($kode), '1') === '1';
    }

    public static function nonaktif(string $kode): bool
    {
        return ! self::aktif($kode);
    }

    /** @return array<int, string> kode yang saat ini nonaktif — dikirim ke JS sbg window.SIMS_POLLING_NONAKTIF */
    public static function daftarNonaktif(): array
    {
        if (ModeUjian::aktif()) {
            return self::kodeValid();
        }

        return array_values(array_filter(self::kodeValid(), fn (string $kode) => self::nonaktif($kode)));
    }
}
