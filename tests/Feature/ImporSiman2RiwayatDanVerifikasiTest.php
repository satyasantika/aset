<?php

use App\Actions\Migrasi\Siman2\BacaXlsx;
use App\Actions\Migrasi\Siman2\VerifikasiMigrasi;
use App\Enums\StatusMutasi;
use App\Enums\StatusPeminjaman;
use App\Filament\Resources\Aset\Pages\LabelPerluCetakUlang;
use App\Models\Aset;
use App\Models\ImporSiman2Log;
use App\Models\Mutasi;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use Database\Seeders\KodefikasiBarangSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Siman2Fixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    $this->seed(KodefikasiBarangSeeder::class);
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

afterEach(fn () => Siman2Fixture::bersihkan());

function pemetaanLengkap(): string
{
    return Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id']);
}

/** Berkas di luar storage/app/tmp agar tidak dihapus oleh impor dan dapat dipakai lagi untuk verifikasi. */
function imporLengkap(?array $sheet = null, array $opsi = []): string
{
    $berkas = Siman2Fixture::buat($sheet ?? Siman2Fixture::lengkap(), sys_get_temp_dir().'/siman2-uji-'.bin2hex(random_bytes(4)).'.xlsx');
    test()->artisan('siman2:impor', ['berkas' => $berkas, '--pemetaan' => pemetaanLengkap(), ...$opsi])->run();

    return $berkas;
}

function unitSiman(string $kodeInternal): Aset
{
    return Aset::query()->where('kode_internal', $kodeInternal)->firstOrFail();
}

it('mengimpor mutasi: ruangan lewat nama, status, pemohon pada alasan, tanpa pelaku terverifikasi', function () {
    imporLengkap();

    $disetujui = Mutasi::query()->with(['asal', 'tujuan'])->where('nomor', 'MUT-S2-00001')->first();
    $pending = Mutasi::query()->where('nomor', 'MUT-S2-00002')->first();

    expect($disetujui)->status->toBe(StatusMutasi::Disetujui)->diajukan_oleh->toBeNull()->diputuskan_oleh->toBeNull()
        ->and($disetujui->asal->nama)->toBe('Lab 1')->and($disetujui->tujuan->nama)->toBe('Aula Utama')
        ->and($disetujui->alasan)->toContain('Dipindah')->toContain('[Pemohon SIMAN-2: Petugas Lab 1]')
        ->and($disetujui->created_at->format('Y-m-d H:i'))->toBe('2026-02-08 10:00')
        ->and($disetujui->diputuskan_pada)->not->toBeNull()
        ->and($pending)->status->toBe(StatusMutasi::Diajukan)->diputuskan_pada->toBeNull();
});

it('mutasi unit memetakan unit ke-n dan menulis riwayat lokasi sumber migrasi hanya untuk yang disetujui', function () {
    imporLengkap();

    $unit = Aset::query()->with('ruangan')->where('kode_internal', '555111-2')->firstOrFail();   // unit 2 induk 4
    $mutasi = Mutasi::query()->with('aset')->where('nomor', 'MUT-S2-00001')->first();

    expect($mutasi->aset->pluck('id')->all())->toBe([$unit->id]);
    $riwayat = DB::table('riwayat_lokasi_aset')->where('aset_id', $unit->id)->where('mutasi_id', $mutasi->id)->first();
    expect($riwayat->sumber)->toBe('migrasi')->and($riwayat->dari_ruangan_id)->toBe($mutasi->ruangan_asal_id)->and($riwayat->ke_ruangan_id)->toBe($mutasi->ruangan_tujuan_id)
        ->and($riwayat->created_at)->toStartWith('2026-02-08 10:00');

    // lokasi aset tidak diubah oleh impor mutasi (lokasi terkini berasal dari inventaris)
    expect($unit->ruangan->nama)->toBe('Lab 1');

    // mutasi Pending tidak menulis riwayat
    $pending = Mutasi::query()->where('nomor', 'MUT-S2-00002')->first();
    expect(DB::table('riwayat_lokasi_aset')->where('mutasi_id', $pending->id)->count())->toBe(0);
});

it('mutasi tanpa aset yang dapat dipetakan, ruangan tak dikenal, atau status asing menjadi galat', function () {
    $d = Siman2Fixture::lengkap();
    $d['mutasi'][] = ['id' => 7, 'tanggal' => '01/01/2026 08:00', 'idBarang' => 999, 'kodeBarang' => 'X-1', 'asal' => 'Lab 1', 'tujuan' => 'Lab 2', 'pemohon' => '', 'alasan' => 'x', 'status' => 'Disetujui', 'tipeMutasi' => 'induk'];
    $d['mutasi'][] = ['id' => 8, 'tanggal' => '01/01/2026 08:00', 'idBarang' => 1, 'kodeBarang' => '83719-1', 'asal' => 'Ruang Gaib', 'tujuan' => 'Lab 2', 'pemohon' => '', 'alasan' => 'x', 'status' => 'Disetujui', 'tipeMutasi' => 'unit', 'unitIndex' => 1];
    $d['mutasi'][] = ['id' => 9, 'tanggal' => '01/01/2026 08:00', 'idBarang' => 1, 'kodeBarang' => '83719-1', 'asal' => 'Lab 1', 'tujuan' => 'Lab 2', 'pemohon' => '', 'alasan' => 'x', 'status' => 'Entah', 'tipeMutasi' => 'unit', 'unitIndex' => 1];

    $berkas = Siman2Fixture::buat($d);
    $this->artisan('siman2:impor', ['berkas' => $berkas, '--pemetaan' => pemetaanLengkap()])
        ->assertFailed()->expectsOutputToContain('Tidak ada aset yang dapat dipetakan')->expectsOutputToContain('Ruang Gaib')->expectsOutputToContain('tidak dikenal');

    expect(Mutasi::count())->toBe(2);
});

it('mutasi induk (seluruh baris) memindahkan semua unit baris itu', function () {
    $d = Siman2Fixture::lengkap();
    $d['mutasi'] = [['id' => 1, 'tanggal' => '05/03/2026 09:00', 'idBarang' => 3, 'kodeBarang' => '935464', 'asal' => 'Lab 2', 'tujuan' => 'Aula Utama', 'pemohon' => 'x', 'alasan' => 'Pindah semua', 'status' => 'Disetujui', 'tipeMutasi' => 'induk']];
    imporLengkap($d);

    expect(Mutasi::first()->aset()->count())->toBe(3);
});

it('mengelompokkan peminjaman per kodeTransaksi dengan item per unit dan kondisi saat kembali', function () {
    imporLengkap();

    expect(Peminjaman::count())->toBe(3);   // seed-3 (unit gaib) galat
    $dua = Peminjaman::query()->with('item.aset')->where('keperluan', 'like', '%PJM-seed-2%')->first();
    expect($dua->item)->toHaveCount(2)->and($dua->status)->toBe(StatusPeminjaman::Dikembalikan)
        ->and($dua->nama_peminjam)->toBe('Siti')->and($dua->kontak_peminjam)->toBe('0813')
        ->and($dua->mulai->format('Y-m-d H:i'))->toBe('2026-09-02 08:00')
        ->and($dua->dikembalikan_pada->format('Y-m-d H:i'))->toBe('2026-09-03 15:00')
        ->and($dua->rencana_kembali->format('Y-m-d H:i'))->toBe('2026-09-03 23:59')   // tanggal tanpa jam → akhir hari
        ->and($dua->nomor)->toStartWith('PJM-S2-')->and($dua->keperluan)->toContain('[Nomor lama: PJM-seed-2; dicatat oleh: Administrator]')
        ->and($dua->dicatat_oleh)->toBeNull()->and($dua->peminjam_user_id)->toBeNull();

    $kondisiKembali = $dua->item->mapWithKeys(fn ($i) => [$i->aset->kode_internal => $i->kondisi_saat_kembali?->value])->all();
    expect($kondisiKembali)->toBe(['83719-1' => 'B', '83719-3' => 'RR']);
});

it('kodeUnit diresolusi lewat label_lama (format KATEGORI-KODE-i dan KODE-i)', function () {
    imporLengkap();

    $satu = Peminjaman::query()->with('item.aset')->where('keperluan', 'like', '%PJM-seed-1%')->first();
    $dua = Peminjaman::query()->with('item.aset')->where('keperluan', 'like', '%PJM-seed-2%')->first();

    expect($satu->item->first()->aset->kode_internal)->toBe('935464-2')                                // '935464-2'
        ->and($dua->item->pluck('aset.kode_internal')->sort()->values()->all())->toBe(['83719-1', '83719-3']); // '83719-1' dan 'ELE-83719-3'
});

it('semua status Dipinjam lama muncul sebagai pinjaman aktif dan aset dihitung sedang dipinjam', function () {
    imporLengkap();

    $aktif = Peminjaman::query()->where('status', StatusPeminjaman::Dipinjam->value)->get();

    expect($aktif)->toHaveCount(2)                                    // seed-1 dan seed-4 (seed-3 galat)
        ->and(unitSiman('935464-2')->sedangDipinjam)->toBeTrue()
        ->and(unitSiman('49281')->sedangDipinjam)->toBeTrue()
        ->and(unitSiman('83719-1')->sedangDipinjam)->toBeFalse();
});

it('rencana kembali sebelum tanggal pinjam diperbaiki dengan peringatan; unit tak dikenal menggagalkan transaksi', function () {
    $berkas = Siman2Fixture::buat(Siman2Fixture::lengkap());

    $this->artisan('siman2:impor', ['berkas' => $berkas, '--pemetaan' => pemetaanLengkap()])
        ->assertFailed()
        ->expectsOutputToContain('Rencana kembali tidak setelah tanggal pinjam')
        ->expectsOutputToContain('Unit "GAIB-1" tidak dapat dipetakan');

    $kamera = Peminjaman::query()->where('keperluan', 'like', '%PJM-seed-4%')->first();
    expect($kamera->rencana_kembali->format('Y-m-d H:i'))->toBe('2026-09-01 23:59')
        ->and(ImporSiman2Log::query()->where('sheet', 'peminjaman')->where('id_lama', 'PJM-seed-3')->value('status'))->toBe('galat');
});

it('status peminjaman asing dan tanggal pinjam kosong menjadi galat', function () {
    $d = Siman2Fixture::lengkap();
    $d['peminjaman'] = [
        [...$d['peminjaman'][0], 'kodeTransaksi' => 'A', 'status' => 'Dibatalkan'],
        [...$d['peminjaman'][0], 'kodeTransaksi' => 'B', 'tanggalPinjam' => ''],
    ];
    $this->artisan('siman2:impor', ['berkas' => Siman2Fixture::buat($d), '--pemetaan' => pemetaanLengkap()])
        ->assertFailed();

    expect(Peminjaman::count())->toBe(0)
        ->and(ImporSiman2Log::query()->where('sheet', 'peminjaman')->where('id_lama', 'A')->value('pesan'))->toContain('tidak dikenal')
        ->and(ImporSiman2Log::query()->where('sheet', 'peminjaman')->where('id_lama', 'B')->value('pesan'))->toContain('tanggalPinjam kosong');
});

it('log aktivitas lama masuk activity_log (log_name siman2) dengan pelaku_lama sebagai properti, bukan causer', function () {
    imporLengkap();

    $log = DB::table('activity_log')->where('log_name', 'siman2')->orderBy('created_at')->get();

    expect($log)->toHaveCount(2)
        ->and($log[0]->description)->toBe('Login')->and($log[0]->causer_id)->toBeNull()->and($log[0]->causer_type)->toBeNull()
        ->and($log[0]->created_at)->toStartWith('2026-01-06 20:30')
        ->and(json_decode($log[0]->properties, true))->toMatchArray(['pelaku_lama' => 'Administrator', 'detail' => 'User berhasil login', 'waktu_lama' => '6 Jan 2026, 20.30'])
        ->and($log[1]->created_at)->toStartWith('2026-01-07 08:15');
});

it('impor lengkap bersifat idempoten', function () {
    imporLengkap();
    $hitung = fn () => [
        Aset::count(), Mutasi::count(), Peminjaman::count(), DB::table('peminjaman_item')->count(), DB::table('mutasi_item')->count(),
        DB::table('riwayat_lokasi_aset')->count(), DB::table('activity_log')->where('log_name', 'siman2')->count(), User::count(), Ruangan::count(),
    ];
    $pertama = $hitung();

    imporLengkap();

    expect($hitung())->toBe($pertama);
});

it('dry-run lengkap tidak menulis riwayat apa pun', function () {
    imporLengkap(null, ['--dry-run' => true]);

    expect(Mutasi::count())->toBe(0)->and(Peminjaman::count())->toBe(0)->and(DB::table('activity_log')->where('log_name', 'siman2')->count())->toBe(0)->and(Aset::count())->toBe(0);
});

it('siman2:verifikasi lolos setelah impor yang bersih dan menampilkan rekonsiliasi', function () {
    $d = Siman2Fixture::lengkap();
    unset($d['peminjaman'][3]);                 // buang transaksi bergalat agar impor bersih
    $d['peminjaman'] = array_values($d['peminjaman']);
    $d['peminjaman'][3]['tanggalRencanaKembali'] = '2026-09-02';
    $berkas = imporLengkap($d);

    $this->artisan('siman2:verifikasi', ['berkas' => $berkas])
        ->assertSuccessful()
        ->expectsOutputToContain('Jumlah unit')->expectsOutputToContain('Ruangan: lab 1')->expectsOutputToContain('Kondisi RR')
        ->expectsOutputToContain('Pinjaman aktif')->expectsOutputToContain('Rekonsiliasi lolos');

    $baris = app(VerifikasiMigrasi::class)->hitung(new BacaXlsx($berkas));
    $peta = collect($baris)->keyBy('cek');

    expect($peta['Jumlah unit (Σ jumlah vs aset)'])->toMatchArray(['sumber' => 14, 'baru' => 14, 'status' => 'OK'])
        ->and($peta['Kondisi RR']['sumber'])->toBe(2)->and($peta['Kondisi RB']['sumber'])->toBe(1)->and($peta['Hilang']['sumber'])->toBe(1)
        ->and($peta['Pinjaman aktif (per transaksi)'])->toMatchArray(['sumber' => 2, 'baru' => 2, 'status' => 'OK'])
        ->and($peta['Pengguna'])->toMatchArray(['sumber' => 3, 'baru' => 3, 'status' => 'OK']);
});

it('siman2:verifikasi gagal bila jumlah tidak cocok (mis. aset terhapus setelah impor)', function () {
    $berkas = imporLengkap();
    DB::table('aset')->where('kode_internal', '83719-4')->update(['kelompok_pengadaan' => 'MANUAL']);   // selisih jumlah unit

    $this->artisan('siman2:verifikasi', ['berkas' => $berkas])->assertFailed()->expectsOutputToContain('BEDA');
});

it('siman2:verifikasi tanpa berkas hanya menampilkan angka sistem baru', function () {
    imporLengkap();

    $this->artisan('siman2:verifikasi')->assertSuccessful()->expectsOutputToContain('perbandingan tidak dilakukan');
});

it('halaman "Label perlu cetak ulang" mengelompokkan per ruangan dan membatasi PIC pada ruangannya', function () {
    imporLengkap();
    $lab1 = Ruangan::query()->where('nama', 'Lab 1')->first();
    $admin = User::factory()->create();
    $admin->assignRole('admin-bmn');
    $admin->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    $pic = User::factory()->create();
    $pic->assignRole('pic-ruangan');
    $pic->ruanganDikelola()->attach($lab1->id);
    $pic->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    $this->actingAs($admin);
    $this->get('/admin/aset/label-perlu-cetak-ulang')->assertOk();
    $terdampak = Aset::query()->where('label_perlu_cetak_ulang', true)->get();
    expect($terdampak)->not->toBeEmpty();

    Livewire::test(LabelPerluCetakUlang::class)->assertCanSeeTableRecords($terdampak)
        ->assertCanNotSeeTableRecords(Aset::query()->where('label_perlu_cetak_ulang', false)->get());

    $this->actingAs($pic);
    $milik = $terdampak->where('ruangan_id', $lab1->id);
    $lain = $terdampak->where('ruangan_id', '!=', $lab1->id);
    Livewire::test(LabelPerluCetakUlang::class)->assertCanSeeTableRecords($milik)->assertCanNotSeeTableRecords($lain);
});

it('halaman label perlu cetak ulang tertutup bagi peran tanpa label.cetak', function () {
    $pimpinan = User::factory()->create();
    $pimpinan->assignRole('pimpinan');
    $pimpinan->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');

    $this->actingAs($pimpinan)->get('/admin/aset/label-perlu-cetak-ulang')->assertForbidden();
});
