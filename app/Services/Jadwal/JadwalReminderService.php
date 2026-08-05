<?php

namespace App\Services\Jadwal;

use App\Models\Jadwal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/*
| Sumber tunggal "siapa mengajar apa, jam berapa, di kelas mana pada hari ini"
| untuk pengingat jadwal mengajar. Dipakai bersama oleh command
| jadwal:digest-harian & jadwal:sesi-reminder agar definisi "sesi mengajar
| sungguhan" tidak tersebar / mudah beda antar tempat.
*/
class JadwalReminderService
{
    /**
     * Sesi mengajar sungguhan pada tanggal tsb: hanya baris jadwal yg punya guru
     * DAN mata pelajaran. Slot istirahat/upacara/dll tidak menyimpan id_pelajaran,
     * jadi otomatis terkecualikan. Diurutkan berdasarkan jam_mulai.
     *
     * @return Collection<int, Jadwal>
     */
    public function sesiHariIni(Carbon $tanggal): Collection
    {
        $hari = $tanggal->dayOfWeekIso; // 1=Senin .. 7=Minggu (jadwals hanya 1..6)

        return Jadwal::query()
            ->with(['pelajaran', 'kelas', 'jam', 'guru.user'])
            ->where('hari', $hari)
            ->whereNotNull('id_guru')
            ->whereNotNull('id_pelajaran')
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * Waktu mulai sesi sebagai Carbon pada tanggal tsb. jadwals.jam_mulai diisi
     * dari slot JamPelajaran saat sel disimpan (JadwalController::saveCell), tapi
     * kita fallback ke relasi jam bila baris lama belum terisi.
     */
    public function waktuMulai(Jadwal $sesi, Carbon $tanggal): ?Carbon
    {
        $jam = $sesi->jam_mulai ?: $sesi->jam?->jam_mulai;

        return $jam ? Carbon::parse($tanggal->toDateString().' '.$jam) : null;
    }

    /** Jam mulai sesi dalam format "HH:MM" untuk teks notifikasi. */
    public function jamMulai(Jadwal $sesi): ?string
    {
        $jam = $sesi->jam_mulai ?: $sesi->jam?->jam_mulai;

        return $jam ? Carbon::parse($jam)->format('H:i') : null;
    }

    /**
     * Aturan grup KANONIK "slot mengajar": baris Jadwal utk (guru, kelas, mapel)
     * yg sama digabung jadi SATU slot dgn rentang jam_mulai(min)..jam_selesai(max).
     * Sumber kebenaran tunggal yg dipakai pengingat agenda MAUPUN
     * AgendaController (slotHari/slotHariBulk) agar keduanya tak pernah drift soal
     * "apa itu satu sesi". Murni operasi in-memory (tanpa query); baris HARUS sudah
     * difilter & di-eager-load (kelas/pelajaran/jam/guru) oleh pemanggil. Tidak
     * memfilter slot tanpa jam — itu keputusan pemanggil (pengingat butuh jam,
     * daftar agenda tetap menampilkannya).
     *
     * @param  Collection<int, Jadwal>  $jadwals
     * @return Collection<int, object{jadwal: Jadwal, id_jadwal: string, id_guru: ?string, id_kelas: ?string, id_pelajaran: ?string, jam_mulai: string, jam_selesai: string}>
     */
    public function kelompokkanSlot(Collection $jadwals): Collection
    {
        return $jadwals
            ->groupBy(fn (Jadwal $s) => $s->id_guru.'|'.$s->id_kelas.'|'.$s->id_pelajaran)
            ->map(function (Collection $rows) {
                $awal = $rows->first();

                // Buang nilai kosong sebelum min/max: baris tanpa jam_mulai/jam_selesai
                // (di kolom maupun relasi jam) tidak boleh mencemari rentang (mis. min
                // string kosong akan selalu "menang" jadi 00:00 yang keliru).
                $mulai = $rows->map(fn (Jadwal $r) => (string) ($r->jam_mulai ?: $r->jam?->jam_mulai))->filter()->min();
                $selesai = $rows->map(fn (Jadwal $r) => (string) ($r->jam_selesai ?: $r->jam?->jam_selesai))->filter()->max();

                return (object) [
                    'jadwal' => $awal,
                    'id_jadwal' => $awal->uuid,
                    'id_guru' => $awal->id_guru,
                    'id_kelas' => $awal->id_kelas,
                    'id_pelajaran' => $awal->id_pelajaran,
                    'jam_mulai' => (string) $mulai,
                    'jam_selesai' => (string) $selesai,
                ];
            })
            ->values();
    }

    /**
     * Slot agenda hari ini utk pengingat "isi agenda": slot kanonik + kolom tampilan,
     * hanya yg punya jam mulai/selesai (pengingat tak bisa dijadwalkan tanpa jam).
     *
     * @return Collection<int, object{id_jadwal: string, id_guru: ?string, guru: ?\App\Models\Guru, id_kelas: ?string, id_pelajaran: ?string, kelas: string, pelajaran: string, jam_mulai: string, jam_selesai: string}>
     */
    public function slotAgendaHariIni(Carbon $tanggal): Collection
    {
        return $this->kelompokkanSlot($this->sesiHariIni($tanggal))
            ->filter(fn (object $slot) => $slot->jam_mulai !== '' && $slot->jam_selesai !== '')
            ->map(function (object $slot) {
                $j = $slot->jadwal;

                return (object) [
                    'id_jadwal' => $slot->id_jadwal,
                    'id_guru' => $slot->id_guru,
                    'guru' => $j->guru,
                    'id_kelas' => $slot->id_kelas,
                    'id_pelajaran' => $slot->id_pelajaran,
                    'kelas' => $j->kelas?->nama_lengkap ?? 'Kelas',
                    'pelajaran' => $j->pelajaran?->nama ?? 'Pelajaran',
                    'jam_mulai' => $slot->jam_mulai,
                    'jam_selesai' => $slot->jam_selesai,
                ];
            })
            ->values();
    }
}
