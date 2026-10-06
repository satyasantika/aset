#!/usr/bin/env python3
"""Membangun halaman panduan statis (public/panduan/*.html) dari struktur di bawah. Jalankan: python3 scripts/panduan/bangun.py
Tangkapan layar: scripts/panduan/ambil-tangkapan-layar.py (butuh server demo + data `siman:siapkan-uat`)."""
import html, os
OUT = os.path.join(os.path.dirname(__file__), '..', '..', 'public', 'panduan')

ROLES = {
 'umum': ('Semua pengguna', 'Masuk, pindai QR, dan lapor kerusakan', 'Untuk semua orang: cara masuk, memindai QR barang, dan melaporkan kerusakan tanpa login.'),
 'civitas': ('Civitas (dosen dan ormawa)', 'Meminjam barang', 'Mengajukan peminjaman barang dan memantau statusnya.'),
 'pic': ('PIC Ruangan', 'Mengelola barang di ruangan Anda', 'Pindai, ubah kondisi, pinjamkan, kembalikan, DBR, inventarisasi, dan pemeliharaan.'),
 'admin': ('Admin BMN', 'Aset, mutasi, inventarisasi, penghapusan, laporan', 'Penatausahaan aset secara menyeluruh: dari pendaftaran sampai laporan.'),
 'pejabat': ('Pejabat Penatausahaan', 'Mengesahkan dokumen', 'Mengesahkan DBR, berita acara inventarisasi, dan usulan penghapusan.'),
 'pimpinan': ('Pimpinan', 'Melihat dasbor dan laporan', 'Memantau kondisi aset dan mengunduh laporan (tanpa data pribadi).'),
 'super': ('Super Admin', 'Pengaturan, pengguna, token API', 'Pengaturan sistem, akun pengguna, token API, dan log aktivitas.'),
}

# Setiap bagian: (judul, [langkah/teks], [(berkas gambar tanpa ekstensi, keterangan)])
G = {}
G['umum'] = [
 ('1. Alamat dan masuk', ['Buka alamat sistem lalu pilih **Masuk**. Gunakan surel `@unsil.ac.id` dan kata sandi dari admin. Lima kali salah dalam semenit menahan login sementara.', 'Super-admin dan admin BMN wajib memasang **MFA** (aplikasi autentikator) pada login pertama, lalu memasukkan kode 6 digit setiap masuk.'], [('umum-login', 'Halaman masuk')]),
 ('2. Memindai QR barang (tanpa login)', ['Pindai QR pada label barang dengan kamera ponsel. Halaman publik menampilkan nama barang, merk/tipe, kategori, ruangan, kondisi, tahun perolehan, serta kode barang + NUP.', 'Nilai, PIC, peminjam, dan riwayat **tidak** ditampilkan kepada publik.'], [('publik-lookup', 'Halaman publik hasil pindai QR')]),
 ('3. Melaporkan kerusakan', ['Dari halaman barang pilih **Laporkan kerusakan**. Jelaskan kerusakannya (minimal 5 karakter); nama dan kontak boleh dikosongkan. Dibatasi 5 laporan per jam per perangkat.', 'Laporan menjadi tiket pemeliharaan dan PIC ruangan diberi tahu. Tidak ada unggah foto — cukup deskripsi.'], [('publik-lapor', 'Formulir lapor kerusakan'), ('publik-lapor-isi', 'Contoh pengisian')]),
]
G['civitas'] = [
 ('1. Masuk', ['Masuk di halaman login memakai surel `@unsil.ac.id`. Setelah berhasil Anda langsung diarahkan ke halaman **Pinjam barang**.'], [('civitas-setelah-login', 'Setelah masuk: halaman Pinjam barang')]),
 ('2. Mengajukan peminjaman', ['Tentukan **rentang waktu** (mulai dan rencana kembali; maksimal 14 hari), cari dan centang **barang** yang tersedia, isi **keperluan** dan unit/prodi/ormawa, lalu kirim.', 'Barang yang sedang dipinjam, dalam perbaikan, atau rusak berat tidak dapat dipilih. Satu pengajuan hanya untuk satu ruangan.', 'Bila rentang waktu bentrok dengan peminjaman lain, pengajuan ditolak dengan alasan "tidak tersedia".'], [('civitas-pinjam', 'Formulir pengajuan peminjaman')]),
 ('3. Memantau pinjaman', ['Buka **Pinjaman saya** untuk melihat status: *diajukan → disetujui → dipinjam → dikembalikan* (atau ditolak/dibatalkan). Anda menerima notifikasi (lonceng/surel) saat PIC memutuskan. Pengajuan yang belum diputuskan dapat dibatalkan.', 'Barang diambil dan dikembalikan kepada PIC ruangan sesuai jadwal; keterlambatan dihitung otomatis dan diingatkan.'], [('civitas-pinjaman-saya', 'Daftar pinjaman saya')]),
]
G['pic'] = [
 ('1. Dasbor', ['Setelah masuk, **Dasbor** menampilkan ringkasan aset di ruangan Anda: jumlah, nilai, kondisi, pinjaman aktif/terlambat, tiket terbuka, dan DBR yang perlu diperbarui. Anda mengelola **hanya ruangan yang ditugaskan**; barang ruangan lain hanya dapat dilihat.'], [('pic-dashboard', 'Dasbor PIC')]),
 ('2. Memindai barang', ['Buka **/pindai** di ponsel dan izinkan kamera, atau ketik kode label lalu **Cari**. Label baru (QR) maupun label lama (`KATEGORI-KODE-UNIT`, nomor BMN/NUP) dikenali.', 'Dari hasil pindai Anda dapat mengubah kondisi, mengajukan mutasi, atau melaporkan kerusakan.'], [('pic-pindai', 'Halaman pindai')]),
 ('3. Daftar aset dan ubah kondisi', ['Menu **Aset** memuat seluruh barang (filter dan pencarian tersedia). Pada baris barang di ruangan Anda pilih **Ubah kondisi**: B (Baik), RR (Rusak Ringan), RB (Rusak Berat) beserta catatan. Semua perubahan tercatat di riwayat.', 'Beberapa barang sekaligus: centang lalu pakai aksi massal. Barang di ruangan lain akan ditolak (403).'], [('pic-aset', 'Daftar aset'), ('pic-ubah-kondisi', 'Dialog Ubah kondisi')]),
 ('4. Meminjamkan dan menerima pengembalian', ['**Keranjang** (`/keranjang`): tambahkan barang, isi peminjam, keperluan, dan rencana kembali. Keranjang bersifat semua-atau-tidak-sama-sekali; barang bermasalah disebutkan namanya.', 'Menu **Peminjaman**: tab Aktif/Terlambat/Diajukan/Riwayat. Pengajuan civitas → **Setujui/Tolak**, lalu **Serahkan barang** saat diambil dan **Terima pengembalian** (isi kondisi saat kembali). Kondisi RR/RB otomatis membuka tiket pemeliharaan.'], [('pic-keranjang', 'Keranjang peminjaman'), ('pic-peminjaman', 'Daftar peminjaman'), ('pic-peminjaman-detail', 'Detail peminjaman dan aksinya')]),
 ('5. Pemeliharaan', ['**Pemeliharaan** memuat tiket kerusakan (dari laporan publik atau dibuka PIC). Alur: **Proses** → *Menunggu suku cadang* → **Selesaikan** (tindakan, biaya, kondisi akhir) atau *Tidak dapat diperbaiki*. Barang yang diperbaiki tidak dapat dipinjam.'], [('pic-pemeliharaan', 'Tiket pemeliharaan')]),
 ('6. Mutasi lokasi', ['**Mutasi lokasi** → **Ajukan mutasi** dari ruangan Anda ke ruangan tujuan, pilih barang dan isi alasan. Admin BMN memutuskan; PIC asal dan tujuan diberi tahu. Mutasi ditahan saat ruangan diinventarisasi.'], [('pic-mutasi', 'Daftar mutasi')]),
 ('7. DBR/DBL', ['**DBR / DBL** → **Bangkitkan DBR/DBL** untuk ruangan Anda, periksa daftar, lalu **Setujui (tanda tangan PIC)**. Pejabat penatausahaan kemudian mengesahkan; PDF dapat dicetak kapan saja. Jika barang di ruangan berubah, versi yang disahkan ditandai *perlu diperbarui*.'], [('pic-dbr', 'Daftar DBR'), ('pic-dbr-detail', 'Detail DBR')]),
 ('8. Inventarisasi ruangan', ['Saat admin menugaskan Anda, buka **Inventarisasi** → ruangan → **Buka pemindaian**. Pindai tiap barang (ditemukan / kondisi berubah), catat temuan berlebih, pantau progres, lalu **Selesai ruangan**; barang yang tidak terpindai menjadi *tidak ditemukan*.'], [('pic-inventarisasi', 'Daftar periode inventarisasi')]),
 ('9. Cetak label', ['Pada **Aset** centang barang → **Cetak label** (PDF berisi QR). Label hasil migrasi yang **perlu dicetak ulang** ada di menu *Label perlu cetak ulang*; tandai setelah ditempel.'], []),
]
G['admin'] = [
 ('1. Dasbor dan peringatan', ['Dasbor menampilkan seluruh fakultas: jumlah dan nilai aset, kondisi, pinjaman, tiket, DBR, serta **peringatan inventarisasi** (PMK 181/2016 Pasal 19) bila inventarisasi terakhir yang disahkan melewati ambang.'], [('admin-dashboard', 'Dasbor admin BMN')]),
 ('2. Master data', ['**Ruangan**, **Gedung**, **Kategori ruangan**, **Program studi**, dan **Kodefikasi barang** (impor CSV) di grup *Master data*. Pada Ruangan, tambahkan PIC dan prodi pemakai, luas, kapasitas, serta atribut K3L.', '**Pengguna**: buat akun `@unsil.ac.id` dan beri peran (Anda dapat menugaskan PIC; akun admin hanya diubah super-admin).'], [('admin-ruangan', 'Daftar ruangan'), ('admin-pengguna', 'Daftar pengguna'), ('admin-kodefikasi', 'Kodefikasi barang')]),
 ('3. Mendaftarkan aset', ['**Aset** → **Buat** untuk satu barang, atau **Daftarkan massal** (mis. 20 kursi dengan NUP berurutan). Kode barang + NUP harus unik; barang belum tercatat di aplikasi BMN memakai *kode internal*. Isi nilai, tanggal, dan sumber perolehan untuk rekonsiliasi.', 'Foto/dokumen berupa **tautan** (tidak ada unggah berkas). Status dan kondisi diubah lewat aksi pada baris barang.'], [('admin-aset', 'Daftar aset'), ('admin-aset-baru', 'Formulir aset baru')]),
 ('4. Label QR', ['Pilih barang → **Cetak label**. Label yang perlu dicetak ulang (hasil migrasi) terkumpul di *Label perlu cetak ulang*.'], [('admin-label-ulang', 'Label perlu dicetak ulang')]),
 ('5. Mutasi lokasi', ['Pengajuan PIC muncul di **Mutasi lokasi**. **Setujui** (lokasi berubah, riwayat tercatat, DBR ruangan asal & tujuan menjadi *perlu diperbarui*) atau **Tolak** dengan alasan.'], [('admin-mutasi', 'Daftar mutasi'), ('admin-mutasi-detail', 'Detail mutasi')]),
 ('6. Peminjaman', ['Pantau tab Aktif/Terlambat/Diajukan/Riwayat. Gunakan **Catat permohonan pihak luar** untuk permohonan eksternal (diteruskan ke pejabat penatausahaan). Data pribadi peminjam dianonimkan otomatis setelah masa retensi.'], [('admin-peminjaman', 'Daftar peminjaman')]),
 ('7. DBR/DBL', ['**Bangkitkan DBR/DBL** per ruangan (atau DBL untuk barang berlokasi lainnya) → PIC menyetujui → pejabat mengesahkan → cetak PDF.'], [('admin-dbr', 'Daftar DBR/DBL')]),
 ('8. Inventarisasi', ['**Inventarisasi** → buat periode → **Buka periode** (hanya satu berjalan) → **Tugaskan petugas** per ruangan. Setelah PIC memindai: **Tutup periode** (berita acara tersusun; unduh PDF dan Excel selisih), **Tetapkan hilang** untuk barang yang tidak ditemukan setelah diverifikasi, lalu pejabat mengesahkan.'], [('admin-inventarisasi', 'Daftar periode'), ('admin-inventarisasi-detail', 'Detail periode')]),
 ('9. Penghapusan', ['**Penghapusan** → buat usulan dari barang RB/hilang → **Ajukan** → pejabat **Setujui internal** (barang menjadi *diusulkan hapus*) → setelah SK terbit **Catat SK penghapusan** (nomor & tanggal wajib; barang menjadi *dihapus*, tidak dihapus permanen).'], [('admin-penghapusan', 'Daftar usulan'), ('admin-penghapusan-detail', 'Detail usulan')]),
 ('10. Laporan dan log', ['**Laporan**: rekonsiliasi (kode + NUP), daftar RB & hilang, riwayat peminjaman, rekap pemeliharaan, DKPS LAMDIK, dan log aktivitas. Ekspor diproses di latar belakang dan diunduh lewat notifikasi (berkas dihapus ≤ 24 jam). **Log aktivitas** mencatat siapa melakukan apa.'], [('admin-laporan', 'Halaman laporan'), ('admin-log', 'Log aktivitas')]),
]
G['pejabat'] = [
 ('1. Dasbor', ['Dasbor menampilkan ringkasan seluruh fakultas. Notifikasi (lonceng dan surel) memberi tahu bila ada DBR atau berita acara yang menunggu pengesahan.'], [('pejabat-dashboard', 'Dasbor pejabat')]),
 ('2. Mengesahkan DBR/DBL', ['**DBR / DBL** → buka dokumen berstatus *disetujui PIC* → periksa daftar → **Sahkan**. Dokumen disimpan sebagai snapshot; **Kembalikan ke draf** bila perlu perbaikan.'], [('pejabat-dbr', 'Daftar DBR'), ('pejabat-dbr-detail', 'Detail DBR dan tombol Sahkan')]),
 ('3. Berita acara inventarisasi', ['**Inventarisasi** → periode yang sudah ditutup → periksa berita acara (PDF dan selisih Excel) → **Sahkan berita acara**.'], [('pejabat-inventarisasi', 'Daftar inventarisasi')]),
 ('4. Usulan penghapusan', ['**Penghapusan** → usulan berstatus *diajukan* → **Setujui internal** atau **Tolak** (alasan wajib). Setelah disetujui, admin mencatat SK penghapusan.'], [('pejabat-penghapusan', 'Daftar usulan'), ('pejabat-penghapusan-detail', 'Detail usulan')]),
 ('5. Peminjaman pihak luar dan laporan', ['Permohonan **pihak luar** diputuskan oleh pejabat penatausahaan (skema pemanfaatan/sewa mengikuti ketentuan yang berlaku). Menu **Laporan** tersedia untuk pengunduhan.'], [('pejabat-peminjaman', 'Daftar peminjaman'), ('pejabat-laporan', 'Laporan')]),
]
G['pimpinan'] = [
 ('1. Dasbor', ['Dasbor menampilkan jumlah dan nilai aset seluruh fakultas, kondisi (B/RR/RB), pinjaman, tiket, dan DBR. Peran pimpinan bersifat **baca-saja**.'], [('pimpinan-dashboard', 'Dasbor pimpinan')]),
 ('2. Aset', ['Menu **Aset** memuat daftar barang beserta kondisi dan lokasinya (tanpa aksi ubah).'], [('pimpinan-aset', 'Daftar aset')]),
 ('3. Laporan dan DKPS', ['Menu **Laporan**: unduh rekonsiliasi, daftar RB & hilang, rekap pemeliharaan, dan tabel **DKPS LAMDIK** (sarana lab & pembelajaran, prasarana, TIK per prodi). Kolom data pribadi (nama/kontak peminjam & pelapor) **disamarkan** untuk pimpinan.'], [('pimpinan-laporan', 'Halaman laporan')]),
]
G['super'] = [
 ('1. Dasbor', ['Super-admin memiliki seluruh hak, termasuk pengaturan sistem. Akun super-admin wajib MFA.'], [('super-dashboard', 'Dasbor super-admin')]),
 ('2. Pengaturan sistem', ['**Pengaturan sistem**: identitas instansi dan penandatangan (kop dokumen), *toggle* fitur (tambah aset, ubah kondisi, mutasi, peminjaman, tahan mutasi saat inventarisasi), ambang pengingat inventarisasi, retensi peminjaman (bulan), dan maksimum hari pinjam.'], [('super-pengaturan', 'Pengaturan sistem')]),
 ('3. Pengguna dan peran', ['**Pengguna**: buat/ubah akun, aktifkan atau nonaktifkan (akun tidak dihapus), dan beri peran: super-admin, admin-bmn, pejabat-penatausahaan, pic-ruangan, pimpinan, civitas.'], [('super-pengguna', 'Daftar pengguna')]),
 ('4. Token API untuk Surat', ['**Token API** → **Buat token**: isi nama klien dan ability (`ruangan:baca`, `ruangan:pakai`). Teks token tampil **sekali** — salin dan serahkan ke pengelola Surat. Cabut token dengan menghapusnya. Dokumentasi endpoint: `docs/API.md`.'], [('super-token', 'Token API')]),
 ('5. Log aktivitas dan antrean', ['**Log aktivitas** memuat siapa melakukan apa dan kapan (data rahasia disamarkan). Antrean dan tugas latar dipantau di **/horizon** (khusus super-admin).'], [('super-log', 'Log aktivitas')]),
]

def md(t):
    t = html.escape(t); out = ''; b = False; c = False; i = False
    parts = t.split('**')
    t = ''.join(('<strong>' if k % 2 else '') + p + ('</strong>' if k % 2 and k < len(parts) - 1 else '') for k, p in enumerate(parts))
    # kode `x` dan miring *x*
    segs = t.split('`'); t = ''.join(('<code>' + s + '</code>') if k % 2 else s for k, s in enumerate(segs))
    segs = t.split('*'); t = ''.join(('<em>' + s + '</em>') if k % 2 else s for k, s in enumerate(segs))
    return t

CSS = '''body{font:16px/1.65 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;background:#f8fafc;color:#0f172a}
header{background:#0f172a;color:#fff;padding:18px 20px}header a{color:#93c5fd;text-decoration:none}
main{max-width:920px;margin:0 auto;padding:24px 20px 64px}h1{margin:0 0 4px;font-size:1.6rem}h2{margin:36px 0 8px;font-size:1.2rem;border-bottom:1px solid #e2e8f0;padding-bottom:6px}
p.lead{color:#475569;margin:0 0 8px}figure{margin:14px 0;background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden}
figure img{display:block;width:100%;height:auto}figcaption{padding:8px 12px;font-size:.88rem;color:#475569;background:#f1f5f9}
code{background:#e2e8f0;padding:1px 5px;border-radius:4px;font-size:.9em}nav.peran{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 0}
nav.peran a{font-size:.85rem;background:#1e293b;color:#e2e8f0;padding:4px 10px;border-radius:999px;text-decoration:none}nav.peran a.aktif{background:#2563eb;color:#fff}
footer{max-width:920px;margin:0 auto;padding:0 20px 40px;color:#64748b;font-size:.85rem}ul{padding-left:20px}'''

def nav(aktif):
    return '<nav class="peran">' + '<a href="../">Beranda</a>' + ''.join(f'<a href="{k}.html"' + (' class="aktif"' if k == aktif else '') + f'>{html.escape(v[0])}</a>' for k, v in ROLES.items()) + '</nav>'

def page(role):
    nama, sub, desc = ROLES[role]
    h = [f'<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Panduan {html.escape(nama)} — SIMAN FKIP</title><link rel="stylesheet" href="panduan.css"></head><body>',
         f'<header><h1>Panduan: {html.escape(nama)}</h1><p class="lead" style="color:#cbd5e1">{html.escape(desc)}</p>{nav(role)}</header><main>']
    for judul, teks, gambar in G[role]:
        h.append(f'<h2>{html.escape(judul)}</h2>')
        h.append('<ul>' + ''.join(f'<li>{md(t)}</li>' for t in teks) + '</ul>' if len(teks) > 1 else f'<p>{md(teks[0])}</p>')
        for g, ket in gambar:
            h.append(f'<figure><img src="img/{g}.jpg" alt="{html.escape(ket)}" loading="lazy"><figcaption>{html.escape(ket)}</figcaption></figure>')
    h.append('</main><footer>SIMAN FKIP — Sistem Informasi Manajemen Aset FKIP UNSIL. Tangkapan layar memakai data contoh (UAT).</footer></body></html>')
    return '\n'.join(h)

os.makedirs(OUT, exist_ok=True)
open(os.path.join(OUT, 'panduan.css'), 'w').write(CSS)
for r in ROLES:
    open(os.path.join(OUT, f'{r}.html'), 'w').write(page(r))
print('dibangun:', ', '.join(ROLES))
