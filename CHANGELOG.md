# Catatan Perubahan

Semua perubahan penting pada proyek ini dicatat di berkas ini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan versi mengikuti
[Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

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
