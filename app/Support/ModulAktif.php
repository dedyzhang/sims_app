<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Registry on/off modul sekolah (Pengaturan Sistem → tab Fitur).
 * Default aktif ('1') agar instalasi yang sudah jalan tidak berubah perilaku.
 */
class ModulAktif
{
    /**
     * @return array<string, array{label: string, deskripsi: string, ikon: string}>
     */
    public static function semua(): array
    {
        return [
            'absensi' => [
                'label' => 'Absensi & Presensi',
                'deskripsi' => 'Absensi siswa, presensi guru, QR, scan wajah, dan 7 KAIH.',
                'ikon' => 'clipboard-check',
            ],
            'akademik' => [
                'label' => 'Akademik',
                'deskripsi' => 'Ruang kelas, jadwal, penilaian, rapor, perangkat ajar, dan ekskul.',
                'ikon' => 'book-open',
            ],
            'asisten_guru' => [
                'label' => 'Asisten Guru',
                'deskripsi' => 'Nalar Guru, generator soal, RPM Learning, rangkuman, dan draft feedback.',
                'ikon' => 'sparkles',
            ],
            'analisis_ai' => [
                'label' => 'Analisis AI',
                'deskripsi' => 'Narasi data sekolah dan tanya-jawab berbasis dokumen (RAG).',
                'ikon' => 'brain',
            ],
            'arena_belajar' => [
                'label' => 'Arena Belajar',
                'deskripsi' => 'Kuis interaktif dan misi edukatif di Ruang Kelas (async, live, template).',
                'ikon' => 'gamepad-2',
            ],
            'ludensa' => [
                'label' => 'Arena Petualangan SD',
                'deskripsi' => 'Gamifikasi belajar untuk siswa SD kelas 1–6: kuis adaptif, quest harian, dan permainan edukatif. Matikan bila sekolah tidak memakai modul ini.',
                'ikon' => 'sparkles',
            ],
            'agenda' => [
                'label' => 'Agenda Guru & Rapat',
                'deskripsi' => 'Agenda mengajar, rekap, buku batas, dan agenda rapat.',
                'ikon' => 'notebook-pen',
            ],
            'disiplin' => [
                'label' => 'Kedisiplinan',
                'deskripsi' => 'Modul Poin/Aturan atau P3 kedisiplinan siswa.',
                'ikon' => 'shield-alert',
            ],
            'sarpras' => [
                'label' => 'Sarana & Prasarana',
                'deskripsi' => 'Denah, inventaris, peminjaman, kerusakan, dan perbaikan.',
                'ikon' => 'building-2',
            ],
            'keuangan' => [
                'label' => 'Keuangan / SPP',
                'deskripsi' => 'Tagihan SPP, verifikasi bukti, dan pengaturan bank.',
                'ikon' => 'wallet',
            ],
            'forum' => [
                'label' => 'Forum Diskusi',
                'deskripsi' => 'Forum topik dan komentar antar warga sekolah.',
                'ikon' => 'messages-square',
            ],
            'pengumuman' => [
                'label' => 'Pengumuman',
                'deskripsi' => 'Pengumuman sekolah untuk guru, siswa, dan orang tua.',
                'ikon' => 'megaphone',
            ],
            'chatbot' => [
                'label' => 'Chat / Asisten Sekolah',
                'deskripsi' => 'Widget chat handoff dan inbox admin.',
                'ikon' => 'message-circle',
            ],
            'cetak' => [
                'label' => 'Cetak Data',
                'deskripsi' => 'Export Excel data siswa, guru, kelas, absensi, dan nilai.',
                'ikon' => 'printer',
            ],
            'kartu_pelajar' => [
                'label' => 'Kartu Pelajar',
                'deskripsi' => 'Kartu pelajar digital untuk siswa dan kelola admin.',
                'ikon' => 'id-card',
            ],
            'alumni' => [
                'label' => 'Data Alumni',
                'deskripsi' => 'Pencatatan dan daftar alumni sekolah.',
                'ikon' => 'award',
            ],
        ];
    }

    public static function settingKey(string $kode): string
    {
        return 'fitur_'.$kode.'_aktif';
    }

    /** True bila modul aktif (default: aktif). */
    public static function aktif(string $kode): bool
    {
        if (! array_key_exists($kode, self::semua())) {
            return true;
        }

        // Transisi merge: jagat_misi digabung ke arena_belajar.
        // Aktif jika arena ON, atau (belum ada row arena) dan legacy jagat ON.
        // Lewat Setting::get (bukan query langsung) agar ikut memo per-request;
        // sentinel dipakai untuk membedakan "baris tidak ada" dari nilai kosong.
        if ($kode === 'arena_belajar') {
            $absent = "\0absent";

            $arena = Setting::get(self::settingKey('arena_belajar'), $absent);
            if ($arena !== $absent) {
                return $arena === '1';
            }

            $legacy = Setting::get('fitur_jagat_misi_aktif', $absent);
            if ($legacy !== $absent) {
                return $legacy === '1';
            }

            return true;
        }

        return Setting::get(self::settingKey($kode), '1') === '1';
    }

    /** Abort 403 bila modul dimatikan di pengaturan. */
    public static function assertAktif(string $kode): void
    {
        abort_unless(self::aktif($kode), 403, 'Fitur ini sedang dinonaktifkan di Pengaturan Sistem.');
    }

    /** @return list<string> */
    public static function kodeValid(): array
    {
        return array_keys(self::semua());
    }
}
