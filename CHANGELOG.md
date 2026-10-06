# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan versi mengikuti
[Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [1.0.0-rc.1] - 2026-10-18

Fase 12: pengerasan, produksi, dan persiapan cutover. Rilis kandidat — `v1.0.0` ditandai setelah langkah manusia
(cutover, `docs/07-MIGRASI-DATA.md` §7) selesai.

### Ditambahkan
- Uji keamanan: `MatriksAksesTest` (seluruh sel matriks PRD §3.1), `KeamananTest` (IDOR, rate limit, XSS, tanpa unggahan, konkurensi, header, tanpa QR pihak ketiga); `docs/KEAMANAN.md` (hasil `composer audit`/`npm audit`: bersih).
- Header keamanan global (`HeaderKeamanan`): CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS di produksi.
- Docker produksi tanpa MySQL (`Dockerfile`, `compose.prod.yaml`, nginx, Horizon, scheduler, cadangan SQLite harian 30 hari), `.env.production.example`, `docs/DEPLOY.md` (DNS, TLS, cadangan & uji pulih).
- `siman:siapkan-uat` (akun & data contoh staging) serta `docs/PANDUAN-PIC.md` dan `docs/PANDUAN-ADMIN.md`.

### Diperbaiki
- Pesan pindai "tidak ditemukan" dirender tanpa escape dari properti Livewire publik; kini di-escape Blade.

### Diubah
- SQLite produksi memakai WAL, `busy_timeout` 5000 ms, `synchronous=NORMAL` (dapat diatur lewat env).

## [0.11.0] - 2026-10-17

Fase 11: notifikasi, penjadwal, dan API untuk Surat.

### Ditambahkan
- Notifikasi (antrean `notifikasi`, mail + database, setelah commit): pengajuan peminjaman baru, keputusan peminjaman, peminjaman terlambat, pengingat pengambilan, mutasi diajukan/diputuskan, laporan kerusakan baru, DBR dan berita acara menunggu pengesahan, pengingat inventarisasi. Saluran WhatsApp opsional (`WHATSAPP_ENABLED`) untuk pengingat terlambat/pengambilan. Pemicu terpusat di `PemicuNotifikasi`; penerima di `Penerima` (tanpa PIC → admin BMN).
- Tugas terjadwal (withoutOverlapping + onOneServer): `aset:pengingat-terlambat`, `aset:pengingat-pengambilan`, `aset:tandai-dbr-usang`, `aset:periksa-tautan`, `aset:pengingat-inventarisasi`, `aset:pangkas-peminjaman` (anonimkan data pribadi setelah retensi, BR-23), `aset:bersihkan-tmp`.
- API v1 (`docs/API.md`): `GET /api/v1/ruangan`, `GET /api/v1/ruangan/{kode}/jadwal`, `POST /api/v1/ruangan/{kode}/pemakaian` (Sanctum, ability `ruangan:baca`/`ruangan:pakai`, 60/menit/token, cek bentrok transaksional, idempoten via `referensi_eksternal`), tabel `pemakaian_ruangan`, dan pengelolaan token oleh super-admin (Sistem → Token API).

## [0.10.0] - 2026-10-15

Fase 10: usulan penghapusan, dasbor, dan laporan.

### Ditambahkan
- Usulan penghapusan BMN (`USL-{tahun}-{4 digit}`): pengajuan oleh admin, persetujuan/penolakan pejabat penatausahaan, pencatatan SK penghapusan (aset → `dihapus`), `UsulanPenghapusanResource`.
- Dasbor statistik aset per peran (jumlah, nilai, kondisi, aset per ruangan, peminjaman aktif/terlambat, tiket, DBR) dengan cache berversi dan widget `StatistikAsetWidget`/`RincianAsetWidget`.
- Halaman **Laporan** (`/admin/laporan`) dengan ekspor terantre (`ekspor`, disk `tmp`): rekonsiliasi kode+NUP, daftar Rusak Berat & hilang, riwayat peminjaman, rekap pemeliharaan, log aktivitas, dan tiga tabel DKPS LAMDIK (`docs/FORMAT-DKPS.md`). Data pribadi disamarkan bagi pengunduh tanpa izin `data-pribadi.lihat`.

### Diubah
- Transisi status aset `diusulkan_hapus` → `hilang` diizinkan.

## [0.9.0] - 2026-10-14

Fase 9: DBR/DBL & inventarisasi.

### Ditambahkan
- DBR/DBL berbasis snapshot: `BangkitkanDbr`, `SetujuiDbrOlehPic`, `SahkanDbr` (hash isi daftar menjamin yang disahkan = yang disetujui PIC), `KembalikanDbr`, penanda `perlu_diperbarui` otomatis dari observer `Aset` dan mutasi, PDF dari snapshot (`/cetak/dbr/{id}`), dan `DbrResource`.
- Periode inventarisasi (satu berjalan, lock Redis), penugasan petugas per ruangan, penahanan mutasi pada ruangan yang diinventarisasi (BR-14, mengikuti toggle).
- Halaman `/inventarisasi/{periode}/{ruangan}` (pindai QR/label lama, koreksi kondisi, temuan berlebih dengan foto tautan, progres, selesai ruangan → sisa `tidak_ditemukan`).
- Penutupan periode (kondisi berubah diterapkan dengan riwayat sumber `inventarisasi`), berita acara (snapshot, PDF, Excel selisih), pengesahan pejabat, verifikasi aset hilang oleh admin, dan widget peringatan inventarisasi (BR-15).

## [0.8.0] - 2026-10-13

Fase 8: pemeliharaan & lapor kerusakan.

### Ditambahkan
- Tiket pemeliharaan (`TKT-{tahun}-{4 digit}`): `BukaTiketPemeliharaan` (idempoten per sumber, opsi aset `dalam_perbaikan`), `UbahStatusTiket`, `SelesaikanTiket` (tindakan, biaya DECIMAL, kondisi akhir → `UbahKondisiAset`, aset kembali aktif), `TiketPemeliharaanResource` (PIC: ruangannya; data pelapor BR-23) dan stub F6.4 terisi.
- Lapor kerusakan publik `/lapor-kerusakan/{aset}` (deskripsi wajib, nama/kontak opsional, honeypot, 5/jam/IP, hash IP HMAC harian tanpa IP mentah) dan pelaporan civitas/staf dari `/pindai`; event `LaporanKerusakanDiterima` untuk notifikasi F11.

## [0.7.0] - 2026-10-12

Fase 7: migrasi data SIMAN-FKIP-2.

### Ditambahkan
- Perintah `siman2:impor` (XLSX; `--dry-run`, `--nup-berurutan`, pemetaan username→surel) yang idempoten lewat `impor_siman2_log`: pengaturan, kategori ruangan, ruangan + foto Drive, pengguna (tanpa kata sandi lama; surel atur kata sandi) + PIC.
- Impor inventaris: baris jumlah n → n aset, kondisi per unit, `dicetak_pada`, foto, `label_lama` (KODEKATEGORI-KODE-i, KODE-i, KODE/NUP/KODEBMN), serta deteksi label terdampak mutasi unit (R-17) yang ditandai cetak ulang tanpa `label_lama` ambigu.
- Impor mutasi, peminjaman (per `kodeTransaksi`, `kodeUnit` → `label_lama`), dan log lama (`activity_log` `siman2`, `pelaku_lama` sebagai properti).
- `siman2:verifikasi` (rekonsiliasi §6) dan halaman "Label perlu cetak ulang" per ruangan.

### Catatan
- Uji dengan ekspor nyata (F7.4) adalah langkah manusia dan belum dijalankan; lihat `docs/migrasi/HASIL-UJI-MIGRASI.md`.

## [0.6.0] - 2026-10-11

Fase 6: peminjaman internal.

### Ditambahkan
- Skema peminjaman (nomor `PJM-{tahun}-{5 digit}`) dan `CekKetersediaan` (BR-08/BR-11: rentang tumpang tindih ditolak, batas bersinggungan boleh, peminjaman terlambat tetap menahan aset).
- Keranjang PIC `/keranjang` dengan `CatatPeminjamanLangsung` (transaksi + `lockForUpdate` + lock Redis per aset, semua-atau-tidak-sama-sekali, batas `maks_hari_pinjam`).
- Pengajuan online civitas `/pinjam` (katalog tanpa data pribadi), `/pinjaman-saya`, serta Action setujui/tolak/batalkan/serahkan dengan cek ketersediaan ulang di dalam lock; permohonan pihak luar dicatat admin dan diputuskan pejabat-penatausahaan (BR-07).
- Pengembalian sekaligus dengan kondisi wajib per item (sebagian ditolak), pembaruan kondisi aset sumber `peminjaman`, stub tiket pemeliharaan (F8), `PeminjamanResource` dengan tab Aktif/Terlambat/Diajukan/Riwayat dan pembatasan data pribadi (BR-23).

## [0.5.0] - 2026-10-10

Fase 5: mutasi lokasi.

### Ditambahkan
- Pengajuan mutasi oleh PIC ruangan asal (BR-05), persetujuan/penolakan admin-bmn, dan pembatalan oleh pengaju; nomor `MUT-{tahun}-{4 digit}` bebas tabrakan lewat `Cache::lock`.
- Persetujuan satu transaksi dengan lock per aset: ruangan berpindah, riwayat lokasi tercatat, identitas aset tidak dinomori ulang (R-17), penanda DBR (stub F9). Aset dipinjam/tidak aktif tidak dapat dimutasi.
- `MutasiResource` (lihat, putuskan), serta aksi mutasi dari halaman aset (satuan/massal) dan `/pindai`.

## [0.4.0] - 2026-10-09

Fase 4: aset, kondisi, label QR, lookup publik.

### Ditambahkan
- Register aset per kode barang + NUP (UUIDv7) dengan invarian BR-01/02/04, riwayat kondisi/lokasi/status, `label_lama`, dan `sedangDipinjam` yang dihitung.
- `AsetResource` dengan pendaftaran tunggal dan massal (n unit → n baris, satu transaksi), filter/pencarian, toggle fitur ditegakkan di Action.
- Ubah kondisi dan status (transisi sah PRD §7, SK wajib untuk dihapus), aksi massal, dan relation manager riwayat.
- Cetak label QR A4 (PDF di-stream) dengan QR server-side (`endroid/qr-code`) berisi `/a/{id}`; `TandaiLabelDicetak`.
- Lookup publik `/a/{id}` (field putih BR-18, noindex, 30 permintaan/menit/IP), resolusi label lama `/l/{kode}` via `label_lama`, dan halaman `/pindai` (kamera `html5-qrcode`).

## [0.3.0] - 2026-10-08

Fase 3: master data.

### Ditambahkan
- Master gedung, kategori ruangan (flag DKPS), prodi, ruangan (K3L, prodi pemakai, dapat dipinjam) dengan penugasan PIC (satu PIC utama) dan scope `Ruangan::dikelolaOleh`.
- Kodefikasi barang BMN dengan impor CSV (antrean `impor`) dan rule `KodeBarangValid`; seeder contoh bertanda CONTOH.
- Pengaturan sistem (identitas, penandatangan, toggle fitur BR-22, ambang & retensi) dengan cache dan halaman super-admin.
- Fondasi tautan berkas (STANDAR-TEKNIS §1a): `tautan_berkas`, `TautanBerkasValid`, `PenyimpananBerkas`/`TautanEksternal`, job `PeriksaTautanBerkas` anti-SSRF, komponen `x-tautan-berkas`; uji arsitektur tanpa FileUpload.

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
