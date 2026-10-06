<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Pengaturan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Pengaturan sistem: hanya super-admin (izin `pengaturan.kelola`).
 *
 * @property-read Schema $form
 */
class PengaturanSistem extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan sistem';

    protected static ?string $title = 'Pengaturan sistem';

    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $slug = 'pengaturan-sistem';

    protected string $view = 'filament.pages.pengaturan-sistem';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        /** @var User|null $pengguna */
        $pengguna = auth()->user();

        return (bool) $pengguna?->can('pengaturan.kelola');
    }

    public function mount(): void
    {
        $this->form->fill(Pengaturan::semua());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas instansi (kop dokumen)')->columns(2)->schema([
                    TextInput::make('instansi_baris1')->label('Baris 1 kop (kementerian)')->required()->maxLength(150),
                    TextInput::make('instansi_baris2')->label('Baris 2 kop (universitas)')->required()->maxLength(150),
                    TextInput::make('nama_kampus')->label('Nama kampus')->required()->maxLength(150),
                    TextInput::make('nama_unit')->label('Nama unit')->required()->maxLength(150),
                    TextInput::make('alamat')->label('Alamat')->maxLength(255),
                    TextInput::make('kontak')->label('Kontak')->maxLength(150),
                    TextInput::make('kota_surat')->label('Kota (tanda tangan)')->maxLength(100),
                ]),
                Section::make('Penandatangan')->columns(3)->schema([
                    TextInput::make('penandatangan_nama')->label('Pejabat penatausahaan — nama')->maxLength(150),
                    TextInput::make('penandatangan_nip')->label('NIP')->maxLength(30),
                    TextInput::make('penandatangan_jabatan')->label('Jabatan')->maxLength(150),
                    TextInput::make('penanggung_jawab_nama')->label('Penanggung jawab — nama')->maxLength(150),
                    TextInput::make('penanggung_jawab_nip')->label('NIP')->maxLength(30),
                    TextInput::make('penanggung_jawab_jabatan')->label('Jabatan')->maxLength(150),
                ]),
                Section::make('Fitur (BR-22)')->description('Ditegakkan di server, bukan hanya di tampilan.')->columns(3)->schema([
                    Toggle::make('fitur_tambah_aset')->label('Tambah aset'),
                    Toggle::make('fitur_hapus_aset')->label('Usul penghapusan aset'),
                    Toggle::make('fitur_ubah_kondisi')->label('Ubah kondisi'),
                    Toggle::make('fitur_mutasi')->label('Mutasi lokasi'),
                    Toggle::make('fitur_peminjaman')->label('Peminjaman'),
                ]),
                Section::make('Ambang & retensi')->columns(3)->schema([
                    TextInput::make('ambang_pengingat_inventarisasi_tahun')->label('Pengingat inventarisasi (tahun)')->numeric()->required()->minValue(1)->maxValue(5),
                    TextInput::make('retensi_peminjaman_bulan')->label('Retensi data peminjaman (bulan)')->numeric()->required()->minValue(1),
                    TextInput::make('maks_hari_pinjam')->label('Maksimal hari pinjam')->numeric()->required()->minValue(1),
                ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('simpan')
                ->footer([
                    Actions::make([
                        Action::make('simpan')->label('Simpan pengaturan')->submit('simpan'),
                    ]),
                ]),
        ]);
    }

    public function simpan(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        $bolehDisimpan = array_intersect_key($data, Pengaturan::BAWAAN);
        Pengaturan::simpanBanyak($bolehDisimpan);

        Notification::make()->success()->title('Pengaturan disimpan')->send();
    }
}
