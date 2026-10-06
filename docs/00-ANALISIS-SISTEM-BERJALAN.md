# 00 — Analisis Sistem Berjalan: SIMAN-FKIP-2

> Hasil pembacaan kode `aset/SIMAN-FKIP-2/` (6 Oktober 2026): `js/services/code.gs` (backend Google Apps Script, ±1.300 baris), `js/services/api.js`, `js/app.js`, modul `js/<modul>/*.js`, `html/<modul>/*.html`, `js/utils/mock-server.js`. Tujuan dokumen: memahami apa yang **sudah benar** (dipertahankan), apa yang **berisiko** (diperbaiki), dan apa yang **belum ada** (ditambah) sebelum membangun ulang di Laravel 13 + MySQL.

## 1. Gambaran arsitektur saat ini

| Lapisan | Implementasi |
|---|---|
| Antarmuka | SPA statis: Vue 3 (CDN) + Tailwind (dibangun via `tailwind.config.js`), SweetAlert2, Chart.js (dimuat dinamis), SheetJS (impor/ekspor Excel), pemindai QR kamera |
| Backend | Google Apps Script Web App (`doPost`/`doGet`) — router `routeAction(action, payload)` |
| Basis data | Google Sheets ("Dynamic Spreadsheet ORM": baris pertama = header, kolom baru otomatis ditambah saat payload memuat kunci baru) |
| Cache | `CacheService` GAS 30 menit + cache per ruangan; cache baca di sisi klien (TTL 15 detik–5 menit) |
| Berkas | Foto barang diunggah base64 → Google Drive folder `Foto_Inventaris_SIMAN`, dibagikan "siapa saja dengan link", ditampilkan via `lh3.googleusercontent.com/d/<id>` |
| Autentikasi | Login username + kata sandi; hash SHA-256 + salt (migrasi otomatis dari kata sandi polos saat login pertama); token sesi 8 jam di sheet `sessions` |
| Fallback | Bila panggilan GAS gagal, `api.js` **diam-diam** memakai `MockServer` (data contoh di browser) |

### Sheet (tabel) yang ada

| Sheet | Kolom utama | Catatan |
|---|---|---|
| `users` | id, username, password, passwordHash, salt, nama, role, ruangan (JSON array), hp | Peran: `Admin`, `Penanggungjawab`, `Pimpinan` |
| `config` | instansi, alamat, logo, kota_surat, nama/NIP kasubag & penanggung jawab, toggle fitur (tambah/hapus barang, ubah kondisi, mutasi, peminjaman), slider login | Satu baris (id 1) |
| `kategori` | namaKategori, kodeKategori | Kategori bebas, bukan kodefikasi BMN |
| `ruanganKategori` | namaKategori | |
| `ruangan` | namaRuangan, idRuangan, kategoriRuangan, kapasitas, fotoRuangan, userIdPIC, namaPIC, hpPIC | |
| `inventaris` | kodeBarang, nup, namaBarang, merkType, kodeBmn, tahun, bulan, kategori, penguasaan, lokasiBarang (**nama** ruangan), kondisi, keterangan, **jumlah**, **unitKondisi** (JSON per unit), foto, printedUnits (JSON), updatedDate, pengguna | Satu baris dapat mewakili banyak unit fisik |
| `mutasi` | idBarang, kodeBarang, namaBarang, asal, tujuan, alasan, pemohon, tanggal, status (Pending/Disetujui/Ditolak), tipeMutasi (`induk`/`unit`), unitIndex, jumlahAsal | |
| `peminjaman` | idBarang, kodeUnit, unitIndex, namaBarang, lokasiAsal, kodeTransaksi (PJM-yyyyMMdd-HHmmss), peminjam, kontak, keperluan, tanggalPinjam, tanggalRencanaKembali, tanggalKembaliAktual, kondisiSaatKembali, status (Dipinjam/Dikembalikan), dicatatOleh | "Terlambat" dihitung di klien |
| `laporan` | tanggal, pengguna, modul, aksi, keterangan | Log aktivitas |
| `sessions` | token, userId, username, role, ruangan, createdAt, expiresAt | |

### Modul (menu) dan peran

| Menu | Admin | Penanggungjawab (PIC ruangan) | Pimpinan |
|---|---|---|---|
| Dashboard (statistik per ruangan/kategori, Chart.js) | ✓ | ✓ (ruangannya) | ✓ |
| Inventaris (CRUD, impor Excel, foto, kondisi per unit) | ✓ | ✓ | ✓ (lihat) |
| Mutasi barang (ajukan & verifikasi; induk atau satu unit) | ✓ | ✓ | — |
| Peminjaman barang (keranjang, harian, tanpa persetujuan admin) | ✓ | ✓ | — |
| Cetak label QR (tandai unit sudah dicetak) | ✓ | ✓ | — |
| Scan QR (cek barang, mulai peminjaman/mutasi) | ✓ | ✓ | ✓ |
| Log aktivitas (ekspor Excel) | ✓ | — | — |
| Panel sistem (config, pengguna, ruangan, kategori) | ✓ | — | — |
| Lookup publik (`html/public/public-lookup.html`, aksi `lookupBarangPublik`) | tanpa login | | |

## 2. Yang sudah baik dan dipertahankan

1. **Konsep PIC per ruangan** — PIC hanya mengelola barang di ruangan yang menjadi tanggung jawabnya (dijaga di titik pemindaian). Sesuai prinsip pengguna barang/kuasa pengguna dan DBR.
2. **QR pada label fisik** dengan format `KATEGORI-KODE-UNIT` atau `KODE-UNIT`, plus lookup publik terbatas (nama, merk, lokasi, kondisi, tahun, NUP, kode BMN). Label yang sudah tertempel **harus tetap terbaca** setelah migrasi.
3. **Pembedaan mutasi vs peminjaman** — mutasi mengubah lokasi (dengan persetujuan), peminjaman sementara tidak mengubah lokasi. Benar secara konsep penatausahaan.
4. **Mutasi per unit** (memecah satu unit dari kelompok) — menunjukkan kebutuhan nyata untuk pencatatan per unit.
5. **Kondisi B / Rusak Ringan / Rusak Berat** — sudah sesuai kategori kondisi PMK 181/2016.
6. **Peminjaman mode keranjang** dengan validasi semua-atau-tidak-sama-sekali dan kode transaksi.
7. **Kata sandi di-hash dengan salt**, token sesi, aksi tulis dilindungi token, hash tidak pernah dikirim ke klien.
8. **Toggle fitur** di config (mematikan tambah/hapus/mutasi/peminjaman sementara, mis. saat inventarisasi).
9. **Data penandatangan** (Kasubag & penanggung jawab + NIP) untuk dokumen cetak.

## 3. Temuan risiko (wajib diperbaiki di sistem baru)

| No | Temuan | Dampak | Penanganan di Laravel |
|---|---|---|---|
| R-01 | **Otorisasi peran hanya di klien.** Server hanya memeriksa "token sah", tidak memeriksa peran. Pengguna Pimpinan/PIC yang memanggil API langsung dapat `saveUsers`, `deleteUsers`, `saveConfig`, `verifikasiMutasi` | Eskalasi hak akses, perubahan data oleh pihak tak berwenang | Policy & permission di server untuk setiap aksi (spatie/permission) |
| R-02 | **Aksi baca tidak dilindungi** (`getInventaris`, `getUsers`, `getRuangan`, `getConfig`, `getMutasi`, `getPeminjaman`, `getLaporan` tidak termasuk `PROTECTED_ACTIONS`) | Seluruh data aset, daftar pengguna (nama, username, nomor HP), log dapat dibaca siapa pun yang mengetahui URL GAS | Semua data di balik autentikasi; endpoint publik hanya lookup terbatas |
| R-03 | **Pelaku log berasal dari payload** (`payload.pengguna`, `payload.pemohon`, `payload.dicatatOleh`) | Jejak audit dapat dipalsukan | Pelaku = pengguna terautentikasi (activitylog `causer`) |
| R-04 | **Fallback diam-diam ke MockServer** bila GAS gagal | Pengguna mengira data tersimpan padahal hanya di browser; data contoh tampil sebagai data asli | Tidak ada data tiruan di produksi; galat ditampilkan jelas |
| R-05 | **Satu baris = banyak unit** (`jumlah` + `unitKondisi` JSON) | Tidak sesuai penatausahaan BMN (setiap barang ber-NUP sendiri); kondisi, lokasi, peminjaman per unit sulit diaudit; mutasi unit memecah baris secara manual | Satu baris per unit (kode barang + NUP); kelompok pengadaan hanya sebagai referensi |
| R-06 | **Lokasi disimpan sebagai nama ruangan (teks)** | Ganti nama ruangan memutus relasi; cache per ruangan bergantung nama | FK `ruangan_id` |
| R-07 | **Kode barang acak 6 digit** bila tidak diisi saat impor (`Math.random`) | Kode ganda/bentrok, bukan kodefikasi BMN | Kode barang mengikuti kodefikasi BMN; label sistem memakai UUID |
| R-08 | **Impor Excel belum dikirim ke backend** (komentar di `inventaris.js`: "perlu mengirim newInventoryList ke Backend") | Impor hanya mengubah tampilan | Impor berantrean dengan validasi per baris |
| R-09 | **Tidak ada penguncian konkurensi** pada peminjaman & mutasi (Sheets tanpa transaksi; cek bentrok dilakukan lalu tulis terpisah) | Satu unit dapat dipinjam dua kali bila dua PIC menekan bersamaan | Transaksi MySQL + `lockForUpdate` + `Cache::lock` |
| R-10 | **Foto diunggah ke Drive dan dibuat publik** oleh skrip | Folder Drive pribadi akun skrip; kebijakan storage | Kebijakan tautan (STANDAR-TEKNIS §1a); foto = tautan Drive unit |
| R-11 | **Sesi di sheet**, dibersihkan acak 10% | Kinerja turun seiring jumlah sesi; tidak ada pencabutan sesi | Sesi Redis Laravel |
| R-12 | **Tidak ada pembatasan laju** login & lookup publik | Tebak kata sandi; enumerasi kode | Rate limit Redis |
| R-13 | **Kolom dinamis** (kolom baru dibuat otomatis dari kunci payload) | Kolom sampah, skema tidak terkendali | Migrasi terversi |
| R-14 | **Batas Google Apps Script** (kuota eksekusi harian, 6 menit per eksekusi, 100 KB per entri cache) — sudah diakali dengan cache per ruangan | Kinerja tidak terduga saat data membesar | MySQL berindeks + Redis |
| R-15 | **URL GAS & ID deployment ter-commit** di `api.js` | Endpoint diketahui publik (diperparah R-02) | Endpoint di balik autentikasi |
| R-16 | **Gambar QR dibangkitkan layanan pihak ketiga** (`quickchart.io/chart?cht=qr&chl=<kodeLabel>` di `print.js`) | Kode barang dikirim ke pihak luar; cetak label gagal bila layanan tidak tersedia | QR dibangkitkan di server (`endroid/qr-code`) |
| R-17 | **Nomor unit digeser ulang setelah mutasi satu unit** (`moveOneUnitToNewRoom`: unit dengan indeks > unit yang dipindah diturunkan satu agar indeks "kontigu"); unit yang dipindah menjadi baris baru berkode `KODE-n` sehingga labelnya `KAT-KODE-n-1`, yang oleh `resolveScannedCode` diurai kembali sebagai unit n milik induk | **Label fisik yang sudah tertempel bisa menunjuk barang yang salah** setelah ada mutasi unit; identitas barang tidak stabil | Identitas tetap per baris (UUID + kode/NUP) dan tidak pernah dinomori ulang; label lama hanya dipetakan bila belum pernah terdampak mutasi unit; lainnya wajib **cetak ulang label** saat inventarisasi pertama (07-MIGRASI §4a) |

## 4. Kesenjangan terhadap kebutuhan penatausahaan BMN

Rincian dasar hukum di `06-REKOMENDASI-REGULASI.md`. Ringkasnya, sistem berjalan **belum** memiliki:

1. **Daftar Barang Ruangan (DBR)** resmi per ruangan yang ditandatangani (PMK 181/2016 mewajibkan pencatatan perpindahan barang antar ruangan ke DBR/DBL).
2. **Inventarisasi (sensus/opname)** terjadwal per ruangan dengan hasil (ditemukan, tidak ditemukan, kondisi berubah, barang berlebih) dan laporan hasil inventarisasi — PMK 181/2016 Pasal 19: inventarisasi BMN selain persediaan dan KDP sekurang-kurangnya sekali dalam 5 tahun.
3. **Nilai perolehan, tanggal perolehan, sumber perolehan/dana** per barang — diperlukan untuk rekonsiliasi dengan aplikasi resmi Kemenkeu dan untuk akreditasi (Tabel DKPS "Sarana Laboratorium dan Pembelajaran", "Prasarana Pendidikan", "Teknologi Informasi dan Komunikasi" LAMDIK IAPSK 3.0).
4. **Daftar barang rusak berat dan barang hilang** sebagai dasar usulan pemindahtanganan/penghapusan.
5. **Pemeliharaan/perbaikan** (laporan kerusakan, riwayat perbaikan, biaya).
6. **Rekonsiliasi dengan aplikasi resmi BMN** (SAKTI/SIMAK-BMN — sistem fakultas melengkapi, tidak menggantikan; nama aplikasi yang dipakai Unsil **perlu verifikasi**).
7. **Persetujuan peminjaman oleh pihak yang berwenang** untuk peminjam di luar PIC, serta pembedaan penggunaan internal vs pemanfaatan oleh pihak luar.

## 5. Keterkaitan dengan sistem lain

- **Surat (OrmawaHub)** juga mengelola ruangan & fasilitas (tab Plot Ruangan, `Rooms`, `RektoratRooms`) untuk permohonan kegiatan ormawa. Dua sumber data ruangan → rawan tidak konsisten. Rekomendasi: **Aset menjadi pemilik master gedung & ruangan**; Surat membaca lewat API baca (lihat `02-ARSITEKTUR.md` §11).
- **Akreditasi** membutuhkan data sarana-prasarana per prodi (DKPS Tabel 13–15 LAMDIK).
- **Regulasi** menyimpan PMK/PP rujukan (tautan dari sistem ini).

## 6. Kesimpulan

Logika bisnis SIMAN-FKIP-2 (PIC ruangan, QR, mutasi vs peminjaman, kondisi B/RR/RB) sudah tepat dan menjadi dasar PRD. Yang harus berubah adalah **fondasi** (keamanan server, model data per unit, transaksi) dan **kelengkapan penatausahaan** (DBR, inventarisasi, nilai perolehan, daftar RB/hilang, pemeliharaan). Migrasi data dari Google Sheets dirinci di `07-MIGRASI-DATA.md`, termasuk menjaga agar **label QR yang sudah tertempel tetap berfungsi** sejauh dapat dipastikan benar, dan mencetak ulang label yang terdampak R-17.
