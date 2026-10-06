<?php

namespace App\Support;

use App\Enums\StatusDbr;
use App\Enums\StatusMutasi;
use App\Enums\StatusPeminjaman;
use App\Enums\StatusPeriodeInventarisasi;
use App\Models\DbrVersi;
use App\Models\Mutasi;
use App\Models\Peminjaman;
use App\Models\PeriodeInventarisasi;
use App\Notifications\BeritaAcaraMenungguPengesahan;
use App\Notifications\DbrMenungguPengesahan;
use App\Notifications\MutasiDiajukan;
use App\Notifications\MutasiDiputuskan;
use App\Notifications\PeminjamanDiputuskan;
use App\Notifications\PengajuanPeminjamanBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Memasang pemicu notifikasi pada perubahan status model (02-ARSITEKTUR §7), sehingga seluruh jalur perubahan —
 * Action, impor, maupun UI — memicu notifikasi yang sama. Penerima ditentukan setelah commit (item/PIC sudah tersimpan);
 * notifikasi sendiri diantrekan ke `notifikasi`. Laporan kerusakan memakai event `LaporanKerusakanDiterima`.
 */
class PemicuNotifikasi
{
    public static function daftarkan(): void
    {
        Peminjaman::created(function (Peminjaman $p): void {
            if ($p->status === StatusPeminjaman::Diajukan) {
                DB::afterCommit(fn () => Notification::send(Penerima::picPeminjaman($p), new PengajuanPeminjamanBaru($p)));
            }
        });

        Peminjaman::updated(function (Peminjaman $p): void {
            if ($p->wasChanged('status') && in_array($p->status, [StatusPeminjaman::Disetujui, StatusPeminjaman::Ditolak], true) && $p->peminjam_user_id !== null) {
                DB::afterCommit(function () use ($p): void {
                    $peminjam = $p->peminjamUser;

                    if ($peminjam !== null && $peminjam->aktif) {
                        $peminjam->notify(new PeminjamanDiputuskan($p));
                    }
                });
            }
        });

        Mutasi::created(function (Mutasi $m): void {
            if ($m->status === StatusMutasi::Diajukan) {
                DB::afterCommit(fn () => Notification::send(Penerima::peran('admin-bmn'), new MutasiDiajukan($m)));
            }
        });

        Mutasi::updated(function (Mutasi $m): void {
            if ($m->wasChanged('status') && in_array($m->status, [StatusMutasi::Disetujui, StatusMutasi::Ditolak], true)) {
                DB::afterCommit(fn () => Notification::send(
                    Penerima::picRuangan([$m->ruangan_asal_id, $m->ruangan_tujuan_id]),
                    new MutasiDiputuskan($m),
                ));
            }
        });

        DbrVersi::updated(function (DbrVersi $d): void {
            if ($d->wasChanged('status') && $d->status === StatusDbr::DisetujuiPic) {
                DB::afterCommit(fn () => Notification::send(Penerima::peran('pejabat-penatausahaan'), new DbrMenungguPengesahan($d)));
            }
        });

        PeriodeInventarisasi::updated(function (PeriodeInventarisasi $p): void {
            if ($p->wasChanged('status') && $p->status === StatusPeriodeInventarisasi::Ditutup) {
                DB::afterCommit(fn () => Notification::send(Penerima::peran('pejabat-penatausahaan'), new BeritaAcaraMenungguPengesahan($p)));
            }
        });
    }
}
