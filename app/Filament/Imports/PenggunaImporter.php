<?php

namespace App\Filament\Imports;

use App\Filament\Resources\Pengguna\Schemas\PenggunaForm;
use App\Models\Role;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Closure;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Impor massal pengguna: name, email, nip, no_hp, roles (pisah "|"), aktif.
 * Baris dengan surel di luar domain unsil.ac.id, surel ganda, atau peran yang tidak berwenang diberikan
 * oleh pelaku impor (lihat PenggunaForm::peranYangBolehDiberi) ditolak dan dilaporkan sebagai baris gagal;
 * akun yang sudah ada TIDAK ditimpa. Setiap akun baru dikirim surel atur kata sandi, sama seperti tambah satu akun.
 */
class PenggunaImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')->label('Nama')->requiredMapping()->rules(['required', 'max:255']),
            ImportColumn::make('email')->label('Surel')->requiredMapping()
                ->rules(['required', 'email', 'max:255', new SurelDomainUnsil, 'unique:users,email']),
            ImportColumn::make('nip')->label('NIP')->rules(['nullable', 'max:30']),
            ImportColumn::make('no_hp')->label('No. HP')->rules(['nullable', 'max:30']),
            ImportColumn::make('roles')->label('Peran')->array('|')
                ->fillRecordUsing(fn () => null)
                ->rules([function (string $attribute, mixed $value, Closure $fail): void {
                    $diizinkan = PenggunaForm::peranYangBolehDiberi();
                    $ditolak = collect((array) $value)->diff($diizinkan);

                    if ($ditolak->isNotEmpty()) {
                        $fail('Peran tidak dikenal atau tidak berwenang diberikan: '.$ditolak->implode(', ').'.');
                    }
                }]),
            ImportColumn::make('aktif')->label('Aktif')->boolean()->ignoreBlankState(),
        ];
    }

    public function resolveRecord(): ?User
    {
        // Selalu baris baru: akun yang sudah ada tidak boleh ditimpa oleh impor massal (ditolak oleh rule unique email).
        return new User;
    }

    protected function beforeCreate(): void
    {
        /** @var User $pengguna */
        $pengguna = $this->record;
        $pengguna->password = Str::random(40);
        $pengguna->aktif = $this->data['aktif'] ?? true;
    }

    protected function afterCreate(): void
    {
        /** @var User $pengguna */
        $pengguna = $this->record;

        $peran = array_values(array_filter((array) ($this->data['roles'] ?? [])));

        if ($peran !== []) {
            $pengguna->syncRoles(Role::query()->whereIn('name', $peran)->get());
        }

        Password::broker()->sendResetLink(['email' => $pengguna->email]);
    }

    public function getJobQueue(): ?string
    {
        return 'impor';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $isi = 'Impor pengguna selesai: '.number_format($import->successful_rows).' akun berhasil dibuat.';

        if ($gagal = $import->getFailedRowsCount()) {
            $isi .= ' '.number_format($gagal).' baris gagal.';
        }

        return $isi;
    }
}
