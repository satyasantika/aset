# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan versi mengikuti
[Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.2.0] - 2026-10-07

Fase 2: autentikasi dan peran.

### Ditambahkan
- Peran (super-admin, admin-bmn, pejabat-penatausahaan, pic-ruangan, pimpinan, civitas) dan matriks permission PRD §3.1; `Gate::before` super-admin; Horizon hanya super-admin.
- Login panel dengan pembatasan 5/menit per surel+IP, akun nonaktif ditolak, tanpa registrasi, reset kata sandi, MFA aplikasi wajib untuk super-admin & admin-bmn, ganti kata sandi mengeluarkan perangkat lain.
- Jejak audit server-side (`TercatatAktivitas`) dan resource Log aktivitas baca-saja.
- Kelola pengguna (surel unsil.ac.id, NIP, HP, aktif/nonaktif) dengan pembatasan pemberian peran istimewa.

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
