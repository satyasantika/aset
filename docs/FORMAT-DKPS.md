# Pemetaan ekspor DKPS LAMDIK (RG-11)

Ekspor DKPS dibuat dari halaman **Laporan** (`/admin/laporan`), format XLSX/CSV. Kolom di bawah adalah **pemetaan awal
dari data SIMAN**; urutan dan judul kolom pada templat resmi LAMDIK IAPSK 3.0 **harus dicocokkan manual oleh admin
BMN/penanggung jawab akreditasi** sebelum dipakai untuk pelaporan (templat resmi tidak tersedia di repositori ini).

| Tabel DKPS | Exporter | Sumber data | Kolom |
|---|---|---|---|
| Sarana Laboratorium dan Pembelajaran | `DkpsSaranaExporter` | Aset berstatus aktif di ruangan berkategori `adalah_laboratorium` atau `adalah_ruang_kelas` | Program studi, jenis ruangan, nama ruangan, nama sarana, merk/tipe, jumlah unit (1 per baris), tahun pengadaan, kondisi, kode/NUP |
| Prasarana Pendidikan | `DkpsPrasaranaExporter` | Ruangan | Program studi, kode, nama, jenis prasarana (kategori), gedung, luas (m²), kapasitas, atribut K3L |
| Teknologi Informasi dan Komunikasi | `DkpsTikExporter` | Aset aktif berkode barang berawalan `ASET_DKPS_AWALAN_TIK` (bawaan `31002` = Peralatan Komputer, 3.10.02) | Program studi, jenis perangkat, merk/tipe, spesifikasi, lokasi, tahun pengadaan, kondisi, kode/NUP |

Catatan:
- **Prodi pemakai** diambil dari relasi ruangan–prodi (`ruangan_prodi`); ruangan tanpa prodi ditulis `(umum)`; ruangan dengan
  beberapa prodi menampilkan semuanya dipisah `;` (satu baris, tidak digandakan).
- Pimpinan, pejabat penatausahaan, admin, dan super-admin mengekspor seluruh fakultas; PIC hanya ruangan yang ditugaskan.
- Awalan kode TIK dapat diubah lewat `.env` bila klasifikasi kode barang BMN yang dipakai fakultas berbeda.
