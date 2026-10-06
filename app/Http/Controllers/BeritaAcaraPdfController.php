<?php

namespace App\Http\Controllers;

use App\Models\PeriodeInventarisasi;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\Response;

/** LAP-05: berita acara (PDF) dan selisih (Excel) dirender dari SNAPSHOT periode, di-stream (tidak disimpan). */
class BeritaAcaraPdfController extends Controller
{
    public function pdf(Request $request, PeriodeInventarisasi $periode): Response
    {
        $s = $this->snapshot($request, $periode);

        return Pdf::loadView('pdf.berita-acara', ['periode' => $periode, 's' => $s])->setPaper('a4', 'portrait')
            ->stream('berita-acara-inventarisasi-'.str($periode->nama)->slug().'.pdf');
    }

    public function selisih(Request $request, PeriodeInventarisasi $periode): Response
    {
        $s = $this->snapshot($request, $periode);

        // Ke berkas sementara lalu diunduh (toBrowser() milik pustaka memanggil exit); dihapus setelah dikirim.
        $jalur = tempnam(sys_get_temp_dir(), 'selisih').'.xlsx';
        $tulis = SimpleExcelWriter::create($jalur);

        foreach ($s['tidak_ditemukan'] as $b) {
            $tulis->addRow(['Ruangan' => $b['ruangan'], 'Hasil' => 'Tidak ditemukan', 'Kode barang' => $b['kode_barang'] ?? $b['kode_internal'], 'NUP' => $b['nup'], 'Nama' => $b['nama'], 'Merk/Tipe' => $b['merk_tipe'], 'Kondisi data' => '', 'Kondisi ditemukan' => '', 'Keterangan' => '']);
        }

        foreach ($s['kondisi_berubah'] as $b) {
            $tulis->addRow(['Ruangan' => $b['ruangan'], 'Hasil' => 'Kondisi berubah', 'Kode barang' => $b['kode_barang'] ?? $b['kode_internal'], 'NUP' => $b['nup'], 'Nama' => $b['nama'], 'Merk/Tipe' => $b['merk_tipe'], 'Kondisi data' => $b['kondisi_data'], 'Kondisi ditemukan' => $b['kondisi_ditemukan'], 'Keterangan' => '']);
        }

        foreach ($s['berlebih'] as $b) {
            $tulis->addRow(['Ruangan' => $b['ruangan'], 'Hasil' => 'Berlebih (tidak tercatat)', 'Kode barang' => '', 'NUP' => '', 'Nama' => '', 'Merk/Tipe' => '', 'Kondisi data' => '', 'Kondisi ditemukan' => '', 'Keterangan' => $b['deskripsi']]);
        }

        $tulis->close();

        return response()->download($jalur, 'selisih-inventarisasi-'.str($periode->nama)->slug().'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** @return array<string, mixed> */
    private function snapshot(Request $request, PeriodeInventarisasi $periode): array
    {
        /** @var User $pengguna */
        $pengguna = $request->user();
        abort_unless($pengguna->can('lihatBeritaAcara', $periode) && $periode->berita_acara !== null, 403);

        return $periode->berita_acara;
    }
}
