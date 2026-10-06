<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PeranDanIzinSeeder extends Seeder
{
    /** @var list<string> */
    public const IZIN = [
        'aset.lihat', 'aset.kelola', 'aset.ubah-kondisi', 'label.cetak',
        'mutasi.ajukan', 'mutasi.putuskan',
        'peminjaman.catat', 'peminjaman.ajukan', 'peminjaman.putuskan',
        'pemeliharaan.kelola',
        'dbr.bangkitkan', 'dbr.sahkan',
        'inventarisasi.kelola', 'inventarisasi.pindai', 'inventarisasi.sahkan',
        'penghapusan.kelola', 'penghapusan.putuskan',
        'laporan.lihat', 'laporan.ekspor', 'data-pribadi.lihat',
        'master.kelola', 'pengguna.kelola', 'pengaturan.kelola',
    ];

    /**
     * Pemetaan peran → izin menurut 01-PRD §3.1. Pembatasan "ruangan yang ditugaskan" (R) dan "milik sendiri" (M)
     * ditegakkan di Policy, bukan di sini.
     *
     * @return array<string, list<string>>
     */
    public static function peta(): array
    {
        return [
            'super-admin' => self::IZIN,
            'admin-bmn' => [
                'aset.lihat', 'aset.kelola', 'aset.ubah-kondisi', 'label.cetak',
                'mutasi.ajukan', 'mutasi.putuskan',
                'peminjaman.catat', 'peminjaman.putuskan',
                'pemeliharaan.kelola',
                'dbr.bangkitkan',
                'inventarisasi.kelola', 'inventarisasi.pindai',
                'penghapusan.kelola',
                'laporan.lihat', 'laporan.ekspor', 'data-pribadi.lihat',
                'master.kelola', 'pengguna.kelola',
            ],
            'pejabat-penatausahaan' => [
                'aset.lihat', 'peminjaman.putuskan', 'dbr.sahkan', 'inventarisasi.sahkan', 'penghapusan.putuskan',
                'laporan.lihat', 'laporan.ekspor', 'data-pribadi.lihat',
            ],
            'pic-ruangan' => [
                'aset.lihat', 'aset.ubah-kondisi', 'label.cetak', 'mutasi.ajukan',
                'peminjaman.catat', 'peminjaman.putuskan', 'pemeliharaan.kelola',
                'dbr.bangkitkan', 'dbr.sahkan', 'inventarisasi.pindai',
                'laporan.lihat', 'laporan.ekspor', 'data-pribadi.lihat',
            ],
            'pimpinan' => ['aset.lihat', 'laporan.lihat', 'laporan.ekspor'],
            'civitas' => ['peminjaman.ajukan'],
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::IZIN as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        foreach (self::peta() as $peran => $izin) {
            Role::findOrCreate($peran, 'web')->syncPermissions($izin);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
