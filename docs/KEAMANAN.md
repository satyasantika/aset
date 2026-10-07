# Keamanan ASET FKIP

Ringkasan kontrol (checklist 05-UJI §3) dan hasil pemeriksaan F12.1.

## Kontrol dan pengujinya

| Kontrol | Penegakan | Uji |
|---|---|---|
| K-01 Otorisasi server | Policy + Gate di setiap Action; `Gate::before` hanya super-admin | `MatriksAksesTest` (seluruh sel PRD §3.1 per peran) |
| K-02 Lookup publik terbatas | `Publik/LookupAsetController` hanya field putih (BR-18) | `LookupPublikTest` |
| K-03 Pelaku = pengguna terautentikasi | `TercatatAktivitas` | `AuditTest` |
| K-05 Konkurensi | Lock Redis per aset + `lockForUpdate` | `KeamananTest` (tumpang tindih, lock dipegang, mutasi ganda) |
| K-06 QR di server | `endroid/qr-code` | `KeamananTest` (tak ada layanan QR pihak ketiga) |
| K-07 Rate limit | login 5/mnt, lookup 30/mnt/IP, lapor 5/jam/IP, API 60/mnt/token | `LoginAmanTest`, `LaporKerusakanTest`, `KeamananTest`, `ApiRuanganTest` |
| K-08 MFA | `WajibMfaAdmin` untuk super-admin & admin-bmn | `PenggunaTest` |
| K-09 Tautan berkas & tanpa unggahan | whitelist https, anti-SSRF; tidak ada `FileUpload` | `TautanBerkasTest`, `KeamananTest` (pemindaian kode + unggahan diabaikan) |
| K-10 Data pribadi | policy `lihatDataPribadi`, IP pelapor di-hash HMAC | `DasborTest`, `LaporanEksporTest`, `LaporKerusakanTest` |
| IDOR | UUID di semua URL; policy per objek; rute `whereUuid` | `KeamananTest` (DBR ruangan lain 403, id berurutan 404, pinjaman orang lain tak terlihat) |
| XSS | Blade `{{ }}` di semua view (tidak ada `{!! !!}`; diuji) | `KeamananTest` |
| Header | `HeaderKeamanan`: CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy (kamera hanya situs sendiri), HSTS di produksi | `KeamananTest` |

## Temuan dan perbaikan F12.1

- `Pindai`: pesan "tidak ditemukan" dirender `{!! !!}` dari properti Livewire publik (dapat dimanipulasi klien) → diganti `{{ }}` tanpa `e()` manual.
- Header keamanan belum ada → `HeaderKeamanan` dipasang global. CSP mengizinkan `'unsafe-inline'`/`'unsafe-eval'` karena Livewire/Alpine/Filament memerlukannya; sumber lain dibatasi ke asal sendiri, dan gambar boleh dari https (foto = tautan Drive).

## Audit dependensi

| Perintah | Tanggal | Hasil |
|---|---|---|
| `composer audit` | 2026-10-06 | Tidak ada advisori keamanan |
| `npm audit` | 2026-10-06 | 0 kerentanan |

Jalankan ulang sebelum setiap rilis dan setiap bulan; CI menjalankan `composer audit`.

## Catatan yang perlu perhatian manusia

- K-04 (tanpa data tiruan di produksi) dan K-11 (menonaktifkan Web App GAS lama) adalah langkah cutover, bukan kode — lihat `docs/DEPLOY.md` dan `docs/07-MIGRASI-DATA.md` §7.
- Uji konkurensi di SQLite memverifikasi logika dan lock Redis; `lockForUpdate` baru efektif pada basis data server (mis. MySQL/PostgreSQL). Bila produksi memakai SQLite, penulisan tetap diserialkan oleh basis data dan lock Redis per aset.
