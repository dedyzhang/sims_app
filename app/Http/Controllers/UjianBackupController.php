<?php

namespace App\Http\Controllers;

use App\Models\Ujian;
use App\Models\UjianPaket;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\UjianKelas;
use App\Models\UjianSesi;
use App\Models\UjianJadwal;
use App\Models\UjianRuangan;
use App\Models\UjianRuanganPeserta;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianPelanggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UjianBackupController extends Controller
{
    public function backup(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->password, auth()->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password tidak valid.']);
        }

        $ujian->load(['paket', 'soal.opsi', 'kelas.attempts.jawaban', 'kelas.attempts.pelanggaran']);

        $data = $ujian->toArray();
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        $filename = 'Backup_Ujian_' . str()->slug($ujian->judul) . '_' . date('Ymd_His') . '.json';
        
        return response()->streamDownload(function() use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'backup_file' => 'required|file'
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password tidak valid.']);
        }

        $json = file_get_contents($request->file('backup_file')->getRealPath());
        $data = json_decode($json, true);

        if (!$data || !isset($data['uuid'])) {
            return back()->with('error', 'File backup tidak valid atau rusak.');
        }

        DB::beginTransaction();
        try {
            // Restore Ujian
            $ujianData = collect($data)->except(["paket", "kelas", "sesi", "jadwal", "ruangan", "attempts", "dibuat_oleh"])->toArray();
            Ujian::withTrashed()->updateOrCreate(['uuid' => $ujianData['uuid']], $ujianData);
            
            // Restore Paket & Soal
            if (isset($data['paket'])) {
                $paketData = collect($data['paket'])->except('soal')->toArray();
                UjianPaket::updateOrCreate(['uuid' => $paketData['uuid']], $paketData);
                
                if (isset($data['soal'])) {
                    foreach ($data['soal'] as $soal) {
                        $soalData = collect($soal)->except('opsi')->toArray();
                        UjianSoal::updateOrCreate(['uuid' => $soalData['uuid']], $soalData);
                        
                        if (isset($soal['opsi'])) {
                            foreach ($soal['opsi'] as $opsi) {
                                UjianSoalOpsi::updateOrCreate(['uuid' => $opsi['uuid']], $opsi);
                            }
                        }
                    }
                }
            }

            // Restore Kelas & Attempts
            if (isset($data['kelas'])) {
                foreach ($data['kelas'] as $kelas) {
                    $kelasData = collect($kelas)->except('attempts')->toArray();
                    UjianKelas::updateOrCreate(['uuid' => $kelasData['uuid']], $kelasData);
                    
                    if (isset($kelas['attempts'])) {
                        foreach ($kelas['attempts'] as $attempt) {
                            $attemptData = collect($attempt)->except(['jawaban', 'pelanggaran'])->toArray();
                            UjianAttempt::updateOrCreate(['uuid' => $attemptData['uuid']], $attemptData);
                            
                            if (isset($attempt['jawaban'])) {
                                foreach ($attempt['jawaban'] as $jawaban) {
                                    UjianJawaban::updateOrCreate(['uuid' => $jawaban['uuid']], $jawaban);
                                }
                            }
                            if (isset($attempt['pelanggaran'])) {
                                foreach ($attempt['pelanggaran'] as $pelanggaran) {
                                    UjianPelanggaran::updateOrCreate(['uuid' => $pelanggaran['uuid']], $pelanggaran);
                                }
                            }
                        }
                    }
                }
            }

            // Un-delete Ujian if it was soft deleted
            $ujian = Ujian::withTrashed()->find($data['uuid']);
            if ($ujian && $ujian->trashed()) {
                $ujian->restore();
            }

            DB::commit();
            return redirect()->route('ujian.index')->with('success', 'Data ujian berhasil di-restore.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memulihkan data: ' . $e->getMessage());
        }
    }
}







