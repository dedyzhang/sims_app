<?php

namespace Tests\Feature\Concerns;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use App\Services\GrupChatService;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture sekolah minimal untuk test Grup Chat: dua kelas (7A & 7B), masing-masing
 * dengan walikelas, satu siswa, dan satu orang tua — plus satu admin.
 *
 * Fixture dibuat manual dengan Model::create (bukan factory) mengikuti konvensi
 * repo. face_descriptor WAJIB diisi untuk siswa & guru, kalau tidak middleware
 * EnsureFaceRegistered mengembalikan 302 ke face.self dan semua assertion jadi
 * salah baca (200/403 tidak pernah tercapai).
 */
trait MembangunSekolahGrupChat
{
    protected Kelas $kelas7a;
    protected Kelas $kelas7b;

    protected User $wali7a;
    protected User $wali7b;
    protected User $siswa7a;
    protected User $siswa7b;
    protected User $ortu7a;
    protected User $ortu7b;
    protected User $admin;

    protected Siswa $profilSiswa7a;
    protected Siswa $profilSiswa7b;

    protected function bangunSekolah(): void
    {
        Setting::create(['key' => 'nama_sekolah', 'value' => 'SMP Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);

        $this->kelas7a = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->kelas7b = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        [$this->wali7a] = $this->buatWalikelas($this->kelas7a, 'wali_7a', 'Bu Ani', '3200000001');
        [$this->wali7b] = $this->buatWalikelas($this->kelas7b, 'wali_7b', 'Pak Budi', '3200000002');

        [$this->siswa7a, $this->profilSiswa7a, $this->ortu7a] = $this->buatSiswa($this->kelas7a, 'siswa_7a', 'Andi', 'NIS7A');
        [$this->siswa7b, $this->profilSiswa7b, $this->ortu7b] = $this->buatSiswa($this->kelas7b, 'siswa_7b', 'Bima', 'NIS7B');

        $this->admin = User::create([
            'username' => 'admin_test',
            'password' => Hash::make('password'),
            'access' => 'admin',
        ]);

        $this->sinkron();
    }

    protected function sinkron(): void
    {
        $service = app(GrupChatService::class);
        $service->syncKelas($this->kelas7a);
        $service->syncKelas($this->kelas7b);
    }

    /** @return array{0: User, 1: Guru} */
    protected function buatWalikelas(Kelas $kelas, string $username, string $nama, string $nik): array
    {
        $user = User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => 'walikelas',
        ]);

        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => $nama,
            'nik' => $nik,
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);

        return [$user, $guru];
    }

    /** @return array{0: User, 1: Siswa, 2: User} [user siswa, profil siswa, user ortu] */
    protected function buatSiswa(?Kelas $kelas, string $username, string $nama, string $nis): array
    {
        $user = User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);

        $siswa = Siswa::create([
            'id_login' => $user->uuid,
            'nama' => $nama,
            'nis' => $nis,
            'jk' => 'L',
            'id_kelas' => $kelas?->uuid,
            'status' => 'aktif',
            'face_descriptor' => [0.3, 0.4],
        ]);

        $userOrtu = User::create([
            'username' => 'P.'.$nis,
            'password' => Hash::make('password'),
            'access' => 'orangtua',
        ]);

        Orangtua::create(['id_siswa' => $siswa->uuid, 'id_login' => $userOrtu->uuid]);

        return [$user, $siswa, $userOrtu];
    }

    protected function buatGuruPengajar(Kelas $kelas, string $username, string $nama, string $nik): User
    {
        $user = User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => $nama,
            'nik' => $nik,
            'jk' => 'L',
            'face_descriptor' => [0.5, 0.6],
        ]);

        $pelajaran = \App\Models\Pelajaran::create(['nama' => 'Matematika '.$nik, 'kkm' => 75]);

        \App\Models\Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);

        return $user;
    }

    protected function grupKelas(Kelas $kelas): \App\Models\GrupChat
    {
        return \App\Models\GrupChat::where('id_kelas', $kelas->uuid)
            ->where('tipe', \App\Models\GrupChat::TIPE_KELAS)
            ->firstOrFail();
    }

    protected function grupPaguyuban(Kelas $kelas): \App\Models\GrupChat
    {
        return \App\Models\GrupChat::where('id_kelas', $kelas->uuid)
            ->where('tipe', \App\Models\GrupChat::TIPE_PAGUYUBAN)
            ->firstOrFail();
    }
}
