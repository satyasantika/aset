<?php

use App\Models\Peminjaman;
use App\Support\LockAset;
use App\Support\NomorTransaksi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * Pengganti Cache::lock (STANDAR-TEKNIS §1b): DB::transaction + lockForUpdate + UNIQUE.
 * Konkurensi sungguhan (dua proses) TIDAK dibuktikan di sini: RefreshDatabase membungkus uji dalam satu
 * transaksi/koneksi. Pembuktian dua proses perlu uji terpisah tanpa transaksi pembungkus (lihat catatan paket).
 */
function galatLock(int $kode): QueryException
{
    $pdo = new PDOException('galat lock');
    $pdo->errorInfo = ['HY000', $kode, 'galat lock'];

    return new QueryException('mariadb', 'select 1', [], $pdo);
}

it('mengunci baris penomoran saat seksi kritis bernama berjalan', function () {
    DB::enableQueryLog();
    $hasil = LockAset::satu('mutex:uji', fn () => 'selesai');
    $kueri = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    expect($hasil)->toBe('selesai')
        ->and($kueri->contains(fn (string $q) => str_contains($q, 'from `penomoran`') && str_contains($q, 'for update')))->toBeTrue()
        ->and(DB::table('penomoran')->where('kunci', 'mutex:uji')->exists())->toBeTrue();
});

it('mengubah lock wait timeout dan deadlock menjadi galat validasi yang ramah', function (int $kode) {
    expect(fn () => LockAset::satu('mutex:uji', fn () => throw galatLock($kode)))
        ->toThrow(ValidationException::class, 'Proses serupa sedang berjalan');

    expect(fn () => LockAset::dengan('pinjam', ['a'], fn () => throw galatLock($kode)))
        ->toThrow(ValidationException::class, 'Aset sedang diproses pengguna lain');
})->with([1205, 1213]);

it('meneruskan galat basis data lain apa adanya', function () {
    expect(fn () => LockAset::satu('mutex:uji', fn () => throw galatLock(1062)))->toThrow(QueryException::class);
});

it('menomori berurutan per jenis dan tahun lewat tabel penomoran', function () {
    $nomor = fn () => NomorTransaksi::buat('PJM', Peminjaman::class, 5, fn (string $n) => $n);
    $tahun = now()->year;

    expect($nomor())->toBe("PJM-{$tahun}-00001")
        ->and($nomor())->toBe("PJM-{$tahun}-00002")
        ->and((int) DB::table('penomoran')->where('kunci', "nomor:PJM:{$tahun}")->value('terakhir'))->toBe(2);
});

it('menyemai penghitung dari nomor terbesar yang sudah ada (data lama)', function () {
    $tahun = now()->year;
    Peminjaman::factory()->create(['nomor' => "PJM-{$tahun}-00041"]);

    expect(NomorTransaksi::buat('PJM', Peminjaman::class, 5, fn (string $n) => $n))->toBe("PJM-{$tahun}-00042");
});

it('tidak melompati nomor bila penyimpanan dibatalkan', function () {
    $tahun = now()->year;

    try {
        DB::transaction(fn () => NomorTransaksi::buat('PJM', Peminjaman::class, 5, fn () => throw new RuntimeException('batal')));
    } catch (RuntimeException) {
    }

    expect(NomorTransaksi::buat('PJM', Peminjaman::class, 5, fn (string $n) => $n))->toBe("PJM-{$tahun}-00001");
});
