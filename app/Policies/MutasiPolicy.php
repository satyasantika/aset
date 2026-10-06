<?php

namespace App\Policies;

use App\Models\Mutasi;
use App\Models\Ruangan;
use App\Models\User;

/**
 * Ajukan: PIC untuk ruangan asalnya (BR-05) atau admin. Putuskan (setujui/tolak): mutasi.putuskan (admin-bmn).
 * Lihat: admin semua; PIC hanya mutasi yang melibatkan ruangannya atau diajukannya.
 */
class MutasiPolicy
{
    public function viewAny(User $pelaku): bool
    {
        return $pelaku->can('mutasi.ajukan') || $pelaku->can('mutasi.putuskan');
    }

    public function view(User $pelaku, Mutasi $mutasi): bool
    {
        if ($pelaku->can('mutasi.putuskan')) {
            return true;
        }

        return $pelaku->can('mutasi.ajukan')
            && ($mutasi->diajukan_oleh === $pelaku->getKey()
                || $pelaku->bolehMengelolaRuangan($mutasi->ruangan_asal_id)
                || $pelaku->bolehMengelolaRuangan($mutasi->ruangan_tujuan_id));
    }

    public function create(User $pelaku): bool
    {
        return $pelaku->can('mutasi.ajukan');
    }

    /** Pengajuan dari ruangan asal tertentu. */
    public function ajukan(User $pelaku, Ruangan $asal): bool
    {
        return $pelaku->can('mutasi.ajukan') && $pelaku->bolehMengelolaRuangan($asal->getKey());
    }

    public function putuskan(User $pelaku, Mutasi $mutasi): bool
    {
        return $pelaku->can('mutasi.putuskan');
    }

    public function batalkan(User $pelaku, Mutasi $mutasi): bool
    {
        return $mutasi->masihDiajukan()
            && ($pelaku->can('mutasi.putuskan') || $mutasi->diajukan_oleh === $pelaku->getKey());
    }

    public function update(User $pelaku, Mutasi $mutasi): bool
    {
        return false;
    }

    public function delete(User $pelaku, Mutasi $mutasi): bool
    {
        return false;
    }
}
