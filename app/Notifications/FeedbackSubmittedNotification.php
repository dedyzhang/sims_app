<?php

namespace App\Notifications;

use App\Models\UserFeedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class FeedbackSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public UserFeedback $feedback)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->feedback->loadMissing('user');

        $sender = $this->feedback->user?->displayName() ?? 'User dihapus';
        $role = $this->feedback->user?->roleLabel() ?? '-';
        $rating = $this->feedback->rating ? $this->feedback->rating.'/5' : '-';
        $contextUrl = $this->feedback->context_url ?: '-';

        return (new MailMessage)
            ->subject('[Masukan Baru SIMS] '.$this->feedback->categoryLabel().' - '.$this->feedback->subject)
            ->greeting('Masukan baru diterima')
            ->line('Ada saran atau masukan baru yang masuk dari pengguna SIMS.')
            ->line('Kategori: '.$this->feedback->categoryLabel())
            ->line('Subjek: '.$this->feedback->subject)
            ->line('Pengirim: '.$sender.' ('.$role.')')
            ->line('Rating: '.$rating)
            ->line('Halaman asal: '.$contextUrl)
            ->line('Detail masukan:')
            ->line($this->feedback->message)
            ->action('Buka Detail Masukan', route('feedback.show', $this->feedback))
            ->line('Email ini hanya notifikasi. Status dan tindak lanjut tetap dikelola dari dashboard SIMS.');
    }
}