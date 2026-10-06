<?php

namespace App\Http\Controllers;

use App\Actions\Label\TandaiLabelDicetak;
use App\Enums\StatusAset;
use App\Models\Aset;
use App\Models\User;
use App\Support\GeneratorQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak label QR (BR-17): A4 multi-stiker, di-stream (tidak disimpan). Sumber: aset terpilih (sesi/ids), seluruh
 * ruangan, atau mode "belum dicetak" / "perlu cetak ulang". PIC hanya untuk ruangannya (BR-05).
 */
class LabelPdfController extends Controller
{
    public const MAKS_LABEL = 300;

    public function __invoke(Request $request, GeneratorQr $qr, TandaiLabelDicetak $tandai): Response
    {
        /** @var User $pengguna */
        $pengguna = $request->user();
        abort_unless($pengguna->can('label.cetak'), 403);

        $data = $request->validate([
            'mode' => ['nullable', 'in:terpilih,ruangan,belum_dicetak,perlu_cetak_ulang'],
            'ruangan_id' => ['nullable', 'uuid'],
            'aset' => ['nullable', 'array', 'max:'.self::MAKS_LABEL],
            'aset.*' => ['uuid'],
            'tandai' => ['nullable', 'boolean'],
        ]);

        $mode = $data['mode'] ?? 'terpilih';
        $aset = $this->kumpulkan($request, $pengguna, $mode, $data);

        abort_if($aset->isEmpty(), 404, 'Tidak ada aset untuk dicetak.');
        abort_if($aset->count() > self::MAKS_LABEL, 422, 'Maksimal '.self::MAKS_LABEL.' label per cetak.');

        foreach ($aset as $a) {
            abort_unless($pengguna->can('cetakLabel', $a), 403);
        }

        $stiker = $aset->map(fn (Aset $a) => [
            'aset' => $a,
            'qr' => $qr->dataUri($a->urlPublik()),
        ]);

        $pdf = Pdf::loadView('pdf.label', ['stiker' => $stiker])->setPaper('a4', 'portrait');
        $respons = $pdf->stream('label-aset.pdf');

        if ($request->boolean('tandai', true)) {
            $tandai->handle($aset, $pengguna);
        }

        return $respons;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Aset>
     */
    private function kumpulkan(Request $request, User $pengguna, string $mode, array $data): Collection
    {
        $query = Aset::query()->with('ruangan')->where('status', '!=', StatusAset::Dihapus->value)->orderBy('ruangan_id')->orderBy('nama');

        if (filled($data['ruangan_id'] ?? null)) {
            $query->diRuangan($data['ruangan_id']);
        }

        return match ($mode) {
            'terpilih' => $query->whereIn('id', $data['aset'] ?? $request->session()->pull('cetak_label_ids', []))->get(),
            'ruangan' => $query->when(blank($data['ruangan_id'] ?? null), fn ($q) => $q->whereRaw('1 = 0'))->get(),
            'belum_dicetak' => $query->dikelolaOleh($pengguna)->whereNull('dicetak_pada')->limit(self::MAKS_LABEL + 1)->get(),
            'perlu_cetak_ulang' => $query->dikelolaOleh($pengguna)->where('label_perlu_cetak_ulang', true)->limit(self::MAKS_LABEL + 1)->get(),
            default => collect(),
        };
    }
}
