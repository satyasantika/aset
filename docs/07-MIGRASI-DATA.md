# 07 — Migrasi Data: SIMAN-FKIP-2 (Google Sheets) → SIMAN FKIP 3 (MySQL)

## 1. Prinsip

1. **Tidak ada data hilang**: setiap baris sumber tercatat di `impor_siman2_log` (sheet, id lama, id baru, status, pesan).
2. **Label lama tetap berfungsi**: semua teks yang pernah tercetak di QR dimasukkan ke `label_lama`.
3. **Idempoten**: impor dapat diulang (dry-run → perbaikan → impor ulang) tanpa duplikasi, dengan kunci `sumber_id` lama.
4. **Paralel lalu potong**: sistem lama tetap berjalan (mode baca) sampai hasil impor diverifikasi; perubahan selama masa paralel dicatat manual atau diimpor ulang (delta).

## 2. Persiapan

| Langkah | Pelaksana |
|---|---|
| Matikan sementara toggle tambah/hapus/mutasi/peminjaman di Panel Sistem SIMAN-2 (fitur bawaan) agar data beku saat ekspor | Admin |
| Ekspor spreadsheet backend GAS ke **XLSX** (File → Download → .xlsx) — satu berkas berisi sheet `users`, `config`, `kategori`, `ruanganKategori`, `ruangan`, `inventaris`, `mutasi`, `peminjaman`, `laporan` (abaikan `sessions`) | Admin |
| Simpan salinan XLSX di Shared Drive fakultas (arsip migrasi) | Admin |
| Siapkan referensi kodefikasi barang & daftar prodi | Admin BMN |

## 3. Pemetaan per sheet

| Sheet lama | Tabel baru | Aturan |
|---|---|---|
| `config` | `pengaturan` | Nama kolom → kunci pengaturan (instansi_baris1, …, toggle fitur). `logo_kiri/kanan`, `login_sliders` → tautan (STANDAR-TEKNIS §1a) atau aset statis |
| `kategori` | `kodefikasi_barang.kategori_lokal` (pemetaan) | Kategori lama tidak menjadi kodefikasi; dipakai untuk memetakan barang tanpa `kodeBmn` |
| `ruanganKategori` | `kategori_ruangan` | 1:1 |
| `ruangan` | `ruangan`, `ruangan_pic` | `idRuangan` → `kode` (bila kosong, bangkitkan `R-xxx`); `userIdPIC` → `ruangan_pic`; `fotoRuangan` → `tautan_berkas` jenis `foto`; `namaPIC`/`hpPIC` diabaikan (diambil dari user) |
| `users` | `users`, peran | `username` → cari surel unsil yang sesuai (tabel pemetaan manual `username → email`, wajib diisi admin); `role`: Admin → `admin-bmn`, Penanggungjawab → `pic-ruangan`, Pimpinan → `pimpinan`; `ruangan` (JSON nama ruangan) → `ruangan_pic`. **Kata sandi tidak dimigrasikan** (skema hash berbeda): semua akun menerima surel atur kata sandi |
| `inventaris` | `aset`, `riwayat_kondisi_aset`, `label_lama`, `tautan_berkas` | §4 |
| `mutasi` | `mutasi`, `mutasi_item`, `riwayat_lokasi_aset` | `asal`/`tujuan` (nama) → `ruangan_id`; `tipeMutasi=unit` + `unitIndex` → aset hasil pemecahan unit ke-n (§4); `status` Pending/Disetujui/Ditolak → diajukan/disetujui/ditolak; `pemohon` (teks) disimpan di `catatan`, `diajukan_oleh` = null (pelaku lama tidak terverifikasi) |
| `peminjaman` | `peminjaman`, `peminjaman_item` | Kelompokkan per `kodeTransaksi` → satu `peminjaman` dengan nomor baru, nomor lama di `catatan`; `kodeUnit` (`935464-2`) → `label_lama` → `aset_id`; status Dipinjam/Dikembalikan; `jenis_peminjam = civitas` |
| `laporan` | `activity_log` (log_name `siman2`) | `pengguna` disimpan sebagai properti `pelaku_lama` (bukan `causer`) karena tidak terverifikasi |
| `sessions` | — | Tidak dimigrasikan |

## 4. Inventaris: memecah baris berjumlah > 1

Satu baris `inventaris` dengan `jumlah = n` menjadi **n baris `aset`**:

| Kolom baru | Sumber |
|---|---|
| `kode_internal` | `kodeBarang` bila n = 1; `kodeBarang-<i>` bila n > 1 (i = 1..n) |
| `kode_barang` | `kodeBmn` bila berupa kode numerik valid di `kodefikasi_barang`; selain itu null |
| `nup` | Bila `nup` lama berupa angka dan n = 1 → nup itu; bila n > 1 dan `nup` angka awal → `nup + i - 1` **hanya bila admin mengonfirmasi** bahwa NUP berurutan (opsi `--nup-berurutan`); selain itu null |
| `status_bmn` | `tercatat` bila kode_barang & nup terisi; selain itu `belum_tercatat` |
| `nama`, `merk_tipe`, `penguasaan`, `keterangan` | langsung |
| `tahun_perolehan` | `tahun`; `bulan` (nama bulan) → `tanggal_perolehan` tanggal 1 bulan itu bila valid |
| `ruangan_id` | `lokasiBarang` (nama) → ruangan; tidak cocok → laporan galat (perbaiki pemetaan, impor ulang) |
| `kondisi` | `unitKondisi[i]` bila ada, selain itu `kondisi`; "Baik" → B, "Rusak Ringan" → RR, "Rusak Berat" → RB |
| `dicetak_pada` | i ∈ `printedUnits` → waktu impor (tanda sudah pernah dicetak) |
| foto | `foto` (URL lh3/Drive) → `tautan_berkas` jenis `foto` untuk setiap unit |
| riwayat | `riwayat_kondisi_aset` sumber `migrasi` |

**`label_lama`** untuk setiap unit i: `KODEKATEGORI-KODE-i` (isi QR cetak SIMAN-2, mis. `MBL-935464-1`, dibentuk di `print.js` dari `kategori.kodeKategori`), `KODE-i`, dan untuk i = 1 juga `KODE`, `NUP`, `KODEBMN` (bila unik). Teks dinormalisasi huruf besar. Bentrok teks → dicatat, tidak ditimpa.

### 4a. Label yang tidak dapat dipercaya (temuan R-17)

Mutasi satu unit di SIMAN-2 menggeser nomor unit sisa dan membuat baris baru berkode `KODE-n`. Akibatnya:

- Untuk setiap baris induk yang **pernah** menjadi asal mutasi `tipeMutasi = unit` berstatus Disetujui (lihat sheet `mutasi`), label unit dengan indeks ≥ `unitIndex` saat mutasi **mungkin** tertukar.
- Baris hasil mutasi (keterangan "Hasil mutasi 1 unit dari …") berlabel `KAT-KODE-n-1`, yang di SIMAN-2 terbaca sebagai unit n milik induk.

Penanganan: perintah impor menandai aset-aset tersebut `label_perlu_cetak_ulang = true` dan **tidak** membuat entri `label_lama` yang ambigu. Laporan "Label perlu dicetak ulang" per ruangan diberikan ke PIC; label baru (QR UUID) dicetak dan ditempel pada inventarisasi pertama, sekaligus memverifikasi fisik barang.

## 5. Perintah

```bash
php artisan siman2:impor storage/app/tmp/siman2.xlsx --dry-run      # validasi, laporan galat, tanpa menulis
php artisan siman2:impor storage/app/tmp/siman2.xlsx --nup-berurutan # impor sungguhan (transaksi per sheet)
php artisan siman2:verifikasi                                        # rekonsiliasi jumlah
```

Urutan dalam perintah: pengaturan → kategori ruangan → ruangan → pengguna & PIC → inventaris (aset, label, foto) → mutasi → peminjaman → log. Berkas XLSX dihapus dari `storage/app/tmp` setelah selesai.

## 6. Verifikasi (wajib sebelum potong)

| Cek | Kriteria |
|---|---|
| Jumlah unit | Σ `jumlah` sheet `inventaris` = jumlah baris `aset` hasil migrasi |
| Per ruangan | Jumlah unit per ruangan sama dengan dasbor SIMAN-2 |
| Kondisi | Jumlah B/RR/RB per ruangan sama |
| Label | 30 label fisik acak (berbagai format, di luar daftar cetak ulang) dipindai di sistem baru → barang benar; daftar label perlu cetak ulang sudah dibagikan ke PIC |
| Peminjaman aktif | Semua status "Dipinjam" lama muncul sebagai pinjaman aktif |
| Pengguna | Setiap PIC menerima surel atur kata sandi & melihat ruangannya |

## 7. Pemotongan (cutover)

1. Umumkan jadwal; bekukan SIMAN-2 (toggle fitur off).
2. Ekspor final → impor → verifikasi §6.
3. Arahkan alamat lama ke sistem baru (pengalihan); nonaktifkan deployment Web App GAS (cabut akses "Siapa saja") agar data tidak lagi terbuka (temuan R-02).
4. Simpan spreadsheet lama sebagai arsip baca-saja minimal 1 tahun.
