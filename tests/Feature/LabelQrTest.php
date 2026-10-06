<?php

use App\Filament\Resources\Aset\Pages\DaftarAset;
use App\Models\Aset;
use App\Models\Ruangan;
use App\Models\User;
use App\Support\GeneratorQr;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const PIKSEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** QR palsu yang mencatat isi yang akan dikodekan. */
function pasangQrPalsu(): object
{
    $perekam = new class extends GeneratorQr
    {
        /** @var list<string> */
        public array $isi = [];

        public function dataUri(string $isi, int $ukuran = 240): string
        {
            $this->isi[] = $isi;

            return PIKSEL;
        }
    };
    app()->instance(GeneratorQr::class, $perekam);

    return $perekam;
}

function pencetak(string $peran, array $ruangan = []): User
{
    $user = User::factory()->create();
    $user->assignRole($peran);
    $user->saveAppAuthenticationSecret('ABCDEFGHIJKLMNOP');
    $user->ruanganDikelola()->attach(collect($ruangan)->pluck('id'));

    return $user;
}

function ruangLbl(string $kode): Ruangan
{
    return Ruangan::query()->create(['kode' => $kode, 'nama' => "Ruang $kode"]);
}

it('men-stream PDF A4 berisi QR yang memuat URL /a/{uuid} dan tidak menyimpannya', function () {
    $qr = pasangQrPalsu();
    $aset = Aset::factory()->create(['nama' => 'Proyektor Epson']);
    $this->actingAs(pencetak('admin-bmn'));

    $respons = $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => [$aset->id]]);

    $respons->assertOk();
    expect($respons->headers->get('content-type'))->toContain('application/pdf')
        ->and($respons->getContent())->toStartWith('%PDF')
        ->and($qr->isi)->toBe([url('/a/'.$aset->id)])
        ->and($qr->isi[0])->toMatch('#/a/[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$#');
    expect(glob(storage_path('app/tmp/*.pdf')))->toBe([]);
});

it('QR nyata dibangkitkan di server sebagai PNG data URI', function () {
    $uri = app(GeneratorQr::class)->dataUri('https://contoh.test/a/abc');

    expect($uri)->toStartWith('data:image/png;base64,');
    expect(base64_decode(substr($uri, strlen('data:image/png;base64,')), true))->toStartWith("\x89PNG");
});

it('tampilan label tidak memuat gambar atau layanan QR pihak ketiga (R-16)', function () {
    $aset = Aset::factory()->create(['nama' => 'Kursi', 'merk_tipe' => 'Chitose', 'tahun_perolehan' => 2023]);
    $html = view('pdf.label', ['stiker' => collect([['aset' => $aset->load('ruangan'), 'qr' => PIKSEL]])])->render();

    expect($html)->toContain('INVENTARIS FKIP UNIVERSITAS SILIWANGI')
        ->toContain('Kursi')->toContain('Chitose')->toContain($aset->kode_tampil)->toContain('2023')->toContain($aset->ruangan->kode)
        ->not->toContain('quickchart')->not->toContain('chart.googleapis');

    preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $cocok);
    expect($cocok[1])->not->toBeEmpty();
    foreach ($cocok[1] as $sumber) {
        expect($sumber)->toStartWith('data:');
    }
});

it('menandai label dicetak: dicetak_pada terisi dan cetak ulang dikosongkan, dengan satu entri audit', function () {
    pasangQrPalsu();
    $pelaku = pencetak('admin-bmn');
    $this->actingAs($pelaku);
    $aset = Aset::factory()->count(3)->create(['label_perlu_cetak_ulang' => true]);

    $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => $aset->pluck('id')->all()])->assertOk();

    foreach ($aset as $a) {
        $a->refresh();
        expect($a->dicetak_pada)->not->toBeNull()->and($a->label_perlu_cetak_ulang)->toBeFalse();
    }
    $log = DB::table('activity_log')->where('log_name', 'label')->get();
    expect($log)->toHaveCount(1)->and($log[0]->causer_id)->toBe($pelaku->id);
});

it('tandai=0 mencetak tanpa mengubah status cetak', function () {
    pasangQrPalsu();
    $this->actingAs(pencetak('admin-bmn'));
    $aset = Aset::factory()->create();

    $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => [$aset->id], 'tandai' => 0])->assertOk();

    expect($aset->fresh()->dicetak_pada)->toBeNull();
});

it('mode belum_dicetak dan perlu_cetak_ulang memilih aset yang tepat', function () {
    $qr = pasangQrPalsu();
    $this->actingAs(pencetak('admin-bmn'));
    $belum = Aset::factory()->create();
    $sudah = Aset::factory()->create(['dicetak_pada' => now()]);
    $ulang = Aset::factory()->create(['dicetak_pada' => now(), 'label_perlu_cetak_ulang' => true]);

    $this->post('/cetak/label', ['mode' => 'belum_dicetak'])->assertOk();
    expect($qr->isi)->toBe([$belum->urlPublik()]);

    $qr->isi = [];
    $this->post('/cetak/label', ['mode' => 'perlu_cetak_ulang'])->assertOk();
    expect($qr->isi)->toBe([$ulang->urlPublik()]);
});

it('mencetak semua aset satu ruangan dan melewati yang sudah dihapus', function () {
    $qr = pasangQrPalsu();
    $this->actingAs(pencetak('admin-bmn'));
    $r = ruangLbl('R-1');
    $a = Aset::factory()->diRuangan($r)->create();
    Aset::factory()->diRuangan($r)->create(['status' => 'dihapus', 'nomor_sk_penghapusan' => 'SK', 'tanggal_sk_penghapusan' => '2026-01-01']);
    Aset::factory()->diRuangan(ruangLbl('R-2'))->create();

    $this->post('/cetak/label', ['mode' => 'ruangan', 'ruangan_id' => $r->id])->assertOk();

    expect($qr->isi)->toBe([$a->urlPublik()]);
});

it('PIC hanya dapat mencetak label aset di ruangannya (BR-05)', function () {
    $qr = pasangQrPalsu();
    [$r1, $r2] = [ruangLbl('R-1'), ruangLbl('R-2')];
    $this->actingAs(pencetak('pic-ruangan', [$r1]));
    $milik = Aset::factory()->diRuangan($r1)->create();
    $lain = Aset::factory()->diRuangan($r2)->create();

    $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => [$milik->id], 'tandai' => 0])->assertOk();
    $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => [$milik->id, $lain->id]])->assertForbidden();
    $this->post('/cetak/label', ['mode' => 'ruangan', 'ruangan_id' => $r2->id])->assertForbidden();

    // mode otomatis hanya mencakup ruangan PIC sendiri
    $qr->isi = [];
    $this->post('/cetak/label', ['mode' => 'belum_dicetak'])->assertOk();
    expect($qr->isi)->toBe([$milik->urlPublik()]);
    expect($lain->fresh()->dicetak_pada)->toBeNull();
});

it('menolak peran tanpa label.cetak dan tamu', function () {
    pasangQrPalsu();
    $aset = Aset::factory()->create();

    $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => [$aset->id]])->assertRedirect('/login');

    foreach (['pimpinan', 'pejabat-penatausahaan', 'civitas'] as $peran) {
        $this->actingAs(pencetak($peran))->post('/cetak/label', ['mode' => 'terpilih', 'aset' => [$aset->id]])->assertForbidden();
    }
});

it('mengembalikan 404 bila tidak ada aset yang cocok dan 422 untuk masukan tidak valid', function () {
    pasangQrPalsu();
    $this->actingAs(pencetak('admin-bmn'));

    $this->post('/cetak/label', ['mode' => 'belum_dicetak'])->assertNotFound();
    $this->post('/cetak/label', ['mode' => 'asal'])->assertSessionHasErrors('mode');
    $this->post('/cetak/label', ['mode' => 'terpilih', 'aset' => ['bukan-uuid']])->assertSessionHasErrors('aset.0');
});

it('aksi massal Cetak label menyimpan pilihan di sesi lalu mengalihkan ke pencetak', function () {
    $r = ruangLbl('R-1');
    $this->actingAs(pencetak('pic-ruangan', [$r]));
    $aset = Aset::factory()->diRuangan($r)->count(2)->create();

    Livewire::test(DaftarAset::class)
        ->selectTableRecords($aset->pluck('id')->all())
        ->callAction(TestAction::make('cetakLabelMassal')->table()->bulk())
        ->assertRedirect(route('cetak.label', ['mode' => 'terpilih']));

    expect(session('cetak_label_ids'))->toEqualCanonicalizing($aset->pluck('id')->all());
});

it('rute cetak memakai pembatas laju', function () {
    $rute = app('router')->getRoutes()->getByName('cetak.label');

    expect($rute->gatherMiddleware())->toContain('throttle:30,1')->toContain('auth');
});
