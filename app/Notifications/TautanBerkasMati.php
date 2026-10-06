<?php

namespace App\Notifications;

use App\Models\TautanBerkas;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TautanBerkasMati extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TautanBerkas $tautan)
    {
        $this->onQueue('notifikasi');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return NotifikasiFilament::make()
            ->warning()
            ->title('Tautan berkas tidak dapat diakses')
            ->body("Tautan \"{$this->tautan->label}\" ({$this->tautan->jenis}) tidak dapat dibuka. Periksa izin berbagi atau ganti tautannya.")
            ->getDatabaseMessage();
    }
}
