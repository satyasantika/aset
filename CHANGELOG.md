# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan versi mengikuti
[Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.1.0] - 2026-10-06

Fase 1: fondasi.

### Ditambahkan
- Laravel 13 (PHP 8.3) dengan SQLite, Redis (cache/sesi/antrean), zona waktu Asia/Jakarta, locale `id`.
- Pest 4, Larastan level 6, Pint, skrip `composer cek`, hook git aktif.
- Filament 5 (panel `/admin`), Horizon, spatie/permission, spatie/activitylog, Sanctum, dompdf, endroid/qr-code, simple-excel.
- Primary key UUIDv7 di semua tabel aplikasi + uji arsitektur.
- Laravel Boost, endpoint `GET /api/health`, versi aplikasi di footer panel.
- Workflow CI (tanpa MySQL).
- Inisialisasi repositori: `.gitignore`, hook git, dokumen paket vibecoding di `docs/`, `CLAUDE.md`, `README.md`.
- Templat pull request dan changelog.

### Catatan
- Sail dan MySQL dikecualikan; runtime lewat container `aset-php`. Lihat `docs/KEPUTUSAN.md`.
