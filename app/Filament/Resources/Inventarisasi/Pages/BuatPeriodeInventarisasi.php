<?php

namespace App\Filament\Resources\Inventarisasi\Pages;

use App\Actions\Inventarisasi\BuatPeriode;
use App\Enums\JenisInventarisasi;
use App\Filament\Resources\Inventarisasi\PeriodeInventarisasiResource;
use App\Models\User;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class BuatPeriodeInventarisasi extends CreateRecord
{
    protected static string $resource = PeriodeInventarisasiResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $pelaku */
        $pelaku = auth()->user();

        return app(BuatPeriode::class)->handle(
            $data['nama'], JenisInventarisasi::from($data['jenis']), Carbon::parse($data['mulai']),
            filled($data['selesai_rencana'] ?? null) ? Carbon::parse($data['selesai_rencana']) : null, $data['ruangan'], $pelaku,
        );
    }
}
