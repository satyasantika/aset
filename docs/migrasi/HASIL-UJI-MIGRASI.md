# Hasil Uji Migrasi Data SIMAN-FKIP-2 → SIMAN FKIP 3

> **Status: BELUM DIJALANKAN dengan data nyata.** Perintah `siman2:impor` / `siman2:verifikasi` sudah diuji otomatis dengan fixture XLSX
> kecil (lihat `tests/Feature/ImporSiman2*`), tetapi uji dengan ekspor spreadsheet SIMAN-2 yang sebenarnya adalah **langkah manusia** (F7.4)
> yang membutuhkan akses ke spreadsheet backend dan basis data staging. Isi berkas ini setelah langkah di bawah dijalankan.
> Jangan mencantumkan data pribadi (nama, surel, HP) di sini.

## Prosedur (07-MIGRASI-DATA §2, §5–§6)

1. Bekukan SIMAN-2 (matikan toggle tambah/hapus/mutasi/peminjaman di Panel Sistem), ekspor spreadsheet backend GAS ke XLSX.
2. Isi `docs/migrasi/pemetaan-pengguna.csv` (username → surel `@unsil.ac.id`; **jangan dikomit setelah terisi**).
3. Taruh XLSX di `storage/app/tmp/siman2.xlsx`, lalu jalankan di staging:
   ```bash
   php artisan siman2:impor storage/app/tmp/siman2.xlsx --dry-run
   # perbaiki galat/pemetaan, ulangi sampai bersih, lalu:
   php artisan siman2:impor storage/app/tmp/siman2.xlsx --nup-berurutan   # hanya bila NUP lama memang berurutan
   php artisan siman2:verifikasi storage/app/tmp/siman2.xlsx
   ```
4. Lengkapi pemeriksaan manual §6: 30 label fisik acak dipindai (di luar daftar cetak ulang), daftar "Label perlu cetak ulang"
   dibagikan ke PIC, setiap PIC menerima surel atur kata sandi dan melihat ruangannya.

## Hasil (diisi manusia)

| Cek | Hasil | Catatan |
|---|---|---|
| Σ jumlah vs aset | _belum_ | |
| Per ruangan | _belum_ | |
| Per kondisi (B/RR/RB/Hilang) | _belum_ | |
| Pinjaman aktif | _belum_ | |
| Pengguna & PIC | _belum_ | |
| 30 label fisik acak | _belum_ | |
| Label perlu cetak ulang (jumlah, per ruangan) | _belum_ | |
| Galat/peringatan yang tersisa | _belum_ | |

## Keputusan perlu verifikasi dari uji data nyata

- Nama kolom/format sel sheet GAS sebenarnya (`tipeMutasi`, `unitIndex`, format tanggal) dibuat berdasarkan `SIMAN-FKIP-2/js` (mock server & kode klien);
  sesuaikan `Nilai`/`Impor*` bila ekspor nyata berbeda.
- Penanganan unit berkondisi "Hilang" (diimpor sebagai status `hilang`, kondisi mengikuti kondisi baris).
- Pemetaan unit pada mutasi unit lama (nomor unit bergeser, R-17) sebaik data sumber; unit terdampak ditandai cetak ulang.
