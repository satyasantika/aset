# 06 — Rekomendasi Tambahan Berdasarkan Aturan: Sistem Aset/BMN

> Dasar: dokumen di folder `aset/` — **PP No. 28 Tahun 2020** tentang Perubahan atas PP No. 27 Tahun 2014 tentang Pengelolaan Barang Milik Negara/Daerah dan **PMK No. 181/PMK.06/2016** tentang Penatausahaan Barang Milik Negara — serta instrumen akreditasi **LAMDIK IAPSK 3.0** (folder `akreditasi/`).
> Kutipan yang terverifikasi dari teks dokumen diberi tanda ✔. Butir lain bertanda **(perlu verifikasi)** — jangan dijadikan aturan sistem sebelum dicek bagian BMN/Biro Keuangan & BMN Unsil.

## 1. Prinsip

1. Sistem fakultas **melengkapi** aplikasi resmi BMN Kemenkeu (SAKTI/SIMAK-BMN — nama aplikasi yang dipakai Unsil **perlu verifikasi**), tidak menggantikannya. Nilai buku, penyusutan, dan laporan resmi tetap dari aplikasi resmi; sistem fakultas unggul di **lokasi fisik, kondisi, PIC, peminjaman, inventarisasi lapangan, dan bukti**.
2. Identitas barang mengikuti **kode barang + NUP** agar data dapat direkonsiliasi dengan aplikasi resmi.
3. Setiap perubahan fisik (lokasi, kondisi, keberadaan) tercatat dengan pelaku, waktu, dan alasan.

## 2. Rekomendasi per aturan

| No | Dasar | Ketentuan (ringkas) | Kondisi SIMAN-FKIP-2 | Rekomendasi fitur | Prioritas |
|---|---|---|---|---|---|
| RG-01 | PMK 181/2016 ✔ — pembukuan ke Buku Barang, **Kartu Identitas Barang (KIB)**, **Daftar Barang Ruangan (DBR)**, dan **Daftar Barang Lainnya (DBL)**; "mencatat semua barang dan perubahannya atas perpindahan barang antar lokasi/ruangan ke dalam DBR dan/atau DBL" | Hanya daftar inventaris per ruangan di layar | **DBR per ruangan** (dibangkitkan dari data, ditandatangani PIC ruangan & pejabat penatausahaan, disimpan sebagai *snapshot* versi saat ditandatangani) dan **DBL** untuk barang di luar ruangan (selasar, lapangan, kendaraan) | Wajib (MVP) |
| RG-02 | PMK 181/2016 ✔ — kondisi barang Baik (B), Rusak Ringan (RR), Rusak Berat (RB) | Sudah ada | Pertahankan; tambah **riwayat kondisi** (siapa, kapan, sumber: inventarisasi/peminjaman/laporan kerusakan) | Wajib |
| RG-03 | PMK 181/2016 ✔ — reklasifikasi ke **Daftar Barang Rusak Berat** dan **Daftar Barang Hilang** sebagai dasar usulan pemindahtanganan/pemusnahan/penghapusan | Belum ada | Status `rusak_berat_diusulkan`, `hilang`; laporan daftar RB & hilang; modul **usulan penghapusan** (berkas usulan berupa tautan, keputusan dicatat) | Wajib |
| RG-04 | PMK 181/2016 Pasal 19 ✔ — inventarisasi BMN (selain persediaan & KDP) melalui sensus barang **sekurang-kurangnya sekali dalam 5 tahun**; penempelan **label registrasi** pada BMN yang telah dihitung | Belum ada modul inventarisasi; label QR sudah ada | **Modul inventarisasi**: periode, tim, ruangan, pemindaian QR/centang, hasil (ditemukan, tidak ditemukan, kondisi berubah, berlebih/tidak tercatat), berita acara & laporan hasil inventarisasi; pengingat bila inventarisasi terakhir > 4 tahun. Label QR = label registrasi fakultas | Wajib |
| RG-05 | PMK 181/2016 ✔ — pelaporan **Laporan Barang Kuasa Pengguna** semesteran/tahunan oleh UAKPB | Belum ada | Fakultas bukan penyusun laporan resmi; sediakan **ekspor rekonsiliasi** semesteran (daftar barang per kode+NUP, lokasi, kondisi) untuk dicocokkan dengan aplikasi resmi, dan daftar selisih | Disarankan |
| RG-06 | PMK 181/2016 ✔ — kodefikasi barang (golongan, bidang, kelompok, subkelompok, sub-subkelompok) + NUP | Kategori bebas + `kodeBmn` teks opsional | Master **kodefikasi barang** (impor dari referensi resmi), kode barang + NUP wajib untuk barang BMN; barang non-BMN (hibah belum tercatat, aset prodi) diberi penanda `status_bmn = belum_tercatat` | Wajib |
| RG-07 | PP 27/2014 jo. PP 28/2020 — penggunaan BMN untuk penyelenggaraan tugas & fungsi; **pemanfaatan** oleh pihak lain (antara lain sewa, pinjam pakai antarinstansi pemerintah) diatur tersendiri | Peminjaman tidak membedakan pihak dalam/luar | Bedakan **peminjaman internal** (civitas FKIP, untuk tugas & fungsi) dari **permohonan pihak luar** → tidak diproses sebagai peminjaman biasa, diteruskan ke pejabat berwenang (kemungkinan skema sewa/PNBP — **perlu verifikasi** aturan & tarif Unsil). Catatan: istilah "pinjam pakai" dalam PP dipakai untuk antarinstansi pemerintah ✔ (Pasal 30 PP 28/2020 menyebut pinjam pakai antara Pemerintah Pusat dan Pemerintah Daerah), jadi jangan dipakai untuk peminjaman internal | Wajib (pembedaan), lanjutan (alur sewa) |
| RG-08 | PP 27/2014 jo. PP 28/2020 — pengamanan & pemeliharaan BMN menjadi tanggung jawab pengguna barang | Belum ada | **Laporan kerusakan** (scan QR → lapor), tiket pemeliharaan, riwayat perbaikan & biaya (DECIMAL), status `dalam_perbaikan` (tidak dapat dipinjam) | Wajib |
| RG-09 | PP 27/2014 jo. PP 28/2020 — **penghapusan** (Pasal 81 ✔ menyebut cakupan penghapusan) | Hapus barang = hapus baris | Barang tidak pernah dihapus permanen; hanya status `dihapus` setelah ada **nomor & tanggal SK penghapusan** (tautan dokumen) | Wajib |
| RG-10 | PMK 181/2016 — pencatatan BMN berdasarkan dokumen sumber (BAST, kontrak/kuitansi pengadaan, hibah) | Belum ada | Kolom `nomor_dokumen_perolehan`, `tanggal_perolehan`, `nilai_perolehan` DECIMAL(15,2), `sumber_perolehan` (pembelian, hibah, transfer masuk), `sumber_dana`, tautan dokumen sumber | Wajib |
| RG-11 | LAMDIK IAPSK 3.0 ✔ — DKPS Tabel "Sarana Laboratorium dan Pembelajaran", "Prasarana Pendidikan", "Teknologi Informasi dan Komunikasi"; elemen K5 "Ketersediaan dan Aksesibilitas Sarana dan Prasarana Utama Pendidikan", "Ketersediaan dan Aksesibilitas Teknologi Informasi", "Keamanan, Keselamatan, dan Kesehatan Lingkungan (K3L)" | Tidak ada pemetaan prodi | Kolom **prodi pemakai** pada ruangan/aset (multi), kategori `laboratorium`/`ruang kelas`/`TIK`; ekspor tabel DKPS sarana-prasarana per prodi; atribut K3L ruangan (APAR, jalur evakuasi, P3K — data sederhana) | Disarankan |
| RG-12 | UU 27/2022 PDP — data peminjam (nama, HP) adalah data pribadi | HP peminjam & PIC terlihat siapa pun (R-02) | Data peminjam hanya untuk PIC/admin; lookup publik tanpa data pribadi; retensi riwayat peminjaman (mis. 3 tahun, **perlu verifikasi kebijakan arsip**) | Wajib |
| RG-13 | Tata kelola TI (PP 71/2019 PSE — **perlu verifikasi pasal**) | Backend GAS di akun pribadi | Aplikasi di server fakultas, akun berbasis surel unsil, jejak audit, cadangan harian | Wajib |

## 3. Penyesuaian istilah

| Istilah di SIMAN lama | Istilah di sistem baru | Alasan |
|---|---|---|
| Inventaris | **Aset** (barang ber-kode + NUP) | Konsisten dengan BMN |
| Penanggungjawab | **PIC ruangan** (penanggung jawab DBR) | Peran terhadap DBR |
| Mutasi | **Mutasi lokasi** (antar ruangan dalam fakultas) | Membedakan dari "mutasi BMN" antar satker di aplikasi resmi |
| Peminjaman | **Peminjaman internal** | Membedakan dari pemanfaatan oleh pihak luar & "pinjam pakai" |
| Hapus barang | **Usulan penghapusan → dihapus (ber-SK)** | RG-09 |

## 4. Yang sengaja **tidak** dibangun

- Penghitungan penyusutan & nilai buku (domain aplikasi resmi).
- Pengajuan/approval pemindahtanganan ke Kemenkeu/KPKNL (di luar kewenangan fakultas; cukup mencatat status & tautan dokumen).
- Persediaan (barang habis pakai) — kandidat modul terpisah bila diperlukan.
