<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SettingController extends Controller
{
    public function index()
    {
        $semester   = Semester::orderBy('tahun')->orderBy('semester')->get();
        $semesterAktif = Semester::aktif();
        $kelas      = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();

        $settings   = Setting::pluck('value', 'key');

        return view('setting.index', compact('semester', 'semesterAktif', 'kelas', 'pelajarans', 'settings'));
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
            'tahun'    => 'required|string',
        ]);
        Semester::create(['semester' => $request->semester, 'tahun' => $request->tahun, 'aktif' => false]);
        return back()->with('success', 'Semester ditambah.');
    }

    public function setIdentitasSekolah(Request $request)
    {
        $fields = ['nama_sekolah', 'npsn', 'alamat_sekolah', 'kepala_sekolah', 'nip_kepala', 'kota', 'provinsi', 'telp_sekolah'];
        foreach ($fields as $f) {
            if ($request->has($f)) Setting::set($f, $request->$f);
        }
        return back()->with('success', 'Identitas sekolah disimpan.');
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
            'waktu_terlambat'      => 'required|date_format:H:i',
            'waktu_terlambat_guru' => 'required|date_format:H:i',
        ]);
        Setting::set('waktu_terlambat', $request->waktu_terlambat);
        Setting::set('waktu_terlambat_guru', $request->waktu_terlambat_guru);
        return back()->with('success', 'Batas jam terlambat siswa & guru disimpan.');
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
        $request->validate(['cara_absensi' => 'required|in:barcode,manual']);
        Setting::set('cara_absensi_guru', $request->cara_absensi);
        return back()->with('success', 'Cara absensi disimpan.');
    }

    public function setRumusRapor(Request $request)
    {
        $request->validate([
            'bobot_harian' => 'required|integer|min:0|max:100',
            'bobot_pts'    => 'required|integer|min:0|max:100',
            'bobot_pas'    => 'required|integer|min:0|max:100',
        ]);
        Setting::set('bobot_harian', $request->bobot_harian);
        Setting::set('bobot_pts', $request->bobot_pts);
        Setting::set('bobot_pas', $request->bobot_pas);
        return back()->with('success', 'Rumus rapor disimpan.');
    }

    public function setBarcodeAbsensi()
    {
        // Generate QR untuk setiap guru
        return redirect()->route('setting.index')->with('success', 'Barcode akan digenerate.');
    }
}
