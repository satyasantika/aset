<?php

use Illuminate\Support\Facades\Schedule;

// Jadwal tugas SIMAN (02-ARSITEKTUR §6). Semua withoutOverlapping + onOneServer (cache `database` bersama).
$jadwal = fn (string $perintah) => Schedule::command($perintah)->withoutOverlapping()->onOneServer();

$jadwal('aset:pengingat-terlambat')->dailyAt('07:00');
$jadwal('aset:pengingat-pengambilan')->dailyAt('07:05');
$jadwal('aset:tandai-dbr-usang')->dailyAt('00:30');
$jadwal('aset:periksa-tautan')->weeklyOn(1, '06:00');
$jadwal('aset:pengingat-inventarisasi')->monthlyOn(1, '07:10');
$jadwal('aset:pangkas-peminjaman')->monthlyOn(1, '02:00');
$jadwal('aset:bersihkan-tmp')->hourly();

// Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon/Redis. Penjadwal menyalakan worker tiap menit dan
// berhenti saat antrean kosong; jeda proses job paling lama ±1 menit.
// --memory=256 pada worker default melindungi RAM server (3,8 GiB).
Schedule::command('queue:work database --queue=default --stop-when-empty --max-time=55 --timeout=60 --tries=3 --memory=256')
    ->everyMinute()->withoutOverlapping(2)->runInBackground();

Schedule::command('queue:work database-berat --queue=berat --stop-when-empty --max-time=600 --timeout=900 --tries=2 --memory=512')
    ->everyMinute()->withoutOverlapping(30)->runInBackground();

// Detak penjadwal: dibaca /api/health; peringatan bila lebih tua dari 5 menit.
Schedule::call(fn () => cache()->put(config('app.kode').':detak-penjadwal', now(), 600))
    ->everyMinute()->name('detak-penjadwal');

Schedule::command('queue:prune-failed --hours=720')->dailyAt('03:00');
Schedule::command('queue:prune-batches --hours=168')->dailyAt('03:05');
