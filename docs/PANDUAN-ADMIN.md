# Panduan Admin BMN — ASET FKIP

Untuk **admin-bmn** (dan **super-admin** untuk pengaturan sistem). Akun admin wajib **MFA** (aplikasi autentikator) —
diminta pada login pertama. Pejabat penatausahaan mengesahkan dokumen; Anda menyusun dan memverifikasi.

## 1. Master data
**Gedung**, **Ruangan** (kode, kategori, lantai, kapasitas, luas, K3L, prodi pemakai, PIC), **Kategori ruangan**,
**Program studi**, **Kodefikasi barang**.
- Kodefikasi: impor CSV (`kode`, `uraian`, `kategori_lokal`) lewat tombol impor di menu Kodefikasi barang.
- Penugasan PIC: Ruangan → tab **PIC** → tambah pengguna; **Jadikan utama** untuk PIC utama. Pengguna → buat akun
  (surel `@unsil.ac.id`) dan beri peran **pic-ruangan**; Anda tidak dapat mengubah akun admin lain.

## 2. Aset (barang)
- **Daftarkan** satu barang atau **Daftarkan massal** (mis. 20 kursi → NUP berurutan, satu kelompok pengadaan).
  Kombinasi **kode barang + NUP** harus unik (BR-01). Barang yang belum tercatat di aplikasi BMN resmi memakai status
  *belum tercatat* dengan **kode internal** unik.
- Nilai perolehan DECIMAL; tanggal/sumber/dokumen perolehan dicatat untuk rekonsiliasi dan DKPS.
- **Foto & dokumen** berupa **tautan** (Google Drive/https) — tidak ada unggah berkas; tautan diperiksa mingguan, pemilik
  tautan mati diberi notifikasi.
- **Ubah status** (aktif ↔ dalam perbaikan, hilang, dll.) dan **Ubah kondisi**; semua perubahan masuk riwayat dan log aktivitas.
- **Label QR**: **Cetak label** (PDF). Label lama dikenali lewat tabel `label_lama` hasil migrasi.

## 3. Mutasi lokasi
**Mutasi lokasi** → pengajuan PIC/Anda → **Setujui** (lokasi berubah, riwayat tercatat, DBR ruangan asal & tujuan menjadi
*perlu diperbarui*) atau **Tolak** (alasan wajib). Barang yang sedang dipinjam/diperbaiki tidak dapat dimutasi.

## 4. Peminjaman
Pantau tab **Aktif / Terlambat / Diajukan / Riwayat**. Catat **permohonan pihak luar** (diteruskan ke pejabat
penatausahaan; skema pemanfaatan/sewa perlu verifikasi). Pengingat keterlambatan dikirim otomatis tiap pagi (07:00).
Data pribadi peminjam dianonimkan otomatis setelah masa retensi (bawaan 36 bulan, **Pengaturan sistem**).

## 5. DBR/DBL
Bangkitkan per ruangan (atau DBL untuk barang berlokasi lainnya) → PIC menyetujui → pejabat mengesahkan → cetak PDF dari
snapshot. Pekerjaan harian `aset:tandai-dbr-usang` menangkap perubahan yang lolos dari penanda otomatis.

## 6. Inventarisasi
1. **Inventarisasi** → buat periode (jenis, rentang) → **Buka periode** (hanya satu berjalan).
2. **Tugaskan petugas** per ruangan; PIC memindai di ponsel.
3. **Tutup periode**: kondisi berubah diterapkan dengan riwayat; **berita acara** tersusun (snapshot). Unduh **Berita acara (PDF)**
   dan **Selisih (Excel)**.
4. **Tetapkan hilang** untuk barang tidak ditemukan setelah diverifikasi; pejabat **Sahkan berita acara**.
5. Peringatan muncul bila inventarisasi terakhir yang disahkan > 4 tahun (batas aturan 5 tahun).

## 7. Penghapusan
**Penghapusan** → buat usulan dari barang **RB** atau **hilang** → **Ajukan** → pejabat **Setujui internal** (barang menjadi
*diusulkan hapus*) → setelah SK terbit, **Catat SK penghapusan** (nomor & tanggal wajib) → barang berstatus *dihapus*
(tidak pernah dihapus permanen). **Batalkan usulan** mengembalikan barang ke aktif.

## 8. Laporan & ekspor
Menu **Laporan**: rekonsiliasi (kode + NUP, lokasi, kondisi, nilai), daftar RB & hilang, riwayat peminjaman, rekap
pemeliharaan, log aktivitas, dan **DKPS LAMDIK** (sarana lab & pembelajaran, prasarana, TIK per prodi; lihat
`docs/FORMAT-DKPS.md` — cocokkan kolom dengan templat resmi). Ekspor berjalan di latar belakang; unduh lewat notifikasi
(berkas dihapus ≤ 24 jam). Data pribadi hanya untuk yang berhak (pimpinan: disamarkan). **Dasbor** menampilkan jumlah/nilai
per ruangan, kondisi, peminjaman aktif/terlambat, tiket, dan DBR.

## 9. Pengaturan (super-admin)
**Pengaturan sistem**: identitas instansi & penandatangan (kop dokumen), toggle fitur (tambah aset, hapus, ubah kondisi,
mutasi, peminjaman, tahan mutasi saat inventarisasi), ambang pengingat inventarisasi, retensi peminjaman, maks. hari pinjam.
**Token API** (untuk Surat): buat token dengan ability `ruangan:baca`/`ruangan:pakai` (lihat `docs/API.md`); token tampil sekali.
**Horizon** `/horizon` memantau antrean; **Log aktivitas** memuat siapa melakukan apa.

## 10. Migrasi dari SIMAN-FKIP-2
`php artisan siman2:impor <berkas.xlsx>` lalu `siman2:verifikasi`. Panduan, pemetaan pengguna, dan hasil uji ada di
`docs/migrasi/` dan `docs/07-MIGRASI-DATA.md`. Lakukan uji pulih cadangan sebelum cutover (`docs/DEPLOY.md` §5).

## 11. UAT di staging
`php artisan siman:siapkan-uat --staging` membuat akun tiap peran (`uat.<peran>@unsil.ac.id`), ruangan Lab Komputer & Aula
berPIC, dan barang contoh (idempoten; kata sandi dicetak sekali). Jalankan skenario `docs/05-UJI-PENERIMAAN.md`.
**Jangan jalankan di produksi sungguhan** (perintah menolak tanpa `--staging`).
