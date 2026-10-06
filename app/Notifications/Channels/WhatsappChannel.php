<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/**
 * Saluran WhatsApp opsional (gateway HTTP). Nonaktif kecuali `WHATSAPP_ENABLED=true`; kegagalan gateway dicatat oleh
 * pengecualian HTTP sehingga job notifikasi dicoba ulang, tanpa mengganggu saluran mail/database.
 */
class WhatsappChannel
{
    public static function aktif(): bool
    {
        return (bool) config('services.whatsapp.enabled') && filled(config('services.whatsapp.url'));
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::aktif() || blank($notifiable->no_hp ?? null) || ! method_exists($notification, 'pesanWhatsapp')) {
            return;
        }

        Http::withToken((string) config('services.whatsapp.token'))
            ->timeout(10)
            ->post((string) config('services.whatsapp.url'), [
                'to' => $notifiable->no_hp,
                'message' => $notification->pesanWhatsapp($notifiable),
            ])
            ->throw();
    }
}
