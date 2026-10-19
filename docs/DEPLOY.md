# Panduan deploy produksi ASET FKIP

> **Profil Ringan berlaku setelah Fase B; kondisi saat ini: `compose.prod.yaml` masih menjalankan enam layanan (`app`, `web`,
> `queue` = Horizon, `scheduler`, `redis`, `cadangan`) dengan antrean/cache/sesi di Redis.** Setelah Fase B (`STANDAR-TEKNIS.md` §1b,
> `02-ARSITEKTUR.md`): tidak ada container `queue`/Horizon dan `redis`; antrean, cache, sesi, dan lock memakai basis data (driver
> `database`); satu container `scheduler` (`schedule:work`) menjalankan jadwal sekaligus worker antrean (`--stop-when-empty`);
> pemantauan lewat `/api/health` (dan `queue:failed`/`queue:retry`). Bagian di bawah yang bertanda *(saat ini)* akan diganti
> ketika Fase B selesai dan berkas ini diperbarui.

Pola saat ini: satu host Docker (Linux) menjalankan `compose.prod.yaml` dengan enam layanan — `app` (php-fpm), `web` (nginx),
`queue` (Horizon), `scheduler`, `redis`, `cadangan`. **Basis data SQLite (mode WAL) pada volume `data`; tidak ada
MySQL/MariaDB (kondisi saat ini)**. Diputuskan 16 Oktober 2026: MariaDB 10.11+ native di host, berlaku setelah Fase B; kondisi saat ini: SQLite (`docs/KEPUTUSAN.md`). TLS dihentikan di reverse proxy host.

> **Status verifikasi:** `compose.prod.yaml` tervalidasi (`docker compose config`), sintaks skrip entrypoint/`cadangan.sh` diperiksa (`sh -n`) tetapi belum dijalankan (CLI `sqlite3` tidak tersedia di lingkungan pengembangan),
> dan build image penuh belum dapat diselesaikan di lingkungan pengembangan (unduhan Composer timeout). Jalankan
> `docker compose ... build` pertama kali di server/staging dan perbaiki bila ada yang perlu disesuaikan sebelum cutover.

## 1. Prasyarat

| Item | Keterangan |
|---|---|
| Host | Linux dengan Docker ≥ 24 dan Compose v2; disarankan 2 vCPU / 2 GB RAM / 20 GB disk |
| DNS | Rekam `A`/`AAAA` `aset.fkip.unsil.ac.id` (atau domain yang dipilih) → IP host. Minta ke admin jaringan UNSIL bila domain di bawah `unsil.ac.id` |
| TLS | Sertifikat untuk domain tersebut (Let's Encrypt via certbot/Caddy/Traefik di host, atau sertifikat institusi). Reverse proxy meneruskan ke `127.0.0.1:${WEB_PORT}` dengan header `X-Forwarded-Proto: https` |
| Surel | Akun SMTP kampus untuk notifikasi |

Contoh reverse proxy Caddy (TLS otomatis):

```
aset.fkip.unsil.ac.id {
    reverse_proxy 127.0.0.1:8080
}
```

## 1a. Alamat produksi: https://supportfkip.unsil.ac.id/aset (subpath)

Sistem dipasang di **subpath `/aset`** pada host `supportfkip.unsil.ac.id` yang dipakai bersama aplikasi lain.
- `APP_URL=https://supportfkip.unsil.ac.id/aset` — aplikasi membangun semua URL (rute, Vite/Filament, endpoint Livewire,
  tautan di surel/QR) dari nilai ini (`App\Support\UrlDasar`, diuji di `SubpathTest`). `SESSION_PATH=/aset` dan
  `SESSION_COOKIE=siman_session` agar cookie tidak bocor ke/bertabrakan dengan aplikasi lain di host yang sama.
- Reverse proxy host **menghapus prefix** `/aset` saat meneruskan ke kontainer `web` (lihat `docker/nginx/supportfkip.conf.example`).
- QR pada label berisi URL publik `https://supportfkip.unsil.ac.id/aset/a/{id}`; label dicetak setelah `APP_URL` final.
- Rute `/up` dan `/aset/api/health` dapat dipakai untuk pemantauan; API Surat: `https://supportfkip.unsil.ac.id/aset/api/v1/...`.

## 2. Pemasangan pertama

```bash
git clone <repo> /opt/siman && cd /opt/siman && git checkout v1.0.0
cp .env.production.example .env.production
docker run --rm php:8.3-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'   # isi APP_KEY
nano .env.production            # APP_URL, APP_KEY, MAIL_*, dst. Jangan commit berkas ini.
docker compose -f compose.prod.yaml --env-file .env.production up -d --build
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=PeranDanIzinSeeder --force
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=PengaturanSeeder --force
docker compose -f compose.prod.yaml exec app php artisan tinker   # buat akun super-admin pertama (lihat bawah)
```

Image dibangun multi-tahap: `npm run build`, `composer install --no-dev`, lalu `artisan optimize` dan
`filament:optimize` dijalankan oleh entrypoint saat `app` mulai (bersama `migrate --force`).

Akun super-admin pertama (surel domain unsil; MFA aplikasi wajib diatur pada login pertama):

```php
$u = App\Models\User::create(['name' => 'Nama Admin', 'email' => 'admin@unsil.ac.id', 'password' => Hash::make(Str::random(40)), 'aktif' => true]);
$u->assignRole('super-admin');
```

Lalu minta admin memakai "lupa kata sandi"/atur ulang untuk menetapkan kata sandi.

## 3. Operasi harian

| Tugas | Perintah |
|---|---|
| Status layanan | `docker compose -f compose.prod.yaml ps` · `curl -s https://<domain>/api/health` |
| Log | `docker compose -f compose.prod.yaml logs -f app queue scheduler` *(saat ini; Profil Ringan: `logs -f app scheduler`)* |
| Antrean *(saat ini: Horizon)* | panel `/horizon` (super-admin) · `exec queue php artisan horizon:status` |
| Antrean *(Profil Ringan, setelah Fase B)* | `curl -s https://<domain>/api/health` (job tertunda, `failed_jobs`, detak penjadwal) · `exec scheduler php artisan queue:failed` · `queue:retry <id>`; tidak ada dasbor `/horizon` |
| Tugas terjadwal | dijalankan layanan `scheduler` (`schedule:work`): lihat `routes/console.php` |
| Pemeliharaan | `exec app php artisan down` / `up` |

## 4. Pembaruan dan rollback

```bash
git fetch --tags && git checkout vX.Y.Z
docker compose -f compose.prod.yaml --env-file .env.production up -d --build
docker compose -f compose.prod.yaml exec queue php artisan horizon:terminate   # worker memuat kode baru (saat ini)
# Profil Ringan (setelah Fase B): tidak perlu langkah ini; worker berumur ≤ 1 menit (--stop-when-empty) sehingga otomatis memuat kode baru.
# Cukup: docker compose -f compose.prod.yaml restart scheduler
```

Migrasi dijalankan otomatis oleh `app`. **Cadangkan dulu** (`cadangan.sh sekali`, bagian 5) sebelum memperbarui.
Rollback: `git checkout <tag lama>`, bangun ulang, lalu pulihkan cadangan bila migrasi tidak dapat dibalik.

## 5. Cadangan dan uji pulih

Layanan `cadangan` membuat salinan konsisten (`sqlite3 .backup`) setiap hari pukul `CADANGAN_JAM` (bawaan 02:00 WIB),
memeriksa `PRAGMA integrity_check`, mengompres, dan menyimpan **30 hari** di `./cadangan/` pada host
(`siman-YYYYMMDD-HHMMSS.sqlite.gz`). Salin folder ini ke penyimpanan terpisah (rsync/rclone ke Drive institusi) —
cadangan pada host yang sama tidak melindungi dari kegagalan disk.

Cadangan manual: `docker compose -f compose.prod.yaml run --rm --entrypoint /bin/sh cadangan -c "apk add --no-cache sqlite >/dev/null && /cadangan.sh sekali"`

### Uji pulih (wajib per semester, dan sebelum cutover)

```bash
gunzip -k cadangan/siman-<stempel>.sqlite.gz
sqlite3 cadangan/siman-<stempel>.sqlite "PRAGMA integrity_check; SELECT count(*) FROM aset; SELECT count(*) FROM users;"
# pulihkan ke produksi:
docker compose -f compose.prod.yaml stop app queue scheduler
docker run --rm -v siman_data:/data -v "$PWD/cadangan:/c" alpine sh -c 'cp /c/siman-<stempel>.sqlite /data/database.sqlite && rm -f /data/database.sqlite-wal /data/database.sqlite-shm'
docker compose -f compose.prod.yaml start app queue scheduler
```

Catat tanggal dan hasil uji pulih (jumlah baris cocok, aplikasi dapat login) di `docs/migrasi/HASIL-UJI-MIGRASI.md` atau log operasi.

## 6. Keamanan produksi

- `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`; HSTS dikirim aplikasi saat `APP_ENV=production` (`HeaderKeamanan`).
- Hanya port 80/443 reverse proxy yang terbuka ke publik; `WEB_PORT` hanya di `127.0.0.1` (atur `ports: "127.0.0.1:8080:80"` bila perlu).
- Layanan `redis` *(saat ini)* tidak dipublikasikan ke host; pada Profil Ringan layanan ini dihapus. Rahasia hanya di `.env.production` (izin `chmod 600`).
- Jalankan `composer audit` dan `npm audit` setiap rilis (hasil: `docs/KEAMANAN.md`).

## 7. Beralih ke MariaDB (berlaku setelah Fase B; kondisi saat ini: SQLite)

Diputuskan 16 Oktober 2026: basis data produksi adalah MariaDB 10.11+ native di host (bukan container), sama dengan aplikasi produksi lain. `compose.prod.yaml` memakai `extra_hosts: ["host.docker.internal:host-gateway"]` dan **tidak boleh memuat container database** (CI boleh memakai service container sementara). Tambahkan koneksi pada
`.env.production` (`DB_CONNECTION=mariadb`, `DB_HOST=host.docker.internal`, `DB_DATABASE=db_aset`, kredensial), jalankan `php artisan migrate --force`, dan ganti layanan
`cadangan` dengan `mariadb-dump` harian (30 hari). Kolom UUID memakai tipe native MariaDB; data lama SQLite (varchar(36), format UUID valid) dapat dimasukkan ke kolom UUID saat migrasi. Migrasi data SQLite ke MariaDB adalah butir Fase B (prosedur menyusul).
Uji konkurensi (`KeamananTest`) baru bermakna di MariaDB; `lockForUpdate` baru efektif pada basis data server.
