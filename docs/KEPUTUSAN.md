# Catatan Keputusan

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
