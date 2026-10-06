<?php

namespace App\Policies;

use App\Enums\StatusDbr;
use App\Models\DbrVersi;
use App\Models\Ruangan;
use App\Models\User;

/**
 * Bangkitkan: admin atau PIC untuk ruangannya (DBL: admin saja). Setujui (tanda tangan PIC): PIC ruangan itu; untuk DBL
 * admin-bmn. Sahkan: pejabat-penatausahaan. Lihat: staf ber-peran pengelola DBR/laporan; PIC hanya ruangannya (BR-05).
 */
class DbrVersiPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('dbr.bangkitkan') || $pelaku->can('dbr.sahkan') || $pelaku->can('laporan.lihat');
    }

    public function view(User $pelaku, DbrVersi $dbr): bool
    {
        if (! $this->viewAny($pelaku)) {
            return false;
        }

        // Draf/menunggu pengesahan adalah dokumen kerja; pimpinan hanya melihat yang sudah disahkan.
        if ($dbr->status->dalamProses() && ! ($pelaku->can('dbr.bangkitkan') || $pelaku->can('dbr.sahkan'))) {
            return false;
        }

        return $pelaku->hasAnyRole(['super-admin', 'admin-bmn', 'pejabat-penatausahaan', 'pimpinan'])
            || $pelaku->bolehMengelolaRuangan($dbr->ruangan_id);
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('dbr.bangkitkan');
    }

    /** @param  Ruangan|null  $ruangan  null = DBL */
    public function bangkitkan(User $pelaku, ?Ruangan $ruangan = null): bool
    {
        return $pelaku->can('dbr.bangkitkan') && $pelaku->bolehMengelolaRuangan($ruangan?->getKey());
    }

    public function setujuiPic(User $pelaku, DbrVersi $dbr): bool
    {
        if ($dbr->adalahDbl()) {
            return $pelaku->hasAnyRole(['admin-bmn', 'super-admin']);
        }

        return $pelaku->can('dbr.sahkan') && $pelaku->hasRole('pic-ruangan') && $pelaku->bolehMengelolaRuangan($dbr->ruangan_id);
    }

    public function sahkan(User $pelaku, DbrVersi $dbr): bool
    {
        return $pelaku->can('dbr.sahkan') && $pelaku->hasRole('pejabat-penatausahaan');
    }

    public function kembalikan(User $pelaku, DbrVersi $dbr): bool
    {
        return $dbr->status === StatusDbr::DisetujuiPic
            && ($this->sahkan($pelaku, $dbr) || $this->setujuiPic($pelaku, $dbr));
    }

    public function update(User $pelaku, DbrVersi $dbr): bool
    {
        return false;
    }

    public function delete(User $pelaku, DbrVersi $dbr): bool
    {
        return false;
    }
}
