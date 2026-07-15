<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Guru;
use App\Models\PresensiGuru;
use App\Models\Setting;
use App\Support\AttendanceParentNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QrAbsensiController extends Controller
{
    /**
     * Token QR aktif — dua mode (diatur admin lewat Pengaturan > Absensi):
     * - "harian": deterministik per tanggal (HMAC + APP_KEY), otomatis berubah tiap hari.
     * - "tetap" : satu token permanen tersimpan di Setting, tidak berubah sampai admin
     *             membuatnya ulang secara manual (cocok utk QR yang dicetak & ditempel).
     */
    private function token(?string $date = null): string
    {
        if (Setting::get('qr_absensi_mode', 'harian') === 'tetap') {
            return $this->tokenTetap();
        }
        $date = $date ?: now()->toDateString();
        return substr(hash_hmac('sha256', 'qrabsen|' . $date, (string) config('app.key')), 0, 12);
    }

    /** Token mode tetap — dibuat sekali (lazy) & disimpan; admin bisa buat ulang lewat Pengaturan. */
    private function tokenTetap(): string
    {
        $t = Setting::get('qr_absensi_token_tetap');
        if (!$t) {
            $t = Str::random(12);
            Setting::set('qr_absensi_token_tetap', $t);
        }
        return $t;
    }

    /** Jarak dua koordinat (meter) — Haversine. */
    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Halaman ADMIN: tampilkan QR absensi hari ini (untuk dipajang). */
    public function show(Request $request)
    {
        return view('qr.show', [
            'token'   => $this->token(),
            'tanggal' => now()->toDateString(),
            'mode'    => Setting::get('qr_absensi_mode', 'harian'),
            'aktif'   => Setting::get('qr_absensi_aktif', '1') == '1',
            'lat'     => Setting::get('sekolah_lat'),
            'lng'     => Setting::get('sekolah_lng'),
            // Mode kiosk ditentukan per-request dari token URL (lihat EnsureKioskOrPermission), bukan session.
            'isKiosk' => \App\Http\Middleware\EnsureKioskOrPermission::hasValidToken($request),
        ]);
    }

    /** Halaman cetak: QR + kop sekolah + langkah-langkah scan, didesain utk dicetak & ditempel. */
    public function cetak()
    {
        $kepsek = Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();

        return view('qr.cetak', [
            'token'         => $this->token(),
            'tanggal'       => now()->toDateString(),
            'mode'          => Setting::get('qr_absensi_mode', 'harian'),
            'namaSekolah'   => Setting::get('nama_sekolah', ''),
            'alamatSekolah' => Setting::get('alamat_sekolah', ''),
            'kopTeks'       => Setting::get('kop_teks'),
            'kopLogoKiri'   => $this->kopImg('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan'  => $this->kopImg('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kepsekNama'    => $kepsek?->nama ?? Setting::get('kepala_sekolah', ''),
        ]);
    }

    private function kopImg(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && Storage::disk('public')->exists($v)) {
            return asset('storage/' . $v);
        }
        if (file_exists(public_path($default))) {
            return asset($default);
        }
        return null;
    }

    /** Halaman USER: scan QR + baca lokasi untuk absen. */
    public function absen()
    {
        $siswa = auth()->user()->siswa;
        $kaihBelum = false;
        $kaihPertanyaans = collect();
        if ($siswa && \App\Support\KaihSiswa::wajibSebelumAbsen() && !\App\Support\KaihSiswa::sudahDiisi($siswa->uuid)) {
            $kaihBelum = true;
            $kaihPertanyaans = \App\Models\KaihPertanyaan::with('opsi')->where('aktif', true)->orderBy('urutan')->get();
        }

        return view('qr.absen', [
            'lat'    => Setting::get('sekolah_lat'),
            'lng'    => Setting::get('sekolah_lng'),
            'radius' => (float) Setting::get('absen_radius', 100),
            'aktif'  => Setting::get('qr_absensi_aktif', '1') == '1',
            'isGuru' => (bool) auth()->user()->guru,   // guru bisa absen masuk & pulang
            'kaihBelum'       => $kaihBelum,
            'kaihPertanyaans' => $kaihPertanyaans,
        ]);
    }

    /** Proses absen (AJAX): validasi token harian + jarak ke sekolah. */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'lat'   => 'required|numeric',
            'lng'   => 'required|numeric',
            'mode'  => 'nullable|in:masuk,pulang',
        ]);
        $mode = $data['mode'] ?? 'masuk';

        if (Setting::get('qr_absensi_aktif', '1') != '1') {
            return response()->json(['ok' => false, 'message' => 'Absen QR sedang dinonaktifkan admin.'], 422);
        }

        // Mode "harian": QR berubah tiap hari, token kemarin tidak berlaku lagi.
        // Mode "tetap": token sama tiap hari, hanya berubah bila admin membuatnya ulang.
        if (!hash_equals($this->token(), $data['token'])) {
            $pesan = Setting::get('qr_absensi_mode', 'harian') === 'tetap'
                ? 'QR tidak valid. QR ini mungkin sudah dibuat ulang oleh admin — pindai QR yang terbaru.'
                : 'QR tidak valid atau sudah kedaluwarsa. Pindai QR hari ini.';
            return response()->json(['ok' => false, 'message' => $pesan], 422);
        }

        $slat = Setting::get('sekolah_lat');
        $slng = Setting::get('sekolah_lng');
        if (!$slat || !$slng) {
            return response()->json(['ok' => false, 'message' => 'Lokasi sekolah belum diatur oleh admin.'], 422);
        }

        $radius = (float) Setting::get('absen_radius', 100);
        $dist = $this->distanceMeters((float) $slat, (float) $slng, (float) $data['lat'], (float) $data['lng']);
        if ($dist > $radius) {
            return response()->json([
                'ok'      => false,
                'message' => 'Anda berada ' . round($dist) . ' m dari sekolah (maks ' . round($radius) . ' m). Absen hanya bisa di lokasi sekolah.',
            ], 422);
        }

        $user = auth()->user();
        $today = now()->toDateString();
        $jam = now()->format('H:i:s');

        // nama panggilan untuk suara (buang gelar setelah koma)
        $namaUser = $user->siswa?->nama ?? $user->guru?->nama ?? $user->username;
        $panggilan = trim(explode(',', $namaUser)[0]);

        $jamDipakai = $jam;
        $label = 'Hadir';

        if ($user->siswa) {
            // Metode absensi aktif harus "Barcode / QR".
            if (!\App\Support\AbsensiGuru::bolehQr()) {
                return response()->json(['ok' => false, 'message' => \App\Support\AbsensiGuru::pesanKunci('QR')], 422);
            }
            // Hormati kalender: absensi siswa harus dibuka untuk hari ini.
            if (!\App\Support\KalenderAbsensi::absenSiswaDibuka($today)) {
                return response()->json(['ok' => false, 'message' => 'Absensi siswa tidak dibuka untuk hari ini.'], 422);
            }
            // Wajib isi kuesioner 7 KAIH hari ini sebelum boleh absen.
            if (!\App\Support\KaihSiswa::bolehAbsen($user->siswa->uuid, $today)) {
                return response()->json(['ok' => false, 'message' => \App\Support\KaihSiswa::pesanTolak()], 422);
            }
            // Siswa: hanya absen masuk, SEKALI per hari
            $row = Absensi::firstOrNew(['id_siswa' => $user->siswa->uuid, 'tanggal' => $today]);
            if (!empty($row->jam_masuk)) {
                return response()->json(['ok' => false, 'message' => 'Anda sudah absen hari ini pukul ' . substr($row->jam_masuk, 0, 5) . '.'], 422);
            }
            $row->id_kelas = $user->siswa->id_kelas;
            $row->status = 'hadir';
            $row->keterangan = 'Absen QR';
            $row->dicatat_oleh = $user->uuid;
            $row->jam_masuk = $jam;
            $row->save();
            AttendanceParentNotifier::notify($row);
            $jamDipakai = $row->jam_masuk;

            // Auto-deduksi poin bila terlambat (khusus sistem Poin/Aturan lama).
            if ($row->terlambat(Setting::get('waktu_terlambat', '07:30'))) {
                \App\Http\Controllers\PoinController::autoTerlambat($user->siswa->uuid, $today);
            }
        } elseif ($user->guru) {
            // Metode absensi guru aktif harus "Barcode / QR".
            if (!\App\Support\AbsensiGuru::bolehQr()) {
                return response()->json(['ok' => false, 'message' => \App\Support\AbsensiGuru::pesanKunci('QR')], 422);
            }
            // Guru: MASUK sekali & PULANG sekali per hari
            $row = PresensiGuru::firstOrNew(['id_guru' => $user->guru->uuid, 'tanggal' => $today]);
            if ($mode === 'pulang') {
                if (!empty($row->jam_pulang)) {
                    return response()->json(['ok' => false, 'message' => 'Anda sudah absen pulang hari ini pukul ' . substr($row->jam_pulang, 0, 5) . '.'], 422);
                }
                // Wajib melengkapi agenda hari ini sebelum boleh absen pulang.
                $belum = \App\Support\AgendaGuru::belumDiisi($user->guru, $today);
                if (!empty($belum) && \App\Support\AgendaGuru::wajibSebelumPulang()) {
                    return response()->json(['ok' => false, 'message' => \App\Support\AgendaGuru::pesanTolak($belum)], 422);
                }
                $row->jam_pulang = $jam;
                $jamDipakai = $row->jam_pulang;
                $label = 'Pulang';
            } else {
                if (!empty($row->jam_masuk)) {
                    return response()->json(['ok' => false, 'message' => 'Anda sudah absen masuk hari ini pukul ' . substr($row->jam_masuk, 0, 5) . '.'], 422);
                }
                $row->jam_masuk = $jam;
                $jamDipakai = $row->jam_masuk;
                $label = 'Masuk';
            }
            $row->status = 'hadir';
            $row->keterangan = 'Absen QR';
            $row->dicatat_oleh = $user->uuid;
            $row->save();
        } else {
            return response()->json(['ok' => false, 'message' => 'Akun ini tidak memiliki data kehadiran (siswa/guru).'], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Absen ' . strtolower($label) . ' berhasil!',
            'label'   => $label,
            'nama'    => $panggilan,
            'jam'     => substr((string) $jamDipakai, 0, 5),
            'jarak'   => round($dist),
        ]);
    }
}
