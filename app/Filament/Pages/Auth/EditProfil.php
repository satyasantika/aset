<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Model;

/** Profil: mengganti kata sandi mengeluarkan sesi di perangkat lain. */
class EditProfil extends EditProfile
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[\SensitiveParameter] array $data): Model
    {
        // Pada $data kata sandi sudah di-hash oleh form; teks asli ada di state form.
        $kataSandiBaru = $this->data['password'] ?? null;

        if (filled($kataSandiBaru)) {
            $data['wajib_ganti_sandi'] = false;
        }

        $record = parent::handleRecordUpdate($record, $data);

        if (filled($kataSandiBaru)) {
            /** @var SessionGuard $guard */
            $guard = Filament::auth();
            $guard->logoutOtherDevices($kataSandiBaru);
        }

        return $record;
    }
}
