<?php

use Illuminate\Support\Facades\Schedule;

// Jadwal tugas SIMAN (02-ARSITEKTUR §6). Semua withoutOverlapping + onOneServer (cache Redis bersama).
$jadwal = fn (string $perintah) => Schedule::command($perintah)->withoutOverlapping()->onOneServer();

$jadwal('aset:pengingat-terlambat')->dailyAt('07:00');
$jadwal('aset:pengingat-pengambilan')->dailyAt('07:05');
$jadwal('aset:tandai-dbr-usang')->dailyAt('00:30');
$jadwal('aset:periksa-tautan')->weeklyOn(1, '06:00');
$jadwal('aset:pengingat-inventarisasi')->monthlyOn(1, '07:10');
$jadwal('aset:pangkas-peminjaman')->monthlyOn(1, '02:00');
$jadwal('aset:bersihkan-tmp')->hourly();
