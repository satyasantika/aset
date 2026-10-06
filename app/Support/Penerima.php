<?php

namespace App\Support;

use App\Models\Aset;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Menentukan penerima notifikasi (02-ARSITEKTUR §7). Selalu pengguna aktif; tanpa PIC, jatuh ke admin BMN. */
class Penerima
{
    /**
     * PIC (ruangan_pic) dari ruangan-ruangan tersebut; bila tak satu pun punya PIC, admin BMN.
     *
     * @param  iterable<int, string|null>  $idRuangan
     * @return Collection<int, User>
     */
    public static function picRuangan(iterable $idRuangan): Collection
    {
        $ids = collect($idRuangan)->filter()->unique()->values();

        $pic = $ids->isEmpty() ? collect() : User::query()->where('aktif', true)->whereIn(
            'id',
            DB::table('ruangan_pic')->whereIn('ruangan_id', $ids->all())->select('user_id'),
        )->get();

        return $pic->isNotEmpty() ? $pic : self::peran('admin-bmn');
    }

    /** @return Collection<int, User> */
    public static function peran(string ...$peran): Collection
    {
        return User::query()->where('aktif', true)->whereHas('roles', fn ($q) => $q->whereIn('name', $peran))->get();
    }

    /** @return Collection<int, User> */
    public static function picPeminjaman(Peminjaman $peminjaman): Collection
    {
        return self::picRuangan(
            Aset::query()->whereIn('id', $peminjaman->item()->pluck('aset_id'))->pluck('ruangan_id'),
        );
    }
}
