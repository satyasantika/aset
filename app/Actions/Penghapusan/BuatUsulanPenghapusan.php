<?php

namespace App\Actions\Penghapusan;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusUsulanHapus;
use App\Models\Aset;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Models\UsulanPenghapusanItem;
use App\Support\NomorTransaksi;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-16 / US-HPS-01: usulan penghapusan hanya berisi aset Rusak Berat (aktif) atau berstatus hilang, dan belum ada dalam
 * usulan terbuka lain. Nomor `USL-{tahun}-{4 digit}`; status awal `draf`.
 */
class BuatUsulanPenghapusan
{
    /**
     * @param  array<int, string>  $idAset
     */
    public function handle(array $idAset, string $alasan, User $pelaku): UsulanPenghapusan
    {
        Gate::forUser($pelaku)->authorize('create', UsulanPenghapusan::class);
        Pengaturan::pastikanFitur('hapus_aset');

        $ids = collect($idAset)->unique()->values();
        $galat = [];

        if (trim($alasan) === '') {
            $galat['alasan'] = 'Alasan usulan wajib diisi.';
        }

        if ($ids->isEmpty()) {
            $galat['aset'] = 'Pilih minimal satu aset.';
        }

        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $aset = Aset::query()->whereIn('id', $ids)->get();
        $masalah = [];

        foreach ($ids as $id) {
            /** @var Aset|null $a */
            $a = $aset->firstWhere('id', $id);
            $alasanTolak = $a === null ? "Aset {$id} tidak ditemukan." : self::alasanTidakLayak($a);

            if ($alasanTolak !== null) {
                $masalah[] = $alasanTolak;
            }
        }

        if ($masalah !== []) {
            throw ValidationException::withMessages(['aset' => $masalah]);
        }

        return DB::transaction(fn (): UsulanPenghapusan => NomorTransaksi::buat('USL', UsulanPenghapusan::class, 4, function (string $nomor) use ($aset, $alasan, $pelaku): UsulanPenghapusan {
            $usulan = UsulanPenghapusan::query()->create([
                'nomor' => $nomor, 'alasan' => trim($alasan), 'status' => StatusUsulanHapus::Draf, 'pengusul_id' => $pelaku->getKey(),
            ]);

            foreach ($aset as $a) {
                UsulanPenghapusanItem::query()->create([
                    'usulan_id' => $usulan->getKey(), 'aset_id' => $a->getKey(),
                    'alasan_item' => $a->status === StatusAset::Hilang ? UsulanPenghapusanItem::HILANG : UsulanPenghapusanItem::RUSAK_BERAT,
                ]);
            }

            return $usulan;
        }));
    }

    /** Alasan aset tidak boleh diusulkan, atau null bila layak. */
    public static function alasanTidakLayak(Aset $aset, ?string $kecualiUsulanId = null): ?string
    {
        $nama = "{$aset->nama} ({$aset->kode_tampil})";

        $layak = $aset->status === StatusAset::Hilang
            || ($aset->status === StatusAset::Aktif && $aset->kondisi === KondisiAset::RusakBerat);

        if (! $layak) {
            return "{$nama} tidak dapat diusulkan: hanya aset Rusak Berat yang aktif atau yang berstatus hilang (BR-16).";
        }

        $terbuka = UsulanPenghapusanItem::query()->where('aset_id', $aset->getKey())
            ->whereHas('usulan', fn ($q) => $q->terbuka()->when($kecualiUsulanId, fn ($w) => $w->whereKeyNot($kecualiUsulanId)))->first();

        if ($terbuka !== null) {
            return "{$nama} sudah ada dalam usulan penghapusan yang masih berjalan.";
        }

        return null;
    }
}
