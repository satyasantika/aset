# Paket Vibecoding — SIMAN FKIP 3 (Aset/BMN & Peminjaman)

> Pembangunan ulang **SIMAN-FKIP-2** (Vue 3 + Google Apps Script + Google Sheets, folder `aset/SIMAN-FKIP-2/`) menjadi Laravel 13 + MariaDB 10.11+ (Profil Ringan: tanpa Redis/Horizon, STANDAR-TEKNIS §1b). Disusun 6 Oktober 2026.

## 1. Mulai dari sini

| Urutan | Berkas | Isi |
|---|---|---|
| 1 | `00-ANALISIS-SISTEM-BERJALAN.md` | Cara kerja SIMAN-FKIP-2, yang dipertahankan, **17 temuan risiko** (mis. otorisasi hanya di klien, data dapat dibaca publik lewat URL GAS, nomor unit digeser ulang setelah mutasi sehingga label bisa salah tunjuk) |
| 2 | `06-REKOMENDASI-REGULASI.md` | Tambahan berdasarkan PP 28/2020, PMK 181/2016, LAMDIK IAPSK 3.0 (RG-01–RG-13): DBR/DBL, inventarisasi 5 tahunan, daftar RB/hilang, penghapusan ber-SK, nilai perolehan, pembedaan pihak luar |
| 3 | `01-PRD.md` | Peran & matriks akses, modul, user story, BR-01–BR-23, diagram status, laporan |
| 4 | `02-ARSITEKTUR.md` | Stack, cache/lock/antrean Profil Ringan (lock peminjaman/mutasi = transaksi + `lockForUpdate`), rute publik & lapangan, job, integrasi dengan sistem Surat |
| 5 | `03-SKEMA-DATABASE.md` | ERD, tabel aset per kode+NUP, transaksi, DBR snapshot, inventarisasi, `label_lama` |
| 6 | `07-MIGRASI-DATA.md` | Sheets → MariaDB: pemecahan unit, label lama, cetak ulang label terdampak, verifikasi, cutover |
| 7 | `04-PROMPT-BERTAHAP.md` | **Inti**: F0–F12, 58 langkah prompt + commit |
| 8 | `05-UJI-PENERIMAAN.md` | UAT per peran, migrasi, checklist keamanan & go-live |
| — | `CLAUDE.md` | Salin ke root repo |
| — | `STANDAR-TEKNIS.md`, `STANDAR-GIT.md` | Standar bersama |

## 2. Fase & tag

F0 repo → F1 fondasi (`v0.1.0`) → F2 auth & peran (`v0.2.0`) → F3 master & tautan berkas (`v0.3.0`) → F4 aset & label (`v0.4.0`) → F5 mutasi (`v0.5.0`) → F6 peminjaman (`v0.6.0`) → **F7 migrasi data SIMAN-2** (`v0.7.0`) → F8 pemeliharaan (`v0.8.0`) → F9 DBR & inventarisasi (`v0.9.0`) → F10 penghapusan & laporan (`v0.10.0`) → F11 notifikasi, penjadwal, API untuk Surat (`v0.11.0`) → F12 pengerasan & cutover (`v1.0.0`).

Migrasi ditempatkan setelah aset, mutasi, dan peminjaman siap agar data lama dapat dipakai menguji fitur berikutnya.

## 3. Hal yang perlu diputuskan sebelum mulai

- Struktur pejabat penatausahaan (Kasubag Umum/WD) dan nama kementerian pada kop.
- Sumber referensi kodefikasi barang BMN & nama aplikasi resmi BMN yang dipakai Unsil.
- Pemetaan akun lama (username) ke surel `@unsil.ac.id`.
- Retensi data peminjaman; skema pemanfaatan untuk pihak luar.
- Kesepakatan dengan pengembang sistem Surat: Aset menjadi pemilik master ruangan (API F11).

## 4. Prasyarat

Docker FKIP (WSL2) dengan MariaDB 10.11+ native, Mailpit bersama (tanpa Redis) — PHP 8.5, Composer, dan Node 22 ada di image `aset-php` (STANDAR-TEKNIS §2), Git di host, agen AI dengan Laravel Boost, akses ke spreadsheet backend SIMAN-2 untuk ekspor XLSX.

## Riwayat

- 15 Oktober 2026 — Profil Ringan (tanpa Horizon/Redis), LMS ditunda.
