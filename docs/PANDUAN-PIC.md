# Panduan PIC Ruangan — ASET FKIP

Untuk penanggung jawab ruangan (peran **pic-ruangan**). Anda mengelola barang di **ruangan yang ditugaskan** kepada Anda;
barang di ruangan lain hanya dapat dilihat. Panel: `https://<domain>/admin`. Halaman cepat (ponsel): `/pindai`, `/keranjang`.

## 1. Masuk
Gunakan surel `@unsil.ac.id` dan kata sandi dari admin. Lima kali salah dalam semenit akan menahan login sementara.
Akun PIC tidak wajib MFA; admin dan super-admin wajib.

## 2. Memindai barang (QR atau label lama)
1. Buka menu **Pindai** (`/pindai`) di ponsel dan izinkan kamera, atau ketik kode label di kolom pencarian lalu **Cari**.
2. Label baru (QR) dan label lama (`KATEGORI-KODE-UNIT`, `KODE-UNIT`, nomor BMN/NUP) sama-sama dikenali.
3. Hasil menampilkan nama, kode barang + NUP, ruangan, kondisi, dan status. Jika tampil "tidak ditemukan", cek ketikan;
   bila label rusak/hilang, cari lewat menu **Aset** lalu cetak label baru (bagian 7).

## 3. Mengubah kondisi barang
Kondisi: **B** (Baik), **RR** (Rusak Ringan), **RB** (Rusak Berat).
- Dari hasil pindai atau menu **Aset** → baris barang → **Ubah kondisi**; pilih kondisi baru dan isi catatan.
- Beberapa barang sekaligus: centang barang → aksi massal **Ubah kondisi**.
- Setiap perubahan tercatat di riwayat kondisi (siapa, kapan, dari-ke). Barang di ruangan lain akan ditolak (403).
- Barang **RB** tidak dapat dipinjam; barang RR/RB dapat dilaporkan kerusakannya (bagian 8).

## 4. Meminjamkan barang (keranjang)
1. Buka **Keranjang** (`/keranjang`). Tambahkan barang (dipindai atau dicari). Barang yang sedang dipinjam, dalam perbaikan,
   RB, atau berstatus bukan aktif tidak dapat dipilih.
2. Isi **Peminjam** (nama/unit/kontak untuk peminjam internal), **Keperluan**, dan **rencana kembali** (maks. 14 hari, dapat
   diubah admin).
3. Kirim. Keranjang bersifat **semua atau tidak sama sekali**: bila satu barang bermasalah, seluruh keranjang ditolak dan
   nama barang bermasalah ditampilkan.
4. Peminjaman yang dicatat langsung berstatus **dipinjam**. Dua PIC yang meminjamkan barang yang sama bersamaan: hanya satu berhasil.

### Pengajuan dari civitas
Civitas mengajukan lewat **Pinjam barang** (`/pinjam`). Anda menerima notifikasi; buka **Peminjaman** → pengajuan →
**Setujui** atau **Tolak** (alasan wajib). Setelah disetujui, saat barang diambil klik **Serahkan barang**. Pengingat H-1
dan pengingat terlambat dikirim otomatis. Permohonan **pihak luar** diteruskan ke pejabat penatausahaan.

## 5. Pengembalian
**Peminjaman** → tab **Aktif** atau **Terlambat** → **Terima pengembalian** → isi **kondisi saat kembali**.
Bila kondisi berubah, kondisi barang ikut diperbarui; jika RR/RB, **tiket pemeliharaan dibuat otomatis**.

## 6. DBR/DBL ruangan
1. **DBR / DBL** → **Bangkitkan DBR/DBL** untuk ruangan Anda (daftar barang diambil dari data saat itu).
2. Periksa daftar, lalu **Setujui (tanda tangan PIC)**. Pejabat penatausahaan menerima notifikasi untuk mengesahkan.
3. Setelah disahkan, **Cetak PDF** kapan saja (diambil dari salinan yang disahkan). Jika barang di ruangan berubah
   (mutasi, kondisi, tambah/ubah), versi disahkan ditandai **perlu diperbarui** — bangkitkan versi baru.

## 7. Cetak label
- Satu/banyak barang: **Aset** → centang → **Cetak label** (PDF berisi QR yang mengarah ke halaman publik barang).
- Label yang **perlu dicetak ulang** (hasil migrasi): menu Aset → **Label perlu dicetak ulang**; cetak, tempel, lalu tandai
  sudah dicetak agar hilang dari daftar.

## 8. Pemeliharaan & laporan kerusakan
- Siapa pun (termasuk mahasiswa tanpa login) dapat melaporkan kerusakan dengan memindai QR barang; Anda menerima notifikasi tiket.
- **Pemeliharaan** → tiket → **Proses** → bila perlu **Menunggu suku cadang** → **Selesaikan** (tindakan, biaya, kondisi akhir)
  atau **Tidak dapat diperbaiki**. Barang berstatus *dalam perbaikan* tidak dapat dipinjam sampai tiket selesai.
- Anda dapat **Buka tiket perbaikan** sendiri dari baris barang.

## 9. Inventarisasi ruangan (ponsel)
1. Saat admin membuka periode dan menugaskan Anda, buka **Inventarisasi** → ruangan → **Buka pemindaian**.
2. Pindai tiap barang: **ditemukan**; ubah kondisi bila berbeda (**kondisi berubah**). Barang fisik yang tidak ada di daftar
   dicatat sebagai **temuan berlebih** (isi keterangan/tautan foto).
3. Pantau progres (%). Setelah selesai klik **Selesai ruangan**; barang yang tidak terpindai otomatis **tidak ditemukan**.

## 10. Mutasi (pindah ruangan)
**Mutasi lokasi** → **Ajukan mutasi**: pilih ruangan asal (milik Anda), tujuan, barang (tidak sedang dipinjam/diperbaiki), dan
alasan. Admin BMN memutuskan; PIC asal dan tujuan diberi tahu. Mutasi ditahan selama ruangan sedang diinventarisasi.

## 11. Masalah umum
| Gejala | Penyebab / tindakan |
|---|---|
| "Anda tidak berwenang" (403) | Barang bukan di ruangan Anda — minta admin menambah penugasan |
| Barang tak bisa masuk keranjang | Sedang dipinjam/diperbaiki, RB, atau tak aktif — lihat pesan di keranjang |
| Fitur "dimatikan" | Admin menonaktifkan fitur (mis. mutasi) sementara |
| Kamera tidak menyala | Izinkan kamera untuk situs; gunakan HTTPS; atau ketik kode manual |
