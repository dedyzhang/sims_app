<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\NilaiPenjabaran;
use App\Models\Pelajaran;
use App\Models\PenjabaranKomponen;
use App\Models\Semester;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    public function setLokasiQr(Request $request)
    {
        $request->validate([
            'sekolah_lat'  => 'nullable|numeric|between:-90,90',
            'sekolah_lng'  => 'nullable|numeric|between:-180,180',
            'absen_radius' => 'required|integer|min:10|max:5000',
        ]);
        Setting::set('sekolah_lat', $request->sekolah_lat);
        Setting::set('sekolah_lng', $request->sekolah_lng);
        Setting::set('absen_radius', $request->absen_radius);
        Setting::set('qr_absensi_aktif', $request->boolean('qr_absensi_aktif') ? '1' : '0');
        return back()->with('success', 'Lokasi & QR absensi disimpan.');
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
            'k_uuid'      => 'array',
            'k_uuid.*'    => 'nullable|string',
            'k_pelajaran' => 'array',
            'k_pelajaran.*' => 'nullable|string',
            'k_nama'      => 'array',
            'k_nama.*'    => 'nullable|string|max:60',
        ]);

        DB::transaction(function () use ($data) {
            $keep = [];
            $urutByPel = [];
            foreach ($data['k_nama'] ?? [] as $i => $nama) {
                $nama = trim((string) $nama);
                $pel  = $data['k_pelajaran'][$i] ?? null;
                if ($nama === '' || !$pel) continue;
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
        abort_unless(auth()->user()->isAdmin(), 403);
        $settings = Setting::pluck('value', 'key');
        return view('setting.kop-rapor', compact('settings'));
    }

    public function kopRaporSave(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $request->validate([
            'kop_logo_kiri'  => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'kop_logo_kanan' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'kop_backdrop'   => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'kop_teks'       => 'nullable|string|max:20000',
        ]);

        foreach (['kop_logo_kiri', 'kop_logo_kanan', 'kop_backdrop'] as $field) {
            if ($request->hasFile($field)) {
                // hapus file lama bila ada
                $old = Setting::get($field);
                if ($old && Storage::disk('public')->exists($old)) Storage::disk('public')->delete($old);
                $ext  = $request->file($field)->getClientOriginalExtension() ?: 'png';
                $path = $request->file($field)->storeAs('kop', $field . '_' . now()->format('YmdHis') . '.' . $ext, 'public');
                Setting::set($field, $path);
            } elseif ($request->boolean('hapus_' . $field)) {
                $old = Setting::get($field);
                if ($old && Storage::disk('public')->exists($old)) Storage::disk('public')->delete($old);
                Setting::set($field, '');
            }
        }

        Setting::set('kop_teks', $request->input('kop_teks', ''));

        return back()->with('success', 'Pengaturan kop surat rapor disimpan.');
    }
}
