<?php

namespace App\Filament\Resources\Pengguna\Pages;

use App\Filament\Resources\Pengguna\PenggunaResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreatePengguna extends CreateRecord
{
    protected static string $resource = PenggunaResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Kata sandi acak tak diketahui siapa pun; pengguna menetapkannya sendiri lewat tautan di surel.
        $data['password'] = Str::random(40);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $pengguna */
        $pengguna = $this->record;

        Password::broker()->sendResetLink(['email' => $pengguna->email]);
    }
}
