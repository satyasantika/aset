<?php

namespace Tests\Support;

use Spatie\SimpleExcel\SimpleExcelWriter;

/** Membuat berkas XLSX tiruan ekspor SIMAN-2 (satu sheet per kunci) untuk uji impor. */
class Siman2Fixture
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $sheet  nama sheet → baris asosiatif
     */
    public static function buat(array $sheet, ?string $jalur = null): string
    {
        $jalur ??= storage_path('app/tmp/siman2-uji-'.bin2hex(random_bytes(4)).'.xlsx');
        @mkdir(dirname($jalur), 0777, true);
        @unlink($jalur);

        $penulis = SimpleExcelWriter::create($jalur);
        $pertama = true;

        foreach ($sheet as $nama => $baris) {
            $pertama ? $penulis->nameCurrentSheet($nama) : $penulis->addNewSheetAndMakeItCurrent($nama);
            $pertama = false;

            if ($baris !== []) {
                $penulis->addRows($baris);
            }
        }

        $penulis->close();

        return $jalur;
    }

    /** @param  array<string, string>  $peta */
    public static function pemetaan(array $peta): string
    {
        $jalur = sys_get_temp_dir().'/pemetaan-'.bin2hex(random_bytes(4)).'.csv';
        $isi = "username,email\n";

        foreach ($peta as $u => $e) {
            $isi .= "{$u},{$e}\n";
        }

        file_put_contents($jalur, $isi);

        return $jalur;
    }

    /** Dataset dasar kecil mengikuti bentuk mock-server.js SIMAN-2. @return array<string, list<array<string, mixed>>> */
    public static function dasar(): array
    {
        return [
            'config' => [[
                'instansi_baris1' => 'KEMENTRIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI', 'instansi_baris2' => 'UNIVERSITAS SILIWANGI',
                'nama_kampus' => 'FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN', 'alamat' => 'Jl. Siliwangi No. 24 Tasikmalaya', 'kontak' => 'fkip@unsil.ac.id',
                'logo_kiri' => 'https://contoh.test/logo.png', 'kota_surat' => 'Tasikmalaya', 'nama_kasubag' => 'Arip Moh. Bahtiar',
                'nip_kasubag' => '197303042008011004', 'nama_penanggungjawab' => 'Redi Hermanto', 'nip_penanggungjawab' => '198109102015041002',
            ]],
            'ruanganKategori' => [
                ['id' => 1, 'namaKategori' => 'Laboratorium'], ['id' => 2, 'namaKategori' => 'Ruangan Kelas'], ['id' => 3, 'namaKategori' => 'Auditorium'],
            ],
            'ruangan' => [
                ['id' => 1, 'namaRuangan' => 'Aula Utama', 'idRuangan' => 'R-001', 'kategoriRuangan' => 'Auditorium', 'kapasitas' => '500', 'fotoRuangan' => 'https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQrStUv', 'userIdPIC' => 2],
                ['id' => 2, 'namaRuangan' => 'Lab 1', 'idRuangan' => 'R-002', 'kategoriRuangan' => 'Laboratorium', 'kapasitas' => '40', 'fotoRuangan' => 'https://img.icons8.com/color/96/laboratory.png', 'userIdPIC' => 2],
                ['id' => 3, 'namaRuangan' => 'Lab 2', 'idRuangan' => '', 'kategoriRuangan' => 'Laboratorium', 'kapasitas' => '', 'fotoRuangan' => '', 'userIdPIC' => ''],
            ],
            'users' => [
                ['id' => 1, 'username' => 'admin', 'password' => 'admin123', 'nama' => 'Administrator', 'role' => 'Admin', 'ruangan' => '[]', 'hp' => '0811-2233-4455'],
                ['id' => 2, 'username' => 'petugas1', 'password' => '123', 'nama' => 'Petugas Lab 1', 'role' => 'Penanggungjawab', 'ruangan' => '["Lab 1","Lab 2","Ruang Hantu"]', 'hp' => '0812-3456-7890'],
                ['id' => 3, 'username' => 'pimpinan', 'password' => '123', 'nama' => 'Tim Infrastruktur', 'role' => 'Pimpinan', 'ruangan' => '', 'hp' => '0898-7654-3210'],
            ],
        ];
    }
}
