<?php

namespace App\Filament\Resources\Pengguna\Schemas;

use App\Models\Role;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PenggunaForm
{
    /** Peran yang hanya boleh diberikan super-admin (01-PRD §3.1: admin-bmn hanya penugasan PIC dan peran biasa). */
    public const PERAN_ISTIMEWA = ['super-admin', 'admin-bmn'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('email')->label('Surel')->email()->required()->maxLength(255)
                ->rules([new SurelDomainUnsil])
                ->unique(User::class, 'email', ignoreRecord: true),
            TextInput::make('nip')->label('NIP')->maxLength(30),
            TextInput::make('no_hp')->label('No. HP')->tel()->maxLength(30),
            Select::make('roles')->label('Peran')
                ->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', self::peranYangBolehDiberi()))
                ->multiple()->preload()
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $diizinkan = self::peranYangBolehDiberi();
                    $ditolak = Role::query()->whereIn('id', (array) $value)->whereNotIn('name', $diizinkan)->pluck('name');

                    if ($ditolak->isNotEmpty()) {
                        $fail('Anda tidak berwenang memberi peran: '.$ditolak->implode(', ').'.');
                    }
                }]),
            Toggle::make('aktif')->label('Aktif')->default(true),
            Toggle::make('wajib_ganti_sandi')->label('Wajib ganti sandi saat masuk')
                ->helperText('Nyalakan untuk akun dengan kata sandi awal yang dibuat admin.')
                ->default(true),
        ]);
    }

    /** @return list<string> */
    public static function peranYangBolehDiberi(): array
    {
        /** @var User|null $pelaku */
        $pelaku = auth()->user();
        $semua = Role::query()->pluck('name')->all();

        if ($pelaku?->hasRole('super-admin')) {
            return $semua;
        }

        return array_values(array_diff($semua, self::PERAN_ISTIMEWA));
    }
}
