<?php

namespace App\Services\Chatbot;

use App\Models\Absensi;
use App\Models\Jadwal;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Jawaban berbasis DATA NYATA aplikasi untuk chatbot — hanya topik yang AMAN & sudah
 * di-scope ke pengguna yang login. Saat ini: jadwal pelajaran hari ini.
 *
 * Privasi: nilai TIDAK pernah dipaparkan di sini; absensi & SPP tetap di-handoff ke admin.
 * Setiap query difilter ke milik pengguna sendiri (kelas siswa / jadwal mengajar guru) agar
 * tidak membocorkan data orang lain.
 */
class SchoolDataService
{
    /**
     * Biodata pengguna SENDIRI (data yang memang sudah bisa dilihat siswa/guru di aplikasi).
     * Aman dipaparkan karena milik sendiri. Mengembalikan null bila role tak terjangkau.
     */
    public function biodata(User $user): ?string
    {
        if ($siswa = $user->siswa) {
            $jk = match ($siswa->jk) { 'L' => 'Laki-laki', 'P' => 'Perempuan', default => $siswa->jk ?: '-' };
            $ttl = trim(($siswa->tempat_lahir ?: '') . ($siswa->tanggal_lahir ? ', ' . Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d F Y') : ''), ', ');

            $baris = array_filter([
                'Nama'           => $siswa->nama,
                'NIS'            => $siswa->nis,
                'NISN'           => $siswa->nisn,
                'Kelas'          => optional($siswa->kelas)->nama_lengkap,
                'Jenis Kelamin'  => $jk,
                'Tempat, Tgl Lahir' => $ttl ?: null,
                'Agama'          => $siswa->agama,
                'Alamat'         => $siswa->alamat,
                'No. HP'         => $siswa->no_handphone,
            ], fn ($v) => filled($v));

            return $this->formatBiodata('🧑‍🎓 Biodata kamu:', $baris);
        }

        if ($guru = $user->guru) {
            $jk = match ($guru->jk) { 'L' => 'Laki-laki', 'P' => 'Perempuan', default => $guru->jk ?: '-' };
            $baris = array_filter([
                'Nama' => $guru->nama,
                'NIP'  => $guru->nip,
                'NIK'  => $guru->nik,
                'Jenis Kelamin' => $jk,
            ], fn ($v) => filled($v));

            return $this->formatBiodata('👩‍🏫 Biodata kamu:', $baris);
        }

        return null;
    }

    /**
     * Rekap kehadiran siswa SENDIRI bulan berjalan (data yang memang bisa dilihat siswa).
     * Scoped ke id_siswa milik pengguna. Null bila bukan siswa.
     */
    public function rekapKehadiran(User $user): ?string
    {
        $siswa = $user->siswa;
        if (! $siswa) {
            return null;
        }

        $awal = Carbon::now()->startOfMonth();
        $akhir = Carbon::now()->endOfMonth();
        $namaBulan = Carbon::now()->translatedFormat('F Y');

        $rows = Absensi::where('id_siswa', $siswa->uuid)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get();

        if ($rows->isEmpty()) {
            return "Belum ada catatan kehadiran untukmu di bulan {$namaBulan}. 😊";
        }

        $hitung = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];
        foreach ($rows as $r) {
            if (isset($hitung[$r->status])) {
                $hitung[$r->status]++;
            }
        }

        return implode("\n", [
            "🗓️ Rekap kehadiranmu bulan {$namaBulan}:",
            '',
            "• ✅ Hadir: {$hitung['hadir']}",
            "• 📝 Izin: {$hitung['izin']}",
            "• 🤒 Sakit: {$hitung['sakit']}",
            "• ❌ Alpa: {$hitung['alpa']}",
            '',
            'Total tercatat: ' . $rows->count() . ' hari. Kalau ada yang keliru, lapor ke wali kelas ya. 🙏',
        ]);
    }

    /**
     * Info kelas & wali kelas siswa SENDIRI. Scoped ke kelas pengguna. Null bila bukan siswa.
     */
    public function infoWaliKelas(User $user): ?string
    {
        $siswa = $user->siswa;
        if (! $siswa) {
            return null;
        }

        $kelas = $siswa->kelas;
        if (! $kelas) {
            return null;
        }

        $wali = $kelas->guru; // hasOneThrough Walikelas → Guru

        $lines = ['🏫 Info kelasmu:', '', '• Kelas: ' . ($kelas->nama_lengkap ?? '-')];
        if ($wali) {
            $lines[] = '• Wali Kelas: ' . $wali->nama;
            if ($wali->nip) {
                $lines[] = '• NIP Wali Kelas: ' . $wali->nip;
            }
        } else {
            $lines[] = '• Wali Kelas: belum ditentukan';
        }
        $lines[] = '';
        $lines[] = 'Butuh menghubungi wali kelas/admin? Klik "Hubungkan ke Admin" di atas. 🙏';

        return implode("\n", $lines);
    }

    private function formatBiodata(string $judul, array $baris): string
    {
        $lines = [$judul, ''];
        foreach ($baris as $label => $nilai) {
            $lines[] = "• {$label}: {$nilai}";
        }
        $lines[] = '';
        $lines[] = 'Kalau ada data yang keliru, hubungi admin/wali kelas untuk diperbaiki ya. 🙏';

        return implode("\n", $lines);
    }

    /**
     * Jadwal pelajaran hari ini untuk pengguna. Mengembalikan teks siap-kirim, atau null bila
     * role tidak terjangkau (mis. orang tua / admin) sehingga pemanggil bisa fallback handoff.
     */
    public function jadwalHariIni(User $user): ?string
    {
        $siswa = $user->siswa;
        $guru = $user->guru;

        // Role lain (ortu/admin) → biar pemanggil arahkan ke admin.
        if (! $siswa && ! $guru) {
            return null;
        }

        $hari = Carbon::now()->dayOfWeekIso; // 1=Senin ... 7=Minggu
        $namaHari = Jadwal::HARI[$hari] ?? null;

        if ($namaHari === null) {
            return 'Hari ini libur (akhir pekan) — tidak ada jadwal pelajaran. 😊';
        }

        if ($siswa) {
            if (! $siswa->id_kelas) {
                return null; // siswa belum punya kelas → handoff
            }
            $rows = Jadwal::with(['pelajaran', 'guru'])
                ->where('id_kelas', $siswa->id_kelas)
                ->where('hari', $hari)
                ->orderBy('jam_ke')->orderBy('jam_mulai')
                ->get();
            $kosong = "Tidak ada jadwal pelajaran untuk kelasmu hari ini ({$namaHari}). 😊";
            $perItem = fn (Jadwal $j) => $j->guru->nama ?? null;
        } else {
            $rows = Jadwal::with(['pelajaran', 'kelas'])
                ->where('id_guru', $guru->uuid)
                ->where('hari', $hari)
                ->orderBy('jam_ke')->orderBy('jam_mulai')
                ->get();
            $kosong = "Tidak ada jadwal mengajar untukmu hari ini ({$namaHari}). 😊";
            $perItem = fn (Jadwal $j) => $j->kelas->nama_lengkap ?? null;
        }

        if ($rows->isEmpty()) {
            return $kosong;
        }

        $lines = ["🗓️ Jadwal pelajaran hari ini ({$namaHari}):", ''];
        foreach ($rows as $j) {
            $jam = $j->jam_mulai
                ? Carbon::parse($j->jam_mulai)->format('H:i')
                : ($j->jam_ke ? "Jam ke-{$j->jam_ke}" : '');
            $mapel = $j->keterangan ?: ($j->pelajaran->nama ?? 'Pelajaran');
            $extra = $perItem($j);

            $line = '• ' . ($jam ? $jam . ' — ' : '') . $mapel;
            if ($extra) {
                $line .= " ({$extra})";
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
