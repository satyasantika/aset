<?php

namespace App\Filament\RelationManagers;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusCekTautan;
use App\Models\TautanBerkas;
use App\Rules\TautanBerkasValid;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Tautan berkas (foto, dokumen perolehan, SK, dst.) untuk model pemilik mana pun. Selalu lewat PenyimpananBerkas
 * (STANDAR-TEKNIS §1a.7); tidak ada unggahan.
 */
class TautanBerkasRelationManager extends RelationManager
{
    protected static string $relationship = 'tautanBerkas';

    protected static ?string $title = 'Foto & dokumen (tautan)';

    protected static ?string $modelLabel = 'tautan berkas';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('jenis')->label('Jenis')->required()->options(
                array_combine((array) config('berkas.jenis'), array_map(fn (string $j): string => str($j)->replace('_', ' ')->headline()->toString(), (array) config('berkas.jenis')))
            ),
            TextInput::make('label')->label('Label')->required()->maxLength(150),
            TextInput::make('url')->label('Tautan (Google Drive/Docs unsil.ac.id)')->required()->url()->maxLength(2048)
                ->rules([new TautanBerkasValid])
                ->helperText('Dokumen umum: "Siapa saja yang memiliki link". Dokumen berisi data pribadi: bagikan terbatas ke domain unsil.ac.id.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('label')->label('Label'),
                TextColumn::make('status_cek')->label('Status tautan')->badge()
                    ->formatStateUsing(fn (StatusCekTautan $state) => match ($state) {
                        StatusCekTautan::Belum => 'Belum diperiksa',
                        StatusCekTautan::DapatDiakses => 'Dapat diakses',
                        StatusCekTautan::TidakDapatDiakses => 'Tidak dapat diakses',
                    })
                    ->color(fn (StatusCekTautan $state) => match ($state) {
                        StatusCekTautan::DapatDiakses => 'success',
                        StatusCekTautan::TidakDapatDiakses => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('url')->label('Buka')->formatStateUsing(fn () => 'Buka berkas')->url(fn (TautanBerkas $r) => $r->url, shouldOpenInNewTab: true),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah tautan')->using(function (array $data, self $livewire): Model {
                    return app(PenyimpananBerkas::class)->tambah($livewire->getOwnerRecord(), $data['jenis'], $data['label'], $data['url'], auth()->user());
                }),
            ])
            ->recordActions([
                EditAction::make()->using(function (TautanBerkas $record, array $data): Model {
                    return app(PenyimpananBerkas::class)->perbarui($record, $data['url'], $data['label']);
                }),
                DeleteAction::make()->using(function (TautanBerkas $record): void {
                    app(PenyimpananBerkas::class)->hapus($record);
                }),
            ]);
    }
}
