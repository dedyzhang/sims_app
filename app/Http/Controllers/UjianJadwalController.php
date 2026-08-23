<?php

namespace App\Http\Controllers;

use App\Models\UjianPaket;
use App\Models\UjianRuangan;
use App\Models\UjianJadwal;
use App\Models\UjianSesi;
use App\Services\UjianJadwalSync;
use Illuminate\Http\Request;

/**
 * Jadwal hari×jam per-mapel dlm satu UjianPaket. Simpan/hapus di sini SEKALIGUS
 * mengisi ujian_kelas.dibuka_mulai/dibuka_sampai milik Ujian terkait (lihat
 * UjianJadwalSync) — jadi gating jam siswa (UjianPolicy::take()) otomatis aktif
 * tanpa perlu sentuh controller siswa sama sekali.
 */
class UjianJadwalController extends Controller
{
    private function bolehKelola($user, UjianPaket $paket): bool
    {
        return $user->isAdmin() || $user->canAccess('manage_ujian') || $paket->created_by === $user->uuid;
    }

    public function store(Request $request, UjianPaket $paket, UjianJadwalSync $sync)
    {
        abort_unless($this->bolehKelola($request->user(), $paket), 403);

        $data = $request->validate([
            'id_ujian'   => 'required|uuid|exists:ujians,uuid',
            'tanggal'    => 'required|date',
            'jam_mulai'  => 'required|date_format:H:i',
            'jam_selesai'=> 'required|date_format:H:i|after:jam_mulai',
            'sesi_label' => 'nullable|string|max:50',
        ]);
        $ujian = $paket->ujian()->findOrFail($data['id_ujian']);
        $this->validasiRentangTanggal($paket, $data['tanggal']);

        $data['id_ujian_paket'] = $paket->uuid;
        $data['id_sesi'] = $this->resolveIdSesi($paket, $data);
        UjianJadwal::create($data);
        $sync->terapkan($ujian);

        return back()->with('success', 'Jadwal ditambahkan — jendela buka/tutup ujian ini disesuaikan otomatis.');
    }

    public function update(Request $request, UjianPaket $paket, UjianJadwal $jadwal, UjianJadwalSync $sync)
    {
        $this->pastikanMilikPaket($paket, $jadwal);
        abort_unless($this->bolehKelola($request->user(), $paket), 403);

        $data = $request->validate([
            'tanggal'    => 'required|date',
            'jam_mulai'  => 'required|date_format:H:i',
            'jam_selesai'=> 'required|date_format:H:i|after:jam_mulai',
            'sesi_label' => 'nullable|string|max:50',
        ]);
        $this->validasiRentangTanggal($paket, $data['tanggal']);

        $data['id_sesi'] = $this->resolveIdSesi($paket, $data);
        $jadwal->update($data);
        $sync->terapkan($jadwal->ujian);

        return back()->with('success', 'Jadwal diperbarui.');
    }

    public function destroy(UjianPaket $paket, UjianJadwal $jadwal, UjianJadwalSync $sync)
    {
        $this->pastikanMilikPaket($paket, $jadwal);
        abort_unless($this->bolehKelola(request()->user(), $paket), 403);

        $ujian = $jadwal->ujian;
        $jadwal->delete();
        $sync->terapkan($ujian);

        return back()->with('success', 'Jadwal dihapus.');
    }

    /**
     * Cari/buat UjianSesi yg dinaungi jadwal ini — dikelompokkan lewat
     * (paket, tanggal, sesi_label, jam_mulai, jam_selesai). Label kosong (`''`)
     * dinormalisasi jadi NULL supaya 2 jadwal tanpa label di jam yg SAMA otomatis
     * dianggap 1 sesi (perilaku yg diinginkan), tapi jam beda tetap sesi terpisah.
     */
    private function resolveIdSesi(UjianPaket $paket, array $data): string
    {
        $label = ($data['sesi_label'] ?? '') === '' ? null : $data['sesi_label'];

        return UjianSesi::firstOrCreate([
            'id_ujian_paket' => $paket->uuid,
            'tanggal' => $data['tanggal'],
            'label' => $label,
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
        ])->uuid;
    }

    private function pastikanMilikPaket(UjianPaket $paket, UjianJadwal $jadwal): void
    {
        abort_unless($jadwal->id_ujian_paket === $paket->uuid, 404);
    }

    private function validasiRentangTanggal(UjianPaket $paket, string $tanggal): void
    {
        if ($paket->tanggal_mulai && $paket->tanggal_selesai) {
            abort_if(
                $tanggal < $paket->tanggal_mulai->toDateString() || $tanggal > $paket->tanggal_selesai->toDateString(),
                422,
                'Tanggal di luar rentang periode paket ini.'
            );
        }
    }
}
