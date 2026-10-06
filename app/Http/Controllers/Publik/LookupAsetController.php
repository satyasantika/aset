<?php

namespace App\Http\Controllers\Publik;

use App\Actions\Label\ResolusiLabel;
use App\Enums\StatusAset;
use App\Http\Controllers\Controller;
use App\Models\Aset;
use App\Models\KodefikasiBarang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Lookup publik tanpa login (BR-18). Hanya "field putih": nama, merk/tipe, kategori, ruangan, kondisi, tahun
 * perolehan, kode barang + NUP. Tidak pernah menampilkan nilai, PIC, peminjam, atau riwayat.
 */
class LookupAsetController extends Controller
{
    public function tampil(string $aset): Response
    {
        $model = Aset::query()->with('ruangan')->whereKey($aset)->where('status', '!=', StatusAset::Dihapus->value)->first();

        if ($model === null) {
            return $this->tidakDitemukan();
        }

        $kategori = $model->kode_barang
            ? KodefikasiBarang::query()->where('kode', $model->kode_barang)->first(['uraian', 'kategori_lokal'])
            : null;

        return $this->tanpaIndeks(response()->view('publik.aset', [
            'aset' => $model,
            'kategori' => $kategori?->kategori_lokal ?: $kategori?->uraian,
        ]));
    }

    public function labelLama(string $kode, ResolusiLabel $resolusi): RedirectResponse|Response
    {
        $aset = $resolusi->darilabelLama($kode);

        if ($aset === null) {
            return $this->tidakDitemukan();
        }

        return redirect('/a/'.$aset->getKey(), 302);
    }

    private function tidakDitemukan(): Response
    {
        return $this->tanpaIndeks(response()->view('publik.tidak-ditemukan', [], 404));
    }

    private function tanpaIndeks(Response $respons): Response
    {
        $respons->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $respons->headers->set('Cache-Control', 'no-store, private');

        return $respons;
    }
}
