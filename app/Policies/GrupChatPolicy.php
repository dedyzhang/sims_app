<?php

namespace App\Policies;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\GrupChatMessage;
use App\Models\Guru;
use App\Models\User;
use App\Models\Walikelas;

/**
 * Otorisasi Grup Chat. Ditemukan otomatis oleh Laravel (App\Models\GrupChat →
 * App\Policies\GrupChatPolicy), tidak perlu didaftarkan di AppServiceProvider —
 * sama seperti ClassroomPolicy & ForumTopicPolicy.
 */
class GrupChatPolicy
{
    /** Izin RBAC opsional supaya sekolah bisa memberi akses kepala tanpa ubah kode. */
    public const IZIN_KELOLA = 'manage_grup_chat';

    public function viewAny(User $user): bool
    {
        return true;   // index() memfilter berdasarkan keanggotaan
    }

    public function view(User $user, GrupChat $grup): bool
    {
        if (! $this->tipeCocok($user, $grup)) {
            return false;
        }

        if ($this->pengelola($user, $grup)) {
            return true;
        }

        return $this->member($user, $grup) !== null;
    }

    /** Boleh menulis pesan baru (bukan balasan). */
    public function send(User $user, GrupChat $grup): bool
    {
        if (! $this->view($user, $grup) || $grup->isArsip()) {
            return false;
        }

        $member = $this->member($user, $grup);

        // Admin / pemegang izin kelola yang bukan anggota tetap boleh menulis
        // (moderasi & pengumuman lintas kelas).
        if (! $member) {
            return $this->pengelola($user, $grup);
        }

        if (! $member->can_write) {
            return false;
        }

        return ! $grup->isModePengumuman() || $member->isStaf();
    }

    /**
     * Boleh membalas pesan tertentu.
     *
     * Di mode pengumuman, target balas DIBATASI pada pesan staf. Tanpa batasan ini
     * anggota bisa membalas pesan sesama siswa/ortu dan mode "pengumuman" berubah
     * jadi diskusi biasa dengan satu langkah ekstra.
     */
    public function reply(User $user, GrupChat $grup, GrupChatMessage $target): bool
    {
        if ($target->grup_id !== $grup->uuid || $target->isDihapus()) {
            return false;
        }

        if (! $this->view($user, $grup) || $grup->isArsip()) {
            return false;
        }

        $member = $this->member($user, $grup);

        if (! $member) {
            return $this->pengelola($user, $grup);
        }

        if (! $member->can_write) {
            return false;
        }

        if (! $grup->isModePengumuman() || $member->isStaf()) {
            return true;
        }

        return in_array($target->sender_peran, GrupChat::PERAN_STAF, true);
    }

    /** Ubah mode/nama grup, atur can_write anggota, moderasi pesan. */
    public function kelola(User $user, GrupChat $grup): bool
    {
        if (! $this->tipeCocok($user, $grup)) {
            return false;
        }

        if ($this->pengelola($user, $grup)) {
            return true;
        }

        return $this->member($user, $grup)?->peran === 'walikelas';
    }

    public function moderasi(User $user, GrupChat $grup): bool
    {
        return $this->kelola($user, $grup);
    }

    /** Hapus pesan sendiri kapan saja; hapus pesan orang lain butuh hak moderasi. */
    public function hapus(User $user, GrupChat $grup, GrupChatMessage $pesan): bool
    {
        if ($pesan->grup_id !== $grup->uuid || $pesan->isDihapus()) {
            return false;
        }

        if ($pesan->user_id === $user->getKey()) {
            return true;
        }

        return $this->moderasi($user, $grup);
    }

    // ─────────────────────── internal ───────────────────────

    /**
     * Guard tipe — pertahanan terpenting di kelas ini.
     *
     * Sengaja redundan dengan tabel keanggotaan: kalau backfill buruk atau bug sync
     * membuat baris salah, kebocoran lintas-tipe (ortu membaca grup siswa, siswa
     * membaca grup ortu) tetap mustahil.
     */
    private function tipeCocok(User $user, GrupChat $grup): bool
    {
        if ($user->access === 'orangtua') {
            return $grup->isPaguyuban();
        }

        if ($user->access === 'siswa') {
            return ! $grup->isPaguyuban();
        }

        return true;
    }

    private function pengelola(User $user, GrupChat $grup): bool
    {
        if ($user->isAdmin() || $user->canAccess(self::IZIN_KELOLA)) {
            return true;
        }

        // Jaring pengaman bila sinkronisasi keanggotaan telat: walikelas kelas ini
        // selalu boleh masuk grupnya sendiri.
        return $this->walikelasDari($user, $grup);
    }

    private function walikelasDari(User $user, GrupChat $grup): bool
    {
        $guruId = Guru::where('id_login', $user->getKey())->value('uuid');

        return $guruId !== null && Walikelas::where('id_kelas', $grup->id_kelas)
            ->where('id_guru', $guruId)
            ->exists();
    }

    /**
     * Baris keanggotaan aktif user di grup ini.
     *
     * Di-memo per INSTANCE (Gate menyimpan satu instance policy per request),
     * sengaja bukan `static`: memo statis akan bertahan lintas request di worker
     * yang berumur panjang, sehingga anggota yang baru dikeluarkan masih lolos
     * pengecekan sampai proses di-restart.
     */
    private array $memoMember = [];

    private function member(User $user, GrupChat $grup): ?GrupChatMember
    {
        $key = $grup->uuid.'|'.$user->getKey();

        if (! array_key_exists($key, $this->memoMember)) {
            $this->memoMember[$key] = GrupChatMember::where('grup_id', $grup->uuid)
                ->where('user_id', $user->getKey())
                ->whereNull('left_at')
                ->first();
        }

        return $this->memoMember[$key];
    }
}
