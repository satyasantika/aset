# API v1 untuk Surat/OrmawaHub

Basis URL: `/api/v1`. Otentikasi: header `Authorization: Bearer <token>` (Sanctum). Token dibuat super-admin di
panel **Sistem → Token API** (teks token tampil sekali). Batas: 60 permintaan/menit/token (429 bila terlampaui).

| Metode & jalur | Ability | Fungsi |
|---|---|---|
| `GET /ruangan` | `ruangan:baca` atau `ruangan:pakai` | Ruangan `dapat_dipinjam`: kode, nama, gedung, lantai, kapasitas, fasilitas ringkas (maks. 8 jenis aset aktif) |
| `GET /ruangan/{kode}/jadwal?dari=&sampai=` | `ruangan:baca` atau `ruangan:pakai` | Pemakaian terjadwal yang beririsan dengan rentang (bawaan hari ini + 30 hari; maks. 92 hari) |
| `POST /ruangan/{kode}/pemakaian` | `ruangan:pakai` | Mencatat pemakaian: `mulai`, `selesai` (ISO 8601), `kegiatan`, `referensi_eksternal` (nomor permohonan di Surat; wajib) |

Kode status: `401` tanpa token · `403` ability kurang · `404` ruangan tidak ada/tidak dapat dipinjam · `422` validasi ·
`409` bentrok (`bentrok[]` berisi jadwal yang menghalangi) atau `referensi_eksternal` sudah dipakai untuk pemakaian lain ·
`201` dibuat · `200` pengiriman ulang yang identik (idempoten, `dibuat: false`).

Catatan perancangan:
- Cek bentrok dilakukan dalam transaksi dengan `lockForUpdate` pada baris ruangan; tepi yang bersentuhan (selesai = mulai berikutnya) bukan bentrok.
- Idempotensi per (`sumber` = `surat`, `referensi_eksternal`).
- Peminjaman SIMAN bersifat per barang (aset), bukan per ruangan, sehingga jadwal ruangan saat ini bersumber dari `pemakaian_ruangan` saja.
- Akun klien tidak memiliki peran dan tidak dapat masuk panel (`aktif = false`); mencabut token = menghapusnya di panel.
