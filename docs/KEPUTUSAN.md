# Catatan Keputusan

## Riwayat

- 15 Oktober 2026 — Profil Ringan (tanpa Horizon/Redis), LMS ditunda.
- 16 Oktober 2026 — Penyeragaman infrastruktur (lanjutan): nama database `db_<app>` (aset: `db_aset`, `db_aset_testing`); UUID native MariaDB; produksi MariaDB native; PHP 8.5; Sail ditiadakan dari dokumen (lihat entri di bawah).

## Butir terbuka (keputusan pemilik)

- **Basis data produksi: SQLite vs MySQL** — **Diputuskan 16 Oktober 2026: MariaDB** (10.11 LTS atau lebih baru, native di host,
  bukan container; SQLite tidak dipakai untuk dev, test, maupun produksi; kolasi default `utf8mb4_unicode_ci`; PHP 8.5).
  Berlaku setelah Fase B; kondisi saat ini: SQLite. Uraian masalah (dicatat 16 Oktober 2026): produksi saat ini memakai SQLite WAL,
  padahal spesifikasi (`STANDAR-TEKNIS.md` §1b, PRD, arsitektur) menetapkan basis data server. Akibatnya `lockForUpdate()` tidak berefek di
  SQLite (penulisan hanya diserialkan oleh basis data), sehingga uji tabrakan (`KeamananTest`, peminjaman/mutasi/penomoran)
  baru bermakna bila dijalankan di MariaDB. Opsi yang dahulu diajukan: tetap SQLite dengan pengecualian tertulis, atau pindah
  ke MySQL (DEPLOY.md §7).
  - Butir Fase B: migrasi data SQLite ke MariaDB (prosedur menyusul, tidak dicatat di sini).
  - Butir Fase B: jalankan ulang uji tabrakan `lockForUpdate` di MariaDB; hasil di SQLite belum membuktikan apa pun.

## 2026-10-16 — Penyeragaman infrastruktur: nama database, UUID native, MariaDB produksi, PHP 8.5, tanpa Sail

Keputusan pemilik; rujukan baku `docs/STANDAR-TEKNIS.md` §2.1, §2.2, §4a, §7.

1. **Nama database seragam** `db_<app>` / `db_<app>_testing`: aset memakai `db_aset` dan `db_aset_testing` (menggantikan `siman`/`siman_testing` pada prompt lama). Nama pengguna DB tidak diubah.
2. **UUID mengikuti Laravel 13**: `uuid()`/`foreignUuid()`/`uuidMorphs()` menghasilkan tipe `UUID` native MariaDB; UUIDv7 dibuat aplikasi lewat `HasUuids`. Klaim "CHAR(36)" sebagai tipe kolom dihapus dari dokumen; `char('id', 36)` dan `binary(16)` eksplisit dilarang. Gerbang uji ditambahkan di F1.4 (tipe kolom id uuid/char(36); 100 id UUIDv7 terurut; FK dan morph). Butir Fase B: produksi saat ini SQLite (`uuid()` berupa varchar(36)); data lama valid format UUID sehingga dapat dimasukkan ke kolom UUID native saat migrasi (tanpa prosedur di sini).
3. **Produksi memakai MariaDB native di host** lewat `host.docker.internal` (`extra_hosts: host-gateway`), sama dengan aplikasi produksi lain; compose produksi tidak boleh memuat container database; CI memakai service container `mariadb:10.11` sementara; alat dump `mariadb-dump`.
4. **PHP 8.5**: `Dockerfile` masih `php:8.3-fpm-alpine` dan `composer.json` `^8.3`; dev container sudah PHP 8.5 (`php/laravel8.5.Dockerfile`). Butir Fase B: naikkan Dockerfile produksi dan constraint composer.
5. **Laravel Sail ditiadakan**: asumsi Sail (`sail:install --with=mariadb`, "buat di docker init", awalan `sail`) dihapus dari `docs/04-PROMPT-BERTAHAP.md`, `docs/CLAUDE.md`, dan `README.md`; langkah F1.1 ditulis ulang mengikuti Docker FKIP (container `aset-php`/`aset-nginx`, alias `asp`, port 8012, MariaDB native), penomoran F1.x tetap. Kolom JSON (`k3l`, `snapshot`, `berita_acara`): JSON = alias LONGTEXT di MariaDB, tanpa fungsi `JSON_*`, cast array Laravel.

## 2026-10-06 — Tabel impor/ekspor Filament tidak memakai UUID (pengecualian STANDAR-TEKNIS §4a butir 5)

Filament 5 tidak menyediakan titik penggantian model yang stabil untuk `imports`, `exports`, dan `failed_import_rows`,
sehingga `id` ketiganya tetap bigint auto-increment. Kolom `user_id` tetap `foreignUuid` agar merujuk `users.id`
(UUIDv7). Ketiga tabel adalah infrastruktur kerangka kerja dan tidak tampil di URL/QR.

## 2026-10-06 — Runtime lewat container `aset-php`, tanpa Sail dan tanpa MySQL

- Sail dan MySQL dikecualikan atas permintaan pemilik. Perintah PHP dijalankan di container `aset-php`
  (PHP 8.3, mount `/home/satya/code/aset` → `/var/www/html`, Redis bersama di host `redis`).
- PHP 8.3 berarti Composer memilih Symfony 7.4 dan `spatie/laravel-activitylog` 4.x (versi 5 butuh PHP 8.4);
  migrasi activitylog terdiri dari tiga berkas dan kunci config `delete_records_older_than_days`.
- Basis data pengembangan & uji memakai SQLite. Konsekuensi: `lockForUpdate()` tidak berefek di SQLite
  (uji konkurensi peminjaman/mutasi perlu ditinjau ulang bila MySQL diaktifkan kembali).

## 2026-10-06 — Produksi memakai SQLite (tanpa MySQL) dan perubahan kecil pada diagram status

- Konfigurasi produksi (`compose.prod.yaml`) memakai SQLite WAL pada volume Docker + cadangan harian 30 hari, sesuai
  pengecualian MySQL oleh pemilik. Peralihan ke MySQL/PostgreSQL didokumentasikan di `docs/DEPLOY.md` §7.
- Transisi status aset `diusulkan_hapus → hilang` ditambahkan di luar diagram PRD §7 (aset diusulkan hapus yang ternyata
  hilang pada inventarisasi tetap dapat ditandai hilang).
- Jadwal ruangan pada API Surat hanya bersumber dari `pemakaian_ruangan`, karena peminjaman SIMAN bersifat per barang
  (bukan per ruangan) — lihat `docs/API.md`.
- Kolom DKPS LAMDIK adalah pemetaan awal (`docs/FORMAT-DKPS.md`); templat resmi harus dicocokkan manual.
- Rilis ditandai `v1.0.0-rc.1`; `v1.0.0` menunggu langkah manusia: uji migrasi data nyata (F7.4), build image di
  staging, UAT (`docs/05-UJI-PENERIMAAN.md`), cutover, dan penonaktifan Web App GAS lama.
