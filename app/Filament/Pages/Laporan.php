<?php

namespace App\Filament\Pages;

use App\Filament\Exports\AsetRbHilangExporter;
use App\Filament\Exports\AsetRekonsiliasiExporter;
use App\Filament\Exports\DkpsPrasaranaExporter;
use App\Filament\Exports\DkpsSaranaExporter;
use App\Filament\Exports\DkpsTikExporter;
use App\Filament\Exports\LogAktivitasExporter;
use App\Filament\Exports\RiwayatPeminjamanExporter;
use App\Filament\Exports\TiketPemeliharaanExporter;
use App\Models\Aktivitas;
use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\TiketPemeliharaan;
use App\Models\User;
use App\Support\Dasbor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laporan & ekspor (PRD §9 LAP-02–LAP-09). Semua ekspor diantrekan (`ekspor`), berkas di disk `tmp` ≤ 24 jam, dan
 * query dibatasi sesuai cakupan pengunduh — ExportAction tidak memeriksa policy per baris (BR-05, BR-23).
 */
class Laporan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan & ekspor';

    protected static ?string $slug = 'laporan';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.laporan';

    public static function canAccess(): bool
    {
        /** @var User|null $pengguna */
        $pengguna = Filament::auth()->user();

        return (bool) $pengguna?->can('laporan.lihat');
    }

    private static function pengguna(): ?User
    {
        /** @var User|null $pengguna */
        $pengguna = Filament::auth()->user();

        return $pengguna;
    }

    private static function global(User $pengguna): bool
    {
        return $pengguna->hasAnyRole(Dasbor::PERAN_GLOBAL);
    }

    /**
     * @param  Builder<Aset>  $query
     * @return Builder<Aset>
     */
    public static function batasiAset(Builder $query, User $pengguna): Builder
    {
        return self::global($pengguna) ? $query : $query->dikelolaOleh($pengguna);
    }

    /**
     * @param  Builder<Ruangan>  $query
     * @return Builder<Ruangan>
     */
    public static function batasiRuangan(Builder $query, User $pengguna): Builder
    {
        return self::global($pengguna) ? $query : $query->dikelolaOleh($pengguna);
    }

    /**
     * @param  Builder<Peminjaman>  $query
     * @return Builder<Peminjaman>
     */
    public static function batasiPeminjaman(Builder $query, User $pengguna): Builder
    {
        return $pengguna->hasAnyRole(['pejabat-penatausahaan', 'pimpinan']) ? $query : $query->terlihatOleh($pengguna);
    }

    /**
     * @param  Builder<TiketPemeliharaan>  $query
     * @return Builder<TiketPemeliharaan>
     */
    public static function batasiTiket(Builder $query, User $pengguna): Builder
    {
        return self::global($pengguna) ? $query : $query->terlihatOleh($pengguna);
    }

    private function aksi(string $nama, string $label, string $exporter, ?callable $batasi, string $ikon): Action
    {
        return ExportAction::make($nama)
            ->label($label)
            ->icon($ikon)
            ->exporter($exporter)
            ->visible(fn (): bool => self::pengguna()?->can('laporan.ekspor') ?? false)
            ->modifyQueryUsing(fn (Builder $query): Builder => ($batasi && ($pengguna = self::pengguna())) ? $batasi($query, $pengguna) : $query);
    }

    public function eksporRekonsiliasiAction(): Action
    {
        return $this->aksi('eksporRekonsiliasi', 'Rekap aset & rekonsiliasi (kode + NUP)', AsetRekonsiliasiExporter::class, self::batasiAset(...), 'heroicon-o-table-cells');
    }

    public function eksporRbHilangAction(): Action
    {
        return $this->aksi('eksporRbHilang', 'Daftar Rusak Berat & hilang', AsetRbHilangExporter::class, self::batasiAset(...), 'heroicon-o-exclamation-triangle');
    }

    public function eksporPeminjamanAction(): Action
    {
        return $this->aksi('eksporPeminjaman', 'Riwayat peminjaman', RiwayatPeminjamanExporter::class, self::batasiPeminjaman(...), 'heroicon-o-arrows-right-left');
    }

    public function eksporPemeliharaanAction(): Action
    {
        return $this->aksi('eksporPemeliharaan', 'Rekap pemeliharaan', TiketPemeliharaanExporter::class, self::batasiTiket(...), 'heroicon-o-wrench-screwdriver');
    }

    public function eksporDkpsSaranaAction(): Action
    {
        return $this->aksi('eksporDkpsSarana', 'DKPS — Sarana laboratorium & pembelajaran', DkpsSaranaExporter::class, self::batasiAset(...), 'heroicon-o-beaker');
    }

    public function eksporDkpsPrasaranaAction(): Action
    {
        return $this->aksi('eksporDkpsPrasarana', 'DKPS — Prasarana pendidikan', DkpsPrasaranaExporter::class, self::batasiRuangan(...), 'heroicon-o-building-office');
    }

    public function eksporDkpsTikAction(): Action
    {
        return $this->aksi('eksporDkpsTik', 'DKPS — Teknologi informasi & komunikasi', DkpsTikExporter::class, self::batasiAset(...), 'heroicon-o-computer-desktop');
    }

    public function eksporLogAction(): Action
    {
        return $this->aksi('eksporLog', 'Log aktivitas', LogAktivitasExporter::class, null, 'heroicon-o-clipboard-document-list')
            ->visible(function (): bool {
                $pengguna = self::pengguna();

                return $pengguna !== null && $pengguna->can('viewAny', Aktivitas::class) && $pengguna->can('laporan.ekspor');
            });
    }

    /** @return array<int, array{aksi: string, ket: string}> */
    public function daftarLaporan(): array
    {
        return [
            ['aksi' => 'eksporRekonsiliasi', 'ket' => 'Kode barang, NUP, lokasi, kondisi, dan nilai — untuk rekonsiliasi semesteran dengan aplikasi BMN resmi.'],
            ['aksi' => 'eksporRbHilang', 'ket' => 'Barang Rusak Berat dan hilang — dasar usulan penghapusan.'],
            ['aksi' => 'eksporPeminjaman', 'ket' => 'Riwayat peminjaman beserta keterlambatan. Identitas peminjam hanya untuk yang berhak.'],
            ['aksi' => 'eksporPemeliharaan', 'ket' => 'Tiket pemeliharaan dan perbaikan beserta biaya.'],
            ['aksi' => 'eksporDkpsSarana', 'ket' => 'Tabel DKPS LAMDIK: sarana laboratorium dan pembelajaran per prodi.'],
            ['aksi' => 'eksporDkpsPrasarana', 'ket' => 'Tabel DKPS LAMDIK: prasarana pendidikan per prodi (luas, kapasitas, K3L).'],
            ['aksi' => 'eksporDkpsTik', 'ket' => 'Tabel DKPS LAMDIK: teknologi informasi dan komunikasi per prodi.'],
            ['aksi' => 'eksporLog', 'ket' => 'Log aktivitas untuk audit (khusus admin).'],
        ];
    }
}
