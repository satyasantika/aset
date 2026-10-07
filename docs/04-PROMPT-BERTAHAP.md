# 04 — Prompt Bertahap Vibecoding: ASET FKIP 3

> Kerjakan berurutan; satu langkah = satu sesi agen = satu commit yang lolos uji. Rujukan: `00-ANALISIS`, `01-PRD` (BR-xx), `02-ARSITEKTUR`, `03-SKEMA-DATABASE`, `06-REKOMENDASI-REGULASI` (RG-xx), `07-MIGRASI-DATA`, standar bersama. Di repo, semua dokumen disalin ke `docs/`.
> Perintah memakai Laravel Sail (`sail` = `./vendor/bin/sail`); bila memakai Herd, hilangkan awalan `sail`.
> Kalimat penutup baku setiap prompt: *"Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan."*

## Peta fase

| Fase | Isi | Branch | Tag |
|---|---|---|---|
| F0 | Repositori & pagar git | `chore/f0-repo` | — |
| F1 | Fondasi Laravel 13, Sail, MySQL, Redis, Horizon, kualitas, UUIDv7, CI | `feat/f1-fondasi` | `v0.1.0` |
| F2 | Login, peran, PIC ruangan, audit | `feat/f2-auth-peran` | `v0.2.0` |
| F3 | Master: gedung, ruangan, prodi, kodefikasi, pengaturan, tautan berkas | `feat/f3-master` | `v0.3.0` |
| F4 | Aset per kode+NUP, kondisi, label QR, lookup publik | `feat/f4-aset-label` | `v0.4.0` |
| F5 | Mutasi lokasi | `feat/f5-mutasi` | `v0.5.0` |
| F6 | Peminjaman internal | `feat/f6-peminjaman` | `v0.6.0` |
| F7 | Migrasi data SIMAN-FKIP-2 | `feat/f7-migrasi` | `v0.7.0` |
| F8 | Pemeliharaan & lapor kerusakan | `feat/f8-pemeliharaan` | `v0.8.0` |
| F9 | DBR/DBL & inventarisasi | `feat/f9-dbr-inventarisasi` | `v0.9.0` |
| F10 | Penghapusan, laporan, dasbor, ekspor | `feat/f10-laporan` | `v0.10.0` |
| F11 | Notifikasi, penjadwal, API untuk Surat | `feat/f11-integrasi` | `v0.11.0` |
| F12 | Pengerasan, produksi, cutover | `feat/f12-rilis` | `v1.0.0` |

---

## F0 — Repositori & pagar git

### F0.1 — Inisialisasi repositori
**Branch:** langsung di `main` (repo kosong)
**Tujuan:** Repo baru `siman-fkip` dengan hook, gitignore, dokumen.

**Prompt (salin ke agen AI):**
```text
Repo masih kosong. 1) Buat .gitignore (Laravel standar + /storage/app/tmp/*, *.sql, *.sql.gz, *.zip,
*.xlsx di root, .env*). 2) Buat .githooks/commit-msg dan .githooks/pre-commit PERSIS STANDAR-GIT §6 (jangan
aktifkan core.hooksPath dulu). 3) Salin dokumen paket ke docs/ (01–07, STANDAR-*), CLAUDE.md ke root.
4) README.md singkat: tujuan sistem, tautan ke docs. Jangan membuat kode aplikasi. Tampilkan ringkasan.
```

**Selesai bila:** `git status` hanya berisi berkas di atas.

**Commit:**
```bash
git add -A
git commit -m "chore: inisialisasi repositori aset fkip"
```

### F0.2 — Templat PR & CHANGELOG
**Prompt (salin ke agen AI):**
```text
Buat CHANGELOG.md (Keep a Changelog, Indonesia) dan .github/pull_request_template.md (Ringkasan, Langkah,
BR/RG yang disentuh, Cara uji, Checklist pint/phpstan/test/tanpa rahasia). Tampilkan ringkasan.
```

**Commit:**
```bash
git add -A
git commit -m "docs: tambah changelog dan templat pull request"
```

---

## F1 — Fondasi

### F1.1 — Laravel 13 + Sail (MySQL, Redis, Mailpit)
**Branch:** `feat/f1-fondasi`

Sebelum prompt: `git switch -c feat/f1-fondasi`.

**Prompt (salin ke agen AI):**
```text
Pasang Laravel 13 di repo ini (composer create-project laravel/laravel ke folder sementara lalu pindahkan,
tanpa menimpa docs/, CLAUDE.md, .githooks/). php artisan sail:install --with=mysql,redis,mailpit.
config/app.php timezone Asia/Jakarta, locale id, faker id_ID. .env.example: DB_DATABASE=siman,
CACHE_STORE=redis, SESSION_DRIVER=redis, QUEUE_CONNECTION=redis, REDIS_CACHE_DB=1, REDIS_QUEUE_DB=2,
CACHE_PREFIX=siman_. AppServiceProvider: Carbon locale id, preventLazyLoading & preventSilentlyDiscardingAttributes
di non-produksi, Date::use(CarbonImmutable). Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai
jalankan sail up -d, sail artisan migrate, lalu tampilkan ringkasan perubahan.
```

**Selesai bila:** `sail artisan about` menunjukkan Laravel 13, MySQL & Redis terhubung.

**Commit:**
```bash
git add -A
git commit -m "build: pasang laravel 13 dengan sail, mysql, redis, dan mailpit"
```

### F1.2 — Gerbang kualitas & aktivasi hook
**Prompt (salin ke agen AI):**
```text
Pasang pestphp/pest ^4 + pest-plugin-laravel, larastan/larastan (level 6), pint preset laravel. phpunit.xml
memakai MySQL database siman_testing (buat di docker init), CACHE_STORE=array, QUEUE_CONNECTION=sync,
SESSION_DRIVER=array. Script composer "cek": pint --test, phpstan, pest. Satu test asap: GET /up 200 dan
locale 'id'. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu
tampilkan ringkasan perubahan.
```

Setelah selesai: `git config core.hooksPath .githooks`.

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "chore(config): pasang pest, larastan, pint, dan aktifkan hook"
```

### F1.3 — Filament 5, Horizon, activitylog, permission, Sanctum
**Prompt (salin ke agen AI):**
```text
composer require filament/filament:^5.0 laravel/horizon spatie/laravel-permission spatie/laravel-activitylog
laravel/sanctum barryvdh/laravel-dompdf endroid/qr-code spatie/simple-excel. Panel Filament id 'admin' path
/admin (tanpa registrasi, warna primer biru, databaseNotifications). horizon:install dengan supervisor untuk
antrean impor, ekspor, notifikasi, tautan, default. Publish & migrate permission + activitylog (retensi 730 hari).
install:api (Sanctum). Gate viewHorizon sementara via env HORIZON_EMAILS. Jangan ubah berkas di luar cakupan
langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "build(deps): pasang filament 5, horizon, permission, activitylog, dan sanctum"
```

### F1.4 — Kunci primer UUIDv7
**Tujuan:** Semua tabel aplikasi memakai UUIDv7 sebagai primary key sejak awal (STANDAR-TEKNIS §4a), sebelum tabel domain dibuat.

**Prompt (salin ke agen AI):**
```text
Baca STANDAR-TEKNIS §4a. 1) Migrasi bawaan: users → $table->uuid('id')->primary(); sessions →
foreignUuid('user_id')->nullable()->index(). Model User memakai Illuminate\Database\Eloquent\Concerns\HasUuids
(Laravel 13: UUIDv7). 2) Untuk paket yang SUDAH terpasang (spatie/laravel-permission, spatie/laravel-activitylog,
tabel notifications, Sanctum personal_access_tokens, tabel impor/ekspor Filament) sesuaikan migrasi & model
kustomnya PERSIS §4a butir 5; paket yang dipasang di langkah berikutnya disesuaikan pada langkah pemasangannya.
3) tests/Arch/UuidTest.php: semua kelas di app/Models memakai HasUuids; tidak ada migrasi (kecuali tabel
infrastruktur §4a butir 6) yang memuat ->id(), foreignId(, morphs( tanpa awalan uuid/nullableUuid, bigIncrements(,
atau increments(. 4) UserFactory & seeder tetap berjalan. 5) php artisan migrate:fresh (belum ada data).
Test: User::factory()->create()->id lolos Str::isUuid() dan berversi 7 (karakter ke-15 = '7'); route model
binding dengan UUID berjalan. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan
test, lalu tampilkan ringkasan perubahan.
```

**Selesai bila:** `UuidTest` hijau; kolom `users.id` bertipe CHAR(36); tidak ada `->id()` di migrasi aplikasi.

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "refactor(db): pakai uuidv7 sebagai primary key semua tabel"
```

### F1.5 — Laravel Boost & endpoint kesehatan
**Prompt (salin ke agen AI):**
```text
composer require laravel/boost --dev; php artisan boost:install. GET /api/health → {app, versi, db, redis}
200/503; versi aplikasi (env APP_VERSION) di footer panel. Test health. Jangan ubah berkas di luar cakupan
langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "chore: pasang laravel boost dan endpoint kesehatan"
```

### F1.6 — CI
**Prompt (salin ke agen AI):**
```text
.github/workflows/ci.yml: service mysql:8.4 & redis:7, PHP 8.4 (intl, gd, redis, bcmath, pcntl), composer
install, npm ci && npm run build, pint --test, phpstan, pest --parallel. Tampilkan ringkasan.
```

**Commit:**
```bash
git add -A
git commit -m "ci: tambah workflow pint, larastan, dan pest"
```

### F1.7 — Tutup fase F1
```bash
# CHANGELOG [0.1.0], APP_VERSION=0.1.0
git add -A && git commit -m "docs(changelog): catat rilis fase 1 fondasi"
git push -u origin feat/f1-fondasi   # PR → merge
git switch main && git pull && git tag -a v0.1.0 -m "Fase 1: fondasi" && git push origin --tags
```

---

## F2 — Login, peran, PIC ruangan, audit

### F2.1 — Perluasan users, peran & permission
**Branch:** `feat/f2-auth-peran`

**Prompt (salin ke agen AI):**
```text
Baca 01-PRD §3 & §3.1 dan 03-SKEMA §8. 1) Migrasi users: nip, no_hp, aktif. User implements FilamentUser
(canAccessPanel: aktif && punya peran selain civitas, ATAU civitas hanya ke halaman Livewire). Surel wajib
domain unsil.ac.id (rule SurelDomainUnsil). 2) PeranDanIzinSeeder idempoten: peran super-admin, admin-bmn,
pejabat-penatausahaan, pic-ruangan, pimpinan, civitas dan permission 03 §8, dipetakan sesuai matriks PRD §3.1.
Gate::before super-admin; viewHorizon → super-admin. 3) Test matriks permission per peran (dataset).
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(peran): tambah peran bmn dan matriks permission"
```

### F2.2 — Login aman: MFA admin, penguncian, reset
**Prompt (salin ke agen AI):**
```text
Panel /admin: login, reset kata sandi, profil, TANPA registrasi; MFA wajib super-admin & admin-bmn (middleware
WajibMfaAdmin; cek API MFA Filament 5 via Boost). Rate limit login 5/menit per email+IP; akun aktif=false
ditolak. Ganti kata sandi → logoutOtherDevices. Test: /admin/register 404; admin tanpa MFA diarahkan ke
penyiapan MFA; 6 percobaan gagal → 429; akun nonaktif ditolak. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(auth): tambah login aman dengan mfa admin dan pembatasan"
```

### F2.3 — Jejak audit server-side
**Prompt (salin ke agen AI):**
```text
Baca BR-21 dan temuan R-03 di 00-ANALISIS. Trait TercatatAktivitas (LogsActivity, logOnlyDirty, tanpa kata
sandi). Pastikan causer selalu auth()->user() dan tidak pernah diambil dari input. LogAktivitasResource
read-only (admin-bmn, super-admin) dengan filter modul, pelaku, tanggal. Test: perubahan tercatat dengan causer
benar; input field "pengguna" palsu diabaikan. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai
jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(audit): tambah jejak audit dengan pelaku dari autentikasi"
```

### F2.4 — Kelola pengguna
**Prompt (salin ke agen AI):**
```text
PenggunaResource (pengguna.kelola): buat akun (surel atur kata sandi), peran, aktif/nonaktif, NIP, HP. Hanya
super-admin memberi peran super-admin/admin-bmn. Test otorisasi. (Penugasan PIC ruangan dibuat di F3.2.)
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(auth): tambah kelola pengguna dan peran"
```

### F2.5 — Tutup fase F2
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 2 autentikasi dan peran"
git push -u origin feat/f2-auth-peran   # PR → merge
git switch main && git pull && git tag -a v0.2.0 -m "Fase 2: autentikasi dan peran" && git push origin --tags
```

---

## F3 — Master data

### F3.1 — Gedung, kategori ruangan, prodi
**Branch:** `feat/f3-master`

**Prompt (salin ke agen AI):**
```text
Baca 03-SKEMA §2. Migrasi, model (SoftDeletes, TercatatAktivitas), Resource Filament (master.kelola) untuk
gedung, kategori_ruangan (flag laboratorium/ruang kelas), prodi (kode, nama, kode_eksternal); seeder contoh
dari CSV. Cache 'aset:master:*' dibersihkan observer. Test CRUD & otorisasi. Jangan ubah berkas di luar
cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(master): tambah gedung, kategori ruangan, dan prodi"
```

### F3.2 — Ruangan dan penugasan PIC
**Prompt (salin ke agen AI):**
```text
Baca 03-SKEMA §2 (ruangan, ruangan_pic, prodi_ruangan) dan BR-05. Migrasi & model Ruangan (id UUIDv7, kode unik,
dapat_dipinjam, luas_m2, k3l JSON). RuanganResource dengan PicRelationManager (pilih user ber-peran pic-ruangan;
satu PIC utama). Helper User::ruanganDikelola() dan scope Ruangan::dikelolaOleh(User). Test: PIC hanya melihat
ruangannya di scope; kode unik. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint
dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(master): tambah ruangan dan penugasan pic"
```

### F3.3 — Kodefikasi barang BMN
**Prompt (salin ke agen AI):**
```text
Baca RG-06 dan 03-SKEMA kodefikasi_barang. Migrasi, model, KodefikasiResource, Filament Importer CSV
(kode, uraian, tingkat, induk_kode, kategori_lokal) via antrean impor (berkas di storage/app/tmp, dihapus).
Rule KodeBarangValid (ada di kodefikasi, tingkat 5). Seeder contoh beberapa kode (tandai "CONTOH — impor
referensi resmi", sumber perlu verifikasi). Test impor & rule. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(master): tambah kodefikasi barang bmn dengan impor csv"
```

### F3.4 — Pengaturan & tautan berkas
**Prompt (salin ke agen AI):**
```text
Baca 03-SKEMA §6 dan STANDAR-TEKNIS §1a. 1) Tabel pengaturan + App\Support\Pengaturan (cache), seeder identitas
& penandatangan & toggle fitur (BR-22) & ambang/retensi; halaman PengaturanSistem (super-admin). 2) Fondasi
tautan berkas PERSIS §1a: config/berkas.php, tabel tautan_berkas polimorfik, rule TautanBerkasValid, kontrak
PenyimpananBerkas + TautanEksternal, job PeriksaTautanBerkas (antrean tautan, anti-SSRF), komponen
x-tautan-berkas (foto Drive via lh3.googleusercontent.com/d/<id>). Test validasi tautan, toggle fitur dibaca
dari pengaturan, tidak ada FileUpload selain Importer (test arsitektur). Jangan ubah berkas di luar cakupan
langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(pengaturan): tambah pengaturan sistem dan tautan berkas"
```

### F3.5 — Tutup fase F3
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 3 master data"
git push -u origin feat/f3-master   # PR → merge
git switch main && git pull && git tag -a v0.3.0 -m "Fase 3: master data" && git push origin --tags
```

---

## F4 — Aset, kondisi, label QR, lookup publik

### F4.1 — Tabel aset & riwayat
**Branch:** `feat/f4-aset-label`

**Prompt (salin ke agen AI):**
```text
Baca 03-SKEMA §3 dan BR-01–BR-04, BR-19. Migrasi aset (semua kolom, indeks, CHECK constraint), riwayat_kondisi_aset,
riwayat_lokasi_aset, riwayat_status_aset, label_lama. Enum KondisiAset, StatusAset, StatusBmn, SumberPerolehan.
Model Aset (HasUuids, TercatatAktivitas, casts decimal:2) dengan accessor sedangDipinjam (dihitung, BR-04),
scope diRuangan, dapatDipinjam (BR-11). AsetPolicy (PIC dibatasi ruangan_pic, BR-05). Test: kode_barang+nup unik;
status dihapus tanpa SK ditolak; PIC ruangan lain 403. Jangan ubah berkas di luar cakupan langkah ini. Setelah
selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(aset): tambah tabel aset per kode dan nup beserta riwayat"
```

### F4.2 — AsetResource & pendaftaran massal
**Prompt (salin ke agen AI):**
```text
Baca US-AST-01, US-AST-02, RG-10. Actions DaftarkanAset, DaftarkanAsetMassal (jumlah n → n baris, NUP awal
+ i, kelompok_pengadaan sama; satu transaksi), UbahDataAset. AsetResource: daftar (filter ruangan, kategori,
kondisi, status, status_bmn, label_perlu_cetak_ulang; pencarian nama/merk/kode/NUP), formulir (kodefikasi
searchable, NUP, nilai & tanggal perolehan, sumber, dokumen perolehan sebagai tautan, foto sebagai tautan),
aksi "Daftarkan massal". Toggle fitur tambah aset (BR-22) diperiksa di Action. Test: daftar 20 kursi → 20 baris
NUP berurutan; toggle off → ditolak; nilai disimpan DECIMAL. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(aset): tambah resource aset dan pendaftaran massal"
```

### F4.3 — Ubah kondisi & status
**Prompt (salin ke agen AI):**
```text
Baca BR-03, BR-04, BR-05 dan diagram status aset PRD §7. Actions UbahKondisiAset (riwayat, sumber manual) dan
UbahStatusAset (transisi sah saja). Aksi Filament & aksi massal (PIC: ruangannya). Relation manager riwayat
kondisi/lokasi/status. Test transisi sah/tidak sah dan otorisasi PIC. Jangan ubah berkas di luar cakupan
langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(aset): tambah perubahan kondisi dan status beserta riwayat"
```

### F4.4 — Label QR (PDF stream)
**Prompt (salin ke agen AI):**
```text
Baca BR-17, temuan R-16/R-17. LabelPdfController: pilih ruangan + aset (atau "belum dicetak"/"perlu cetak
ulang"), render label A4 multi-stiker via dompdf; QR dibangkitkan endroid/qr-code di server berisi
url('/a/'.id) — TIDAK memakai layanan QR pihak ketiga; teks: "INVENTARIS FKIP UNIVERSITAS SILIWANGI", nama,
merk, kode barang + NUP (atau kode internal), tahun, kode ruangan. Di-stream, tidak disimpan. Action
TandaiLabelDicetak mengisi dicetak_pada & mengosongkan label_perlu_cetak_ulang. Test: PDF content-type,
QR mengandung URL UUID, PIC hanya ruangannya. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai
jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(label): tambah cetak label qr berbasis id di server"
```

### F4.5 — Lookup publik & resolusi label lama
**Prompt (salin ke agen AI):**
```text
Baca BR-17, BR-18, 02-ARSITEKTUR §5. 1) GET /a/{aset} → halaman publik ringan (Blade) dengan field putih
BR-18 saja + tombol "Laporkan kerusakan" (rute dibuat F8.2); throttle lookup 30/menit/IP; noindex.
2) App\Support\FormatLabelLama::kandidat(string): urutan kandidat PERSIS handleLookupBarangPublik SIMAN-2
(KAT-KODE-UNIT, KODE-UNIT, KODE, teks utuh), normalisasi uppercase. GET /l/{kode} mencari label_lama → 302 ke
/a/{id}; tidak ditemukan → 404 ramah. 3) Halaman Livewire /pindai (login): kamera html5-qrcode, menerima URL
baru atau teks lama, menampilkan aset & aksi sesuai peran (ubah kondisi, pinjam, mutasi). Test: label lama
'MBL-935464-1' terresolusi via label_lama; lookup tidak membocorkan nilai/PIC/peminjam; 31 permintaan/menit → 429.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(publik): tambah lookup qr publik dan resolusi label lama"
```

### F4.6 — Tutup fase F4
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 4 aset dan label"
git push -u origin feat/f4-aset-label   # PR → merge
git switch main && git pull && git tag -a v0.4.0 -m "Fase 4: aset dan label" && git push origin --tags
```

---

## F5 — Mutasi lokasi

### F5.1 — Pengajuan & persetujuan mutasi
**Branch:** `feat/f5-mutasi`

**Prompt (salin ke agen AI):**
```text
Baca BR-06 dan US-MUT-01. Migrasi mutasi & mutasi_item; enum StatusMutasi; nomor MUT-{tahun}-{4 digit} via
Cache::lock. Actions AjukanMutasi (PIC ruangan asal; aset tidak sedang dipinjam/dalam perbaikan; toggle mutasi),
SetujuiMutasi (transaksi + lockForUpdate setiap aset: ubah ruangan_id, riwayat_lokasi_aset, tandai DBR asal &
tujuan perlu_diperbarui — panggil stub yang diisi F9), TolakMutasi (catatan wajib). MutasiResource + aksi dari
halaman aset & /pindai. Identitas aset TIDAK pernah dinomori ulang (R-17). Test: alur penuh; dua admin menyetujui
bersamaan → satu kali; aset dipinjam ditolak; PIC lain 403. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(mutasi): tambah pengajuan dan persetujuan mutasi lokasi"
```

### F5.2 — Tutup fase F5
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 5 mutasi"
git push -u origin feat/f5-mutasi   # PR → merge
git switch main && git pull && git tag -a v0.5.0 -m "Fase 5: mutasi" && git push origin --tags
```

---

## F6 — Peminjaman internal

### F6.1 — Skema & cek ketersediaan
**Branch:** `feat/f6-peminjaman`

**Prompt (salin ke agen AI):**
```text
Baca BR-07–BR-11 dan 03-SKEMA §4 peminjaman. Migrasi peminjaman & peminjaman_item; enum StatusPeminjaman,
JenisPeminjam; nomor PJM-{tahun}-{5 digit}. Action CekKetersediaan(aset, mulai, selesai): tolak bila status/kondisi
BR-11 atau ada peminjaman aktif/tersetujui tumpang tindih. Test rentang tumpang tindih (batas tepat
bersinggungan diperbolehkan). Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan
test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(peminjaman): tambah skema peminjaman dan cek ketersediaan"
```

### F6.2 — Keranjang PIC (catat langsung)
**Prompt (salin ke agen AI):**
```text
Baca US-PJM-01, BR-08, BR-09. Livewire /keranjang: tambah aset via pindai/cari (hanya ruangan PIC), data
peminjam (pilih user civitas atau nama+unit+kontak), keperluan, rencana kembali (≤ pengaturan maks_hari_pinjam).
Action CatatPeminjamanLangsung: transaksi, lockForUpdate + Cache::lock per aset, semua-atau-tidak, status dipinjam,
kondisi_saat_pinjam. Test: dua PIC meminjamkan aset sama bersamaan → satu berhasil; satu aset tak tersedia →
seluruh keranjang gagal. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test,
lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(peminjaman): tambah keranjang peminjaman pic dengan penguncian"
```

### F6.3 — Pengajuan online civitas & persetujuan
**Prompt (salin ke agen AI):**
```text
Baca US-PJM-02, BR-07, BR-09. Livewire /pinjam: katalog aset & ruangan dapat_dipinjam (tanpa data pribadi),
pilih rentang, ajukan (civitas). Pihak luar tidak tersedia sebagai pilihan civitas; admin dapat mencatat
permohonan pihak luar yang diteruskan ke pejabat-penatausahaan (BR-07). Actions AjukanPeminjaman, SetujuiPeminjaman
(PIC ruangan asal/admin; cek ketersediaan ulang dalam lock), TolakPeminjaman, BatalkanPeminjaman,
SerahkanPeminjaman. Halaman "Pinjaman saya". Test alur & otorisasi & bentrok saat persetujuan. Jangan ubah
berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(peminjaman): tambah pengajuan online civitas dan persetujuan"
```

### F6.4 — Pengembalian & keterlambatan
**Prompt (salin ke agen AI):**
```text
Baca BR-09, BR-10, US-PJM-03. Action KembalikanPeminjaman (kondisi_saat_kembali wajib per item; bila berubah →
UbahKondisiAset sumber peminjaman; RR/RB → tiket pemeliharaan (stub, diisi F8)). PeminjamanResource dengan tab
Aktif, Terlambat (dihitung), Diajukan, Riwayat; data pribadi hanya untuk yang berhak (BR-23). Test pengembalian
sebagian ditolak (semua item sekaligus atau per item — pilih per item dan uji), kondisi berubah tercatat.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(peminjaman): tambah pengembalian dan daftar keterlambatan"
```

### F6.5 — Tutup fase F6
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 6 peminjaman"
git push -u origin feat/f6-peminjaman   # PR → merge
git switch main && git pull && git tag -a v0.6.0 -m "Fase 6: peminjaman" && git push origin --tags
```

---

## F7 — Migrasi data SIMAN-FKIP-2

### F7.1 — Perintah impor (dry-run) & pemetaan
**Branch:** `feat/f7-migrasi`

**Prompt (salin ke agen AI):**
```text
Baca docs/07-MIGRASI-DATA.md seluruhnya. Buat tabel impor_siman2_log dan perintah
`siman2:impor {berkas} {--dry-run} {--nup-berurutan}` membaca XLSX (spatie/simple-excel) sheet config, kategori,
ruanganKategori, ruangan, users, inventaris, mutasi, peminjaman, laporan. Tahap ini: pengaturan, kategori
ruangan, ruangan (+PIC via tabel pemetaan username→email di CSV docs/migrasi/pemetaan-pengguna.csv), pengguna
(tanpa kata sandi; kirim surel atur kata sandi hanya bila bukan dry-run). Dry-run menulis laporan galat ke
layar & log tanpa menulis data. Idempoten berdasarkan id lama. Test dengan XLSX fixture kecil buatan test.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(migrasi): tambah impor siman2 untuk pengaturan, ruangan, dan pengguna"
```

### F7.2 — Impor inventaris: pemecahan unit, label lama, foto
**Prompt (salin ke agen AI):**
```text
Baca 07-MIGRASI §4 dan §4a. Lanjutkan siman2:impor untuk inventaris: baris jumlah n → n aset; kondisi dari
unitKondisi; dicetak_pada dari printedUnits; foto → tautan_berkas; label_lama untuk KODEKATEGORI-KODE-i,
KODE-i, dan KODE/NUP/KODEBMN (i=1, bila unik). Deteksi aset terdampak mutasi unit (sheet mutasi tipeMutasi=unit
Disetujui; baris berketerangan "Hasil mutasi 1 unit") → label_perlu_cetak_ulang=true dan JANGAN buat label_lama
ambigu. Riwayat kondisi sumber migrasi. Test fixture: baris jumlah 3 → 3 aset; label 'MBL-935464-2' → unit 2;
induk terdampak mutasi → ditandai cetak ulang. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai
jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(migrasi): tambah impor inventaris dengan pemecahan unit dan label lama"
```

### F7.3 — Impor mutasi, peminjaman, log & verifikasi
**Prompt (salin ke agen AI):**
```text
Baca 07-MIGRASI §3, §6. Lanjutkan impor mutasi (ruangan by nama), peminjaman (dikelompokkan per kodeTransaksi,
kodeUnit → label_lama), laporan → activity_log log_name siman2 dengan properti pelaku_lama. Perintah
`siman2:verifikasi` mencetak tabel rekonsiliasi §6 (Σ jumlah vs aset, per ruangan, per kondisi, pinjaman aktif).
Halaman Filament "Label perlu cetak ulang" per ruangan. Test fixture end-to-end. Jangan ubah berkas di luar
cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(migrasi): tambah impor riwayat dan verifikasi rekonsiliasi"
```

### F7.4 — Uji dengan data nyata & tutup fase
Langkah manusia: ekspor XLSX SIMAN-2 (07-MIGRASI §2), jalankan `sail artisan siman2:impor storage/app/tmp/siman2.xlsx --dry-run`, perbaiki pemetaan, impor ke database **staging**, jalankan `siman2:verifikasi`, catat hasil di `docs/migrasi/HASIL-UJI-MIGRASI.md` (tanpa data pribadi).

```bash
git add docs/migrasi/HASIL-UJI-MIGRASI.md CHANGELOG.md
git commit -m "docs(migrasi): catat hasil uji migrasi data siman2"
git push -u origin feat/f7-migrasi   # PR → merge
git switch main && git pull && git tag -a v0.7.0 -m "Fase 7: migrasi data" && git push origin --tags
```

---

## F8 — Pemeliharaan & lapor kerusakan

### F8.1 — Tiket pemeliharaan
**Branch:** `feat/f8-pemeliharaan`

**Prompt (salin ke agen AI):**
```text
Baca RG-08 dan 03-SKEMA tiket_pemeliharaan. Migrasi, enum StatusTiket, nomor TKT-{tahun}-{4 digit}. Actions
BukaTiket (status aset dalam_perbaikan bila dipilih), SelesaikanTiket (tindakan, biaya DECIMAL, kondisi akhir →
UbahKondisiAset, status aset kembali aktif). TiketPemeliharaanResource (PIC: ruangannya). Isi stub F6.4.
Test alur & biaya & aset dalam perbaikan tidak dapat dipinjam. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(pemeliharaan): tambah tiket pemeliharaan dan perbaikan aset"
```

### F8.2 — Lapor kerusakan publik & civitas
**Prompt (salin ke agen AI):**
```text
Baca BR-12, US-PML-01. GET/POST /lapor-kerusakan/{aset}: deskripsi wajib, nama & kontak opsional,
honeypot, throttle lapor 5/jam/IP, ip_hash (HMAC dengan garam harian di Redis, tanpa IP mentah). Civitas login
melapor dari /pindai. Action TerimaLaporanKerusakan → tiket baru → notifikasi PIC (event; notifikasi F11).
Test: tanpa IP mentah tersimpan; 6 laporan/jam → 429; honeypot diabaikan. Jangan ubah berkas di luar cakupan
langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(publik): tambah lapor kerusakan dari qr"
```

### F8.3 — Tutup fase F8
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 8 pemeliharaan"
git push -u origin feat/f8-pemeliharaan   # PR → merge
git switch main && git pull && git tag -a v0.8.0 -m "Fase 8: pemeliharaan" && git push origin --tags
```

---

## F9 — DBR/DBL & inventarisasi

### F9.1 — DBR/DBL dengan snapshot
**Branch:** `feat/f9-dbr-inventarisasi`

**Prompt (salin ke agen AI):**
```text
Baca RG-01, BR-13, US-DBR-01, diagram DBR PRD §7. Migrasi dbr_versi; enum StatusDbr. Actions BangkitkanDbr
(ruangan atau DBL untuk aset lokasi_lainnya), SetujuiDbrOlehPic, SahkanDbr (pejabat-penatausahaan; menyimpan
snapshot JSON lengkap + penandatangan + waktu), TandaiDbrPerluDiperbarui (dipanggil SetujuiMutasi, Daftarkan/
UbahStatus aset). DbrPdfController merender PDF dari SNAPSHOT (bukan data kini) — kop dari pengaturan, kolom
No, Kode Barang, NUP, Nama, Merk/Tipe, Tahun, Kondisi, Keterangan; tanda tangan PIC & pejabat (nama/NIP dari
snapshot). DbrResource. Test: PDF versi lama tetap sama setelah aset berpindah; mutasi menandai DBR
perlu_diperbarui. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu
tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(dbr): tambah daftar barang ruangan bertanda tangan berbasis snapshot"
```

### F9.2 — Periode inventarisasi & penugasan
**Prompt (salin ke agen AI):**
```text
Baca RG-04, BR-14, US-INV-01. Migrasi periode_inventarisasi, inventarisasi_ruangan, hasil_inventarisasi; enum.
Actions BukaPeriode (hanya satu berjalan; Cache::lock), penugasan petugas per ruangan. Selama berjalan,
AjukanMutasi untuk ruangan yang sedang diinventarisasi memberi peringatan/ditahan (BR-14). Test satu periode
berjalan saja. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu
tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(inventarisasi): tambah periode inventarisasi dan penugasan ruangan"
```

### F9.3 — Pemindaian inventarisasi di ponsel
**Prompt (salin ke agen AI):**
```text
Baca BR-14. Livewire /inventarisasi/{periode}/{ruangan}: daftar aset ruangan, pindai QR (URL baru/label lama) →
tandai ditemukan (kondisi dapat dikoreksi → kondisi_berubah), tombol "Temuan berlebih" (deskripsi + foto tautan),
progres %, selesai ruangan → sisa otomatis tidak_ditemukan. Aset label_perlu_cetak_ulang ditampilkan dengan
pengingat cetak ulang. Test: pindai aset ruangan lain → peringatan; selesai menghasilkan tidak_ditemukan.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(inventarisasi): tambah pemindaian inventarisasi per ruangan"
```

### F9.4 — Penutupan & berita acara
**Prompt (salin ke agen AI):**
```text
Baca BR-14, BR-15, LAP-05. Actions TutupPeriode (rekap per ruangan, snapshot berita acara), SahkanBeritaAcara
(pejabat). PDF berita acara dari snapshot + Excel selisih. Aset tidak_ditemukan → daftar verifikasi admin →
UbahStatusAset hilang (dengan catatan). Kondisi_berubah diterapkan ke aset (riwayat sumber inventarisasi).
Widget peringatan inventarisasi terakhir > ambang tahun (BR-15). Test end-to-end kecil. Jangan ubah berkas di
luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(inventarisasi): tambah penutupan periode dan berita acara"
```

### F9.5 — Tutup fase F9
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 9 dbr dan inventarisasi"
git push -u origin feat/f9-dbr-inventarisasi   # PR → merge
git switch main && git pull && git tag -a v0.9.0 -m "Fase 9: DBR dan inventarisasi" && git push origin --tags
```

---

## F10 — Penghapusan, laporan, dasbor

### F10.1 — Usulan penghapusan
**Branch:** `feat/f10-laporan`

**Prompt (salin ke agen AI):**
```text
Baca RG-03, RG-09, BR-16, US-HPS-01. Migrasi usulan_penghapusan & item. Actions BuatUsulanPenghapusan (hanya
RB/hilang), SetujuiUsulan (pejabat → status aset diusulkan_hapus), CatatSkPenghapusan (nomor, tanggal, tautan
SK → aset dihapus). Test aset Baik tidak bisa diusulkan; dihapus wajib SK. Jangan ubah berkas di luar cakupan
langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(penghapusan): tambah usulan penghapusan dan pencatatan sk"
```

### F10.2 — Dasbor
**Prompt (salin ke agen AI):**
```text
Baca LAP-02, US-LAP-01. Widget: jumlah & nilai aset per ruangan/kategori/kondisi, pinjaman aktif & terlambat,
tiket terbuka, DBR perlu diperbarui, peringatan inventarisasi. Cache Redis 30 menit dibersihkan saat data berubah.
PIC melihat ruangannya; pimpinan semua (tanpa data pribadi). Test angka & cakupan. Jangan ubah berkas di luar
cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(laporan): tambah dasbor aset sesuai peran"
```

### F10.3 — Laporan & ekspor
**Prompt (salin ke agen AI):**
```text
Baca PRD §9 (LAP-02–LAP-09) dan RG-05, RG-11. Exporter (antrean ekspor, disk tmp ≤ 24 jam): rekap aset,
daftar RB & hilang, riwayat peminjaman, rekonsiliasi semesteran (kode+NUP, lokasi, kondisi, nilai), DKPS LAMDIK
sarana laboratorium & pembelajaran / prasarana / TIK per prodi (urutan kolom persis perlu verifikasi dari
templat DKPS; dokumentasikan pemetaan di docs/FORMAT-DKPS.md), rekap pemeliharaan, log. Pimpinan tanpa data
pribadi. Test cakupan & isi kolom. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan
pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(laporan): tambah laporan bmn, rekonsiliasi, dan ekspor dkps"
```

### F10.4 — Tutup fase F10
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 10 laporan"
git push -u origin feat/f10-laporan   # PR → merge
git switch main && git pull && git tag -a v0.10.0 -m "Fase 10: penghapusan dan laporan" && git push origin --tags
```

---

## F11 — Notifikasi, penjadwal, API untuk Surat

### F11.1 — Notifikasi
**Branch:** `feat/f11-integrasi`

**Prompt (salin ke agen AI):**
```text
Baca 02-ARSITEKTUR §7. Buat semua notifikasi (ShouldQueue, antrean notifikasi, mail + database; WhatsApp
opsional via channel kustom & env WHATSAPP_ENABLED). Ganti event stub fase sebelumnya. Test penerima benar.
Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan
ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(notifikasi): tambah notifikasi peminjaman, mutasi, dan pemeliharaan"
```

### F11.2 — Penjadwal
**Prompt (salin ke agen AI):**
```text
Baca 02-ARSITEKTUR §6. Perintah & jadwal: pengingat terlambat, pengingat pengambilan, tandai DBR usang,
periksa tautan, pengingat inventarisasi, pangkas peminjaman (BR-23 anonimkan data pribadi lama), bersihkan tmp;
withoutOverlapping & onOneServer. Test jadwal & retensi. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(penjadwal): tambah tugas terjadwal pengingat dan retensi"
```

### F11.3 — API baca ruangan & jadwal (untuk Surat)
**Prompt (salin ke agen AI):**
```text
Baca 02-ARSITEKTUR §11 dan 03-SKEMA pemakaian_ruangan. 1) Token Sanctum per klien (super-admin membuat;
ability ruangan:baca / ruangan:pakai). 2) GET /api/v1/ruangan (dapat_dipinjam; kode, nama, gedung, kapasitas,
fasilitas ringkas), GET /api/v1/ruangan/{kode}/jadwal?dari=&sampai= (pemakaian_ruangan terjadwal + peminjaman
ruangan tersetujui). 3) POST /api/v1/ruangan/{kode}/pemakaian (ability ruangan:pakai): cek bentrok dalam
transaksi + lockForUpdate ruangan; 409 bila bentrok; idempoten via referensi_eksternal. Rate limit 60/menit/token.
Test 401/403/409/idempoten. Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan
test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "feat(api): tambah api ruangan dan pemakaian untuk sistem surat"
```

### F11.4 — Tutup fase F11
```bash
git add -A && git commit -m "docs(changelog): catat rilis fase 11 integrasi"
git push -u origin feat/f11-integrasi   # PR → merge
git switch main && git pull && git tag -a v0.11.0 -m "Fase 11: notifikasi dan integrasi" && git push origin --tags
```

---

## F12 — Pengerasan, produksi, cutover

### F12.1 — Uji keamanan
**Branch:** `feat/f12-rilis`

**Prompt (salin ke agen AI):**
```text
Baca 05-UJI §3. MatriksAksesTest (seluruh sel PRD §3.1), IdorTest (UUID), LookupPublikTest (field putih),
RateLimitTest, XssTest, TanpaUnggahTest, KonkurensiTest (peminjaman & mutasi). Header keamanan (CSP, HSTS
produksi, X-Frame-Options). Perbaiki temuan; composer audit & npm audit di docs/KEAMANAN.md. Jangan ubah
berkas di luar cakupan langkah ini. Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
sail bin pint && sail artisan test
git add -A
git commit -m "test(keamanan): tambah uji matriks akses, idor, dan konkurensi"
```

### F12.2 — Docker produksi & cadangan
**Prompt (salin ke agen AI):**
```text
Dockerfile & compose produksi pola docker-apps (app, nginx, queue horizon, scheduler, mysql, redis), build
(composer --no-dev, npm build, optimize, filament:optimize), docs/DEPLOY.md (DNS, TLS, backup harian mysqldump
30 hari + uji pulih). .env.production.example tanpa rahasia. Jangan ubah berkas di luar cakupan langkah ini.
Setelah selesai jalankan pint dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
git add -A
git commit -m "build(docker): tambah konfigurasi produksi dan panduan deploy"
```

### F12.3 — UAT & panduan
**Prompt (salin ke agen AI):**
```text
Perintah siman:siapkan-uat (staging) dan docs/PANDUAN-PIC.md (pindai, ubah kondisi, keranjang pinjam,
pengembalian, inventarisasi, cetak label), docs/PANDUAN-ADMIN.md (aset, mutasi, DBR, inventarisasi,
penghapusan, laporan, migrasi). Jangan ubah berkas di luar cakupan langkah ini. Setelah selesai jalankan pint
dan test, lalu tampilkan ringkasan perubahan.
```

**Commit:**
```bash
git add -A
git commit -m "docs(panduan): tambah panduan pic dan admin serta persiapan uat"
```

### F12.4 — Cutover & rilis v1.0.0
Langkah manusia: jalankan 07-MIGRASI §7 (bekukan SIMAN-2, impor final, verifikasi, nonaktifkan Web App GAS, arsipkan spreadsheet). Lalu:

```bash
git add -A
git commit -m "docs(changelog): catat rilis 1.0.0 aset fkip"
git push -u origin feat/f12-rilis   # PR → CI hijau → merge
git switch main && git pull
git tag -a v1.0.0 -m "ASET FKIP 1.0.0" && git push origin --tags
```
