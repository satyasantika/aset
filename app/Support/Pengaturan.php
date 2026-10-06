<?php

namespace App\Support;

use App\Exceptions\FiturNonaktif;
use App\Models\PengaturanItem;
use Illuminate\Support\Facades\Cache;

/**
 * Akses pengaturan sistem (kunci/nilai) dengan cache. Toggle fitur (BR-22) dibaca lewat `fitur()` dan
 * ditegakkan di Action dengan `pastikanFitur()`, bukan hanya di UI.
 */
class Pengaturan
{
    public const KUNCI_CACHE = 'aset:pengaturan';

    /** Nilai bawaan; dipakai bila baris belum ada di basis data. @var array<string, mixed> */
    public const BAWAAN = [
        'instansi_baris1' => 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI',
        'instansi_baris2' => 'UNIVERSITAS SILIWANGI',
        'nama_kampus' => 'Universitas Siliwangi',
        'nama_unit' => 'Fakultas Keguruan dan Ilmu Pendidikan',
        'alamat' => 'Jl. Siliwangi No. 24, Tasikmalaya',
        'kontak' => '',
        'kota_surat' => 'Tasikmalaya',
        'penandatangan_nama' => '',
        'penandatangan_nip' => '',
        'penandatangan_jabatan' => 'Kepala Subbagian Umum',
        'penanggung_jawab_nama' => '',
        'penanggung_jawab_nip' => '',
        'penanggung_jawab_jabatan' => 'Wakil Dekan',
        'fitur_tambah_aset' => true,
        'fitur_hapus_aset' => true,
        'fitur_ubah_kondisi' => true,
        'fitur_mutasi' => true,
        'fitur_peminjaman' => true,
        'fitur_tahan_mutasi_saat_inventarisasi' => true,
        'ambang_pengingat_inventarisasi_tahun' => 4,
        'retensi_peminjaman_bulan' => 36,
        'maks_hari_pinjam' => 14,
    ];

    /** @var list<string> */
    public const FITUR = ['tambah_aset', 'hapus_aset', 'ubah_kondisi', 'mutasi', 'peminjaman'];

    /** @return array<string, mixed> */
    public static function semua(): array
    {
        /** @var array<string, mixed> $tersimpan */
        $tersimpan = Cache::rememberForever(self::KUNCI_CACHE, fn () => PengaturanItem::query()->pluck('nilai', 'kunci')->all());

        return [...self::BAWAAN, ...$tersimpan];
    }

    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        return self::semua()[$kunci] ?? $bawaan;
    }

    public static function simpan(string $kunci, mixed $nilai): void
    {
        PengaturanItem::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        self::bersihkan();
    }

    /** @param  array<string, mixed>  $pasangan */
    public static function simpanBanyak(array $pasangan): void
    {
        foreach ($pasangan as $kunci => $nilai) {
            PengaturanItem::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        }

        self::bersihkan();
    }

    public static function bersihkan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    public static function fitur(string $nama): bool
    {
        return (bool) self::ambil('fitur_'.$nama, true);
    }

    /** @throws FiturNonaktif */
    public static function pastikanFitur(string $nama): void
    {
        if (! self::fitur($nama)) {
            throw FiturNonaktif::untuk($nama);
        }
    }
}
