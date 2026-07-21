<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Guru;
use App\Models\PresensiGuru;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\GuruIzinPulangNotification;
use App\Support\AbsensiGuru;
use App\Support\AttendanceParentNotifier;
use App\Support\Geofence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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

    /** Snapshot geofence live (points + bonus jam sibuk) — dipoll klien agar tidak stale. */
    public function geoConfig()
    {
        return response()->json([
            'ok' => true,
            'points' => Geofence::schoolPoints(),
            'rush_bonus' => Geofence::rushBonusMeters(),
            'radius' => (float) Setting::get('absen_radius', 200),
            'lat' => Setting::get('sekolah_lat'),
            'lng' => Setting::get('sekolah_lng'),
            'soft_tolerance' => Geofence::SOFT_TOLERANCE_M,
            'server_time' => now()->format('H:i'),
        ]);
    }

    /** Halaman USER: scan QR + baca lokasi untuk absen. */
    public function absen()
    {
        $siswa = auth()->user()->siswa;
        $kaihBelum = false;
        $kaihPertanyaans = collect();
        if ($siswa && ! \App\Support\KaihSiswa::bolehAbsen($siswa->uuid)) {
            $kaihBelum = true;
            $kaihPertanyaans = \App\Models\KaihPertanyaan::with('opsi')->where('aktif', true)->orderBy('urutan')->get();
        }

        $points = Geofence::schoolPoints();
        $rushBonus = Geofence::rushBonusMeters();

        return view('qr.absen', [
            'lat'    => Setting::get('sekolah_lat'),
            'lng'    => Setting::get('sekolah_lng'),
            'radius' => (float) Setting::get('absen_radius', 200),
            'points' => $points,
            'rushBonus' => $rushBonus,
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
            'token'    => 'required|string',
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:10000',
            'mode'     => 'nullable|in:masuk,pulang',
        ]);
        $mode = $data['mode'] ?? 'masuk';
        $accuracy = (float) $data['accuracy'];

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

        $gate = $this->assertWithinSchool((float) $data['lat'], (float) $data['lng'], $accuracy);
        if ($gate !== true) {
            return $gate;
        }
        $eval = Geofence::evaluate((float) $data['lat'], (float) $data['lng']);
        $dist = $eval['dist'];
        $radius = $eval['radius'];

        $user = auth()->user();
        $today = now()->toDateString();
        $jam = now()->format('H:i:s');

        // nama panggilan untuk suara (buang gelar setelah koma)
        $namaUser = $user->siswa?->nama ?? $user->guru?->nama ?? $user->username;
        $panggilan = trim(explode(',', $namaUser)[0]);

        $jamDipakai = $jam;
        $label = 'Hadir';
        $geoAudit = $this->geoAuditPayload((float) $data['lat'], (float) $data['lng'], $accuracy, $dist);

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
            $previousStatus = $row->exists ? $row->status : null;
            $row->id_kelas = $user->siswa->id_kelas;
            $row->status = 'hadir';
            $row->keterangan = 'Absen QR';
            $row->dicatat_oleh = $user->uuid;
            $row->jam_masuk = $jam;
            $row->fill($geoAudit);
            $row->save();
            AttendanceParentNotifier::notifyIfStatusChanged($previousStatus, $row);
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
                $row->geo_lat_pulang = $geoAudit['geo_lat'];
                $row->geo_lng_pulang = $geoAudit['geo_lng'];
                $row->geo_accuracy_pulang = $geoAudit['geo_accuracy'];
                $row->geo_jarak_pulang = $geoAudit['geo_jarak'];
                $jamDipakai = $row->jam_pulang;
                $label = 'Pulang';
            } else {
                if (!empty($row->jam_masuk)) {
                    return response()->json(['ok' => false, 'message' => 'Anda sudah absen masuk hari ini pukul ' . substr($row->jam_masuk, 0, 5) . '.'], 422);
                }
                $row->jam_masuk = $jam;
                $row->geo_lat_masuk = $geoAudit['geo_lat'];
                $row->geo_lng_masuk = $geoAudit['geo_lng'];
                $row->geo_accuracy_masuk = $geoAudit['geo_accuracy'];
                $row->geo_jarak_masuk = $geoAudit['geo_jarak'];
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
            'ok'       => true,
            'message'  => 'Absen ' . strtolower($label) . ' berhasil!',
            'label'    => $label,
            'nama'     => $panggilan,
            'jam'      => substr((string) $jamDipakai, 0, 5),
            'jarak'    => round($dist),
            'accuracy' => (int) round($accuracy),
            'radius'   => (int) round($radius),
            'bonus'    => (int) round($eval['bonus']),
            'titik'    => $eval['label'],
        ]);
    }

    /**
     * Izin pulang awal via QR — dipakai saat metode absensi aktif sekolah = Barcode/QR
     * (padanan presensi-guru.izinPulang.store yang berbasis wajah). Sengaja TIDAK
     * mengecek AgendaGuru::belumDiisi() — izin pulang awal memang berarti sebagian
     * jam mengajar hari ini belum selesai/terisi; itu wajar & ditandai lewat alasan.
     */
    public function izinPulangMark(Request $request)
    {
        $guru = auth()->user()->guru;
        abort_unless($guru, 403);

        $data = $request->validate([
            'token'    => 'required|string',
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
            'accuracy' => 'required|numeric|min:0|max:10000',
            'alasan'   => 'required|string|max:500',
        ]);
        $accuracy = (float) $data['accuracy'];

        if (Setting::get('qr_absensi_aktif', '1') != '1') {
            return response()->json(['ok' => false, 'message' => 'Absen QR sedang dinonaktifkan admin.'], 422);
        }
        if (!AbsensiGuru::bolehQr()) {
            return response()->json(['ok' => false, 'message' => AbsensiGuru::pesanKunci('QR')], 422);
        }
        if (!hash_equals($this->token(), $data['token'])) {
            $pesan = Setting::get('qr_absensi_mode', 'harian') === 'tetap'
                ? 'QR tidak valid. QR ini mungkin sudah dibuat ulang oleh admin — pindai QR yang terbaru.'
                : 'QR tidak valid atau sudah kedaluwarsa. Pindai QR hari ini.';
            return response()->json(['ok' => false, 'message' => $pesan], 422);
        }

        $gate = $this->assertWithinSchool((float) $data['lat'], (float) $data['lng'], $accuracy);
        if ($gate !== true) {
            return $gate;
        }
        [$dist] = $this->schoolDistance((float) $data['lat'], (float) $data['lng']);
        $geoAudit = $this->geoAuditPayload((float) $data['lat'], (float) $data['lng'], $accuracy, $dist);

        $row = PresensiGuru::where('id_guru', $guru->uuid)->whereDate('tanggal', now())->first();
        if (!$row || empty($row->jam_masuk)) {
            return response()->json(['ok' => false, 'message' => 'Anda belum tercatat absen masuk hari ini.'], 422);
        }
        if (!empty($row->jam_pulang)) {
            return response()->json(['ok' => false, 'message' => 'Anda sudah tercatat pulang hari ini.'], 422);
        }

        $row->jam_pulang = now()->format('H:i:s');
        $row->keterangan = trim(($row->keterangan ? $row->keterangan . ' | ' : '') . 'Izin pulang awal: ' . $data['alasan']);
        $row->geo_lat_pulang = $geoAudit['geo_lat'];
        $row->geo_lng_pulang = $geoAudit['geo_lng'];
        $row->geo_accuracy_pulang = $geoAudit['geo_accuracy'];
        $row->geo_jarak_pulang = $geoAudit['geo_jarak'];
        $row->save();

        Notification::send(
            User::query()->whereIn('access', ['kepala', 'admin', 'superadmin'])->get(),
            new GuruIzinPulangNotification($row, $data['alasan'])
        );

        return response()->json([
            'ok'       => true,
            'jam'      => substr($row->jam_pulang, 0, 5),
            'jarak'    => round($dist),
            'accuracy' => (int) round($accuracy),
        ]);
    }

    /**
     * @return array{0: float, 1: float}  [jarak ke titik terbaik, radius titik itu]
     */
    private function schoolDistance(float $lat, float $lng): array
    {
        $eval = Geofence::evaluate($lat, $lng);
        if ($eval === null) {
            $radius = (float) Setting::get('absen_radius', 200);

            return [PHP_FLOAT_MAX, $radius];
        }

        return [$eval['dist'], $eval['radius']];
    }

    /** true bila OK; JsonResponse 422 bila gagal. */
    private function assertWithinSchool(float $lat, float $lng, float $accuracy)
    {
        $eval = Geofence::evaluate($lat, $lng);
        if ($eval === null) {
            return response()->json(['ok' => false, 'message' => 'Lokasi sekolah belum diatur oleh admin.'], 422);
        }

        // Sengaja TIDAK menolak berdasar accuracy GPS lagi — yang menentukan boleh/tidaknya
        // absen murni jarak ke titik sekolah (di bawah). Accuracy tetap disimpan untuk audit
        // (lihat geoAuditPayload) tapi tidak lagi jadi gate penolakan.
        if (!$eval['ok']) {
            $bonusTxt = $eval['bonus'] > 0
                ? ' (termasuk +' . (int) round($eval['bonus']) . ' m zona jam sibuk)'
                : '';

            $safeLabel = Geofence::sanitizePointLabel($eval['label']);

            return response()->json([
                'ok'       => false,
                'message'  => 'Anda berada ' . round($eval['dist']) . ' m dari titik «' . e($safeLabel) . '» (maks ' . round($eval['effective']) . ' m, termasuk toleransi GPS' . $bonusTxt . '). Absen hanya bisa di area sekolah.',
                'jarak'    => round($eval['dist']),
                'radius'   => (int) round($eval['radius']),
                'accuracy' => (int) round($accuracy),
                'titik'    => $safeLabel,
                'bonus'    => (int) round($eval['bonus']),
            ], 422);
        }

        return true;
    }

    /** @return array{geo_lat: float, geo_lng: float, geo_accuracy: int, geo_jarak: int} */
    private function geoAuditPayload(float $lat, float $lng, float $accuracy, float $dist): array
    {
        return [
            'geo_lat'      => round($lat, 7),
            'geo_lng'      => round($lng, 7),
            'geo_accuracy' => (int) min(65535, max(0, round($accuracy))),
            'geo_jarak'    => (int) min(65535, max(0, round($dist))),
        ];
    }
}
