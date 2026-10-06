<?php

use App\Actions\Label\ResolusiLabel;
use App\Enums\KondisiAset;
use App\Enums\StatusAset;
use App\Enums\StatusBmn;
use App\Models\Aset;
use App\Models\ImporSiman2Log;
use App\Models\LabelLama;
use Database\Seeders\KodefikasiBarangSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Siman2Fixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    $this->seed(KodefikasiBarangSeeder::class); // 3100203001 (PC), 3100102002 (kursi) valid sub-sub kelompok
    Notification::fake();
});

afterEach(fn () => Siman2Fixture::bersihkan());

function imporInv(array $sheet, array $opsi = []): void
{
    $pemetaan = Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id']);
    test()->artisan('siman2:impor', ['berkas' => Siman2Fixture::buat($sheet), '--pemetaan' => $pemetaan, ...$opsi])->run();
}

function unit(string $kodeInternal): Aset
{
    return Aset::query()->where('kode_internal', $kodeInternal)->firstOrFail();
}

it('memecah baris jumlah n menjadi n aset dengan kode_internal KODE-i (n > 1) dan KODE (n = 1)', function () {
    imporInv(Siman2Fixture::denganInventaris());

    expect(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-1')->count())->toBe(5)
        ->and(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-3')->count())->toBe(3)
        ->and(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-3')->pluck('kode_internal')->sort()->values()->all())->toBe(['935464-1', '935464-2', '935464-3'])
        ->and(Aset::query()->where('kode_internal', '49281')->exists())->toBeTrue();   // n = 1 → tanpa sufiks
});

it('memetakan atribut: nama, merk, penguasaan, tahun/tanggal, ruangan, keterangan', function () {
    imporInv(Siman2Fixture::denganInventaris());

    $a = unit('83719-1');
    expect($a)->nama->toBe('Laptop ASUS ROG')->merk_tipe->toBe('Asus')->penguasaan->toBe('milik_sendiri')->tahun_perolehan->toBe(2026)
        ->keterangan->toBe('Untuk praktikum')->status->toBe(StatusAset::Aktif)->dapat_dipinjam->toBeFalse()
        ->and($a->tanggal_perolehan->toDateString())->toBe('2026-11-01')
        ->and($a->ruangan->nama)->toBe('Lab 1')
        ->and(unit('935464-1')->penguasaan)->toBe('milik_negara')
        ->and(unit('935464-1')->tanggal_perolehan->toDateString())->toBe('2022-08-01')
        ->and(unit('555111-1')->tanggal_perolehan)->toBeNull();   // bulan kosong
});

it('kondisi per unit dari unitKondisi, selain itu kondisi baris; riwayat sumber migrasi', function () {
    imporInv(Siman2Fixture::denganInventaris());

    expect(unit('83719-1')->kondisi)->toBe(KondisiAset::Baik)
        ->and(unit('83719-2')->kondisi)->toBe(KondisiAset::RusakRingan)
        ->and(unit('83719-5')->kondisi)->toBe(KondisiAset::RusakBerat);

    $riwayat = unit('83719-2')->riwayatKondisi()->first();
    expect($riwayat)->dari->toBeNull()->ke->toBe('RR')->sumber->toBe('migrasi')
        ->and(unit('83719-2')->riwayatLokasi()->first()->sumber)->toBe('migrasi')
        ->and(unit('83719-2')->riwayatStatus()->first()->ke)->toBe('aktif');
});

it('unit berkondisi Hilang diimpor berstatus hilang dengan peringatan', function () {
    imporInv(Siman2Fixture::denganInventaris());

    expect(unit('935464-3'))->status->toBe(StatusAset::Hilang)->kondisi->toBe(KondisiAset::Baik)
        ->and(unit('935464-2')->status)->toBe(StatusAset::Aktif);
});

it('dicetak_pada terisi untuk unit di printedUnits saja', function () {
    imporInv(Siman2Fixture::denganInventaris());

    expect(unit('83719-1')->dicetak_pada)->not->toBeNull()->and(unit('83719-3')->dicetak_pada)->not->toBeNull()
        ->and(unit('83719-4')->dicetak_pada)->toBeNull()->and(unit('83719-5')->dicetak_pada)->toBeNull()
        ->and(unit('49281')->dicetak_pada)->toBeNull();
});

it('kode_barang hanya dari kodeBmn valid di kodefikasi; NUP hanya bila n = 1 atau --nup-berurutan', function () {
    imporInv(Siman2Fixture::denganInventaris());

    // kodeBmn valid + NUP, n = 1? (laptop n = 5 tanpa --nup-berurutan → NUP tidak dipakai → belum tercatat)
    expect(unit('83719-1'))->status_bmn->toBe(StatusBmn::BelumTercatat)->kode_barang->toBeNull()->nup->toBeNull()
        // kodeBmn 777778 tidak ada di kodefikasi → kode_barang null → belum tercatat walau NUP ada
        ->and(unit('49281'))->status_bmn->toBe(StatusBmn::BelumTercatat)->kode_barang->toBeNull()->nup->toBeNull();
});

it('--nup-berurutan memberi NUP awal + i - 1 dan status tercatat bila kode valid', function () {
    imporInv(Siman2Fixture::denganInventaris(), ['--nup-berurutan' => true]);

    expect(unit('935464-1'))->status_bmn->toBe(StatusBmn::Tercatat)->kode_barang->toBe('3100102002')->nup->toBe(100)
        ->and(unit('935464-3')->nup)->toBe(102)
        ->and(unit('83719-5'))->kode_barang->toBe('3100203001')->nup->toBe(123460);
});

it('n = 1 dengan NUP dan kodeBmn valid langsung tercatat', function () {
    $d = Siman2Fixture::denganInventaris();
    $d['inventaris'] = [[...$d['inventaris'][1], 'kodeBmn' => '3100203001', 'nup' => '777']];
    imporInv($d);

    expect(unit('49281'))->status_bmn->toBe(StatusBmn::Tercatat)->kode_barang->toBe('3100203001')->nup->toBe(777);
});

it('membuat label_lama KODEKATEGORI-KODE-i, KODE-i, dan KODE/NUP/KODEBMN untuk unit 1', function () {
    imporInv(Siman2Fixture::denganInventaris(), ['--nup-berurutan' => true]);

    $teks = fn (string $kode) => LabelLama::query()->where('aset_id', unit($kode)->id)->pluck('teks')->sortBy(fn ($t) => $t, SORT_STRING)->values()->all();

    expect($teks('935464-1'))->toBe(['100', '3100102002', '935464', '935464-1', 'MBL-935464-1'])
        ->and($teks('935464-2'))->toBe(['935464-2', 'MBL-935464-2'])
        ->and($teks('83719-3'))->toBe(['83719-3', 'ELE-83719-3']);

    // resolusi label lama: MBL-935464-2 → unit 2; teks KODE-i dan KODE (unit 1)
    $r = app(ResolusiLabel::class);
    expect($r->handle('MBL-935464-2')->kode_internal)->toBe('935464-2')
        ->and($r->handle('mbl-935464-3')->kode_internal)->toBe('935464-3')
        ->and($r->handle('935464-2')->kode_internal)->toBe('935464-2')
        ->and($r->handle('935464')->kode_internal)->toBe('935464-1')
        ->and($r->handle('3100102002')->kode_internal)->toBe('935464-1')
        ->and($r->handle('935464-9'))->toBeNull();
});

it('induk terdampak mutasi unit: label_perlu_cetak_ulang dan tanpa label_lama ambigu (R-17)', function () {
    imporInv(Siman2Fixture::denganInventaris());

    // induk 555111 jumlah 4; mutasi unit disetujui dari unit 2 → unit 2..4 terdampak; unit 1 tidak
    expect(unit('555111-1')->label_perlu_cetak_ulang)->toBeFalse()
        ->and(unit('555111-2')->label_perlu_cetak_ulang)->toBeTrue()
        ->and(unit('555111-3')->label_perlu_cetak_ulang)->toBeTrue()
        ->and(unit('555111-4')->label_perlu_cetak_ulang)->toBeTrue();

    foreach (['555111-2', '555111-3', '555111-4'] as $k) {
        expect(LabelLama::query()->where('aset_id', unit($k)->id)->count())->toBe(0);
    }
    expect(LabelLama::query()->where('aset_id', unit('555111-1')->id)->pluck('teks')->all())->toContain('MBL-555111-1');

    // label ambigu tidak boleh meresolusi apa pun
    expect(app(ResolusiLabel::class)->handle('MBL-555111-3'))->toBeNull();
});

it('baris hasil mutasi (keterangan "Hasil mutasi 1 unit") ditandai cetak ulang tanpa label_lama', function () {
    imporInv(Siman2Fixture::denganInventaris());

    // kode_internal baris hasil mutasi ("555111-2") bentrok dengan unit 2 milik induk → diberi sufiks ~{id lama}
    $hasil = Aset::query()->where('kelompok_pengadaan', 'SIMAN2-5')->first();

    expect($hasil)->label_perlu_cetak_ulang->toBeTrue()->kode_internal->toBe('555111-2~5')->kondisi->toBe(KondisiAset::RusakRingan)
        ->and(LabelLama::query()->where('aset_id', $hasil->id)->count())->toBe(0)
        ->and(unit('555111-2')->id)->not->toBe($hasil->id);
});

it('mutasi unit yang belum disetujui tidak menandai apa pun', function () {
    imporInv(Siman2Fixture::denganInventaris());

    // mutasi #2 (Pending) menyasar laptop unit ≥ 1: tidak boleh mengaktifkan penanda
    expect(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-1')->where('label_perlu_cetak_ulang', true)->count())->toBe(0);
});

it('foto Drive menjadi tautan jenis foto untuk setiap unit; foto non-Drive diabaikan dengan peringatan', function () {
    imporInv(Siman2Fixture::denganInventaris());

    $laptop = Aset::query()->where('kelompok_pengadaan', 'SIMAN2-1')->get();
    foreach ($laptop as $a) {
        expect($a->tautanBerkas()->where('jenis', 'foto')->first()->url)->toBe('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view');
    }
    expect(unit('49281')->tautanBerkas()->count())->toBe(0);
});

it('baris dengan ruangan tak dikenal menjadi galat tanpa membuat unit sebagian (atomik per baris)', function () {
    $d = Siman2Fixture::denganInventaris();
    $d['inventaris'][2]['lokasiBarang'] = 'Ruang Gaib';

    $pemetaan = Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id']);
    test()->artisan('siman2:impor', ['berkas' => Siman2Fixture::buat($d), '--pemetaan' => $pemetaan])
        ->assertFailed()->expectsOutputToContain('Ruang Gaib');

    expect(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-3')->count())->toBe(0)
        ->and(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-1')->count())->toBe(5)
        ->and(ImporSiman2Log::query()->where('sheet', 'inventaris')->where('id_lama', '3')->value('status'))->toBe('galat');
});

it('galat untuk kondisi tak dikenal, jumlah tidak valid, dan kode barang kosong', function () {
    $d = Siman2Fixture::denganInventaris();
    $d['inventaris'] = [
        [...$d['inventaris'][1], 'id' => 11, 'kondisi' => 'Setengah Rusak'],
        [...$d['inventaris'][1], 'id' => 12, 'kodeBarang' => '', 'namaBarang' => 'Tanpa kode'],
        [...$d['inventaris'][1], 'id' => 13, 'kodeBarang' => '99999', 'jumlah' => 0],
    ];

    $pemetaan = Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id']);
    test()->artisan('siman2:impor', ['berkas' => Siman2Fixture::buat($d), '--pemetaan' => $pemetaan])
        ->assertFailed()->expectsOutputToContain('Kondisi tidak dikenal')->expectsOutputToContain('kodeBarang kosong')->expectsOutputToContain('jumlah tidak valid');

    expect(Aset::count())->toBe(0);
});

it('Σ jumlah sheet inventaris sama dengan jumlah aset hasil impor', function () {
    imporInv(Siman2Fixture::denganInventaris());

    $sumber = array_sum(array_column(Siman2Fixture::denganInventaris()['inventaris'], 'jumlah'));
    expect(Aset::count())->toBe($sumber)->and($sumber)->toBe(14);
});

it('idempoten: impor ulang tidak menggandakan aset, label, riwayat, atau tautan', function () {
    imporInv(Siman2Fixture::denganInventaris(), ['--nup-berurutan' => true]);
    $hitung = fn () => [Aset::count(), LabelLama::count(), DB::table('riwayat_kondisi_aset')->count(), DB::table('tautan_berkas')->count(), ImporSiman2Log::count()];
    $pertama = $hitung();

    imporInv(Siman2Fixture::denganInventaris(), ['--nup-berurutan' => true]);

    expect($hitung())->toBe($pertama);
});

it('impor ulang memperbarui nilai sumber dan menambah unit baru bila jumlah bertambah', function () {
    imporInv(Siman2Fixture::denganInventaris());

    $d = Siman2Fixture::denganInventaris();
    $d['inventaris'][2]['namaBarang'] = 'Kursi Kuliah Baru';
    $d['inventaris'][2]['jumlah'] = 4;
    imporInv($d);

    expect(Aset::query()->where('kelompok_pengadaan', 'SIMAN2-3')->count())->toBe(4)
        ->and(unit('935464-1')->nama)->toBe('Kursi Kuliah Baru')
        ->and(unit('935464-4')->nama)->toBe('Kursi Kuliah Baru');
});

it('dry-run inventaris tidak menulis aset, label, atau tautan', function () {
    imporInv(Siman2Fixture::denganInventaris(), ['--dry-run' => true]);

    expect(Aset::count())->toBe(0)->and(LabelLama::count())->toBe(0)->and(DB::table('tautan_berkas')->count())->toBe(0);
});

it('label bentrok dengan aset lain tidak ditimpa dan dilaporkan sebagai peringatan', function () {
    $d = Siman2Fixture::denganInventaris();
    $d['inventaris'] = [
        [...$d['inventaris'][1], 'id' => 21, 'kodeBarang' => 'AAA', 'nup' => '5', 'kodeBmn' => ''],
        [...$d['inventaris'][1], 'id' => 22, 'kodeBarang' => 'BBB', 'nup' => '5', 'kodeBmn' => ''], // NUP "5" sama → teks label "5" bentrok
    ];
    $d['mutasi'] = [];

    $pemetaan = Siman2Fixture::pemetaan(['admin' => 'admin@unsil.ac.id', 'petugas1' => 'petugas1@unsil.ac.id', 'pimpinan' => 'pimpinan@unsil.ac.id']);
    test()->artisan('siman2:impor', ['berkas' => Siman2Fixture::buat($d), '--pemetaan' => $pemetaan])
        ->assertSuccessful()->expectsOutputToContain('bentrok dengan aset lain');

    expect(LabelLama::query()->where('teks', '5')->count())->toBe(1)
        ->and(LabelLama::query()->where('teks', '5')->first()->aset_id)->toBe(unit('AAA')->id);
});
