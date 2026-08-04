<?php

namespace App\Console\Commands;

use App\Models\Agenda;
use App\Notifications\JadwalAgendaReminderNotification;
use App\Services\Jadwal\JadwalReminderService;
use App\Support\ModulAktif;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/*
| Pengingat isi agenda: dikirim setelah tiap sesi mengajar selesai.
| Dijalankan tiap 5 menit oleh scheduler (routes/console.php).
|
| Self-healing (bukan window sempit): setiap slot hari ini yang jam_selesai-nya
| SUDAH terlewat, agenda-nya BELUM diisi, dan BELUM pernah dinotif → dikirim.
| Jadi bila satu tick scheduler terlewat/terlambat (cron shared-hosting melompat,
| atau withoutOverlapping() men-skip tick karena burst FCM sinkron), pengingat
| tetap menyusul di run berikutnya. Idempoten dijaga dua lapis: agenda sudah
| diisi (tabel agendas) & pengingat sudah terkirim (tabel notifications). Kedua
| pengecekan di-preload sekali (bukan query per slot) agar sifat self-healing
| tidak berubah jadi mahal saat dievaluasi ulang tiap 5 menit sepanjang hari.
*/
class JadwalAgendaReminder extends Command
{
    protected $signature = 'jadwal:agenda-reminder';

    protected $description = 'Kirim pengingat isi agenda mengajar setelah tiap sesi selesai (in-app + FCM).';

    public function handle(JadwalReminderService $svc): int
    {
        if (! ModulAktif::aktif('agenda')) {
            $this->info('Modul agenda nonaktif — dilewati.');

            return self::SUCCESS;
        }

        $now = Carbon::now();
        $tanggal = $now->toDateString();

        $slots = $svc->slotAgendaHariIni($now);
        if ($slots->isEmpty()) {
            $this->info('Tidak ada sesi mengajar hari ini.');

            return self::SUCCESS;
        }

        // Agenda yg SUDAH diisi hari ini — key id_guru|id_kelas|id_pelajaran (satu query).
        $sudahIsi = Agenda::query()
            ->whereDate('tanggal', $tanggal)
            ->get(['id_guru', 'id_kelas', 'id_pelajaran'])
            ->map(fn ($a) => $a->id_guru.'|'.$a->id_kelas.'|'.$a->id_pelajaran)
            ->flip();

        // Pengingat agenda yg SUDAH dikirim hari ini — key notifiable_id|agenda_key
        // (satu query lintas semua guru; agenda_key sudah memuat tanggal, tapi filter
        // created_at membatasi himpunan agar tak memindai seluruh riwayat notifikasi).
        $sudahNotif = DatabaseNotification::query()
            ->where('type', JadwalAgendaReminderNotification::class)
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->get(['notifiable_id', 'data'])
            ->map(fn ($n) => $n->notifiable_id.'|'.(((array) $n->data)['agenda_key'] ?? ''))
            ->flip();

        $terkirim = 0;
        foreach ($slots as $slot) {
            $selesai = Carbon::parse($tanggal.' '.$slot->jam_selesai);
            if ($selesai->gt($now)) {
                continue; // sesi belum selesai — tunggu run berikutnya
            }

            $user = $slot->guru?->user;
            if (! $user) {
                continue;
            }

            if ($sudahIsi->has($slot->id_guru.'|'.$slot->id_kelas.'|'.$slot->id_pelajaran)) {
                continue; // agenda sudah diisi
            }

            $agendaKey = $slot->id_kelas.':'.$slot->id_pelajaran.':'.$tanggal;
            if ($sudahNotif->has($user->getKey().'|'.$agendaKey)) {
                continue; // pengingat sudah pernah dikirim
            }

            $rentang = Carbon::parse($slot->jam_mulai)->format('H:i').'–'.Carbon::parse($slot->jam_selesai)->format('H:i');
            $url = '/agenda/buat?tanggal='.$tanggal.'&jadwal='.$slot->id_jadwal;

            $user->notify(new JadwalAgendaReminderNotification(
                $agendaKey,
                $slot->pelajaran,
                $slot->kelas,
                $rentang,
                $url,
            ));
            $terkirim++;
        }

        $this->info("Selesai. {$terkirim} pengingat agenda dikirim.");

        return self::SUCCESS;
    }
}
