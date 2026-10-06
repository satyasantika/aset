<?php

namespace App\Http\Controllers\Api;

use App\Actions\Ruangan\CatatPemakaianRuangan;
use App\Exceptions\PemakaianBentrok;
use App\Http\Controllers\Controller;
use App\Models\Aset;
use App\Models\PemakaianRuangan;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** API v1 untuk Surat/OrmawaHub (02-ARSITEKTUR §11): daftar ruangan, jadwal, dan pencatatan pemakaian. Ability: `ruangan:baca`, `ruangan:pakai`. */
class RuanganController extends Controller
{
    private const MAKS_RENTANG_HARI = 92;

    public function index(): JsonResponse
    {
        $ruangan = Ruangan::query()->with('gedung')->where('dapat_dipinjam', true)->orderBy('kode')->get();

        $fasilitas = Aset::query()
            ->whereIn('ruangan_id', $ruangan->pluck('id'))->where('status', 'aktif')
            ->select('ruangan_id', 'nama', DB::raw('count(*) as jumlah'))
            ->groupBy('ruangan_id', 'nama')->orderByDesc('jumlah')->get()->groupBy('ruangan_id');

        return response()->json(['data' => $ruangan->map(fn (Ruangan $r): array => [
            'kode' => $r->kode,
            'nama' => $r->nama,
            'gedung' => $r->gedung?->nama,
            'lantai' => $r->lantai,
            'kapasitas' => $r->kapasitas,
            'fasilitas' => ($fasilitas[$r->id] ?? collect())->take(8)->map(fn ($f): array => ['nama' => $f->nama, 'jumlah' => (int) $f->jumlah])->values(),
        ])->values()]);
    }

    public function jadwal(Request $request, string $kode): JsonResponse
    {
        $ruangan = $this->cari($kode);

        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $dari = CarbonImmutable::parse($data['dari'] ?? now()->startOfDay());
        $sampai = CarbonImmutable::parse($data['sampai'] ?? $dari->addDays(30));

        if ($dari->diffInDays($sampai, true) > self::MAKS_RENTANG_HARI) {
            return response()->json(['message' => 'Rentang maksimal '.self::MAKS_RENTANG_HARI.' hari.', 'errors' => ['sampai' => ['Rentang terlalu panjang.']]], 422);
        }

        $jadwal = PemakaianRuangan::query()->where('ruangan_id', $ruangan->getKey())->beririsan($dari, $sampai)->orderBy('mulai')->get();

        return response()->json([
            'ruangan' => ['kode' => $ruangan->kode, 'nama' => $ruangan->nama],
            'dari' => $dari->toIso8601String(),
            'sampai' => $sampai->toIso8601String(),
            'data' => $jadwal->map(fn (PemakaianRuangan $p): array => $this->bentukPemakaian($p))->values(),
        ]);
    }

    public function pakai(Request $request, string $kode, CatatPemakaianRuangan $catat): JsonResponse
    {
        $ruangan = $this->cari($kode);

        $data = $request->validate([
            'mulai' => ['required', 'date'],
            'selesai' => ['required', 'date', 'after:mulai'],
            'kegiatan' => ['required', 'string', 'max:255'],
            'referensi_eksternal' => ['required', 'string', 'max:100'],
        ]);

        /** @var User $klien */
        $klien = $request->user();

        try {
            $hasil = $catat->handle(
                $ruangan, CarbonImmutable::parse($data['mulai']), CarbonImmutable::parse($data['selesai']),
                $data['kegiatan'], $data['referensi_eksternal'], PemakaianRuangan::SUMBER_SURAT, $klien,
            );
        } catch (PemakaianBentrok $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'bentrok' => array_map(fn (PemakaianRuangan $p): array => $this->bentukPemakaian($p), $e->bentrok),
            ], 409);
        }

        return response()->json(['data' => $this->bentukPemakaian($hasil['pemakaian']), 'dibuat' => $hasil['dibuat']], $hasil['dibuat'] ? 201 : 200);
    }

    private function cari(string $kode): Ruangan
    {
        return Ruangan::query()->where('kode', $kode)->where('dapat_dipinjam', true)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function bentukPemakaian(PemakaianRuangan $p): array
    {
        return [
            'id' => $p->id,
            'mulai' => $p->mulai->toIso8601String(),
            'selesai' => $p->selesai->toIso8601String(),
            'kegiatan' => $p->kegiatan,
            'sumber' => $p->sumber,
            'referensi_eksternal' => $p->referensi_eksternal,
            'status' => $p->status,
        ];
    }
}
