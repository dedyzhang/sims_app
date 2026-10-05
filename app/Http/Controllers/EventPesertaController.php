<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\EventKegiatan;
use App\Models\EventPeserta;
use Illuminate\Support\Str;

class EventPesertaController extends Controller
{
    public function create(EventKegiatan $kegiatan)
    {
        $namaSekolah = \App\Models\Setting::get('nama_sekolah', 'SIMS');
        $logo = \App\Models\Setting::get('sekolah_logo');
        $sekolahLogoUrl = ($logo && file_exists(storage_path('app/public/' . $logo))) ? asset('storage/' . $logo) : null;
        
        return view('kegiatan.daftar', compact('kegiatan', 'namaSekolah', 'sekolahLogoUrl'));
    }

    public function store(Request $request, EventKegiatan $kegiatan)
    {
        $fields = collect($kegiatan->form_fields);
        $rules = [];
        foreach ($fields as $f) {
            $rules[$f['name']] = $f['required'] ? 'required' : 'nullable';
        }

        $validated = $request->validate($rules);

        // Generate QR Token unik 6-8 karakter
        $token = strtoupper(Str::random(8));
        while (EventPeserta::where('qr_token', $token)->exists()) {
            $token = strtoupper(Str::random(8));
        }

        EventPeserta::create([
            'event_kegiatan_id' => $kegiatan->id,
            'user_id' => auth()->id(), // null jika tidak login
            'biodata' => $validated,
            'qr_token' => $token,
            'status_kehadiran' => 'belum',
        ]);

        $pesan = $kegiatan->pesan_pendaftaran ?: 'Pendaftaran berhasil. Silakan hadir pada waktu yang ditentukan untuk scan QR Code.';
        return back()->with('success', $pesan);
    }

    public function edit(EventPeserta $peserta)
    {
        $kegiatan = $peserta->kegiatan;
        return view('kegiatan.peserta_edit', compact('peserta', 'kegiatan'));
    }

    public function update(Request $request, EventPeserta $peserta)
    {
        $kegiatan = $peserta->kegiatan;
        $fields = collect($kegiatan->form_fields);
        $rules = [];
        foreach ($fields as $f) {
            $rules[$f['name']] = $f['required'] ? 'required' : 'nullable';
        }

        $validated = $request->validate($rules);

        $peserta->update([
            'biodata' => $validated,
        ]);

        return redirect()->route('kegiatan.show', $kegiatan)->with('success', 'Data peserta berhasil diperbarui.');
    }

    public function destroy(EventPeserta $peserta)
    {
        $kegiatan = $peserta->kegiatan;
        $peserta->delete();
        return redirect()->route('kegiatan.show', $kegiatan)->with('success', 'Peserta berhasil dihapus.');
    }

    public function resetAbsensi(EventPeserta $peserta)
    {
        $kegiatan = $peserta->kegiatan;
        $peserta->update([
            'status_kehadiran' => 'belum',
            'waktu_hadir' => null,
        ]);
        return redirect()->route('kegiatan.show', $kegiatan)->with('success', 'Kehadiran peserta berhasil dibatalkan.');
    }
}
