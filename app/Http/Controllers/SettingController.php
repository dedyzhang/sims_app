<?php

namespace App\Http\Controllers;

use App\Models\Aturan;
use App\Models\Kelas;
use App\Models\NilaiPenjabaran;
use App\Models\Pelajaran;
use App\Models\PenjabaranKomponen;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Setting;
use App\Support\ModulAktif;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /** Role & permission yang sah untuk matriks hak akses (satu sumber kebenaran). */
    private const VALID_ROLES = ['kepala', 'kurikulum', 'kesiswaan', 'sarpras', 'bendahara', 'guru', 'orangtua', 'siswa'];

    private const PERMISSION_LABELS = [
        'manage_users' => 'Mengelola Data Siswa & Guru (Data Master)',
        'manage_absensi' => 'Mengelola Absensi & Presensi',
        'manage_jadwal' => 'Mengelola Jadwal Pelajaran',
        'view_all_nilai' => 'Melihat Nilai Semua Mapel & Guru',
        'edit_all_nilai' => 'Mengubah Nilai Semua Mapel & Guru',
        'manage_agenda' => 'Mengelola & Validasi Rekap Agenda',
        'manage_rapor' => 'Mengelola Rekap Nilai & Cetak Rapor',
        'manage_disiplin' => 'Mengelola Modul Kedisiplinan (Poin/P3)',
        'manage_sarpras' => 'Mengelola Sarana & Prasarana',
        'manage_keuangan' => 'Mengelola Modul Keuangan',
        'manage_pengumuman' => 'Membuat & Mengelola Pengumuman',
        'manage_feedback' => 'Merespon Saran & Masukan Pengguna',
        'manage_settings' => 'Mengelola Pengaturan Sistem',
        'manage_perangkat' => 'Memantau Perangkat Ajar Guru',
        'manage_rapat' => 'Mengelola Agenda Rapat (Notulen)',
        'manage_kaih' => 'Mengelola Kuesioner & Rekap 7 KAIH',
    ];

    public function index()
    {
        $semester = Semester::orderBy('tahun')->orderBy('semester')->get();
        $semesterAktif = Semester::aktif();
        $kelas = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();

        $settings = Setting::pluck('value', 'key');
        $aturans = Aturan::orderBy('kode')->get();

        return view('setting.index', compact('semester', 'semesterAktif', 'kelas', 'pelajarans', 'settings', 'aturans'));
    }

    public function updateSemester(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
        ]);
        Semester::query()->update(['aktif' => false]);
        Semester::findOrFail($request->semester_id)->update(['aktif' => true]);

        return back()->with('success', 'Semester aktif diperbarui.');
    }

    public function storeSemester(Request $request)
    {
        $request->validate([
            'semester' => 'required|in:1,2',
            'tahun' => 'required|string',
        ]);
        Semester::create(['semester' => $request->semester, 'tahun' => $request->tahun, 'aktif' => false]);

        return back()->with('success', 'Semester ditambah.');
    }

    public function setIdentitasSekolah(Request $request)
    {
        $request->validate([
            'sekolah_logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'hapus_logo' => 'nullable|boolean',
        ]);

        $fields = ['nama_sekolah', 'npsn', 'alamat_sekolah', 'kepala_sekolah', 'nip_kepala', 'kota', 'provinsi', 'telp_sekolah'];
        foreach ($fields as $f) {
            if ($request->has($f)) {
                Setting::set($f, $request->$f);
            }
        }

        if ($request->hasFile('sekolah_logo')) {
            // hapus logo lama jika ada
            $old = Setting::get('sekolah_logo');
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $ext = Uploads::safeExtension($request->file('sekolah_logo'), ['png', 'jpg', 'jpeg', 'webp'], 'png');
            $path = $request->file('sekolah_logo')->storeAs('logo', 'sekolah_logo_'.now()->format('YmdHis').'.'.$ext, 'public');
            Setting::set('sekolah_logo', $path);
        } elseif ($request->boolean('hapus_logo')) {
            $old = Setting::get('sekolah_logo');
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            Setting::set('sekolah_logo', '');
        }

        return back()->with('success', 'Identitas sekolah disimpan.');
    }

    public function setMediaSosial(Request $request)
    {
        Setting::set('sosmed_aktif', $request->boolean('sosmed_aktif') ? '1' : '0');

        foreach (array_keys(config('sosmed')) as $key) {
            Setting::set("sosmed_{$key}_url", trim((string) $request->input("sosmed_{$key}_url", '')));
            Setting::set("sosmed_{$key}_on", $request->boolean("sosmed_{$key}_on") ? '1' : '0');
        }

        return back()->with('success', 'Media sosial sekolah disimpan.');
    }

    public function setPoinTerlambat(Request $request)
    {
        $request->validate(['poin_terlambat' => 'required|integer']);
        Setting::set('poin_terlambat', $request->poin_terlambat);

        return back()->with('success', 'Pengaturan poin terlambat disimpan.');
    }

    public function setWaktuTerlambat(Request $request)
    {
        $request->validate([
            'waktu_terlambat' => 'required|date_format:H:i',
            'waktu_terlambat_guru' => 'required|date_format:H:i',
        ]);
        Setting::set('waktu_terlambat', $request->waktu_terlambat);
        Setting::set('waktu_terlambat_guru', $request->waktu_terlambat_guru);

        return back()->with('success', 'Batas jam terlambat siswa & guru disimpan.');
    }

    public function setLokasiQr(Request $request)
    {
        $request->validate([
            'sekolah_lat' => 'nullable|numeric|between:-90,90',
            'sekolah_lng' => 'nullable|numeric|between:-180,180',
            'absen_radius' => 'required|integer|min:10|max:5000',
            'qr_absensi_mode' => 'nullable|in:harian,tetap',
            'sekolah_geo_points' => 'nullable|string|max:8000',
            'absen_rush_bonus' => 'nullable|integer|min:0|max:500',
            'absen_rush_start' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'absen_rush_end' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ]);

        $rushStart = $this->normalizeClockHm($request->input('absen_rush_start', '06:30'));
        $rushEnd = $this->normalizeClockHm($request->input('absen_rush_end', '07:45'));
        if ($rushStart === null || $rushEnd === null) {
            return back()->withErrors([
                'absen_rush_start' => 'Jam zona sibuk harus valid (HH:MM), contoh 06:30–07:45.',
            ])->withInput();
        }

        $points = json_decode((string) $request->input('sekolah_geo_points', '[]'), true);
        if (!is_array($points)) {
            return back()->withErrors(['sekolah_geo_points' => 'Format titik tambahan tidak valid.'])->withInput();
        }

        $clean = [];
        $skipped = 0;
        foreach ($points as $p) {
            if (!is_array($p) || !isset($p['lat'], $p['lng']) || !is_numeric($p['lat']) || !is_numeric($p['lng'])) {
                $skipped++;
                continue;
            }
            $lat = (float) $p['lat'];
            $lng = (float) $p['lng'];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $skipped++;
                continue;
            }
            $item = [
                'label' => \App\Support\Geofence::sanitizePointLabel((string) ($p['label'] ?? 'Titik')),
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
            ];
            if (isset($p['radius']) && $p['radius'] !== '' && $p['radius'] !== null && is_numeric($p['radius'])) {
                // Titik tambahan dibatasi 1000 m agar mis-set 5000 m tidak menonaktifkan geofence.
                $item['radius'] = max(10, min(1000, (int) $p['radius']));
            }
            $clean[] = $item;
            if (count($clean) >= 8) {
                break;
            }
        }

        Setting::set('sekolah_lat', $request->sekolah_lat);
        Setting::set('sekolah_lng', $request->sekolah_lng);
        Setting::set('absen_radius', $request->absen_radius);
        Setting::set('sekolah_geo_points', json_encode($clean, JSON_UNESCAPED_UNICODE));
        Setting::set('absen_rush_bonus', (string) (int) $request->input('absen_rush_bonus', 100));
        Setting::set('absen_rush_start', $rushStart);
        Setting::set('absen_rush_end', $rushEnd);
        Setting::set('qr_absensi_aktif', $request->boolean('qr_absensi_aktif') ? '1' : '0');
        Setting::set('qr_absensi_mode', $request->input('qr_absensi_mode', 'harian'));

        $msg = 'Lokasi & QR absensi disimpan.';
        if ($skipped > 0) {
            $msg .= " {$skipped} titik tambahan diabaikan karena data tidak valid.";
        }

        return back()->with('success', $msg);
    }

    /** Normalisasi input jam ke HH:MM; tolak nilai di luar 00:00–23:59. */
    private function normalizeClockHm(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '06:30';
        }
        if (!preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        if ($h > 23 || $i > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $i);
    }

    /** Buat ulang token QR mode "tetap" — dipakai bila QR lama dicurigai bocor/disalahgunakan. */
    public function regenerateQrTokenTetap()
    {
        Setting::set('qr_absensi_token_tetap', Str::random(12));

        return back()->with('success', 'Token QR tetap berhasil dibuat ulang. QR lama tidak berlaku lagi — cetak & tempel ulang QR yang baru.');
    }

    public function setMapelRapor(Request $request)
    {
        Setting::set('mapel_rapor', json_encode($request->input('mapels', [])));

        return back()->with('success', 'Setting mapel rapor disimpan.');
    }

    public function setTanggalRapor(Request $request)
    {
        $request->validate(['tanggal_rapor' => 'required|date']);
        Setting::set('tanggal_rapor', $request->tanggal_rapor);

        return back()->with('success', 'Tanggal rapor disimpan.');
    }

    public function setCaraAbsensi(Request $request)
    {
        $request->validate(['cara_absensi' => 'required|in:wajah,barcode,keduanya']);
        Setting::set('cara_absensi_guru', $request->cara_absensi);

        return back()->with('success', 'Cara absensi disimpan.');
    }

    /** Apa yang dibaca kamera halaman Scan Absensi: wajah saja, QR kartu saja, atau keduanya. */
    public function setScanKioskMode(Request $request)
    {
        $request->validate(['scan_kiosk_mode' => 'required|in:wajah,qr,keduanya']);
        Setting::set('scan_kiosk_mode', $request->scan_kiosk_mode);

        return back()->with('success', 'Mode kamera scan disimpan.');
    }

    /** Buat/ganti token link kiosk absensi publik. Mengganti token otomatis mematikan link lama. */
    public function regenerateKioskToken()
    {
        Setting::set('kiosk_token', Str::random(40));

        return back()->with('success', 'Link kiosk absensi berhasil dibuat ulang. Link lama tidak berlaku lagi.');
    }

    public function setAgendaWajibPulang(Request $request)
    {
        Setting::set('agenda_wajib_pulang', $request->boolean('agenda_wajib_pulang') ? '1' : '0');

        return back()->with('success', 'Pengaturan agenda sebelum pulang disimpan.');
    }

    /** Izinkan wali kelas melihat (read-only) nilai formatif/sumatif/PAS mapel lain di kelasnya. */
    public function setWalikelasLihatNilai(Request $request)
    {
        Setting::set('walikelas_lihat_nilai', $request->boolean('walikelas_lihat_nilai') ? '1' : '0');

        return back()->with('success', 'Pengaturan akses nilai wali kelas disimpan.');
    }

    /** Sistem aturan kedisiplinan siswa: 'poin' (ledger 100) atau 'p3' (Pelanggaran/Prestasi/Partisipasi). */
    public function setJenisAturan(Request $request)
    {
        $request->validate(['jenis_aturan' => 'required|in:poin,p3']);
        Setting::set('jenis_aturan', $request->jenis_aturan);

        return back()->with('success', 'Sistem aturan kedisiplinan disimpan.');
    }

    /** Aturan (poin/aturan lama) yang dipakai untuk auto-deduksi saat siswa terlambat absen. */
    public function setPoinTerlambatAturan(Request $request)
    {
        $request->validate(['poin_terlambat_aturan' => 'nullable|exists:aturan,uuid']);
        Setting::set('poin_terlambat_aturan', $request->poin_terlambat_aturan ?: '');

        return back()->with('success', 'Aturan poin keterlambatan disimpan.');
    }

    public function setRumusRapor(Request $request)
    {
        $request->validate([
            'rumus_rapor' => 'required|in:bagi3,bagi4,jumlahDulu',
        ]);
        Setting::set('rumus_rapor', $request->rumus_rapor);

        return back()->with('success', 'Rumus perhitungan nilai rapor berhasil diperbarui.');
    }

    public function setBarcodeAbsensi()
    {
        // Generate QR untuk setiap guru
        return redirect()->route('setting.index')->with('success', 'Barcode akan digenerate.');
    }

    /** Batas min/maks Tujuan Pembelajaran per materi (0 = tanpa batas). */
    public function setTpRange(Request $request)
    {
        $data = $request->validate([
            'tp_min' => 'nullable|integer|min:0|max:50',
            'tp_max' => 'nullable|integer|min:0|max:50',
        ]);
        $min = (int) ($data['tp_min'] ?? 0);
        $max = (int) ($data['tp_max'] ?? 0);
        if ($max > 0 && $min > $max) {
            return back()->with('error', 'Minimal TP tidak boleh lebih besar dari maksimal.');
        }
        Setting::set('tp_min', $min);
        Setting::set('tp_max', $max);

        return back()->with('success', 'Batas jumlah Tujuan Pembelajaran disimpan.');
    }

    /** ====== Konfigurasi Nilai Penjabaran (admin) ====== */
    public function penjabaran()
    {
        $pelajarans = Pelajaran::with('penjabaranKomponen')->orderBy('urutan')->orderBy('nama')->get();

        return view('setting.penjabaran', compact('pelajarans'));
    }

    public function penjabaranSave(Request $request)
    {
        $data = $request->validate([
            'k_uuid' => 'array',
            'k_uuid.*' => 'nullable|string',
            'k_pelajaran' => 'array',
            'k_pelajaran.*' => 'nullable|string',
            'k_nama' => 'array',
            'k_nama.*' => 'nullable|string|max:60',
        ]);

        DB::transaction(function () use ($data) {
            $keep = [];
            $urutByPel = [];
            foreach ($data['k_nama'] ?? [] as $i => $nama) {
                $nama = trim((string) $nama);
                $pel = $data['k_pelajaran'][$i] ?? null;
                if ($nama === '' || ! $pel) {
                    continue;
                }
                $uuid = $data['k_uuid'][$i] ?? null;
                $urut = ($urutByPel[$pel] = ($urutByPel[$pel] ?? 0) + 1);
                if ($uuid && ($row = PenjabaranKomponen::find($uuid))) {
                    $row->update(['nama' => $nama, 'urutan' => $urut]);
                    $keep[] = $uuid;
                } else {
                    $keep[] = PenjabaranKomponen::create(['id_pelajaran' => $pel, 'nama' => $nama, 'urutan' => $urut])->uuid;
                }
            }
            // hapus komponen yang dibuang dari form (beserta nilainya)
            $dibuang = PenjabaranKomponen::whereNotIn('uuid', $keep ?: ['-'])->pluck('uuid');
            if ($dibuang->isNotEmpty()) {
                NilaiPenjabaran::whereIn('id_komponen', $dibuang)->delete();
                PenjabaranKomponen::whereIn('uuid', $dibuang)->delete();
            }
        });

        return back()->with('success', 'Konfigurasi nilai penjabaran disimpan.');
    }

    /** Halaman pengaturan Kop Surat rapor (admin: logo, teks, backdrop). */
    public function kopRapor()
    {
        abort_unless(auth()->user()->canAccess('manage_settings'), 403);
        $settings = Setting::pluck('value', 'key');

        return view('setting.kop-rapor', compact('settings'));
    }

    public function kopRaporSave(Request $request)
    {
        abort_unless(auth()->user()->canAccess('manage_settings'), 403);
        $request->validate([
            'kop_logo_kiri' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'kop_logo_kanan' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'kop_backdrop' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'kop_teks' => 'nullable|string|max:20000',
        ]);

        foreach (['kop_logo_kiri', 'kop_logo_kanan', 'kop_backdrop'] as $field) {
            if ($request->hasFile($field)) {
                // hapus file lama bila ada
                $old = Setting::get($field);
                if ($old && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
                $ext = Uploads::safeExtension($request->file($field), ['png', 'jpg', 'jpeg', 'webp'], 'png');
                $path = $request->file($field)->storeAs('kop', $field.'_'.now()->format('YmdHis').'.'.$ext, 'public');
                Setting::set($field, $path);
            } elseif ($request->boolean('hapus_'.$field)) {
                $old = Setting::get($field);
                if ($old && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
                Setting::set($field, '');
            }
        }

        Setting::set('kop_teks', $request->input('kop_teks', ''));

        return back()->with('success', 'Pengaturan kop surat rapor disimpan.');
    }

    /**
     * Unduh Aplikasi (APK Android + Installer Windows). Admin mengunggah file,
     * mengaktifkan/menonaktifkan fitur, dan opsional label versi. File disimpan di
     * disk privat `local` (storage/app/private) — hanya bisa diunduh lewat route
     * ber-auth (AppDownloadController), tidak bisa diakses langsung via URL.
     */
    public function setAppDownload(Request $request)
    {
        abort_unless(auth()->user()->canAccess('manage_settings'), 403);

        $request->validate([
            'app_apk' => ['nullable', 'file', 'max:307200', function ($attr, $value, $fail) {
                if ($value && strtolower($value->getClientOriginalExtension()) !== 'apk') {
                    $fail('File aplikasi Android harus berekstensi .apk');
                }
            }],
            'app_windows' => ['nullable', 'file', 'max:307200', function ($attr, $value, $fail) {
                if ($value && ! in_array(strtolower($value->getClientOriginalExtension()), ['exe', 'msi'], true)) {
                    $fail('File installer Windows harus berekstensi .exe atau .msi');
                }
            }],
            'app_apk_version' => ['nullable', 'string', 'max:40'],
            'app_windows_version' => ['nullable', 'string', 'max:40'],
        ], [], [
            'app_apk' => 'file APK',
            'app_windows' => 'installer Windows',
        ]);

        Setting::set('app_download_aktif', $request->boolean('app_download_aktif') ? '1' : '0');
        Setting::set('app_apk_version', trim((string) $request->input('app_apk_version', '')));
        Setting::set('app_windows_version', trim((string) $request->input('app_windows_version', '')));

        $this->handleAppFile($request, 'app_apk', 'apk', 'app_apk_path', 'app_apk_name', 'apk');
        $this->handleAppFile($request, 'app_windows', 'windows', 'app_windows_path', 'app_windows_name');

        return back()->with('success', 'Pengaturan unduh aplikasi disimpan.');
    }

    /** On/off modul sekolah (tab Fitur). Default aktif; bisa dimatikan sementara. */
    public function updateFitur(Request $request)
    {
        foreach (ModulAktif::kodeValid() as $kode) {
            Setting::set(ModulAktif::settingKey($kode), $request->boolean($kode) ? '1' : '0');
        }

        return back()->with('success', 'Pengaturan fitur disimpan. Modul yang dimatikan disembunyikan dari menu dan tidak bisa diakses.');
    }

    /** Toggle launcher + Canva Connect (jalur gratis belajar.id). */
    public function updateIntegrasi(Request $request)
    {
        $canva = app(\App\Services\CanvaConnectService::class);

        $data = $request->validate([
            'tp_launcher_aktif' => ['sometimes', 'boolean'],
            'canva_connect_aktif' => ['sometimes', 'boolean'],
            'canva_allowed_email_suffix' => [
                'nullable',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail) use ($canva): void {
                    $value = strtolower(trim((string) $value));
                    if ($value === '') {
                        return;
                    }
                    if (! $canva->isValidBelajarIdSuffix($value)) {
                        $fail('Suffix harus berakhiran .belajar.id (contoh .belajar.id atau @smpn1.belajar.id).');
                    }
                },
            ],
        ]);

        Setting::set('tp_launcher_aktif', $request->boolean('tp_launcher_aktif') ? '1' : '0');
        Setting::set('canva_connect_aktif', $request->boolean('canva_connect_aktif') ? '1' : '0');

        $suffix = strtolower(trim((string) ($data['canva_allowed_email_suffix'] ?? '')));
        if ($suffix === '') {
            $suffix = '.belajar.id';
        }
        Setting::set('canva_allowed_email_suffix', $suffix);

        return back()->with('success', 'Pengaturan integrasi Asisten Guru & Canva disimpan.');
    }

    /** Simpan/hapus satu file aplikasi ke disk privat + catat nama asli untuk nama unduhan. */
    private function handleAppFile(Request $request, string $field, string $slug, string $pathKey, string $nameKey, ?string $forcedExtension = null): void
    {
        if ($request->hasFile($field)) {
            $old = Setting::get($pathKey);
            if ($old && Storage::disk('local')->exists($old)) {
                Storage::disk('local')->delete($old);
            }
            $file = $request->file($field);
            $ext = $forcedExtension ?: strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('app-downloads', $slug.'_'.now()->format('YmdHis').'.'.$ext, 'local');
            Setting::set($pathKey, $path);
            Setting::set($nameKey, $file->getClientOriginalName());
        } elseif ($request->boolean('hapus_'.$field)) {
            $old = Setting::get($pathKey);
            if ($old && Storage::disk('local')->exists($old)) {
                Storage::disk('local')->delete($old);
            }
            Setting::set($pathKey, '');
            Setting::set($nameKey, '');
        }
    }

    public function roles()
    {
        $roles = self::VALID_ROLES;
        $permissions = self::PERMISSION_LABELS;

        $granted = RolePermission::all()->groupBy('role')->map(function ($items) {
            return $items->pluck('permission')->toArray();
        })->toArray();

        $settings = Setting::pluck('value', 'key');
        $modulFitur = ModulAktif::semua();

        return view('settings.roles', compact('roles', 'permissions', 'granted', 'settings', 'modulFitur'));
    }

    public function rolesSave(Request $request)
    {
        // 'perms' opsional (boleh kosong bila semua checkbox dilepas), tapi kalau
        // ada wajib berbentuk array — cegah TypeError dari input non-array.
        $data = $request->validate(['perms' => 'nullable|array']);
        $perms = $data['perms'] ?? [];

        $validRoles = self::VALID_ROLES;
        $validPermissions = array_keys(self::PERMISSION_LABELS);

        DB::transaction(function () use ($perms, $validRoles, $validPermissions) {
            RolePermission::query()->delete();
            foreach ($perms as $role => $rolePerms) {
                // Whitelist role & pastikan nilainya array — abaikan entri di luar itu
                // (mencegah role/permission sembarang tersimpan ke tabel hak akses).
                if (! in_array($role, $validRoles, true) || ! is_array($rolePerms)) {
                    continue;
                }
                foreach ($rolePerms as $permission => $val) {
                    if ($val && in_array($permission, $validPermissions, true)) {
                        RolePermission::create([
                            'role' => $role,
                            'permission' => $permission,
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Pengaturan hak akses peran berhasil disimpan.');
    }
}
