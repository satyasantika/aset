<?php

namespace App\Http\Controllers;

use App\Models\DbrVersi;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * LAP-01: PDF DBR/DBL dirender dari SNAPSHOT versi (BR-13), bukan dari data aset saat ini; di-stream, tidak disimpan.
 * Versi yang belum disahkan diberi tanda air "DRAF".
 */
class DbrPdfController extends Controller
{
    public function __invoke(Request $request, DbrVersi $dbr): Response
    {
        /** @var User $pengguna */
        $pengguna = $request->user();
        abort_unless($pengguna->can('view', $dbr), 403);

        $pdf = Pdf::loadView('pdf.dbr', ['dbr' => $dbr, 's' => $dbr->snapshot, 'draf' => ! $dbr->status->pernahDisahkan()])->setPaper('a4', 'landscape');

        $nama = ($dbr->adalahDbl() ? 'DBL' : 'DBR').'-'.($dbr->snapshot['ruangan']['kode'] ?? 'lainnya').'-v'.$dbr->versi.'.pdf';

        return $pdf->stream($nama);
    }
}
