<?php

namespace App\Http\Controllers\Publik;

use App\Actions\Pemeliharaan\TerimaLaporanKerusakan;
use App\Enums\StatusAset;
use App\Http\Controllers\Controller;
use App\Models\Aset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lapor kerusakan dari halaman QR tanpa login (BR-12): deskripsi wajib, nama & kontak opsional, honeypot,
 * pembatas 5/jam/IP (`throttle:lapor` pada POST). IP tidak disimpan mentah.
 */
class LaporKerusakanController extends Controller
{
    /** Nama kolom jebakan (disembunyikan dari pengguna asli). */
    public const HONEYPOT = 'situs_web';

    public function form(string $aset): Response
    {
        $model = $this->cari($aset);

        return $model === null
            ? $this->tanpaIndeks(response()->view('publik.tidak-ditemukan', [], 404))
            : $this->tanpaIndeks(response()->view('publik.lapor', ['aset' => $model, 'honeypot' => self::HONEYPOT]));
    }

    public function kirim(Request $request, string $aset, TerimaLaporanKerusakan $terima): RedirectResponse|Response
    {
        $model = $this->cari($aset);

        if ($model === null) {
            return $this->tanpaIndeks(response()->view('publik.tidak-ditemukan', [], 404));
        }

        $data = $request->validate([
            'deskripsi' => ['required', 'string', 'min:5', 'max:'.TerimaLaporanKerusakan::MAKS_DESKRIPSI],
            'nama' => ['nullable', 'string', 'max:150'],
            'kontak' => ['nullable', 'string', 'max:50'],
            self::HONEYPOT => ['nullable'],
        ]);

        // Bot yang mengisi kolom jebakan dianggap berhasil, tetapi tidak ada tiket yang dibuat.
        if (filled($data[self::HONEYPOT] ?? null)) {
            return redirect()->route('publik.lapor', $model->getKey())->with('lapor_berhasil', true);
        }

        $tiket = $terima->handle($model, $data['deskripsi'], $data['nama'] ?? null, $data['kontak'] ?? null, $request->ip());

        return redirect()->route('publik.lapor', $model->getKey())->with('lapor_berhasil', $tiket->nomor);
    }

    private function cari(string $id): ?Aset
    {
        return Aset::query()->with('ruangan')->whereKey($id)->where('status', '!=', StatusAset::Dihapus->value)->first();
    }

    private function tanpaIndeks(Response $respons): Response
    {
        $respons->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $respons->headers->set('Cache-Control', 'no-store, private');

        return $respons;
    }
}
