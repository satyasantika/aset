<?php

namespace App\Actions\Label;

use App\Enums\StatusAset;
use App\Models\Aset;
use App\Models\LabelLama;
use App\Support\FormatLabelLama;
use Illuminate\Support\Str;

/**
 * Menentukan aset dari isi QR/teks yang dipindai: URL baru `/a/{uuid}`, UUID polos, atau teks label lama
 * (lewat `label_lama`, kandidat berurutan dari FormatLabelLama). Aset berstatus `dihapus` tidak diresolusi.
 */
class ResolusiLabel
{
    public function handle(string $teks): ?Aset
    {
        $teks = trim($teks);

        if ($teks === '') {
            return null;
        }

        if ($id = $this->idDariUrlAtauUuid($teks)) {
            return $this->cari($id);
        }

        return $this->darilabelLama($teks);
    }

    public function darilabelLama(string $teks): ?Aset
    {
        foreach (FormatLabelLama::kandidat($teks) as $kandidat) {
            $label = LabelLama::query()->where('teks', $kandidat)->first();

            if ($label !== null && ($aset = $this->cari($label->aset_id)) !== null) {
                return $aset;
            }
        }

        return null;
    }

    private function cari(string $id): ?Aset
    {
        return Aset::query()->whereKey($id)->where('status', '!=', StatusAset::Dihapus->value)->first();
    }

    private function idDariUrlAtauUuid(string $teks): ?string
    {
        if (Str::isUuid($teks)) {
            return strtolower($teks);
        }

        if (preg_match('~^https?://[^/\s]+/a/([0-9a-fA-F-]{36})/?(?:[?#].*)?$~', $teks, $cocok) && Str::isUuid($cocok[1])) {
            return strtolower($cocok[1]);
        }

        return null;
    }
}
