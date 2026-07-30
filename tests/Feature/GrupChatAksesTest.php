<?php

namespace Tests\Feature;

use App\Models\GrupChatMember;
use App\Models\Kelas;
use App\Models\RolePermission;
use App\Models\User;
use App\Policies\GrupChatPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\MembangunSekolahGrupChat;
use Tests\TestCase;

/**
 * Kebocoran akses lintas peran — prioritas tertinggi di modul ini. Grup paguyuban
 * berisi percakapan orang tua tentang anak-anak; satu kebocoran ke siswa atau ke
 * kelas lain adalah insiden privasi, bukan sekadar bug.
 */
class GrupChatAksesTest extends TestCase
{
    use RefreshDatabase, MembangunSekolahGrupChat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bangunSekolah();
    }

    public function test_siswa_hanya_melihat_grup_kelasnya(): void
    {
        $this->actingAs($this->siswa7a)
            ->get(route('grup.show', $this->grupKelas($this->kelas7a)))
            ->assertOk();

        $this->actingAs($this->siswa7a)
            ->get(route('grup.show', $this->grupKelas($this->kelas7b)))
            ->assertForbidden();
    }

    public function test_siswa_tidak_bisa_melihat_paguyuban_kelasnya_sendiri(): void
    {
        $this->actingAs($this->siswa7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertForbidden();
    }

    public function test_ortu_hanya_melihat_paguyuban_kelas_anaknya(): void
    {
        $this->actingAs($this->ortu7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertOk();

        // Grup kelas anaknya sendiri pun tertutup untuk ortu.
        $this->actingAs($this->ortu7a)
            ->get(route('grup.show', $this->grupKelas($this->kelas7a)))
            ->assertForbidden();

        $this->actingAs($this->ortu7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7b)))
            ->assertForbidden();
    }

    public function test_ortu_dua_anak_beda_kelas_dapat_dua_paguyuban(): void
    {
        // Anak kedua di 7B ditautkan ke akun ortu yang sudah ada.
        [, $anakKedua] = $this->buatSiswa($this->kelas7b, 'siswa_adik', 'Adik', 'NISADK');
        \App\Models\Orangtua::where('id_siswa', $anakKedua->uuid)->delete();
        \App\Models\Orangtua::create([
            'id_siswa' => $anakKedua->uuid,
            'id_login' => $this->ortu7a->uuid,
        ]);

        app(\App\Services\GrupChatService::class)->syncOrangtuaUser($this->ortu7a->uuid);

        $this->actingAs($this->ortu7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertOk();

        $this->actingAs($this->ortu7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7b)))
            ->assertOk();
    }

    public function test_guru_pengajar_masuk_grup_kelas_bukan_paguyuban(): void
    {
        $guru = $this->buatGuruPengajar($this->kelas7a, 'guru_mtk', 'Pak Mat', '3200000009');
        $this->sinkron();

        $this->actingAs($guru)
            ->get(route('grup.show', $this->grupKelas($this->kelas7a)))
            ->assertOk();

        $this->actingAs($guru)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertForbidden();
    }

    public function test_walikelas_masuk_kedua_grup(): void
    {
        $this->actingAs($this->wali7a)
            ->get(route('grup.show', $this->grupKelas($this->kelas7a)))
            ->assertOk();

        $this->actingAs($this->wali7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertOk();

        // Tapi bukan grup kelas lain.
        $this->actingAs($this->wali7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7b)))
            ->assertForbidden();
    }

    public function test_admin_melihat_semua_grup(): void
    {
        foreach ([$this->kelas7a, $this->kelas7b] as $kelas) {
            $this->actingAs($this->admin)->get(route('grup.show', $this->grupKelas($kelas)))->assertOk();
            $this->actingAs($this->admin)->get(route('grup.show', $this->grupPaguyuban($kelas)))->assertOk();
        }
    }

    public function test_kepala_ditolak_tanpa_izin_dan_diterima_dengan_manage_grup_chat(): void
    {
        $kepala = User::create([
            'username' => 'kepala_test',
            'password' => Hash::make('password'),
            'access' => 'kepala',
        ]);

        $this->actingAs($kepala)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertForbidden();

        RolePermission::create(['role' => 'kepala', 'permission' => GrupChatPolicy::IZIN_KELOLA]);

        $this->actingAs($kepala)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertOk();
    }

    public function test_peran_lain_tidak_punya_akses(): void
    {
        $bendahara = User::create([
            'username' => 'bendahara_test',
            'password' => Hash::make('password'),
            'access' => 'bendahara',
        ]);

        $this->actingAs($bendahara)
            ->get(route('grup.show', $this->grupKelas($this->kelas7a)))
            ->assertForbidden();
    }

    public function test_bukan_anggota_ditolak_di_poll_dan_kirim(): void
    {
        $grup = $this->grupKelas($this->kelas7b);

        $this->actingAs($this->siswa7a)
            ->getJson(route('grup.poll', $grup))
            ->assertForbidden();

        $this->actingAs($this->siswa7a)
            ->postJson(route('grup.pesan', $grup), ['body' => 'menyusup'])
            ->assertForbidden();

        $this->assertDatabaseCount('grup_chat_messages', 0);
    }

    public function test_anggota_left_at_terisi_kehilangan_akses(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $this->actingAs($this->siswa7a)->get(route('grup.show', $grup))->assertOk();

        GrupChatMember::where('grup_id', $grup->uuid)
            ->where('user_id', $this->siswa7a->uuid)
            ->update(['left_at' => now()]);

        $this->actingAs($this->siswa7a)->get(route('grup.show', $grup))->assertForbidden();
    }

    public function test_index_hanya_menampilkan_grup_milik_user(): void
    {
        $this->actingAs($this->siswa7a)
            ->get(route('grup.index'))
            ->assertOk()
            ->assertSee('Grup Kelas 7 A')
            ->assertDontSee('Paguyuban Kelas 7 A')
            ->assertDontSee('Grup Kelas 7 B');
    }

    public function test_grup_tanpa_kelas_tidak_menjerat_siswa_lulus(): void
    {
        $grup = $this->grupKelas($this->kelas7a);

        $this->profilSiswa7a->update(['status' => 'lulus', 'id_kelas' => null]);
        app(\App\Services\GrupChatService::class)->syncSiswa($this->profilSiswa7a);

        $this->actingAs($this->siswa7a)->get(route('grup.show', $grup))->assertForbidden();
        $this->actingAs($this->ortu7a)
            ->get(route('grup.show', $this->grupPaguyuban($this->kelas7a)))
            ->assertForbidden();
    }

    public function test_kelas_baru_tidak_membocorkan_grup_ke_siapa_pun(): void
    {
        $kelas8 = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        app(\App\Services\GrupChatService::class)->provisionKelas($kelas8);

        $this->actingAs($this->siswa7a)
            ->get(route('grup.show', $this->grupKelas($kelas8)))
            ->assertForbidden();
    }
}
