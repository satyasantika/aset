# Standar Teknis Bersama — Support System FKIP Unsil

> Berkas ini identik di setiap folder sistem (`akreditasi` — folder `lamdik2026`, `alias`, `aset`, `kerjasama`, `keuangan`, `lms`, `puspresma`, `regulasi`, `sdm`, `surat`). Bila diubah, ubah di semua folder agar seluruh sistem tetap seragam.
> Disusun 6 Oktober 2026. Versi paket di bawah adalah versi stabil terbaru saat penyusunan; saat instalasi, biarkan Composer/NPM mengambil rilis stabil terbaru pada major yang sama.

## 1. Tumpukan teknologi (wajib)

| Lapisan | Pilihan | Catatan |
|---|---|---|
| Bahasa | **PHP 8.4** (minimal 8.3) | Laravel 13 mensyaratkan PHP ≥ 8.3 |
| Framework | **Laravel 13.x** (rilis 17 Maret 2026) | Bug fix s.d. Q3 2027, security fix s.d. 17 Maret 2028. Constraint `^13.0` |
| Basis data | **MySQL 8.4 LTS** | `utf8mb4` / `utf8mb4_0900_ai_ci`, engine InnoDB, zona waktu aplikasi `Asia/Jakarta` |
| Cache, sesi, antrean, lock | **Redis 7.x** | Satu instance, DB index dipisah: 0=default, 1=cache, 2=queue (atur via `REDIS_*_DB`) |
| Monitor antrean | **Laravel Horizon** | Dashboard `/horizon`, hanya role `super-admin` |
| Panel back-office | **Filament 5** (di atas Livewire 4) | CRUD, tabel, filter, impor/ekspor bawaan, notifikasi database, MFA |
| Halaman publik / swalayan | Blade + **Livewire 4** + **Tailwind CSS 4** | Dibangun dengan Vite |
| Hak akses | `spatie/laravel-permission` | Role & permission; policy Laravel untuk aturan per-record |
| Jejak audit | `spatie/laravel-activitylog` | Wajib untuk tabel transaksi & status |
| Berkas/dokumen | **Tautan publik (Google Drive, dsb.) — TIDAK ada unggah berkas ke server** | Lihat §1a. Server hanya menyimpan URL + metadata. Penyimpanan fisik (disk/MinIO) disiapkan lewat antarmuka, belum diaktifkan |
| PDF | `barryvdh/laravel-dompdf` | Opsional **Gotenberg** (container) bila butuh tata letak presisi (surat resmi) |
| Excel/CSV | Filament Import/Export Action (berbasis antrean) + `spatie/simple-excel` | Impor besar wajib lewat queue |
| QR code | `endroid/qr-code` | Isi QR = URL verifikasi publik ber-UUID (`id` UUIDv7, §4a), bukan ID berurutan |
| Pencarian | MySQL FULLTEXT (default) | Opsional **Meilisearch** + Laravel Scout untuk pencarian dokumen besar |
| Surel | SMTP kampus; **Mailpit** di lokal | Semua surel lewat antrean (`ShouldQueue`) |
| WhatsApp | Gateway HTTP (mis. Fonnte, sudah dipakai OrmawaHub) | Bungkus sebagai *Notification Channel* kustom, dikirim via antrean |
| Uji | **Pest 4** + plugin Laravel | Feature test per alur bisnis, minimal jalur sukses + gagal otorisasi |
| Kualitas kode | **Laravel Pint** (PSR-12 preset laravel), **Larastan** level 6 | Dijalankan sebelum setiap commit |
| Bantuan AI | **Laravel Boost** (`laravel/boost`) | Memberi agen AI (Claude Code, Cursor, dsb.) MCP server + pedoman versi-spesifik Laravel/Filament/Livewire |

## 1a. Kebijakan berkas: tautan, bukan unggahan (keputusan 6 Oktober 2026)

Karena kapasitas storage server terbatas, **untuk sementara seluruh sistem tidak menerima unggahan berkas**. Setiap kebutuhan "unggah dokumen/foto/bukti" diganti dengan **isian tautan** ke berkas yang disimpan pengguna di Google Drive (akun `@unsil.ac.id`) atau layanan sejenis.

Aturan wajib:

1. **Tabel tautan polimorfik** dipakai semua modul, bukan kolom URL tersebar:
   `tautan_berkas` (`id` UUIDv7, `pemilik_type`, `pemilik_id` UUID (`uuidMorphs`), `jenis` (mis. `sk`, `bukti`, `foto`, `sertifikat`), `label`, `url` VARCHAR(2048), `penyedia` ENUM(`google_drive`,`google_docs`,`onedrive`,`unsil`,`lainnya`), `drive_file_id` nullable, `status_cek` ENUM(`belum`,`dapat_diakses`,`tidak_dapat_diakses`), `dicek_pada` nullable, `ditambahkan_oleh`, timestamps, soft delete). Model memakai relasi `morphMany`.
2. **Validasi** (Form Request/Filament rule kustom `TautanBerkasValid`): hanya `https://`; domain dalam daftar putih di `config/berkas.php` (`drive.google.com`, `docs.google.com`, `*.unsil.ac.id`, opsional `onedrive.live.com`/`*.sharepoint.com`); tolak pemendek URL (bit.ly, s.id) dan tautan folder bila yang diminta satu berkas. Ekstrak `drive_file_id` dari pola URL Drive.
3. **Pemeriksaan keteraksesan** lewat job antrean `PeriksaTautanBerkas` (HTTP HEAD/GET tanpa mengunduh isi, timeout 10 detik) saat disimpan dan ulang terjadwal mingguan; tautan yang mati ditandai dan pemiliknya diberi notifikasi. Status ini informatif — jangan memblokir simpan karena Drive kadang meminta login.
4. **Privasi**: tampilkan petunjuk di formulir — dokumen umum boleh "Siapa saja yang memiliki link"; dokumen berisi data pribadi (SK kepegawaian, ijazah, KTP, nilai) wajib dibagikan **terbatas ke domain unsil.ac.id** atau ke akun tertentu, bukan publik. Sistem tidak pernah menampilkan tautan ke peran yang tidak berhak (otorisasi tetap di Policy).
5. **Tampilan**: tombol "Buka berkas" (`target="_blank" rel="noopener noreferrer"`); pratinjau sematan Drive (`https://drive.google.com/file/d/<id>/preview`) di iframe untuk PDF; foto ditampilkan via `https://lh3.googleusercontent.com/d/<id>` (pola yang sudah dipakai SIMAN). Sediakan placeholder bila gagal dimuat.
6. **Keluaran yang dihasilkan sistem** (PDF surat, ekspor Excel, label QR) **di-stream langsung ke browser**, tidak disimpan permanen. Bila harus lewat antrean, simpan sementara di `storage/app/tmp` dan hapus otomatis ≤ 24 jam (`schedule` harian). QR dibangkitkan on-the-fly.
7. **Siap dialihkan**: seluruh akses berkas lewat satu antarmuka `App\Contracts\PenyimpananBerkas` dengan implementasi `TautanEksternal` (aktif). Kelak bila storage tersedia, cukup menambah implementasi `DiskLokal`/`S3` tanpa mengubah modul. Nama konfigurasi: `BERKAS_MODE=tautan`.
8. **Jejak**: perubahan tautan dicatat activitylog (URL lama → baru), karena isi berkas di Drive bisa berubah di luar sistem; untuk dokumen final (SK, perjanjian, surat terbit) minta pengguna memakai berkas PDF final yang tidak diedit lagi.

### Kenapa Redis (dan layanan tambahan lain)
- **Antrean**: ekspor Excel/PDF, impor data, pemeriksaan tautan berkas, surel & WhatsApp tidak boleh membuat pengguna menunggu.
- **Cache**: master data (prodi, ruangan, kategori, konfigurasi) dibaca sangat sering.
- **Atomic lock**: penomoran surat/dokumen dan peminjaman barang wajib bebas tabrakan (`Cache::lock()`), dikombinasikan dengan transaksi DB + `lockForUpdate()`.
- **Rate limit**: login & endpoint publik (lookup QR, verifikasi surat).
- **Sesi**: siap *scale-out* bila kelak aplikasi dijalankan lebih dari satu container.

Layanan opsional (pakai bila memang dibutuhkan, bukan default): Meilisearch (pencarian), Gotenberg (PDF presisi), Laravel Reverb (notifikasi real-time). MinIO (penyimpanan objek) baru relevan bila kelak kebijakan §1a dicabut.

## 2. Lingkungan pengembangan (Windows)

Pilih salah satu, konsisten dalam satu tim:

**A. Laravel Sail di WSL2 (disarankan, paling mirip server)**
```bash
# di dalam WSL2 Ubuntu, bukan di C:\
composer create-project laravel/laravel <nama-app> "^13.0"
cd <nama-app>
php artisan sail:install --with=mysql,redis,mailpit   # tambah meilisearch/minio bila perlu
./vendor/bin/sail up -d
```

**B. Laravel Herd for Windows + MySQL & Redis via Docker Desktop**
```bash
composer create-project laravel/laravel <nama-app> "^13.0"
docker run -d --name mysql84 -e MYSQL_ROOT_PASSWORD=secret -p 3306:3306 mysql:8.4
docker run -d --name redis7 -p 6379:6379 redis:7-alpine
```

## 3. Konfigurasi dasar `.env`

```dotenv
APP_NAME="<Nama Sistem> FKIP Unsil"
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta          # set juga di config/app.php

DB_CONNECTION=mysql
DB_HOST=127.0.0.1                  # 'mysql' bila memakai Sail
DB_PORT=3306
DB_DATABASE=<nama_db>
DB_USERNAME=<user>
DB_PASSWORD=<rahasia>

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1               # 'redis' bila memakai Sail

FILESYSTEM_DISK=local
MAIL_MAILER=smtp
```

## 4. Konvensi kode

- **Bahasa**: nama tabel/kolom/model memakai istilah domain berbahasa Indonesia (`inventaris`, `peminjaman`, `tanggal_mulai`) agar sesuai dokumen regulasi; kata kerja teknis Laravel tetap bahasa Inggris (`store`, `update`). Konsisten dalam satu proyek.
- **Struktur**: `app/Models`, `app/Enums` (status sebagai PHP Enum yang di-cast), `app/Actions` (satu kelas per aksi bisnis, mis. `SetujuiMutasi`), `app/Policies`, `app/Filament/Resources`, `app/Notifications`, `app/Jobs`.
- **Status alur kerja** selalu PHP Enum + tabel riwayat status (siapa, kapan, dari→ke, catatan). Jangan menyimpan riwayat sebagai JSON di satu kolom.
- **Kunci**: semua primary key memakai **UUIDv7** (`id` CHAR(36)); FK & morph ikut UUID. Tidak ada kolom `ulid` terpisah dan tidak ada `id` auto-increment. Rincian wajib §4a.
- **Uang**: `DECIMAL(15,2)` — jangan float.
- **Tanggal**: kolom `date`/`datetime`; tampilan format Indonesia (`d F Y`) via Carbon locale `id`.
- **Soft delete** untuk master data dan dokumen; transaksi yang sudah final tidak boleh dihapus, hanya dibatalkan dengan status.
- **Berkas**: tidak ada unggahan; gunakan tabel `tautan_berkas` dan aturan §1a. Jangan menambah `<input type="file">` / `FileUpload` Filament kecuali untuk **impor data Excel/CSV** yang diproses langsung lalu dihapus (bukan disimpan).
- **Otorisasi** di Policy, bukan di view. Setiap Resource Filament wajib memakai policy.
- **N+1**: aktifkan `Model::preventLazyLoading(! app()->isProduction())`.
- **Data pribadi** (NIK, HP, alamat) hanya tampil ke peran berwenang; log aktivitas tidak boleh menyimpan kata sandi/token.

## 4a. Kunci primer UUIDv7 (keputusan 6 Oktober 2026)

Semua sistem memakai **UUID versi 7** (berurut waktu, RFC 9562) sebagai primary key. Di Laravel 13 trait `HasUuids` sudah menghasilkan UUIDv7 (`Str::uuid7()`), jadi tidak perlu paket tambahan.

1. **Migrasi**: `$table->uuid('id')->primary();` — jangan `$table->id()`. FK: `$table->foreignUuid('prodi_id')->constrained('prodi')`. Polimorfik: `uuidMorphs('pemilik')` / `nullableUuidMorphs(...)` — jangan `morphs()`. Pivot tanpa entitas sendiri memakai PK komposit dua kolom UUID. Tipe MySQL `CHAR(36)` bawaan Laravel (bukan `BINARY(16)`), collation `utf8mb4_0900_ai_ci` agar sederhana dibaca di alat DB.
2. **Model**: setiap model memakai `use HasUuids;` (langsung di setiap model atau lewat kelas dasar `App\Models\ModelDasar`). Jangan menimpa `newUniqueId()` ke versi lain; jangan memakai `HasVersion4Uuids`/`HasUlids`.
3. **URL & QR**: rute publik/panel memakai `id` langsung (`/verifikasi/{naskah}`, route model binding bawaan, `->whereUuid('naskah')`). Kolom `ulid` tidak dibuat. UUIDv7 memuat stempel waktu pembuatan (milidetik) dan 74 bit acak: tidak dapat ditebak berurutan, tetapi **otorisasi Policy tetap wajib** — UUID bukan pengganti izin.
4. **Urutan**: UUIDv7 kira-kira berurut waktu sehingga indeks InnoDB tetap efisien; namun untuk tampilan urutkan dengan `created_at`, bukan `id`.
5. **Tabel paket** — sesuaikan migrasi yang di-publish **sebelum** `migrate` pertama:
   - `users`: `uuid('id')->primary()`; `sessions.user_id` → `foreignUuid('user_id')->nullable()->index()`.
   - `spatie/laravel-permission`: `roles.id` & `permissions.id` → `uuid`; pivot `model_has_roles`/`model_has_permissions` kolom `model_id` → `uuid`, `role_id`/`permission_id` → `uuid`; `role_has_permissions` → `uuid`. Model kustom `App\Models\Role` & `App\Models\Permission` (extends model spatie + `HasUuids`) didaftarkan di `config/permission.php` (`models.role`, `models.permission`); `column_names.model_morph_key` tetap `model_id`.
   - `spatie/laravel-activitylog`: `id` → `uuid`; `nullableUuidMorphs('subject')`, `nullableUuidMorphs('causer')`; model kustom `App\Models\Aktivitas` (extends `Spatie\Activitylog\Models\Activity` + `HasUuids`) di `config/activitylog.php` `activity_model`.
   - `notifications`: `id` sudah UUID; ganti `morphs('notifiable')` → `uuidMorphs('notifiable')`.
   - Sanctum `personal_access_tokens`: `id` → `uuid`, `uuidMorphs('tokenable')`; model kustom `App\Models\TokenAkses` (extends `Laravel\Sanctum\PersonalAccessToken` + `HasUuids`) via `Sanctum::usePersonalAccessTokenModel()`.
   - Filament Import/Export (`imports`, `exports`, `failed_import_rows`): `user_id` → `foreignUuid`; `id` ke UUID **bila** model kustom didukung versi Filament terpasang (cek via Boost); bila tidak, catat sebagai pengecualian di `docs/KEPUTUSAN.md`.
6. **Pengecualian yang diizinkan** (tabel infrastruktur kerangka kerja, bukan data domain, tidak pernah tampil di URL): `migrations`, `jobs`, `job_batches` (id sudah string), `failed_jobs` (punya kolom `uuid`), `cache`, `cache_locks`, `sessions` (id sudah string), `password_reset_tokens`. Tabel lain **tanpa kecuali** memakai UUIDv7.
7. **Integrasi antarsistem**: rujuk entitas sistem lain dengan **kode alami** (mis. `kode_ruangan`, `nip`) atau UUID-nya sebagai `VARCHAR(36)` tanpa FK lintas basis data.
8. **Uji arsitektur** (Pest, wajib sejak F1): semua kelas di `app/Models` memakai `HasUuids`; tidak ada berkas di `database/migrations` (selain tabel infrastruktur butir 6 dan pengecualian Filament yang tercatat menurut butir 5) yang memuat `->id()`, `foreignId(`, `morphs(` tanpa awalan `uuid`/`nullableUuid`, `bigIncrements(`, atau `increments(`.

## 5. Peran dasar lintas sistem

Setiap sistem minimal memiliki `super-admin` (TI fakultas) dan peran domain sesuai PRD masing-masing. Nama peran memakai kebab-case (`admin-fakultas`, `admin-prodi`, `pimpinan`, `dosen`, `mahasiswa`).

## 6. Kesiapan integrasi (tanpa membangun FKIP Edu)

FKIP Edu (portal & SSO) dikembangkan terpisah. Agar nanti mudah disambungkan:
- Tabel `users` memuat `email` unik (akun `@unsil.ac.id`), `nip`/`nidn` atau `npm` (nullable), `prodi_id` (nullable).
- Autentikasi dibungkus di satu tempat (panel Filament + guard `web`) sehingga kelak dapat diganti/ditambah login OIDC/Socialite tanpa mengubah modul lain.
- Data master lintas sistem (prodi, pegawai, mahasiswa) diberi kolom `kode_eksternal` untuk pemetaan ke sumber data pusat.
- Sediakan endpoint `GET /api/health` dan versi aplikasi di footer.

## 7. Deployment

- Mengikuti pola `docker-apps` (fkipapp, plp): image PHP-FPM 8.4 + Nginx (atau FrankenPHP), container `queue` (`php artisan horizon`), container `scheduler` (`php artisan schedule:work`), MySQL 8.4, Redis 7.
- `php artisan optimize`, `filament:optimize`, `icons:cache` saat build.
- Backup harian MySQL (retensi minimal 30 hari), uji pulihkan tiap semester. Berkas pengguna berada di Google Drive masing-masing unit; sarankan unit memakai Shared Drive fakultas agar berkas tidak hilang saat pegawai pindah/purnatugas.
- HTTPS wajib; cookie `secure`, `SESSION_SECURE_COOKIE=true`.
