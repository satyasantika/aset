<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsappChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi SIMAN: antrean `notifikasi`, dikirim setelah transaksi commit, saluran mail + database (tampil di
 * lonceng panel Filament). WhatsApp hanya bila subkelas mengaktifkannya (`pakaiWhatsapp`) dan gateway menyala.
 */
abstract class NotifikasiSiman extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('notifikasi');
        $this->afterCommit();
    }

    abstract public function judul(): string;

    abstract public function isi(): string;

    abstract public function url(): string;

    protected function pakaiWhatsapp(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $saluran = ['database'];

        if (filled($notifiable->email ?? null)) {
            $saluran[] = 'mail';
        }

        if ($this->pakaiWhatsapp() && WhatsappChannel::aktif()) {
            $saluran[] = WhatsappChannel::class;
        }

        return $saluran;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->judul())
            ->greeting('Yth. '.($notifiable->name ?? 'Pengguna'))
            ->line($this->isi())
            ->action('Buka di SIMAN', $this->url());
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return NotifikasiFilament::make()
            ->title($this->judul())
            ->body($this->isi())
            ->actions([
                Action::make('buka')->label('Buka')->url($this->url()),
            ])
            ->getDatabaseMessage();
    }

    public function pesanWhatsapp(object $notifiable): string
    {
        return $this->judul().'. '.$this->isi();
    }
}
