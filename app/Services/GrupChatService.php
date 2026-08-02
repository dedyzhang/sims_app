<?php

namespace App\Services;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use App\Models\Pengumuman;
use App\Support\TahunAjaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisioning & sinkronisasi keanggotaan Grup Chat.
 *
 * Modelnya App\Services\ClassroomService, TAPI dengan perbaikan penting:
 * ClassroomService hanya firstOrCreate dan TIDAK PERNAH mengeluarkan anggota.
 * Untuk ruang kelas itu tak fatal; untuk chat artinya ex-siswa terus membaca grup
 * kelas lamanya selamanya. Karena itu syncKelas() di sini melakukan diff DUA ARAH.
 *
 * Keanggotaan diturunkan dari:
 *   Grup Kelas     = walikelas + siswa aktif di kelas itu (guru pengajar/mapel
 *                     TIDAK ikut — grup ini murni jalur walikelas ke siswanya)
 *   Grup Paguyuban = walikelas + orang tua dari siswa aktif di kelas itu
 *
 * Grup Kelas SELALU mode pengumuman (lihat GrupChat::MODE_PENGUMUMAN): satu-satunya
 * staf di grup ini adalah walikelas, jadi hanya walikelas yang bisa menulis pesan
 * baru — siswa hanya bisa membalas pesan walikelas (lihat GrupChatPolicy::reply()).
 * Grup Paguyuban TETAP mode diskusi biasa — walikelas & orang tua bebas mengobrol.
 */
class GrupChatService
{
    public function __construct(private GrupChatMessenger $messenger) {}

    /**
     * Broadcast pengumuman global ke grup kelas & paguyuban tahun ajaran aktif.
     * Pengumuman bertarget peran tidak masuk grup agar siswa/orang tua di luar
     * sasaran tidak menerima salinan yang tidak relevan.
     */
    public function broadcastPengumuman(Pengumuman $pengumuman, User $pengirim): int
    {
        if (! $pengumuman->untukSemua()) {
            return 0;
        }

        $tahun = Semester::aktif()?->tahun ?? TahunAjaran::current();
        $body = "[PENGUMUMAN PENTING]\n{$pengumuman->judul}\n\n{$pengumuman->isi}";
        $body = Str::limit($body, GrupChatMessenger::MAX_BODY, '');

        return DB::transaction(function () use ($tahun, $pengirim, $body): int {
            $terkirim = 0;

            GrupChat::query()
                ->aktif()
                ->where('tahun_ajaran', $tahun)
                ->orderBy('uuid')
                ->each(function (GrupChat $grup) use ($pengirim, $body, &$terkirim) {
                    $this->messenger->kirim($grup, $pengirim, 'admin', $body);
                    $terkirim++;
                });

            return $terkirim;
        });
    }

    /**
     * Pastikan kedua grup untuk sebuah kelas ada. Idempoten.
     *
     * @return array{0: GrupChat, 1: GrupChat} [grup kelas, grup paguyuban]
     */
    public function provisionKelas(Kelas $kelas, ?string $tahunAjaran = null): array
    {
        $semester = Semester::aktif();
        $tahun = $tahunAjaran ?? $semester?->tahun ?? TahunAjaran::current();

        $buat = function (string $tipe) use ($kelas, $tahun, $semester): GrupChat {
            return GrupChat::firstOrCreate(
                ['id_kelas' => $kelas->uuid, 'tipe' => $tipe, 'tahun_ajaran' => $tahun],
                [
                    'nama' => GrupChat::namaDefault($tipe, $kelas),
                    'id_semester' => $semester?->getKey(),
                    'mode' => $tipe === GrupChat::TIPE_KELAS ? GrupChat::MODE_PENGUMUMAN : GrupChat::MODE_DISKUSI,
                ]
            );
        };

        return [$buat(GrupChat::TIPE_KELAS), $buat(GrupChat::TIPE_PAGUYUBAN)];
    }

    /**
     * Rekonsiliasi penuh kedua grup sebuah kelas: tambah yang kurang, keluarkan
     * (soft-leave) yang tak lagi diturunkan, hidupkan kembali yang balik.
     */
    public function syncKelas(Kelas $kelas, ?string $tahunAjaran = null): void
    {
        [$grupKelas, $grupPaguyuban] = $this->provisionKelas($kelas, $tahunAjaran);

        $siswa = Siswa::where('id_kelas', $kelas->uuid)
            ->where('status', 'aktif')
            ->get(['uuid', 'id_login']);

        DB::transaction(function () use ($kelas, $grupKelas, $grupPaguyuban, $siswa) {
            // Sabuk dan bretel untuk grup yang sudah ada sebelum aturan "Grup Kelas
            // selalu pengumuman" berlaku — provisionKelas() di atas hanya menyetel mode
            // saat grup BARU dibuat (firstOrCreate tak menyentuh baris yang sudah ada).
            // Di dalam transaksi yang sama dengan reconcile() supaya keduanya
            // commit/rollback bersamaan, bukan dua write terpisah yang bisa pincang
            // kalau reconcile() di bawah gagal.
            if ($grupKelas->mode !== GrupChat::MODE_PENGUMUMAN) {
                $grupKelas->update(['mode' => GrupChat::MODE_PENGUMUMAN]);
            }

            $this->reconcile($grupKelas, $this->anggotaGrupKelas($kelas, $siswa));
            $this->reconcile($grupPaguyuban, $this->anggotaGrupPaguyuban($kelas, $siswa));
        });
    }

    /**
     * Jalur murah untuk satu siswa (dipanggil saat siswa dibuat, pindah kelas, atau
     * lulus). Hanya menyentuh baris milik siswa ini dan orang tuanya — tidak
     * merekonsiliasi seluruh kelas.
     */
    public function syncSiswa(Siswa $siswa): void
    {
        $tujuan = $this->grupTujuanSiswa($siswa);

        DB::transaction(function () use ($siswa, $tujuan) {
            // ── Keanggotaan siswa itu sendiri di grup kelas ────────────────────────
            if ($siswa->id_login) {
                $simpan = $tujuan ? [$tujuan[0]->uuid] : [];

                GrupChatMember::where('user_id', $siswa->id_login)
                    ->where('peran', 'siswa')
                    ->whereNull('left_at')
                    ->whereNotIn('grup_id', $simpan)
                    ->update(['left_at' => now()]);

                if ($tujuan) {
                    $this->pastikanAnggota($tujuan[0], $siswa->id_login, 'siswa', $siswa->uuid);
                }
            }

            // ── Keanggotaan orang tuanya di grup paguyuban ─────────────────────────
            // Sengaja dihitung ulang per AKUN ORTU, bukan dihapus berdasarkan
            // id_siswa: satu akun ortu bisa punya beberapa anak, dan anak lain
            // mungkin masih di kelas yang sama. Menghapus berdasarkan id_siswa akan
            // mengeluarkan ortu dari paguyuban yang seharusnya ia tetap ikuti.
            $ortuUserIds = Orangtua::where('id_siswa', $siswa->uuid)
                ->pluck('id_login')->filter()->unique();

            foreach ($ortuUserIds as $userId) {
                $this->syncOrangtuaUser($userId);
            }
        });
    }

    /**
     * Rekonsiliasi keanggotaan paguyuban SATU akun orang tua berdasarkan seluruh
     * anaknya yang masih aktif. Aman dipanggil untuk ortu multi-anak.
     */
    public function syncOrangtuaUser(string $userId): void
    {
        $anak = Orangtua::where('id_login', $userId)
            ->with('siswa:uuid,id_kelas,status')
            ->get()
            ->pluck('siswa')
            ->filter(fn ($s) => $s && $s->status === 'aktif' && $s->id_kelas);

        $tujuan = [];   // grup_id => id_siswa (anak yang jadi dasar keanggotaan)
        foreach ($anak as $s) {
            $kelas = Kelas::find($s->id_kelas);
            if (! $kelas) {
                continue;
            }
            [, $paguyuban] = $this->provisionKelas($kelas);
            $tujuan[$paguyuban->uuid] ??= $s->uuid;
        }

        GrupChatMember::where('user_id', $userId)
            ->where('peran', 'orangtua')
            ->whereNull('left_at')
            ->whereNotIn('grup_id', array_keys($tujuan))
            ->update(['left_at' => now()]);

        foreach ($tujuan as $grupId => $idSiswa) {
            $grup = GrupChat::find($grupId);
            if ($grup) {
                $this->pastikanAnggota($grup, $userId, 'orangtua', $idSiswa);
            }
        }
    }

    // ─────────────────────── internal ───────────────────────

    /**
     * Grup tujuan siswa saat ini, atau null bila ia tak lagi punya kelas aktif
     * (lulus / pindah / keluar).
     *
     * @return array{0: GrupChat, 1: GrupChat}|null
     */
    private function grupTujuanSiswa(Siswa $siswa): ?array
    {
        // Model::create() tidak menghidrasi nilai default kolom dari database, jadi
        // siswa yang baru dibuat lewat SiswaController punya status = null di
        // instance-nya walau tersimpan 'aktif' di DB. Perlakukan null sebagai aktif —
        // kalau tidak, siswa baru tidak pernah masuk grup mana pun.
        $status = $siswa->status ?? 'aktif';

        if ($status !== 'aktif' || ! $siswa->id_kelas) {
            return null;
        }

        $kelas = Kelas::find($siswa->id_kelas);

        return $kelas ? $this->provisionKelas($kelas) : null;
    }

    /**
     * Anggota seharusnya untuk grup kelas.
     *
     * @return array<string, array{peran: string, id_siswa: ?string}> user_id => meta
     */
    private function anggotaGrupKelas(Kelas $kelas, $siswa): array
    {
        $out = [];

        // Guru pengajar/mapel SENGAJA tidak dimasukkan — Grup Kelas murni jalur
        // walikelas ke siswanya (lihat docblock kelas ini).
        if ($waliUserId = $this->walikelasUserId($kelas)) {
            $out[$waliUserId] = ['peran' => 'walikelas', 'id_siswa' => null];
        }

        foreach ($siswa as $s) {
            if ($s->id_login) {
                $out[$s->id_login] = ['peran' => 'siswa', 'id_siswa' => $s->uuid];
            }
        }

        return $out;
    }

    /**
     * Anggota seharusnya untuk grup paguyuban.
     *
     * @return array<string, array{peran: string, id_siswa: ?string}>
     */
    private function anggotaGrupPaguyuban(Kelas $kelas, $siswa): array
    {
        $out = [];

        if ($waliUserId = $this->walikelasUserId($kelas)) {
            $out[$waliUserId] = ['peran' => 'walikelas', 'id_siswa' => null];
        }

        $ortu = Orangtua::whereIn('id_siswa', $siswa->pluck('uuid'))
            ->whereNotNull('id_login')
            ->get(['id_login', 'id_siswa']);

        foreach ($ortu as $o) {
            // Ortu dengan >1 anak di kelas yang sama tetap satu baris
            // (unique grup_id+user_id); anak pertama dipakai sebagai jejak.
            $out[$o->id_login] ??= ['peran' => 'orangtua', 'id_siswa' => $o->id_siswa];
        }

        return $out;
    }

    private function walikelasUserId(Kelas $kelas): ?string
    {
        $guruId = Walikelas::where('id_kelas', $kelas->uuid)->value('id_guru');

        return $guruId ? Guru::where('uuid', $guruId)->value('id_login') : null;
    }

    /**
     * Diff dua arah antara keanggotaan seharusnya dan keanggotaan tersimpan.
     *
     * @param  array<string, array{peran: string, id_siswa: ?string}>  $seharusnya
     */
    private function reconcile(GrupChat $grup, array $seharusnya): void
    {
        $tersimpan = GrupChatMember::where('grup_id', $grup->uuid)->get()->keyBy('user_id');

        foreach ($seharusnya as $userId => $meta) {
            $this->pastikanAnggota($grup, $userId, $meta['peran'], $meta['id_siswa'], $tersimpan->get($userId));
        }

        foreach ($tersimpan as $userId => $member) {
            if (! isset($seharusnya[$userId]) && $member->left_at === null) {
                $member->update(['left_at' => now()]);
            }
        }
    }

    /**
     * Tambah / hidupkan kembali / perbarui peran satu anggota.
     *
     * Anggota BARU dimulai dari titik sekarang: joined_seq = last_seq (tak membaca
     * riwayat sebelum bergabung, lihat migration members) dan last_read_seq =
     * last_notified_seq = last_seq supaya ia tidak dibanjiri badge & digest pesan lama.
     *
     * Anggota yang MASUK LAGI sengaja MEMPERTAHANKAN joined_seq lamanya. Memajukannya
     * akan menghapus akses ke riwayat yang sudah sah ia baca hanya karena ada glitch
     * sync sesaat — kerugiannya lebih besar daripada paparan pesan selama ia keluar,
     * yang toh terjadi di kelas yang kini ia ikuti lagi.
     */
    private function pastikanAnggota(
        GrupChat $grup,
        string $userId,
        string $peran,
        ?string $idSiswa,
        ?GrupChatMember $existing = null
    ): void {
        // Sabuk dan bretel: peran ortu tak pernah boleh masuk grup kelas, dan
        // sebaliknya. Redundan dengan guard tipe di GrupChatPolicy, sengaja.
        if ($peran === 'orangtua' && ! $grup->isPaguyuban()) {
            return;
        }
        if ($peran === 'siswa' && $grup->isPaguyuban()) {
            return;
        }

        $member = $existing ?? GrupChatMember::where('grup_id', $grup->uuid)
            ->where('user_id', $userId)
            ->first();

        if (! $member) {
            $seq = (int) $grup->last_seq;

            GrupChatMember::create([
                'grup_id' => $grup->uuid,
                'user_id' => $userId,
                'peran' => $peran,
                'id_siswa' => $idSiswa,
                'joined_at' => now(),
                'joined_seq' => $seq,
                'last_read_seq' => $seq,
                'last_notified_seq' => $seq,
            ]);

            return;
        }

        $ubah = [];
        if ($member->peran !== $peran) {
            $ubah['peran'] = $peran;
        }
        if ($idSiswa !== null && $member->id_siswa !== $idSiswa) {
            $ubah['id_siswa'] = $idSiswa;
        }
        if ($member->left_at !== null) {
            $ubah['left_at'] = null;
            $ubah['joined_at'] = now();
        }

        if ($ubah !== []) {
            $member->update($ubah);
        }
    }
}
