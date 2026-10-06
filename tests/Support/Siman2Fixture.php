<?php

namespace Tests\Support;

use Spatie\SimpleExcel\SimpleExcelWriter;

/** Membuat berkas XLSX tiruan ekspor SIMAN-2 (satu sheet per kunci) untuk uji impor. */
class Siman2Fixture
{
    /** @var list<string> berkas yang dibuat proses ini (agar uji paralel tidak saling menghapus) */
    private static array $dibuat = [];

    public static function bersihkan(): void
    {
        foreach (self::$dibuat as $jalur) {
            @unlink($jalur);
        }

        self::$dibuat = [];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $sheet  nama sheet → baris asosiatif
     */
    public static function buat(array $sheet, ?string $jalur = null): string
    {
        $jalur ??= storage_path('app/tmp/siman2-uji-'.bin2hex(random_bytes(4)).'.xlsx');
        @mkdir(dirname($jalur), 0777, true);
        @unlink($jalur);
        self::$dibuat[] = $jalur;

        $penulis = SimpleExcelWriter::create($jalur);
        $pertama = true;

        foreach ($sheet as $nama => $baris) {
            $pertama ? $penulis->nameCurrentSheet($nama) : $penulis->addNewSheetAndMakeItCurrent($nama);
            $pertama = false;

            if ($baris !== []) {
                // XLSX ditulis posisional: samakan kunci semua baris agar kolom tidak bergeser.
                $kunci = array_values(array_unique(array_merge(...array_map('array_keys', $baris))));
                $penulis->addRows(array_map(fn (array $b) => array_map(fn (string $k) => $b[$k] ?? '', array_combine($kunci, $kunci)), $baris));
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

    /** Dataset dasar + kategori, inventaris, dan mutasi (R-17). @return array<string, list<array<string, mixed>>> */
    public static function denganInventaris(): array
    {
        $d = self::dasar();
        $d['kategori'] = [
            ['id' => 1, 'namaKategori' => 'Elektronik', 'kodeKategori' => 'ELE'],
            ['id' => 2, 'namaKategori' => 'Mebeler', 'kodeKategori' => 'MBL'],
        ];
        $d['inventaris'] = [
            ['id' => 1, 'kodeBarang' => '83719', 'nup' => '123456', 'kodeBmn' => '3100203001', 'namaBarang' => 'Laptop ASUS ROG', 'merkType' => 'Asus',
                'penguasaan' => 'Milik Sendiri', 'tahun' => '2026', 'bulan' => 'November', 'kategori' => 'Elektronik', 'kondisi' => 'Baik', 'lokasiBarang' => 'Lab 1',
                'jumlah' => 5, 'foto' => 'https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQrStUv', 'keterangan' => 'Untuk praktikum',
                'unitKondisi' => '{"2":"Rusak Ringan","5":"Rusak Berat"}', 'printedUnits' => '[1,2,3]'],
            ['id' => 2, 'kodeBarang' => '49281', 'nup' => '123457', 'kodeBmn' => '777778', 'namaBarang' => 'Kamera DSLR', 'merkType' => 'Sony Alpha',
                'penguasaan' => 'Milik Sendiri', 'tahun' => '2026', 'bulan' => 'Januari', 'kategori' => 'Elektronik', 'kondisi' => 'Baik', 'lokasiBarang' => 'Lab 1',
                'jumlah' => 1, 'foto' => 'https://img.icons8.com/color/96/slr-camera.png', 'keterangan' => '', 'unitKondisi' => '', 'printedUnits' => '[]'],
            ['id' => 3, 'kodeBarang' => '935464', 'nup' => '100', 'kodeBmn' => '3100102002', 'namaBarang' => 'Kursi Kuliah', 'merkType' => 'Chitose',
                'penguasaan' => 'Milik Negara', 'tahun' => '2022', 'bulan' => 'Agustus', 'kategori' => 'Mebeler', 'kondisi' => 'Baik', 'lokasiBarang' => 'Lab 2',
                'jumlah' => 3, 'foto' => '', 'keterangan' => 'Pengadaan 2022', 'unitKondisi' => '{"3":"Hilang"}', 'printedUnits' => '[1,2]'],
            ['id' => 4, 'kodeBarang' => '555111', 'nup' => '', 'kodeBmn' => '', 'namaBarang' => 'Meja Lipat', 'merkType' => '',
                'penguasaan' => 'Milik Negara', 'tahun' => '2021', 'bulan' => '', 'kategori' => 'Mebeler', 'kondisi' => 'Baik', 'lokasiBarang' => 'Lab 1',
                'jumlah' => 4, 'foto' => '', 'keterangan' => '', 'unitKondisi' => '', 'printedUnits' => '[1,2,3,4]'],
            ['id' => 5, 'kodeBarang' => '555111-2', 'nup' => '', 'kodeBmn' => '', 'namaBarang' => 'Meja Lipat', 'merkType' => '',
                'penguasaan' => 'Milik Negara', 'tahun' => '2021', 'bulan' => '', 'kategori' => 'Mebeler', 'kondisi' => 'Rusak Ringan', 'lokasiBarang' => 'Aula Utama',
                'jumlah' => 1, 'foto' => '', 'keterangan' => 'Hasil mutasi 1 unit dari 555111', 'unitKondisi' => '', 'printedUnits' => '[]'],
        ];
        $d['mutasi'] = [
            ['id' => 1, 'tanggal' => '08/02/2026 10:00', 'idBarang' => 4, 'kodeBarang' => '555111-2', 'namaBarang' => 'Meja Lipat', 'asal' => 'Lab 1', 'tujuan' => 'Aula Utama',
                'pemohon' => 'Petugas Lab 1', 'alasan' => 'Dipindah', 'status' => 'Disetujui', 'tipeMutasi' => 'unit', 'unitIndex' => 2],
            ['id' => 2, 'tanggal' => '09/02/2026 10:00', 'idBarang' => 1, 'kodeBarang' => '83719-1', 'namaBarang' => 'Laptop', 'asal' => 'Lab 1', 'tujuan' => 'Lab 2',
                'pemohon' => 'x', 'alasan' => 'x', 'status' => 'Pending', 'tipeMutasi' => 'unit', 'unitIndex' => 1],
        ];

        return $d;
    }

    /** Lengkap: inventaris + peminjaman + laporan. @return array<string, list<array<string, mixed>>> */
    public static function lengkap(): array
    {
        $d = self::denganInventaris();
        $d['peminjaman'] = [
            ['id' => 1, 'idBarang' => 3, 'kodeUnit' => '935464-2', 'unitIndex' => 2, 'namaBarang' => 'Kursi Kuliah', 'lokasiAsal' => 'Lab 2', 'kodeTransaksi' => 'PJM-seed-1',
                'peminjam' => 'Budi Santoso', 'kontakPeminjam' => '0812-0000-1111', 'keperluan' => 'Rapat mendadak di Aula', 'tanggalPinjam' => '01 Sep 2026, 09.00',
                'tanggalRencanaKembali' => '2026-09-05', 'tanggalKembaliAktual' => '', 'kondisiSaatKembali' => '', 'status' => 'Dipinjam', 'dicatatOleh' => 'Petugas Lab 1'],
            ['id' => 2, 'idBarang' => 1, 'kodeUnit' => '83719-1', 'unitIndex' => 1, 'namaBarang' => 'Laptop', 'lokasiAsal' => 'Lab 1', 'kodeTransaksi' => 'PJM-seed-2',
                'peminjam' => 'Siti', 'kontakPeminjam' => '0813', 'keperluan' => 'Seminar', 'tanggalPinjam' => '02/09/2026 08:00',
                'tanggalRencanaKembali' => '2026-09-03', 'tanggalKembaliAktual' => '03/09/2026 15:00', 'kondisiSaatKembali' => 'Baik', 'status' => 'Dikembalikan', 'dicatatOleh' => 'Administrator'],
            ['id' => 3, 'idBarang' => 1, 'kodeUnit' => 'ELE-83719-3', 'unitIndex' => 3, 'namaBarang' => 'Laptop', 'lokasiAsal' => 'Lab 1', 'kodeTransaksi' => 'PJM-seed-2',
                'peminjam' => 'Siti', 'kontakPeminjam' => '0813', 'keperluan' => 'Seminar', 'tanggalPinjam' => '02/09/2026 08:00',
                'tanggalRencanaKembali' => '2026-09-03', 'tanggalKembaliAktual' => '03/09/2026 15:00', 'kondisiSaatKembali' => 'Rusak Ringan', 'status' => 'Dikembalikan', 'dicatatOleh' => 'Administrator'],
            ['id' => 4, 'idBarang' => 99, 'kodeUnit' => 'GAIB-1', 'unitIndex' => 1, 'namaBarang' => 'Gaib', 'lokasiAsal' => 'Lab 1', 'kodeTransaksi' => 'PJM-seed-3',
                'peminjam' => 'X', 'kontakPeminjam' => '', 'keperluan' => 'x', 'tanggalPinjam' => '03 Sep 2026, 09.00', 'tanggalRencanaKembali' => '2026-09-04',
                'tanggalKembaliAktual' => '', 'kondisiSaatKembali' => '', 'status' => 'Dipinjam', 'dicatatOleh' => ''],
            ['id' => 5, 'idBarang' => 2, 'kodeUnit' => '49281-1', 'unitIndex' => 1, 'namaBarang' => 'Kamera', 'lokasiAsal' => 'Lab 1', 'kodeTransaksi' => 'PJM-seed-4',
                'peminjam' => 'Dewi', 'kontakPeminjam' => '', 'keperluan' => 'Liputan', 'tanggalPinjam' => '01 Sep 2026, 09.00', 'tanggalRencanaKembali' => '2026-08-30',
                'tanggalKembaliAktual' => '', 'kondisiSaatKembali' => '', 'status' => 'Dipinjam', 'dicatatOleh' => 'Petugas Lab 1'],
        ];
        $d['laporan'] = [
            ['id' => 1, 'waktu' => '6 Jan 2026, 20.30', 'user' => 'Administrator', 'aksi' => 'Login', 'detail' => 'User berhasil login'],
            ['id' => 2, 'waktu' => '07/01/2026 08:15', 'user' => 'Petugas Lab 1', 'aksi' => 'Tambah Barang', 'detail' => 'Laptop ASUS ROG'],
        ];

        return $d;
    }
}
