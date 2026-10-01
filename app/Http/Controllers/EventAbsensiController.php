<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\EventPeserta;

class EventAbsensiController extends Controller
{
    public function show($qr_token)
    {
        $peserta = EventPeserta::with('kegiatan')->where('qr_token', $qr_token)->firstOrFail();
        $kegiatan = $peserta->kegiatan;
        
        $waktuAcara = \Carbon\Carbon::parse($kegiatan->tanggal_mulai->format('Y-m-d') . ' ' . ($kegiatan->waktu_mulai ?? '00:00:00'));
        if (now()->lt($waktuAcara)) {
            session()->now('error', 'Absensi belum dibuka. Acara baru akan dimulai pada ' . $waktuAcara->translatedFormat('d F Y, H:i') . '.');
        }

        $namaSekolah = \App\Models\Setting::get('nama_sekolah', 'SIMS');
        $logo = \App\Models\Setting::get('sekolah_logo');
        $sekolahLogoUrl = ($logo && file_exists(storage_path('app/public/' . $logo))) ? asset('storage/' . $logo) : null;

        return view('kegiatan.hadir', compact('peserta', 'kegiatan', 'namaSekolah', 'sekolahLogoUrl'));
    }

    public function mark(Request $request, $qr_token)
    {
        $peserta = EventPeserta::with('kegiatan')->where('qr_token', $qr_token)->firstOrFail();
        $kegiatan = $peserta->kegiatan;
        
        $waktuAcara = \Carbon\Carbon::parse($kegiatan->tanggal_mulai->format('Y-m-d') . ' ' . ($kegiatan->waktu_mulai ?? '00:00:00'));
        if (now()->lt($waktuAcara)) {
            return back()->with('error', 'Absensi belum dibuka. Acara baru akan dimulai pada ' . $waktuAcara->translatedFormat('d F Y, H:i') . '.');
        }

        if ($peserta->status_kehadiran !== 'hadir') {
            $peserta->update([
                'status_kehadiran' => 'hadir',
                'waktu_hadir' => now(),
            ]);
            return back()->with('success', 'Kehadiran berhasil dicatat.');
        }

        return back()->with('info', 'Anda sudah tercatat hadir sebelumnya.');
    }
}
