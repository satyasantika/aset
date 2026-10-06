<?php

namespace App\Actions\Migrasi\Siman2;

use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusBmn;
use App\Models\Aset;
use App\Models\ImporSiman2Log;
use App\Models\KodefikasiBarang;
use App\Models\LabelLama;
use App\Models\Ruangan;
use App\Support\UrlFotoLama;
use Illuminate\Support\Str;

/**
 * Sheet `inventaris` → `aset` (1 baris jumlah n → n aset), `riwayat_*`, `label_lama`, `tautan_berkas` (07-MIGRASI §4).
 * Identitas unit: `inventaris_unit` "{id lama}#{i}" pada impor_siman2_log (idempoten). Label unit terdampak mutasi
 * unit (R-17) ditandai `label_perlu_cetak_ulang` dan TIDAK dibuatkan `label_lama` ambigu (§4a).
 */
class ImporInventaris implements Importer
{
    public const MAKS_UNIT_PER_BARIS = 5000;

    /** @var array<string, string> nama ruangan (huruf kecil) → id */
    private array $ruangan = [];

    /** @var array<string, string> nama kategori (huruf kecil) → kode kategori */
    private array $kodeKategori = [];

    /** @var array<int|string, true> kode barang valid (tingkat 5) */
    private array $kodeValid = [];

    private AnalisisMutasiUnit $mutasi;

    public function nama(): string
    {
        return 'inventaris';
    }

    public function jalankan(Konteks $konteks, BacaXlsx $xlsx): void
    {
        $this->ruangan = Ruangan::query()->get()->mapWithKeys(fn (Ruangan $r) => [mb_strtolower(trim($r->nama)) => $r->getKey()])->all();
        $this->kodeKategori = $xlsx->semua('kategori')->mapWithKeys(fn (array $k) => [
            mb_strtolower(Nilai::teks($k['namaKategori'] ?? '')) => mb_strtoupper(Nilai::teks($k['kodeKategori'] ?? '')),
        ])->filter()->all();
        $this->kodeValid = array_fill_keys(KodefikasiBarang::query()->where('tingkat', KodefikasiBarang::TINGKAT_SUB_SUB_KELOMPOK)->pluck('kode')->all(), true);
        $this->mutasi = AnalisisMutasiUnit::dari($xlsx);

        foreach ($xlsx->baris('inventaris') as $baris) {
            $idLama = Nilai::teks($baris['id'] ?? $baris['kodeBarang'] ?? '');

            $konteks->proses('inventaris', $idLama, fn (): ?string => $this->imporBaris($konteks, $baris, $idLama));
        }
    }

    /** @param  array<string, mixed>  $baris */
    private function imporBaris(Konteks $konteks, array $baris, string $idLama): ?string
    {
        $kode = Nilai::tidakKosong($baris['kodeBarang'] ?? null) ?? throw new \InvalidArgumentException('kodeBarang kosong.');
        $nama = Nilai::tidakKosong($baris['namaBarang'] ?? null) ?? throw new \InvalidArgumentException('namaBarang kosong.');
        $jumlah = Nilai::angka($baris['jumlah'] ?? null) ?? 1;

        if ($jumlah < 1 || $jumlah > self::MAKS_UNIT_PER_BARIS) {
            throw new \InvalidArgumentException("jumlah tidak valid ({$jumlah}).");
        }

        $namaRuangan = Nilai::tidakKosong($baris['lokasiBarang'] ?? null) ?? throw new \InvalidArgumentException('lokasiBarang kosong.');
        $ruanganId = $this->ruangan[mb_strtolower(trim($namaRuangan))]
            ?? throw new \InvalidArgumentException("Ruangan \"{$namaRuangan}\" tidak ada di sheet ruangan; perbaiki pemetaan lalu impor ulang.");

        $kondisiBaris = Nilai::kondisi($baris['kondisi'] ?? null);
        $hilangBaris = mb_strtolower(Nilai::teks($baris['kondisi'] ?? '')) === 'hilang';
        $kondisiUnit = Nilai::peta($baris['unitKondisi'] ?? null);
        $dicetak = array_map('intval', Nilai::daftar($baris['printedUnits'] ?? null));
        $keterangan = Nilai::tidakKosong($baris['keterangan'] ?? null);
        $hasilMutasi = AnalisisMutasiUnit::barisHasilMutasi($keterangan);
        $tahun = Nilai::angka($baris['tahun'] ?? null);
        $bulan = Nilai::bulan($baris['bulan'] ?? null);
        $foto = Nilai::tidakKosong($baris['foto'] ?? null);
        $fotoDrive = UrlFotoLama::keDrive($foto);

        if ($foto !== null && $fotoDrive === null) {
            $konteks->peringatan('inventaris', $idLama, 'Foto bukan tautan Google Drive sehingga tidak dimigrasikan: '.Str::limit($foto, 80));
        }

        $kodeBarang = $this->kodeBarangBmn($baris);
        $nupAwal = Nilai::angka($baris['nup'] ?? null);

        if ($jumlah > 1 && $nupAwal !== null && ! $konteks->nupBerurutan) {
            $konteks->peringatan('inventaris', $idLama, "NUP {$nupAwal} untuk {$jumlah} unit tidak dipakai (gunakan --nup-berurutan bila NUP memang berurutan); unit dibuat berstatus belum tercatat.");
        }

        $idPertama = null;

        for ($i = 1; $i <= $jumlah; $i++) {
            $nup = match (true) {
                $nupAwal === null => null,
                $jumlah === 1 => $nupAwal,
                $konteks->nupBerurutan => $nupAwal + $i - 1,
                default => null,
            };

            $tercatat = $kodeBarang !== null && $nup !== null;

            $nilaiKondisiUnit = $kondisiUnit[$i] ?? null;
            $hilang = $hilangBaris || ($nilaiKondisiUnit !== null && mb_strtolower($nilaiKondisiUnit) === 'hilang');
            $kondisi = ($nilaiKondisiUnit !== null ? Nilai::kondisi($nilaiKondisiUnit) : null) ?? $kondisiBaris;

            if ($kondisi === null && ! $hilang) {
                throw new \InvalidArgumentException('Kondisi tidak dikenal untuk unit '.$i.': "'.($nilaiKondisiUnit ?? Nilai::teks($baris['kondisi'] ?? '')).'".');
            }

            if ($hilang) {
                $kondisi ??= KondisiAset::Baik;
                $konteks->peringatan('inventaris', $idLama, "Unit {$i} berkondisi \"Hilang\": diimpor berstatus hilang dengan kondisi {$kondisi->value}.");
            }

            $kodeInternal = $this->kodeInternalUnik($konteks, $idLama, $i, $jumlah === 1 ? $kode : "{$kode}-{$i}");
            $atribut = [
                'status_bmn' => $tercatat ? StatusBmn::Tercatat : StatusBmn::BelumTercatat,
                'kode_barang' => $tercatat ? $kodeBarang : null,
                'nup' => $tercatat ? $nup : null,
                'kode_internal' => $kodeInternal,
                'nama' => $nama,
                'merk_tipe' => Nilai::tidakKosong($baris['merkType'] ?? null),
                'penguasaan' => $this->penguasaan(Nilai::tidakKosong($baris['penguasaan'] ?? null)),
                'tahun_perolehan' => $tahun,
                'tanggal_perolehan' => ($tahun !== null && $bulan !== null && checkdate($bulan, 1, $tahun)) ? sprintf('%04d-%02d-01', $tahun, $bulan) : null,
                'ruangan_id' => $ruanganId,
                'lokasi_lainnya' => null,
                'kondisi' => $kondisi,
                'status' => $hilang ? StatusAset::Hilang : StatusAset::Aktif,
                'kelompok_pengadaan' => 'SIMAN2-'.$idLama,
                'keterangan' => $keterangan,
            ];

            $aset = $this->simpanUnit($konteks, $idLama, $i, $atribut);

            $terdampak = $hasilMutasi || $this->mutasi->unitTerdampak($idLama, $i);

            $perubahan = [];

            if ($terdampak) {
                $perubahan['label_perlu_cetak_ulang'] = true;
            }

            if (in_array($i, $dicetak, true) && $aset->dicetak_pada === null) {
                $perubahan['dicetak_pada'] = now();
            }

            if ($perubahan !== []) {
                $aset->forceFill($perubahan)->save();
            }

            $this->pasangLabel($konteks, $aset, $idLama, $i, $baris, $kode, $nupAwal, $terdampak);

            if ($fotoDrive !== null) {
                $tautan = $aset->tautanBerkas()->where('jenis', 'foto')->first();
                $tautan === null
                    ? $aset->tautanBerkas()->create(['jenis' => 'foto', 'label' => 'Foto (SIMAN-2)', 'url' => $fotoDrive])
                    : $tautan->update(['url' => $fotoDrive]);
            }

            $idPertama ??= $aset->getKey();
        }

        return $idPertama;
    }

    /**
     * Membuat unit baru atau memperbarui unit hasil impor sebelumnya (idempoten).
     *
     * @param  array<string, mixed>  $atribut
     */
    private function simpanUnit(Konteks $konteks, string $idLama, int $unit, array $atribut): Aset
    {
        $kunci = "{$idLama}#{$unit}";
        $idBaru = $konteks->idBaru('inventaris_unit', $kunci);
        $aset = $idBaru ? Aset::withTrashed()->find($idBaru) : null;
        $baru = $aset === null;

        $aset ??= new Aset;
        $aset->fill($atribut)->save();

        if ($baru) {
            $aset->riwayatKondisi()->create(['dari' => null, 'ke' => $aset->kondisi->value, 'sumber' => 'migrasi', 'catatan' => "Impor SIMAN-2 (inventaris #{$idLama}, unit {$unit})"]);
            $aset->riwayatLokasi()->create(['dari_ruangan_id' => null, 'ke_ruangan_id' => $aset->ruangan_id, 'sumber' => 'migrasi']);
            $aset->riwayatStatus()->create(['dari' => null, 'ke' => $aset->status->value, 'catatan' => 'Impor SIMAN-2']);
        }

        ImporSiman2Log::query()->updateOrCreate(
            ['sheet' => 'inventaris_unit', 'id_lama' => $kunci],
            ['id_baru' => $aset->getKey(), 'status' => ImporSiman2Log::OK, 'pesan' => null],
        );

        return $aset;
    }

    /**
     * `label_lama` untuk unit i: `KODEKATEGORI-KODE-i`, `KODE-i`, dan (i = 1) `KODE`, `NUP`, `KODEBMN` bila unik.
     * Unit terdampak R-17 tidak dibuatkan label.
     *
     * @param  array<string, mixed>  $baris
     */
    private function pasangLabel(Konteks $konteks, Aset $aset, string $idLama, int $unit, array $baris, string $kode, ?int $nup, bool $terdampak): void
    {
        if ($terdampak) {
            return;
        }

        $kodeUp = mb_strtoupper($kode);
        $katKode = $this->kodeKategori[mb_strtolower(Nilai::teks($baris['kategori'] ?? ''))] ?? null;

        $teks = [];
        $katKode !== null && $katKode !== '' ? $teks[] = "{$katKode}-{$kodeUp}-{$unit}" : null;
        $teks[] = "{$kodeUp}-{$unit}";

        if ($unit === 1) {
            $teks[] = $kodeUp;
            $nup !== null ? $teks[] = (string) $nup : null;
            ($bmn = Nilai::tidakKosong($baris['kodeBmn'] ?? null)) !== null ? $teks[] = mb_strtoupper($bmn) : null;
        }

        foreach (array_unique($teks) as $t) {
            $ada = LabelLama::query()->where('teks', $t)->first();

            if ($ada === null) {
                LabelLama::query()->create(['teks' => $t, 'aset_id' => $aset->getKey()]);
            } elseif ($ada->aset_id !== $aset->getKey()) {
                $konteks->peringatan('inventaris', $idLama, "Teks label \"{$t}\" bentrok dengan aset lain; tidak ditimpa.");
            }
        }
    }

    /**
     * `kode_internal` harus unik. Baris hasil mutasi unit SIMAN-2 berkode `KODE-n` bisa sama dengan unit n milik induk;
     * pada bentrok dengan aset lain, sufiks `~{id lama}` ditambahkan dan dilaporkan.
     */
    private function kodeInternalUnik(Konteks $konteks, string $idLama, int $unit, string $kode): string
    {
        $idBaru = ImporSiman2Log::query()->where('sheet', 'inventaris_unit')->where('id_lama', "{$idLama}#{$unit}")->value('id_baru');
        $pemilik = Aset::withTrashed()->where('kode_internal', $kode)->value('id');

        if ($pemilik === null || $pemilik === $idBaru) {
            return $kode;
        }

        $konteks->peringatan('inventaris', $idLama, "kode_internal \"{$kode}\" sudah dipakai aset lain; unit ini memakai \"{$kode}~{$idLama}\".");

        return "{$kode}~{$idLama}";
    }

    /** `kodeBmn` hanya dipakai bila numerik dan terdaftar sebagai sub-sub kelompok di kodefikasi_barang. */
    /** @param  array<string, mixed>  $baris */
    private function kodeBarangBmn(array $baris): ?string
    {
        $kodeBmn = Nilai::tidakKosong($baris['kodeBmn'] ?? null);

        return ($kodeBmn !== null && ctype_digit($kodeBmn) && isset($this->kodeValid[$kodeBmn])) ? $kodeBmn : null;
    }

    private function penguasaan(?string $nilai): string
    {
        return $nilai === null ? 'milik_sendiri' : Str::snake(mb_strtolower($nilai));
    }
}
