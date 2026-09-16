<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemoOnboardingNotification extends Notification
{
    /**
     * @param  list<array{role: string, username: string, password: string}>  $accounts
     */
    public function __construct(
        public string $schoolName,
        public string $loginUrl,
        public string $expiresAtWib,
        public array $accounts,
        public bool $rotated = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->rotated
                ? 'Password baru akun demo SIMS'
                : 'Akses demo SIMS siap digunakan')
            ->greeting('Halo,')
            ->line('Berikut akses demo SIMS untuk '.$this->schoolName.'.')
            ->line('Masuk di: '.$this->loginUrl)
            ->line('Masa akses berakhir: '.$this->expiresAtWib.' WIB.');

        foreach ($this->accounts as $account) {
            $mail->line(sprintf(
                '%s — username: %s — password: %s',
                ucfirst($account['role']),
                $account['username'],
                $account['password'],
            ));
        }

        return $mail
            ->line('Data di sandbox bersifat contoh. Jangan masukkan data sekolah nyata atau secret produksi.')
            ->line('Simpan email ini. Password tidak disimpan di situs penjualan.');
    }
}
