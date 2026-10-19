# ASET FKIP — Sistem Informasi Manajemen Aset

Sistem pengelolaan aset/Barang Milik Negara (BMN) Fakultas Keguruan dan Ilmu Pendidikan,
Universitas Siliwangi. Mencatat setiap barang per kode barang + NUP (bukan per baris agregat),
mendukung siklus penatausahaan sesuai PMK 181/PMK.06/2016 dan PP 28/2020, serta menjadi
pengganti SIMAN-FKIP-2. Sistem ini melengkapi aplikasi resmi BMN Kemenkeu (SIMAK-BMN/SAKTI),
bukan menggantikannya.

## Fitur utama

- **Inventaris per barang** — lokasi (gedung/ruangan), kondisi (Baik/Rusak Ringan/Rusak Berat),
  nilai perolehan, dan riwayat per unit barang, bukan per baris agregat.
- **Label QR** — cetak label per barang; pemindaian membuka halaman publik berisi info aset
  dan formulir lapor kerusakan, tanpa perlu login.
- **Mutasi lokasi** — pengajuan dan persetujuan perpindahan barang antarruangan.
- **Peminjaman** — pengajuan daring oleh civitas, serta pencatatan langsung keluar/masuk oleh
  PIC ruangan; riwayat peminjaman per pengguna.
- **Pemeliharaan** — tiket kerusakan dan penanganannya per barang/ruangan.
- **DBR/DBL** — pembangkitan dan persetujuan (tanda tangan) Daftar Barang Ruangan/Daftar
  Barang Lainnya sesuai format penatausahaan BMN.
- **Inventarisasi periodik** — pemindaian per ruangan, berita acara, dan rekap selisih.
- **Penghapusan** — pengajuan dan persetujuan usulan penghapusan barang.
- **Laporan & ekspor** — rekap lintas modul, termasuk mode tanpa data pribadi untuk pimpinan.
- **Log aktivitas & token API** — audit trail seluruh aksi dan integrasi terbatas via token.

## Peran pengguna

| Peran | Lingkup |
|---|---|
| Super Admin | Pengaturan sistem, pengguna, token API, log aktivitas |
| Admin BMN | Aset, mutasi, inventarisasi, penghapusan, laporan |
| Pejabat Penatausahaan | Mengesahkan DBR, berita acara, dan usulan penghapusan |
| PIC Ruangan | Pindai, ubah kondisi, pinjamkan, DBR, inventarisasi, pemeliharaan di ruangan yang ditugaskan |
| Pimpinan | Dasbor dan laporan (tanpa data pribadi) |
| Civitas (dosen, ormawa) | Mengajukan dan memantau peminjaman barang |
| Publik (tanpa akun) | Pindai QR barang dan lapor kerusakan |

## Teknologi

- **Backend**: Laravel 13 (PHP 8.5; kode saat ini masih PHP 8.3 sampai Fase B), Filament 5, Livewire
- **Data**: MariaDB 10.11+ native di host (`db_aset`, `db_aset_testing`; UUID native MariaDB, UUIDv7 dibuat aplikasi; produksi saat ini masih SQLite sampai Fase B, lihat `docs/KEPUTUSAN.md`). Profil Ringan: antrean, cache, sesi, dan lock di basis data (tanpa Redis/Horizon); kondisi kode saat ini sampai Fase B masih memakai Redis + Horizon
- **Keamanan & audit**: Laravel Sanctum, Spatie Permission, Spatie Activitylog
- **Dokumen & cetak**: barryvdh/laravel-dompdf, endroid/qr-code, spatie/simple-excel
- **Frontend build**: Vite, Tailwind CSS 4
- **Pengujian**: Pest, Larastan (PHPStan), Laravel Pint

## Menjalankan secara lokal

Proyek berjalan di dalam Docker FKIP (`docs/STANDAR-TEKNIS.md` §2, tanpa Laravel Sail): container `aset-php` (PHP-FPM),
`aset-nginx` (port 8012), dan `aset-scheduler`; MariaDB native di host lewat `host.docker.internal`; alias shell `asp` = `docker compose exec aset-php`
(mis. `asp php artisan test`). Variabel lingkungan diatur lewat
`.env` (lihat `.env.example`), tidak pernah disertakan di repository.
