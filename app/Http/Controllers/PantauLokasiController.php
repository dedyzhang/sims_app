<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\Geofence;
use App\Support\PantauLokasi;
use Illuminate\Http\Request;

class PantauLokasiController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless(PantauLokasi::canAccess($user), 403, 'Fitur Pantau Lokasi tidak tersedia untuk akun Anda, atau sedang dinonaktifkan admin.');
        abort_unless(PantauLokasi::sekolahPinSiap(), 403, 'Lokasi sekolah belum diatur admin. Pantau Lokasi hanya menampilkan titik di dalam area sekolah.');

        $schoolWide = PantauLokasi::canViewSchoolWide($user);
        $walikelasKelas = PantauLokasi::walikelasKelasId($user);
        $anakIds = PantauLokasi::anakIds($user);
        $isOrtu = $user->access === 'orangtua';

        $data = $request->validate([
            'tanggal' => 'nullable|date',
            'kelas'   => 'nullable|string',
            'siswa'   => 'nullable|string',
        ]);
        $tanggal = $data['tanggal'] ?? now()->toDateString();

        $kelasList = collect();
        $siswaList = collect();

        if ($schoolWide) {
            $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        } elseif ($walikelasKelas) {
            $kelasList = Kelas::where('uuid', $walikelasKelas)->get();
        } elseif ($isOrtu) {
            $siswaList = Siswa::with('kelas')
                ->whereIn('uuid', $anakIds)
                ->orderBy('nama')
                ->get();
        }

        $selectedKelas = null;
        if ($schoolWide) {
            $selectedKelas = $data['kelas'] ?? null;
        } elseif ($walikelasKelas) {
            $selectedKelas = $walikelasKelas;
        }

        $selectedSiswa = $data['siswa'] ?? null;
        if ($isOrtu) {
            // Ortu hanya boleh filter di antara anaknya; default anak pertama.
            if (!$selectedSiswa || !in_array($selectedSiswa, $anakIds, true)) {
                $selectedSiswa = $siswaList->first()?->uuid;
            }
        }

        $query = Absensi::query()
            ->with(['siswa:uuid,nama,id_kelas', 'siswa.kelas:uuid,tingkat,kelas'])
            ->whereDate('tanggal', $tanggal)
            ->whereNotNull('geo_lat')
            ->whereNotNull('geo_lng');

        if ($schoolWide) {
            if ($selectedKelas) {
                $query->where('id_kelas', $selectedKelas);
            }
            if ($selectedSiswa) {
                $query->where('id_siswa', $selectedSiswa);
            }
        } elseif ($walikelasKelas) {
            $query->where('id_kelas', $walikelasKelas);
            if ($selectedSiswa) {
                $query->where('id_siswa', $selectedSiswa);
            }
        } else {
            $query->whereIn('id_siswa', $anakIds);
            if ($selectedSiswa) {
                $query->where('id_siswa', $selectedSiswa);
            }
        }

        // Daftar siswa untuk filter (admin/wali setelah kelas dipilih).
        if (!$isOrtu) {
            $siswaFilterQ = Siswa::query()->orderBy('nama');
            if ($selectedKelas) {
                $siswaFilterQ->where('id_kelas', $selectedKelas);
            } elseif ($walikelasKelas) {
                $siswaFilterQ->where('id_kelas', $walikelasKelas);
            }
            if ($schoolWide || $walikelasKelas) {
                $siswaList = $siswaFilterQ->limit(500)->get(['uuid', 'nama', 'id_kelas']);
            }
        }

        // Pin & radius dibaca SEKALI (bukan per baris). sekolahPinSiap() sudah
        // dipastikan di atas, jadi areaSekolah() tidak null di sini.
        $area = PantauLokasi::areaSekolah();
        $radius = $area['radius'];
        $effectiveRadius = Geofence::effectiveRadius($radius);

        // Saring kasar di SQL memakai kotak pembatas: hanya baris di sekitar sekolah
        // yang ditarik, bukan seluruh absensi ber-GPS hari itu. Kotak sengaja memakai
        // effectiveRadius (superset lingkaran) agar tak ada titik sah yang terbuang;
        // pengecekan lingkaran yang presisi tetap dilakukan setelahnya.
        $deltaLat = $effectiveRadius / 111320;
        $deltaLng = $effectiveRadius / (111320 * max(cos(deg2rad($area['lat'])), 0.000001));
        $query->whereBetween('geo_lat', [$area['lat'] - $deltaLat, $area['lat'] + $deltaLat])
            ->whereBetween('geo_lng', [$area['lng'] - $deltaLng, $area['lng'] + $deltaLng]);

        // Batas aman agar satu hari di sekolah besar tidak menarik ribuan baris ke memori.
        $maxTitik = 1000;
        $totalKandidat = (clone $query)->count();
        $rows = $query->orderBy('jam_masuk')->limit($maxTitik)->get();
        $titikTerpotong = $totalKandidat > $maxTitik;

        // Pengecekan lingkaran presisi — tanpa akses Setting di dalam loop.
        $markers = $rows
            ->filter(fn (Absensi $a) => PantauLokasi::titikDiDalamArea($area, (float) $a->geo_lat, (float) $a->geo_lng))
            ->map(fn (Absensi $a) => [
                'lat'      => (float) $a->geo_lat,
                'lng'      => (float) $a->geo_lng,
                'nama'     => $a->siswa?->nama ?? '—',
                'kelas'    => $a->siswa?->kelas
                    ? trim(($a->siswa->kelas->tingkat ?? '') . ' ' . ($a->siswa->kelas->kelas ?? ''))
                    : '—',
                'jam'      => $a->jam_masuk ? substr((string) $a->jam_masuk, 0, 5) : '—',
                'status'   => Absensi::STATUS[$a->status] ?? $a->status,
                'jarak'    => $a->geo_jarak,
                'accuracy' => $a->geo_accuracy,
                'id_siswa' => $a->id_siswa,
            ])
            ->values();

        return view('pantau-lokasi.index', [
            'tanggal'         => $tanggal,
            'kelasList'       => $kelasList,
            'siswaList'       => $siswaList,
            'selectedKelas'   => $selectedKelas,
            'selectedSiswa'   => $selectedSiswa,
            'markers'         => $markers,
            'schoolLat'       => Setting::get('sekolah_lat'),
            'schoolLng'       => Setting::get('sekolah_lng'),
            'radius'          => $radius,
            'effectiveRadius' => $effectiveRadius,
            'isOrtu'          => $isOrtu,
            'schoolWide'      => $schoolWide,
            'walikelasKelas'  => $walikelasKelas,
            'titikTerpotong'  => $titikTerpotong,
            'maxTitik'        => $maxTitik,
            'totalKandidat'   => $totalKandidat,
        ]);
    }
}
