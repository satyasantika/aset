# 05 — Uji Penerimaan: SIMAN FKIP 3

## 1. Persiapan

Staging = konfigurasi produksi; Horizon & scheduler berjalan; data hasil migrasi SIMAN-2 (F7) atau `siman:siapkan-uat`. Akun uji: `super-admin`, `admin-bmn`, `pejabat-penatausahaan`, dua `pic-ruangan` (Lab Komputer, Aula), `pimpinan`, `civitas` (dosen & pengurus ormawa). Siapkan 10 label lama tercetak (berbagai format) dan 2 label dari daftar "perlu cetak ulang".

## 2. Skenario UAT

### 2.1 Admin BMN

| No | Skenario | Hasil diharapkan | ✓ |
|---|---|---|---|
| A-01 | Daftarkan 20 kursi (NUP 1–20) sekaligus | 20 baris aset, NUP berurutan, satu kelompok pengadaan | |
| A-02 | Daftarkan barang dengan kode+NUP yang sudah ada | Ditolak | |
| A-03 | Impor kodefikasi CSV | Kode tersedia di formulir aset | |
| A-04 | Setujui mutasi 3 aset Lab → Aula | Lokasi berubah; DBR Lab & Aula "perlu diperbarui"; riwayat lokasi tercatat | |
| A-05 | Buka periode inventarisasi; tugaskan PIC | Hanya satu periode berjalan | |
| A-06 | Tutup periode, verifikasi aset tidak ditemukan → hilang | Berita acara tersusun; status aset hilang | |
| A-07 | Usulan penghapusan dari daftar RB → pejabat setuju → catat SK | Status diusulkan_hapus → dihapus; tanpa SK ditolak | |
| A-08 | Ekspor rekonsiliasi & DKPS sarana-prasarana | Excel terunduh lewat notifikasi; berkas hilang dari tmp ≤ 24 jam | |
| A-09 | Matikan toggle "mutasi" lalu PIC mengajukan mutasi | Ditolak di server | |

### 2.2 PIC ruangan

| No | Skenario | Hasil diharapkan | ✓ |
|---|---|---|---|
| P-01 | Pindai label **lama** `KAT-KODE-n` dan label baru (UUID) | Keduanya membuka aset yang benar | |
| P-02 | Ubah kondisi barang di ruangannya; coba di ruangan lain | Berhasil + riwayat; ruangan lain 403 | |
| P-03 | Keranjang: pinjamkan 3 barang; salah satu sedang dipinjam | Seluruh keranjang ditolak dengan nama barang bermasalah | |
| P-04 | Dua PIC meminjamkan barang sama bersamaan | Hanya satu berhasil | |
| P-05 | Kembalikan dengan kondisi RR | Kondisi aset RR, tiket pemeliharaan terbuka otomatis | |
| P-06 | Setujui pengajuan civitas | Peminjam menerima notifikasi | |
| P-07 | Bangkitkan & setujui DBR ruangan | Status disetujui_pic; menunggu pejabat | |
| P-08 | Inventarisasi ruangan di ponsel | Progres %; sisa tidak terpindai → tidak ditemukan | |
| P-09 | Cetak label "perlu cetak ulang" | PDF berisi QR UUID; penanda hilang setelah ditandai dicetak | |

### 2.3 Pejabat penatausahaan & pimpinan

| No | Skenario | Hasil diharapkan | ✓ |
|---|---|---|---|
| J-01 | Sahkan DBR | Snapshot tersimpan; PDF versi itu tidak berubah walau aset dipindah kemudian | |
| J-02 | Sahkan berita acara inventarisasi | Status disahkan | |
| J-03 | Putuskan permohonan pihak luar | Tercatat; tidak menjadi peminjaman internal | |
| M-01 | Pimpinan membuka dasbor & laporan peminjaman | Angka tampil; nama/kontak peminjam tidak terlihat | |

### 2.4 Civitas & publik

| No | Skenario | Hasil diharapkan | ✓ |
|---|---|---|---|
| C-01 | Ajukan pinjam proyektor pada rentang yang sudah dipesan | Ditolak "tidak tersedia" | |
| C-02 | Ajukan pada rentang kosong, lalu batalkan | Status dibatalkan | |
| U-01 | Pindai QR tanpa login | Hanya nama, merk, kategori, ruangan, kondisi, tahun, kode+NUP | |
| U-02 | Lapor kerusakan dari halaman QR | Tiket baru untuk PIC; 6 laporan/jam → 429 | |
| U-03 | Pengguna nonaktif mencoba login | Ditolak | |

### 2.5 Migrasi

| No | Skenario | Hasil diharapkan | ✓ |
|---|---|---|---|
| G-M1 | `siman2:verifikasi` | Σ jumlah lama = jumlah aset; per ruangan & per kondisi cocok | |
| G-M2 | 30 label lama acak (di luar daftar cetak ulang) | Semua terresolusi benar | |
| G-M3 | Pinjaman "Dipinjam" lama | Muncul sebagai pinjaman aktif | |
| G-M4 | Semua PIC menerima surel atur kata sandi | Dapat login & melihat ruangannya | |

## 3. Checklist keamanan

| No | Kontrol | ✓ |
|---|---|---|
| K-01 | Setiap aksi tulis diotorisasi di server (Policy) — tidak ada lagi otorisasi hanya di klien (R-01) | |
| K-02 | Tidak ada endpoint publik yang mengembalikan daftar aset/pengguna (R-02); lookup hanya field putih | |
| K-03 | Pelaku log = pengguna terautentikasi (R-03) | |
| K-04 | Tidak ada data tiruan/fallback di produksi (R-04) | |
| K-05 | Konkurensi peminjaman & mutasi teruji (R-09) | |
| K-06 | QR dibangkitkan di server; tidak ada panggilan ke layanan QR pihak ketiga (R-16) | |
| K-07 | Rate limit login, lookup, lapor, API | |
| K-08 | MFA super-admin & admin-bmn | |
| K-09 | Tautan berkas: https + daftar putih; job pemeriksaan anti-SSRF; tidak ada unggahan selain impor | |
| K-10 | Data pribadi peminjam/pelapor hanya untuk yang berhak; IP pelapor di-hash | |
| K-11 | Web App GAS lama dinonaktifkan setelah cutover | |

## 4. Daftar test Pest minimal

| Modul | Test |
|---|---|
| peran/auth | `MatriksPermissionTest`, `LoginTest`, `MfaAdminTest`, `AuditPelakuTest` |
| master | `RuanganPicTest`, `KodefikasiImporTest`, `TautanBerkasTest`, `Arch/TanpaUnggahTest` |
| aset | `AsetUnikTest`, `DaftarMassalTest`, `KondisiStatusTest`, `ToggleFiturTest` |
| label/publik | `LabelPdfTest`, `LabelLamaTest`, `LookupPublikTest` |
| mutasi | `MutasiAlurTest`, `MutasiKonkurensiTest` |
| peminjaman | `KetersediaanTest`, `KeranjangTest`, `PengajuanCivitasTest`, `PengembalianTest` |
| migrasi | `ImporSiman2Test` (fixture XLSX), `VerifikasiMigrasiTest` |
| pemeliharaan | `TiketTest`, `LaporKerusakanPublikTest` |
| dbr/inventarisasi | `DbrSnapshotTest`, `InventarisasiAlurTest` |
| penghapusan/laporan | `PenghapusanTest`, `DasborCakupanTest`, `EksporTest` |
| api | `RuanganApiTest` (401/403/409/idempoten) |

## 5. Checklist go-live

| No | Butir | ✓ |
|---|---|---|
| GL-01 | Struktur penandatangan (pejabat penatausahaan) & nama kementerian di kop dikonfirmasi | |
| GL-02 | Referensi kodefikasi barang resmi diimpor | |
| GL-03 | Pemetaan username lama → surel unsil lengkap | |
| GL-04 | Hasil uji migrasi di staging disetujui admin BMN | |
| GL-05 | Daftar label perlu cetak ulang dibagikan ke PIC & dijadwalkan bersama inventarisasi pertama | |
| GL-06 | Retensi data peminjaman diputuskan (kebijakan arsip) | |
| GL-07 | Backup harian & uji pulih | |
| GL-08 | Sistem Surat diberi token API ruangan (bila sudah siap) | |
| GL-09 | UAT §2 lulus tanpa temuan kritis | |
