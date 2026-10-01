<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\EventKegiatan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Models\Setting; // Asumsi untuk logo/kop surat

class EventKegiatanController extends Controller
{
    public function index()
    {
        $kegiatans = EventKegiatan::withCount('pesertas')->latest()->get();
        return view('kegiatan.index', compact('kegiatans'));
    }

    public function create()
    {
        return view('kegiatan.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_kegiatan' => 'required|string|max:255',
            'tema' => 'nullable|string',
            'tanggal_mulai' => 'required|date',
            'waktu_mulai' => 'nullable|string',
            'tempat' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'pesan_pendaftaran' => 'nullable|string',
        ]);

        // Check for custom form_fields
        if ($request->has('form_fields')) {
            $validated['form_fields'] = json_decode($request->form_fields, true);
        } else {
            // Default form fields: nama, instansi (wajib)
            $validated['form_fields'] = [
                ['name' => 'nama', 'label' => 'Nama Lengkap (Gelar)', 'type' => 'text', 'required' => true],
                ['name' => 'instansi', 'label' => 'Instansi', 'type' => 'text', 'required' => true],
                ['name' => 'nohp', 'label' => 'No. HP / WA', 'type' => 'text', 'required' => false],
            ];
        }

        EventKegiatan::create($validated);

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil dibuat.');
    }

    public function show(EventKegiatan $kegiatan)
    {
        $kegiatan->load('pesertas');
        return view('kegiatan.show', compact('kegiatan'));
    }

    public function edit(EventKegiatan $kegiatan)
    {
        return view('kegiatan.edit', compact('kegiatan'));
    }

    public function update(Request $request, EventKegiatan $kegiatan)
    {
        $validated = $request->validate([
            'nama_kegiatan' => 'required|string|max:255',
            'tema' => 'nullable|string|max:255',
            'tanggal_mulai' => 'required|date',
            'waktu_mulai' => 'nullable|string',
            'tempat' => 'nullable|string',
            'deskripsi' => 'nullable|string',
            'pesan_pendaftaran' => 'nullable|string',
        ]);

        if ($request->has('form_fields')) {
            $validated['form_fields'] = json_decode($request->form_fields, true);
        }

        $kegiatan->update($validated);

        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(EventKegiatan $kegiatan)
    {
        $kegiatan->delete();
        return redirect()->route('kegiatan.index')->with('success', 'Kegiatan berhasil dihapus.');
    }

    public function printQr(EventKegiatan $kegiatan)
    {
        $kegiatan->load('pesertas');
        
        $getImg = function(string $key, string $default) {
            $v = \App\Models\Setting::get($key);
            if ($v && file_exists(storage_path('app/public/' . $v))) return asset('storage/' . $v);
            if (file_exists(public_path($default))) return asset($default);
            return null;
        };

        $kopLogoKiri = $getImg('kop_logo_kiri', 'img/tutwuri.png');

        $kopLogoKanan = $getImg('kop_logo_kanan', 'img/maitreyawira_square.png');
        $kopTeks = \App\Models\Setting::get('kop_teks');
        
        $logo = \App\Models\Setting::get('sekolah_logo');
        $sekolahLogoUrl = ($logo && file_exists(storage_path('app/public/' . $logo))) ? asset('storage/' . $logo) : null;
        
        $sekolah = [
            'nama' => \App\Models\Setting::get('nama_sekolah', 'SIMS'),
            'alamat' => \App\Models\Setting::get('alamat_sekolah'),
            'kota' => \App\Models\Setting::get('kota'),
            'provinsi' => \App\Models\Setting::get('provinsi'),
            'telp' => \App\Models\Setting::get('telp_sekolah'),
            'npsn' => \App\Models\Setting::get('npsn'),
            'kepala' => \App\Models\Setting::get('kepala_sekolah', 'Nama Kepsek'),
            'nip' => \App\Models\Setting::get('nip_kepala'),
        ];

        return view('kegiatan.cetak-qr', compact('kegiatan', 'kopLogoKiri', 'kopLogoKanan', 'kopTeks', 'sekolah', 'sekolahLogoUrl'));
    }

    public function printPdf(EventKegiatan $kegiatan)
    {
        $kegiatan->load(['pesertas' => function($q) {
            $q->orderBy('waktu_hadir', 'asc');
        }]);
        
        $getImg = function(string $key, string $default) {
            $v = \App\Models\Setting::get($key);
            if ($v && file_exists(storage_path('app/public/' . $v))) return public_path('storage/' . $v);
            if (file_exists(public_path($default))) return public_path($default);
            return null;
        };

        $kopLogoKiri = $getImg('kop_logo_kiri', 'img/tutwuri.png');

        $kopLogoKanan = $getImg('kop_logo_kanan', 'img/maitreyawira_square.png');
        $kopTeks = \App\Models\Setting::get('kop_teks');
        
        $sekolah = [
            'nama' => \App\Models\Setting::get('nama_sekolah', 'SIMS'),
            'alamat' => \App\Models\Setting::get('alamat_sekolah'),
            'kota' => \App\Models\Setting::get('kota'),
            'provinsi' => \App\Models\Setting::get('provinsi'),
            'telp' => \App\Models\Setting::get('telp_sekolah'),
            'npsn' => \App\Models\Setting::get('npsn'),
            'kepala' => \App\Models\Setting::get('kepala_sekolah', 'Nama Kepsek'),
            'nip' => \App\Models\Setting::get('nip_kepala'),
        ];
        
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('kegiatan.cetak-pdf', compact('kegiatan', 'kopLogoKiri', 'kopLogoKanan', 'kopTeks', 'sekolah'));
        return $pdf->stream('Daftar_Hadir_'.$kegiatan->nama_kegiatan.'.pdf');
    }
}
