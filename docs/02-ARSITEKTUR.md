# 02 — Arsitektur: ASET FKIP 3

Turunan `STANDAR-TEKNIS.md`. Penyimpangan dinyatakan eksplisit.

## 1. Gambaran

```mermaid
flowchart LR
    subgraph Pengguna
        A[Admin BMN / pejabat / pimpinan]
        P[PIC ruangan - ponsel & desktop]
        C[Civitas]
        U[Publik - pindai QR]
    end
    subgraph App["ASET FKIP 3 (Laravel 13)"]
        F[Panel Filament /admin]
        L[Halaman Livewire /pinjam, /pindai, /inventarisasi]
        Q[Rute publik /a/{id}, /lapor-kerusakan]
        API[API baca v1 - Sanctum]
        ACT[Actions + Policies]
    end
    DB[(MySQL 8.4)]
    R[(Redis 7)]
    H[Horizon: impor, ekspor, notifikasi, tautan]
    S[Scheduler]
    SUR[Sistem Surat] -->|ruangan & jadwal| API
    AKR[Sistem Akreditasi] -.->|ekspor DKPS| F
    A --> F
    P --> F
    P --> L
    C --> L
    U --> Q
    F --> ACT
    L --> ACT
    Q --> ACT
    API --> ACT
    ACT --> DB
    ACT --> R
    R --> H
    H --> DB
    S --> R
```

## 2. Stack

| Lapisan | Pilihan | Catatan |
|---|---|---|
| Framework | Laravel 13, PHP 8.4 | Proyek baru (`composer create-project`) — kode SIMAN lama tidak dipakai ulang, hanya logika & data |
| Panel | Filament 5 (`/admin`) | Admin BMN, pejabat, pimpinan, PIC (desktop) |
| Halaman lapangan | Livewire 4 + Tailwind 4, `html5-qrcode` (npm) | Pemindaian QR, keranjang peminjaman, inventarisasi, pengajuan civitas — dioptimalkan untuk ponsel |
| DB | MySQL 8.4 | |
| Redis | cache, sesi, antrean, lock, rate limit | §4 |
| PDF | `barryvdh/laravel-dompdf` | Label QR (A4 multi-label), DBR, berita acara — dirender dari data/snapshot, di-stream |
| QR | `endroid/qr-code` | URL `/a/{id}` |
| Excel | Filament Import/Export + `spatie/simple-excel` | Impor aset, impor migrasi, ekspor laporan |
| Hak akses | `spatie/laravel-permission` + Policy + tabel `ruangan_pic` | |
| Audit | `spatie/laravel-activitylog` | |
| API | `laravel/sanctum` (token read-only untuk sistem Surat) | F11 |
| Uji | Pest 4 (MySQL) | |

Paket opsional: `bezhansalleh/filament-shield` **tidak** dipakai (kompatibilitas Filament 5 — tulis Policy eksplisit).

## 3. Struktur `app/`

```
app/
├── Actions/
│   ├── Aset/            DaftarkanAset, DaftarkanAsetMassal, UbahDataAset, UbahKondisiAset, UbahStatusAset
│   ├── Label/           ResolusiLabel, TandaiLabelDicetak
│   ├── Mutasi/          AjukanMutasi, SetujuiMutasi, TolakMutasi
│   ├── Peminjaman/      CatatPeminjamanLangsung, AjukanPeminjaman, SetujuiPeminjaman, TolakPeminjaman,
│   │                    SerahkanPeminjaman, KembalikanPeminjaman, BatalkanPeminjaman, CekKetersediaan
│   ├── Pemeliharaan/    TerimaLaporanKerusakan, BukaTiket, SelesaikanTiket
│   ├── Dbr/             BangkitkanDbr, SetujuiDbrOlehPic, SahkanDbr, TandaiDbrPerluDiperbarui
│   ├── Inventarisasi/   BukaPeriode, CatatHasilPindai, CatatTemuanBerlebih, TutupPeriode, SahkanBeritaAcara
│   ├── Penghapusan/     BuatUsulanPenghapusan, SetujuiUsulan, CatatSkPenghapusan
│   └── Migrasi/         ImporDariSiman2 (per sheet)
├── Contracts/           PenyimpananBerkas
├── Enums/               KondisiAset, StatusAset, StatusBmn, StatusMutasi, StatusPeminjaman, JenisPeminjam,
│                        StatusTiket, StatusDbr, StatusPeriodeInventarisasi, HasilInventarisasi, StatusUsulanHapus,
│                        SumberPerolehan, JenisTautan, StatusCekTautan
├── Filament/Resources/  GedungResource, RuanganResource (+ PicRelationManager, AsetRelationManager),
│                        KodefikasiResource, AsetResource (+ RiwayatKondisi, RiwayatLokasi, Peminjaman, Tiket),
│                        MutasiResource, PeminjamanResource, TiketPemeliharaanResource, DbrResource,
│                        PeriodeInventarisasiResource, UsulanPenghapusanResource, PenggunaResource
├── Http/Controllers/    Publik\LookupAsetController, Publik\LaporKerusakanController, LabelPdfController,
│                        DbrPdfController, BeritaAcaraPdfController, Api\V1\RuanganController, BukaTautanController
├── Livewire/            Pindai, KeranjangPinjam, AjukanPinjam, InventarisasiRuangan, KatalogPinjam
├── Jobs/                PeriksaTautanBerkas, KirimPengingatTerlambat, SegarkanStatistik
├── Policies/            satu per model domain
├── Rules/               TautanBerkasValid, KodeBarangValid
└── Support/             FormatLabelLama (parser label SIMAN-2), Pengaturan, DriveUrl
```

## 4. Redis

| Pemakaian | Kunci | Detail |
|---|---|---|
| Lock peminjaman | `aset:pinjam:{aset_id}` (10 s) | BR-08, dipakai bersama `lockForUpdate` |
| Lock mutasi | `aset:mutasi:{aset_id}` | BR-06 |
| Lock nomor transaksi | `aset:nomor:{jenis}:{tahun}` | Nomor peminjaman `PJM-2026-00001`, mutasi `MUT-2026-0001`, tiket `TKT-...` |
| Cache master | `aset:master:ruangan`, `aset:master:kodefikasi` | 1 jam, dibersihkan observer |
| Cache dasbor | `aset:statistik:{dimensi}` | 30 menit, dibersihkan saat aset berubah (debounce via job) |
| Rate limit | `lookup` 30/menit/IP, `lapor` 5/jam/IP, `login` 5/menit, `api` 60/menit/token | |
| Antrean | `impor`, `ekspor`, `notifikasi`, `tautan`, `default` | Horizon |
| Sesi | `SESSION_DRIVER=redis` | |

## 5. Rute publik & lapangan

| Rute | Fungsi | Middleware |
|---|---|---|
| `GET /a/{aset}` | Lookup aset (BR-18) + tombol "Laporkan kerusakan" | `throttle:lookup` |
| `GET /l/{kode}` | Resolusi label lama → redirect 302 ke `/a/{id}` (BR-17) | `throttle:lookup` |
| `GET/POST /lapor-kerusakan/{aset}` | Formulir publik (BR-12) | `throttle:lapor` |
| `GET /pindai` | Pemindai QR (login): membuka aset, aksi sesuai peran | `auth` |
| `GET /pinjam` | Katalog & pengajuan civitas | `auth` |
| `GET /keranjang` | Keranjang peminjaman PIC | `auth`, permission |
| `GET /inventarisasi/{periode}/{ruangan}` | Pemindaian inventarisasi | `auth`, Policy |
| `GET /cetak/label` (POST pilihan) | PDF label (stream) | `auth` |
| `GET /cetak/dbr/{dbrVersi}` | PDF DBR dari snapshot | `auth` |
| `GET /api/v1/ruangan`, `/api/v1/ruangan/{kode}/jadwal` | Untuk sistem Surat | `auth:sanctum`, `abilities:ruangan:baca` |

Pemindai menerima isi QR berupa URL baru (`/a/{id}`) **atau** teks label lama; teks lama diurai `FormatLabelLama` (format `KATEGORI-KODE-UNIT`, `KODE-UNIT`, `KODE`, NUP, kode BMN — urutan kandidat sama dengan `handleLookupBarangPublik` SIMAN-2) lalu dicocokkan ke `label_lama`.

## 6. Job & jadwal

| Jadwal | Perintah | Fungsi |
|---|---|---|
| Harian 07:00 | `aset:pengingat-terlambat` | Notifikasi peminjam & PIC untuk pinjaman lewat rencana kembali |
| Harian 07:05 | `aset:pengingat-pengambilan` | Pengajuan disetujui yang belum diserahkan H-1 |
| Harian 00:30 | `aset:tandai-dbr-usang` | Konsistensi penanda `perlu_diperbarui` |
| Senin 06:00 | `aset:periksa-tautan` | `PeriksaTautanBerkas` untuk foto & dokumen |
| Bulanan | `aset:pengingat-inventarisasi` | BR-15 |
| Bulanan | `aset:pangkas-peminjaman` | BR-23 retensi |
| Per jam | `aset:bersihkan-tmp` | `storage/app/tmp` > 24 jam |

## 7. Notifikasi

Mail + database: `PengajuanPeminjamanBaru` (PIC), `PeminjamanDiputuskan` (peminjam), `PeminjamanTerlambat` (peminjam, PIC), `MutasiDiajukan` (admin), `MutasiDiputuskan` (PIC asal & tujuan), `LaporanKerusakanBaru` (PIC), `DbrMenungguPengesahan` (pejabat), `BeritaAcaraMenungguPengesahan` (pejabat), `TautanBerkasBermasalah`. WhatsApp (gateway HTTP) opsional untuk pengingat terlambat.

## 8. Berkas

Kebijakan tautan (STANDAR-TEKNIS §1a): foto aset, foto ruangan, dokumen perolehan (BAST/kontrak), SK penghapusan, berita acara bertanda tangan basah = `tautan_berkas`. Foto Drive lama dimigrasikan apa adanya (sudah berformat `lh3.googleusercontent.com/d/<id>`). PDF label/DBR/berita acara di-stream dari data/snapshot.

## 9. Keamanan

- Policy untuk setiap Resource & Action; PIC dibatasi `ruangan_pic`.
- Lookup publik dengan field putih (BR-18); tidak ada endpoint publik yang mengembalikan daftar.
- MFA super-admin & admin-bmn; rate limit; UUID; activitylog dengan `causer` dari auth.
- Tidak ada fallback data tiruan di produksi (R-04 analisis).

## 10. Docker produksi

Mengikuti pola `docker-apps` FKIP: `app` (PHP-FPM 8.4 + ekstensi intl, gd, redis, pdo_mysql, bcmath, pcntl), `nginx`, `queue` (Horizon), `scheduler`, `mysql`, `redis`.

## 11. Integrasi dengan Surat

Master ruangan & gedung dimiliki Aset. Pembagian tanggung jawab:

| Tahap | Aset | Surat |
|---|---|---|
| MVP | Menyediakan `GET /api/v1/ruangan` (ruangan `dapat_dipinjam`) dan `GET /api/v1/ruangan/{kode}/jadwal` (pemakaian tersetujui) | Menampilkan daftar & jadwal ruangan dari API; persetujuan pemakaian ruangan untuk kegiatan ormawa tetap lewat alur disposisi di Surat |
| Lanjutan (F11) | Tabel `pemakaian_ruangan` + endpoint tulis terbatas `POST /api/v1/ruangan/{kode}/pemakaian` (ability `ruangan:pakai`) dengan cek bentrok transaksional | Setelah permohonan disetujui, Surat mencatat pemakaian ke Aset sehingga jadwal satu sumber dan tidak bentrok |
